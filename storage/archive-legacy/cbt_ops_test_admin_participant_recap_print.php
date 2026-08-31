<?php
require_once dirname(__DIR__, 3).'/config.php';

if (! isset($_SESSION['user_id'])) {
    header('Location: '.BASE_URL.'index.php');
    exit;
}

require_once BASE_PATH.'/includes/tad_participant_recap.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
$printedBy = $_SESSION['user_name'] ?? 'User';
$filters = [
    'date' => $_GET['recap_date'] ?? '',
    'is_range' => isset($_GET['recap_is_range']) ? 1 : 0,
    'start_date' => $_GET['recap_start_date'] ?? '',
    'end_date' => $_GET['recap_end_date'] ?? '',
    'exclude_admin_ids' => $_GET['recap_exclude_admin_ids'] ?? [],
    'exclude' => $_GET['recap_exclude'] ?? '',
];

try {
    $recap = getTadParticipantRecap($pdo, $pdo_run, $pdo_war, $userId, null, $filters);
    $rows = $recap['rows'];
    $totals = $recap['totals'];
    $canView = (bool) $recap['can_view'];
} catch (Throwable $e) {
    $rows = [];
    $totals = [];
    $canView = false;
}

$periodStart = ! empty($totals['period_start']) ? date('d M Y', strtotime($totals['period_start'])) : '-';
$periodEnd = ! empty($totals['period_end']) ? date('d M Y', strtotime($totals['period_end'])) : '-';
$periodLabel = $periodStart === $periodEnd ? $periodStart : $periodStart.' - '.$periodEnd;
$excludeIds = normalizeTadRecapAdminIds($filters['exclude_admin_ids']);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rekap Peserta per Nomor Admin</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: #6b7280; font-size: 12px; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 18px 0; }
        .card { border: 1px solid #d1d5db; border-radius: 8px; padding: 10px; }
        .label { color: #6b7280; font-size: 10px; text-transform: uppercase; font-weight: 700; }
        .value { font-size: 18px; font-weight: 800; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; font-size: 11px; text-transform: uppercase; }
        .right { text-align: right; }
        .footer { margin-top: 16px; font-size: 11px; color: #6b7280; }
        @media print { body { margin: 12mm; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 16px;">
        <button onclick="window.print()" style="padding: 8px 12px; font-weight: 700; cursor: pointer;">Cetak</button>
    </div>

    <h1>Rekap Peserta per Nomor Admin</h1>
    <div class="muted">Periode: <?= htmlspecialchars($periodLabel) ?> &middot; Dicetak oleh <?= htmlspecialchars($printedBy) ?> pada <?= date('d M Y H:i') ?></div>
    <?php if (trim((string) ($filters['exclude'] ?? '')) !== '') { ?>
        <div class="muted">Kecualikan sekolah/kode: <?= htmlspecialchars((string) $filters['exclude']) ?></div>
    <?php } ?>
    <?php if (! empty($excludeIds)) { ?>
        <div class="muted">Kecualikan pilihan spesifik: <?= number_format(count($excludeIds)) ?> nomor admin</div>
    <?php } ?>

    <?php if (! $canView) { ?>
        <p>Anda tidak memiliki akses untuk melihat rekap peserta TAD.</p>
    <?php } else { ?>
        <div class="summary">
            <div class="card"><div class="label">Total Tanggal</div><div class="value"><?= number_format((int) ($totals['total_dates'] ?? 0)) ?></div></div>
            <div class="card"><div class="label">Total Nomor Admin</div><div class="value"><?= number_format((int) ($totals['total_admins'] ?? 0)) ?></div></div>
            <div class="card"><div class="label">Total Peserta</div><div class="value"><?= number_format((int) ($totals['total_participants'] ?? 0)) ?></div></div>
            <div class="card"><div class="label">Peserta Selesai</div><div class="value"><?= number_format((int) ($totals['finished_participants'] ?? 0)) ?></div></div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 36px;" class="right">No</th>
                    <th style="width: 110px;">Tanggal</th>
                    <th>Daftar Sekolah / Kode</th>
                    <th style="width: 110px;" class="right">Jumlah Peserta</th>
                    <th style="width: 110px;" class="right">Peserta Selesai</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)) { ?>
                    <tr><td colspan="5" style="text-align: center;">Belum ada jadwal peserta mendatang.</td></tr>
                <?php } else { ?>
                    <?php $printNo = 1; ?>
                    <?php foreach ($rows as $row) { ?>
                        <?php foreach (($row['items'] ?? []) as $index => $item) { ?>
                            <tr>
                                <td class="right"><?= $printNo++ ?></td>
                                <td><?= $index === 0 ? date('d M Y', strtotime($row['date'])) : '' ?></td>
                                <td><?= htmlspecialchars($item['item_text'] ?? '-') ?></td>
                                <td class="right"><?= number_format((int) ($item['total_participants'] ?? 0)) ?></td>
                                <td class="right"><?= number_format((int) ($item['finished_participants'] ?? 0)) ?></td>
                            </tr>
                        <?php } ?>
                        <tr style="background: #ecfdf5; font-weight: 800;">
                            <td colspan="2">Total <?= date('d M Y', strtotime($row['date'])) ?></td>
                            <td><?= number_format((int) ($row['admin_count'] ?? 0)) ?> nomor admin</td>
                            <td class="right"><?= number_format((int) ($row['total_participants'] ?? 0)) ?></td>
                            <td class="right"><?= number_format((int) ($row['finished_participants'] ?? 0)) ?></td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>

    <div class="footer">RUNITC - Dashboard TAD</div>

    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>
