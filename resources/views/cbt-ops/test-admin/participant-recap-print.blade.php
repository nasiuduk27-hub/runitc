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
@php
    $periodStart = ! empty($totals['period_start']) ? date('d M Y', strtotime($totals['period_start'])) : '-';
    $periodEnd = ! empty($totals['period_end']) ? date('d M Y', strtotime($totals['period_end'])) : '-';
    $periodLabel = $periodStart === $periodEnd ? $periodStart : $periodStart.' - '.$periodEnd;
    $excludeIds = function_exists('normalizeTadRecapAdminIds') ? normalizeTadRecapAdminIds($filters['exclude_admin_ids'] ?? []) : [];
@endphp
<body>
    <div class="no-print" style="margin-bottom: 16px;">
        <button onclick="window.print()" style="padding: 8px 12px; font-weight: 700; cursor: pointer;">Cetak</button>
    </div>

    <h1>Rekap Peserta per Nomor Admin</h1>
    <div class="muted">Periode: {{ $periodLabel }} &middot; Dicetak oleh {{ $printedBy }} pada {{ now()->format('d M Y H:i') }}</div>
    @if (trim((string) ($filters['exclude'] ?? '')) !== '')
        <div class="muted">Kecualikan sekolah/kode: {{ $filters['exclude'] }}</div>
    @endif
    @if (! empty($excludeIds))
        <div class="muted">Kecualikan pilihan spesifik: {{ number_format(count($excludeIds)) }} nomor admin</div>
    @endif

    @if (! $canView)
        <p>Anda tidak memiliki akses untuk melihat rekap peserta TAD.</p>
    @else
        <div class="summary">
            <div class="card"><div class="label">Total Tanggal</div><div class="value">{{ number_format((int) ($totals['total_dates'] ?? 0)) }}</div></div>
            <div class="card"><div class="label">Total Nomor Admin</div><div class="value">{{ number_format((int) ($totals['total_admins'] ?? 0)) }}</div></div>
            <div class="card"><div class="label">Total Peserta</div><div class="value">{{ number_format((int) ($totals['total_participants'] ?? 0)) }}</div></div>
            <div class="card"><div class="label">Peserta Selesai</div><div class="value">{{ number_format((int) ($totals['finished_participants'] ?? 0)) }}</div></div>
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
                @php $printNo = 1; @endphp
                @forelse ($rows as $row)
                    @foreach (($row['items'] ?? []) as $index => $item)
                        <tr>
                            <td class="right">{{ $printNo++ }}</td>
                            <td>{{ $index === 0 ? date('d M Y', strtotime($row['date'])) : '' }}</td>
                            <td>{{ $item['item_text'] ?? '-' }}</td>
                            <td class="right">{{ number_format((int) ($item['total_participants'] ?? 0)) }}</td>
                            <td class="right">{{ number_format((int) ($item['finished_participants'] ?? 0)) }}</td>
                        </tr>
                    @endforeach
                    <tr style="background: #ecfdf5; font-weight: 800;">
                        <td colspan="2">Total {{ date('d M Y', strtotime($row['date'])) }}</td>
                        <td>{{ number_format((int) ($row['admin_count'] ?? 0)) }} nomor admin</td>
                        <td class="right">{{ number_format((int) ($row['total_participants'] ?? 0)) }}</td>
                        <td class="right">{{ number_format((int) ($row['finished_participants'] ?? 0)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align: center;">Belum ada jadwal peserta mendatang.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif

    <div class="footer">RUNITC - Dashboard TAD</div>
    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
