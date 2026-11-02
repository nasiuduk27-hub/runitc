<?php

namespace App\Exports;

use App\Exports\CreditUnion\CuExcel;
use App\Services\CreditUnion\CreditUnionPeriod;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class MonthlyProcessingExport
{
    public function __construct(
        private readonly Collection $rows,
        private readonly array $totals,
        private readonly ?string $period = null,
        private readonly ?string $cmpcd = null
    ) {}

    public function download(string $filename): Response
    {
        $title = 'Laporan Pemotongan Gaji Bulanan';
        $subtitle = 'Credit Union • Tagihan Bulanan Karyawan';
        if ($this->period) {
            $subtitle .= ' • Periode: '.CreditUnionPeriod::label($this->period).' ('.$this->period.')';
        }
        if ($this->cmpcd) {
            $subtitle .= ' • Perusahaan: '.$this->cmpcd;
        }

        $columns = [
            ['label' => 'No Anggota', 'width' => 14, 'align' => 'center', 'format' => 'code'],
            ['label' => 'Nama Member', 'width' => 28, 'align' => 'left', 'format' => 'text'],
            ['label' => 'Saving (Rp)', 'width' => 16, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Loan (Rp)', 'width' => 16, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Cicilan ke', 'width' => 14, 'align' => 'center', 'format' => 'text'],
            ['label' => 'Expense (Rp)', 'width' => 16, 'align' => 'right', 'format' => 'number'],
            ['label' => 'Total (Rp)', 'width' => 18, 'align' => 'right', 'format' => 'number'],
        ];

        $dataRows = [];
        foreach ($this->rows as $row) {
            $dataRows[] = [
                $row['member_icuno'] ?? '',
                $row['member_name'] ?? '',
                (int) ($row['saving'] ?? 0),
                (int) ($row['loan'] ?? 0),
                (string) ($row['installments'] ?? ''),
                (int) ($row['expense'] ?? 0),
                (int) ($row['total'] ?? 0),
            ];
        }

        $totalRows = [
            [
                'label' => 'TOTAL',
                'values' => [
                    1 => '',
                    2 => (int) ($this->totals['saving'] ?? 0),
                    3 => (int) ($this->totals['loan'] ?? 0),
                    4 => '',
                    5 => (int) ($this->totals['expense'] ?? 0),
                    6 => (int) ($this->totals['total'] ?? 0),
                ],
            ],
        ];

        $spreadsheet = CuExcel::createSpreadsheet($title);
        $sheet = $spreadsheet->getActiveSheet();
        CuExcel::buildSheet(
            sheet: $sheet,
            title: $title,
            subtitle: $subtitle,
            columns: $columns,
            dataRows: $dataRows,
            totalRows: $totalRows,
            includeNo: true
        );

        return CuExcel::downloadResponse($spreadsheet, $filename);
    }
}

