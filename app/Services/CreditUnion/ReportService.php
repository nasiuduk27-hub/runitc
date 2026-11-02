<?php

namespace App\Services\CreditUnion;

use App\Models\CreditUnion\CreditUnionBankTrxDetail;
use InvalidArgumentException;

/**
 * Helper laporan credit union — fungsi murni yang mudah diuji.
 */
class ReportService
{
    public const DUE_PAID = 'paid';

    public const DUE_OVERDUE = 'overdue';

    public const DUE_NOW = 'due_now';

    public const DUE_UPCOMING = 'upcoming';

    /** @var array<string, string> */
    public const DUE_LABELS = [
        self::DUE_PAID => 'Lunas',
        self::DUE_OVERDUE => 'Terlewat',
        self::DUE_NOW => 'Jatuh Tempo Kini',
        self::DUE_UPCOMING => 'Akan Datang',
    ];

    /**
     * Label flow LN sys_seqflow untuk icu_mloan.statrec.
     *
     * @var array<int, string>
     */
    public const LN_STATUS_LABELS = [
        0 => 'Draft',
        1 => 'Waiting Approval',
        2 => 'Transfer List',
        3 => 'Loan Submitted',
        4 => 'Partial Payment',
        5 => 'Loan Completed',
        6 => 'Loan Rejected',
    ];

    /**
     * Klasifikasi satu baris jadwal terhadap periode acuan (YYYYMM).
     *
     * @throws InvalidArgumentException bila format periode tidak valid
     */
    public function classifyInstallment(int $paidst, string $periode, string $asOfPeriod): string
    {
        if (! CreditUnionPeriod::isValid($periode) || ! CreditUnionPeriod::isValid($asOfPeriod)) {
            throw new InvalidArgumentException('Format periode harus YYYYMM.');
        }

        if ($paidst === 1) {
            return self::DUE_PAID;
        }

        return match (true) {
            $periode < $asOfPeriod => self::DUE_OVERDUE,
            $periode === $asOfPeriod => self::DUE_NOW,
            default => self::DUE_UPCOMING,
        };
    }

    public function dueLabel(string $classification): string
    {
        return self::DUE_LABELS[$classification] ?? ucfirst($classification);
    }

    public function lnStatusLabel(int|string|null $statrec): string
    {
        return self::LN_STATUS_LABELS[(int) $statrec] ?? 'Kode '.(string) $statrec;
    }

    /**
     * Rentang periode default: Januari tahun berjalan s.d. periode sekarang.
     *
     * @return array{from: string, to: string}
     */
    public function defaultSavingsRange(): array
    {
        $current = CreditUnionPeriod::current();

        return ['from' => substr($current, 0, 4).'01', 'to' => $current];
    }

    /**
     * Agregasi pendapatan bunga dari baris jadwal pinjaman.
     * Aturan:
     * - Hanya cicilan terbayar (paidst = 1)
     * - Hanya bunga (int_amt), others diabaikan
     * - Dikelompokkan per periode dalam rentang [from, to] urut naik
     *
     * @param  iterable<array<string, mixed>|object>  $rows
     * @return array{
     *     rows: list<array{periode: string, installment_count: int, interest_total: int}>,
     *     totals: array{installments: int, interest: int}
     * }
     */
    public function aggregateInterestRevenue(iterable $rows, ?string $from = null, ?string $to = null): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $item = (array) $row;
            if ((int) ($item['paidst'] ?? 0) !== 1) {
                continue;
            }

            $periode = (string) ($item['periode'] ?? '');
            if (! CreditUnionPeriod::isValid($periode)) {
                continue;
            }

            if ($from !== null && $from !== '' && $periode < $from) {
                continue;
            }
            if ($to !== null && $to !== '' && $periode > $to) {
                continue;
            }

            $interest = (int) ($item['int_amt'] ?? 0);

            if (! isset($grouped[$periode])) {
                $grouped[$periode] = [
                    'periode' => $periode,
                    'installment_count' => 0,
                    'interest_total' => 0,
                ];
            }

            $grouped[$periode]['installment_count']++;
            $grouped[$periode]['interest_total'] += $interest;
        }

        ksort($grouped);

        $resultRows = array_values($grouped);
        $totalInstallments = (int) array_sum(array_column($resultRows, 'installment_count'));
        $totalInterest = (int) array_sum(array_column($resultRows, 'interest_total'));

        return [
            'rows' => $resultRows,
            'totals' => [
                'installments' => $totalInstallments,
                'interest' => $totalInterest,
            ],
        ];
    }

    /**
     * Agregasi pendapatan bunga dikelompokkan per anggota.
     *
     * @param  iterable<array<string, mixed>|object>  $rows
     * @return array{
     *     groups: list<array{
     *         icuno: string,
     *         icunm: string,
     *         is_unlinked: bool,
     *         loan_count: int,
     *         installment_count: int,
     *         principal_subtotal: int,
     *         interest_subtotal: int,
     *         items: list<array{
     *             icuno: string,
     *             icunm: string,
     *             mst_rec_id: int,
     *             trnno: string,
     *             periode: string,
     *             seqno: int,
     *             totseqno: int,
     *             installment_label: string,
     *             principal: int,
     *             interest: int
     *         }>
     *     }>,
     *     flat_rows: list<array{
     *         icuno: string,
     *         icunm: string,
     *         mst_rec_id: int,
     *         trnno: string,
     *         periode: string,
     *         seqno: int,
     *         totseqno: int,
     *         installment_label: string,
     *         principal: int,
     *         interest: int
     *     }>,
     *     totals: array{
     *         member_count: int,
     *         loan_count: int,
     *         installments: int,
     *         principal: int,
     *         interest: int
     *     }
     * }
     */
    public function aggregateInterestByMember(iterable $rows, ?string $from = null, ?string $to = null): array
    {
        $grouped = [];
        $unlinkedItems = [];
        $allLoans = [];
        $totalInstallments = 0;
        $totalPrincipal = 0;
        $totalInterest = 0;

        foreach ($rows as $row) {
            $item = (array) $row;
            if ((int) ($item['paidst'] ?? 0) !== 1) {
                continue;
            }

            $periode = (string) ($item['periode'] ?? '');
            if (! CreditUnionPeriod::isValid($periode)) {
                continue;
            }

            if ($from !== null && $from !== '' && $periode < $from) {
                continue;
            }
            if ($to !== null && $to !== '' && $periode > $to) {
                continue;
            }

            $seqno = (int) ($item['seqno'] ?? 1);
            $totseqno = (int) ($item['totseqno'] ?? 0);
            $installmentLabel = $totseqno > 0 ? "{$seqno}/{$totseqno}" : (string) $seqno;
            $principal = (int) ($item['amount'] ?? 0);
            $interest = (int) ($item['int_amt'] ?? 0);
            $mstRecId = (int) ($item['mst_rec_id'] ?? 0);
            $trnno = trim((string) ($item['trnno'] ?? ''));
            $icuno = trim((string) ($item['icuno'] ?? ''));
            $icunm = trim((string) ($item['icunm'] ?? ''));

            // others sengaja diabaikan untuk angka mana pun

            $isUnlinked = $icuno === '' || ($mstRecId === 0 && $trnno === '');

            $normalizedItem = [
                'icuno' => $isUnlinked ? '-' : $icuno,
                'icunm' => $isUnlinked ? 'Anggota Tidak Tertaut' : ($icunm !== '' ? $icunm : 'not available'),
                'mst_rec_id' => $mstRecId,
                'trnno' => $trnno !== '' ? $trnno : '-',
                'periode' => $periode,
                'seqno' => $seqno,
                'totseqno' => $totseqno,
                'installment_label' => $installmentLabel,
                'principal' => $principal,
                'interest' => $interest,
            ];

            $totalInstallments++;
            $totalPrincipal += $principal;
            $totalInterest += $interest;

            if ($mstRecId > 0) {
                $allLoans['id_'.$mstRecId] = true;
            } elseif ($trnno !== '' && $trnno !== '-') {
                $allLoans['no_'.$trnno] = true;
            }

            if ($isUnlinked) {
                $unlinkedItems[] = $normalizedItem;
            } else {
                $memberKey = $icuno;
                if (! isset($grouped[$memberKey])) {
                    $grouped[$memberKey] = [
                        'icuno' => $icuno,
                        'icunm' => $icunm !== '' ? $icunm : 'not available',
                        'is_unlinked' => false,
                        'loans' => [],
                        'installment_count' => 0,
                        'principal_subtotal' => 0,
                        'interest_subtotal' => 0,
                        'items' => [],
                    ];
                }
                $grouped[$memberKey]['items'][] = $normalizedItem;
                $grouped[$memberKey]['installment_count']++;
                $grouped[$memberKey]['principal_subtotal'] += $principal;
                $grouped[$memberKey]['interest_subtotal'] += $interest;
                if ($mstRecId > 0) {
                    $grouped[$memberKey]['loans']['id_'.$mstRecId] = true;
                } elseif ($trnno !== '' && $trnno !== '-') {
                    $grouped[$memberKey]['loans']['no_'.$trnno] = true;
                }
            }
        }

        $sortItems = function (array &$items): void {
            usort($items, function (array $a, array $b): int {
                $cmp = strcmp($a['periode'], $b['periode']);
                if ($cmp !== 0) {
                    return $cmp;
                }
                $cmp = strcmp($a['trnno'], $b['trnno']);
                if ($cmp !== 0) {
                    return $cmp;
                }
                return $a['seqno'] <=> $b['seqno'];
            });
        };

        foreach ($grouped as &$g) {
            $sortItems($g['items']);
            $g['loan_count'] = count($g['loans']);
            unset($g['loans']);
        }
        unset($g);

        uasort($grouped, function (array $a, array $b): int {
            $cmp = strcasecmp($a['icunm'], $b['icunm']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp($a['icuno'], $b['icuno']);
        });

        $finalGroups = array_values($grouped);

        if (! empty($unlinkedItems)) {
            $sortItems($unlinkedItems);
            $unlinkedLoans = [];
            $unlinkedPrincipal = 0;
            $unlinkedInterest = 0;
            foreach ($unlinkedItems as $uItem) {
                $unlinkedPrincipal += $uItem['principal'];
                $unlinkedInterest += $uItem['interest'];
                if ($uItem['mst_rec_id'] > 0) {
                    $unlinkedLoans['id_'.$uItem['mst_rec_id']] = true;
                } elseif ($uItem['trnno'] !== '-' && $uItem['trnno'] !== '') {
                    $unlinkedLoans['no_'.$uItem['trnno']] = true;
                }
            }

            $finalGroups[] = [
                'icuno' => '-',
                'icunm' => 'Anggota Tidak Tertaut',
                'is_unlinked' => true,
                'loan_count' => count($unlinkedLoans),
                'installment_count' => count($unlinkedItems),
                'principal_subtotal' => $unlinkedPrincipal,
                'interest_subtotal' => $unlinkedInterest,
                'items' => $unlinkedItems,
            ];
        }

        $flatRows = [];
        foreach ($finalGroups as $grp) {
            foreach ($grp['items'] as $item) {
                $flatRows[] = $item;
            }
        }

        return [
            'groups' => $finalGroups,
            'flat_rows' => $flatRows,
            'totals' => [
                'member_count' => count($grouped),
                'loan_count' => count($allLoans),
                'installments' => $totalInstallments,
                'principal' => $totalPrincipal,
                'interest' => $totalInterest,
            ],
        ];
    }

    /**
     * Agregasi mutasi bank (biaya admin bank dan pendapatan bunga bank) per periode.
     *
     * @param  iterable<array<string, mixed>|object>  $rows
     * @return array{
     *     rows: list<array{
     *         periode: string,
     *         admin_fee: int,
     *         gross_interest: int,
     *         tax_interest: int,
     *         net_interest: int,
     *         net_difference: int
     *     }>,
     *     totals: array{
     *         admin_fee: int,
     *         gross_interest: int,
     *         tax_interest: int,
     *         net_interest: int,
     *         net_difference: int
     *     }
     * }
     */
    public function aggregateBankReport(iterable $rows, ?string $from = null, ?string $to = null): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $item = (array) $row;
            $periode = (string) ($item['periode'] ?? '');
            if (! CreditUnionPeriod::isValid($periode)) {
                continue;
            }

            if ($from !== null && $from !== '' && $periode < $from) {
                continue;
            }
            if ($to !== null && $to !== '' && $periode > $to) {
                continue;
            }

            if (! isset($grouped[$periode])) {
                $grouped[$periode] = [
                    'periode' => $periode,
                    'admin_fee' => 0,
                    'gross_interest' => 0,
                    'tax_interest' => 0,
                    'net_interest' => 0,
                    'net_difference' => 0,
                ];
            }

            $category = (string) ($item['category'] ?? '');
            $net = (int) ($item['net_amount'] ?? 0);
            $gross = (int) ($item['gross_amount'] ?? 0);
            $tax = (int) ($item['tax_amount'] ?? 0);

            if ($category === CreditUnionBankTrxDetail::CATEGORY_ADMIN_FEE) {
                $grouped[$periode]['admin_fee'] += $net;
            } elseif ($category === CreditUnionBankTrxDetail::CATEGORY_INTEREST) {
                $grouped[$periode]['gross_interest'] += $gross;
                $grouped[$periode]['tax_interest'] += $tax;
                $grouped[$periode]['net_interest'] += $net;
            }
        }

        foreach ($grouped as $periode => &$entry) {
            $entry['net_difference'] = $entry['net_interest'] - $entry['admin_fee'];
        }
        unset($entry);

        ksort($grouped);

        $resultRows = array_values($grouped);
        $totalAdminFee = (int) array_sum(array_column($resultRows, 'admin_fee'));
        $totalGross = (int) array_sum(array_column($resultRows, 'gross_interest'));
        $totalTax = (int) array_sum(array_column($resultRows, 'tax_interest'));
        $totalNet = (int) array_sum(array_column($resultRows, 'net_interest'));

        return [
            'rows' => $resultRows,
            'totals' => [
                'admin_fee' => $totalAdminFee,
                'gross_interest' => $totalGross,
                'tax_interest' => $totalTax,
                'net_interest' => $totalNet,
                'net_difference' => $totalNet - $totalAdminFee,
            ],
        ];
    }
}
