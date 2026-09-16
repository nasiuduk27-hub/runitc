@extends('layouts.app')

@section('title', 'RUN-ITC | Dashboard Credit Union')

@section('content')
<div class="mx-auto w-full max-w-screen-2xl space-y-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Dashboard Credit Union</h1>
        <p class="mt-0.5 text-sm text-gray-600">
            Ringkasan kondisi credit union (hanya dapat dilihat dari sistem lama). Periode berjalan: {{ $currentPeriodLabel }}.
            @if ($dataUpdatedAt)
                Terakhir diperbarui: {{ $dataUpdatedAt->format('d M Y H:i') }}.
            @endif
        </p>
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <a href="{{ route('cu.savings.detail') }}" class="flex flex-col rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-brand-primary/40">
            <p class="text-[11px] font-extrabold uppercase tracking-wide text-gray-700">Total Simpanan</p>
            <p class="mt-1 text-lg font-extrabold text-brand-primary">Rp {{ number_format($savingsSummary['neto'], 0, ',', '.') }}</p>
            <p class="mt-0.5 text-[11px] text-gray-500">Saldo neto (setoran &minus; penarikan)</p>
            <p class="mt-auto flex items-center gap-1 pt-2 text-[11px] font-semibold text-brand-primary">Lihat rincian <i class="fas fa-chevron-right text-[9px]"></i></p>
        </a>

        <div class="flex flex-col rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-extrabold uppercase tracking-wide text-gray-700">Total Anggota</p>
            <p class="mt-1 text-lg font-extrabold text-gray-900">{{ number_format($memberStats['total'], 0, ',', '.') }}</p>
            <ul class="mt-1.5 grid grid-cols-2 gap-x-3 gap-y-0.5">
                @foreach (\App\Models\CreditUnion\CreditUnionMember::STATUS_LABELS as $code => $label)
                    @php $statusCount = $memberStats['by_status'][$code] ?? 0; @endphp
                    @if ($statusCount > 0)
                        <li class="flex items-center justify-between gap-1 text-[10px] text-gray-500">
                            <span class="truncate" title="{{ $label }}">{{ $label }}</span>
                            <span class="shrink-0 font-semibold text-gray-700">{{ number_format($statusCount, 0, ',', '.') }}</span>
                        </li>
                    @endif
                @endforeach
            </ul>
        </div>

        <a href="{{ route('cu.loans.index', ['status' => 'running']) }}" class="flex flex-col rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-brand-primary/40">
            <p class="text-[11px] font-extrabold uppercase tracking-wide text-gray-700">Pinjaman Berjalan</p>
            <p class="mt-1 text-lg font-extrabold text-blue-600">{{ number_format($loanStats['running'], 0, ',', '.') }}</p>
            <p class="mt-0.5 text-[11px] text-gray-500">dari {{ number_format($loanStats['total'], 0, ',', '.') }} pinjaman</p>
            <p class="mt-auto flex items-center gap-1 pt-2 text-[11px] font-semibold text-brand-primary">Lihat rincian <i class="fas fa-chevron-right text-[9px]"></i></p>
        </a>

        <a href="{{ route('cu.members.index', ['status' => 6]) }}" class="flex flex-col rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-brand-primary/40">
            <p class="text-[11px] font-extrabold uppercase tracking-wide text-gray-700">Anggota Non-Aktif</p>
            <p class="mt-1 text-lg font-extrabold text-red-500">{{ number_format($memberStats['non_active'], 0, ',', '.') }}</p>
            <p class="mt-0.5 text-[11px] text-gray-500">Status tidak aktif</p>
            <p class="mt-auto flex items-center gap-1 pt-2 text-[11px] font-semibold text-brand-primary">Lihat rincian <i class="fas fa-chevron-right text-[9px]"></i></p>
        </a>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                <div>
                    <p class="text-sm font-bold text-gray-800">Simpanan &amp; Pinjaman per Bulan</p>
                    <p class="mt-0.5 text-xs text-gray-500">Perbandingan setoran dan jadwal angsuran sepanjang tahun {{ $selectedYear }}.</p>
                </div>
                <form method="GET" action="{{ route('cu.dashboard') }}">
                    <select name="year" onchange="this.form.submit()"
                            class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                        @foreach ($chartYears as $year)
                            <option value="{{ $year }}" {{ (string) $year === (string) $selectedYear ? 'selected' : '' }}>{{ $year }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div class="px-4 py-3">
                @php
                    $totalSetoran = array_sum(array_column($chartSeries, 'setoran'));
                    $totalPinjaman = array_sum(array_column($chartSeries, 'pinjaman'));
                    $hasChartData = $totalSetoran > 0 || $totalPinjaman > 0;
                @endphp
                @if ($hasChartData)
                    <div class="h-52">
                        <canvas id="cuMonthlyChart"></canvas>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">
                        Total periode tertampil:
                        <span class="font-bold text-brand-primary">Rp {{ number_format($totalSetoran, 0, ',', '.') }}</span> simpanan,
                        <span class="font-bold text-amber-500">Rp {{ number_format($totalPinjaman, 0, ',', '.') }}</span> pinjaman.
                        Arahkan kursor ke batang untuk nominal lengkap dan perubahan dari bulan sebelumnya.
                    </p>
                @else
                    <p class="py-8 text-center text-sm text-gray-500">Belum ada data simpanan atau pinjaman pada tahun ini.</p>
                @endif
            </div>
        </div>

        <div class="flex flex-col rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex items-center gap-2">
                <p class="text-sm font-bold text-gray-800">Rekonsiliasi Pinjaman</p>
                <i class="fas fa-circle-info text-xs text-gray-500"
                   title="Sisa Pokok Indikatif = jumlah (pokok &minus; pembayaran) seluruh pinjaman. Kalkulasi Pinjaman Berjalan = total pokok pinjaman berjalan &minus; jumlah angsuran berstatus terbayar. Selisih timbul karena cakupan pinjaman dan definisi pembayaran berbeda; nilai indikatif belum memperhitungkan pembayaran yang belum tercatat."></i>
            </div>
            <div class="mt-3 flex-1 space-y-2">
                <a href="{{ route('cu.loans.index') }}" class="block rounded-xl border border-gray-100 bg-gray-50/60 px-3 py-2 transition hover:border-brand-primary/40">
                    <p class="text-[10px] font-extrabold uppercase tracking-wide text-gray-700">Sisa Pokok Indikatif</p>
                    <p class="mt-0.5 text-base font-extrabold text-brand-primary">Rp {{ number_format($loanReconciliation['indicative'], 0, ',', '.') }}</p>
                    <p class="text-[10px] text-gray-500">Seluruh pinjaman (pokok &minus; pembayaran)</p>
                </a>
                <a href="{{ route('cu.loan-calculation.detail') }}" class="block rounded-xl border border-gray-100 bg-gray-50/60 px-3 py-2 transition hover:border-brand-primary/40">
                    <p class="text-[10px] font-extrabold uppercase tracking-wide text-gray-700">Kalkulasi Pinjaman Berjalan</p>
                    <p class="mt-0.5 text-base font-extrabold text-brand-primary">Rp {{ number_format($loanReconciliation['calculated'], 0, ',', '.') }}</p>
                    <p class="text-[10px] text-gray-500">Pokok berjalan &minus; angsuran terbayar</p>
                </a>
                <div class="rounded-xl border px-3 py-2 {{ $loanReconciliation['difference'] === 0 ? 'border-gray-100 bg-gray-50/60' : 'border-amber-200 bg-amber-50' }}">
                    <p class="text-[10px] font-extrabold uppercase tracking-wide text-gray-700">Selisih</p>
                    <p class="mt-0.5 text-base font-extrabold {{ $loanReconciliation['difference'] === 0 ? 'text-gray-700' : 'text-amber-600' }}">Rp {{ number_format($loanReconciliation['difference'], 0, ',', '.') }}</p>
                    <p class="text-[10px] text-gray-500">Perbedaan definisi &amp; cakupan data</p>
                </div>
            </div>
            <p class="mt-3 text-[11px] text-gray-500">Angka dihitung dari data jadwal sistem lama; nilai indikatif dapat berbeda sampai pembayaran yang belum tercatat diselesaikan.</p>
        </div>
    </div>

    @php
        $auditSummary = function (object $log): string {
            $meta = json_decode((string) ($log->metadata_json ?? ''), true) ?: [];
            $money = fn ($value): string => 'Rp '.number_format((int) $value, 0, ',', '.');

            return match ($log->action) {
                'cu.member.savings_updated' => 'Perbarui simpanan wajib dari '.$money($meta['old_swajib'] ?? 0).' menjadi '.$money($meta['new_swajib'] ?? 0),
                'cu.member.created' => 'Tambah anggota credit union',
                'cu.member.synced' => 'Sinkronkan anggota credit union',
                'cu.savings_withdrawal.submitted' => 'Ajukan penarikan simpanan',
                'cu.savings_withdrawal.approved' => 'Setujui penarikan simpanan',
                'cu.savings_withdrawal.rejected' => 'Tolak penarikan simpanan',
                'cu.savings_withdrawal.cancelled' => 'Batalkan penarikan simpanan',
                'cu.bank_transaction.deleted' => 'Hapus transaksi bank',
                default => 'Aktivitas credit union',
            };
        };
    @endphp

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                <div>
                    <p class="text-sm font-bold text-gray-800">Cicilan Jatuh Tempo</p>
                    <p class="mt-0.5 text-xs text-gray-500">Periode {{ $currentPeriodLabel }}; status bayar belum tercatat di sistem lama.</p>
                </div>
                <a href="{{ route('cu.deposits.detail', ['period' => $currentPeriod, 'tab' => 'pinjaman']) }}" class="shrink-0 text-xs font-semibold text-brand-primary hover:underline">Lihat semua</a>
            </div>
            <div class="flex-1 px-4 py-2">
                <div class="mb-2 flex items-baseline gap-2">
                    <p class="text-lg font-extrabold text-gray-900">{{ number_format($dueSummary['count'], 0, ',', '.') }} baris jadwal</p>
                    <p class="text-xs text-gray-500">Rp {{ number_format($dueSummary['total_due'], 0, ',', '.') }}</p>
                </div>
                <ul class="divide-y divide-gray-100 text-sm">
                    @forelse ($dueSummary['rows'] as $row)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <div class="min-w-0">
                                <a href="{{ route('cu.loans.detail', ['rec_id' => $row->mst_rec_id]) }}" class="block truncate font-semibold text-gray-800 hover:text-brand-primary">{{ $row->loan?->member?->icunm ?? 'Tanpa Anggota' }}</a>
                                <p class="truncate font-mono text-[10px] text-gray-500">{{ $row->loan?->trnno ?? '-' }} | cicilan {{ $row->installmentLabel() }} | {{ \Carbon\Carbon::parse($dueSummary['period_end'])->format('d M Y') }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="whitespace-nowrap text-sm font-bold text-gray-700">Rp {{ number_format($row->totalDue(), 0, ',', '.') }}</p>
                                <span class="mt-0.5 inline-block whitespace-nowrap rounded-full border px-2 py-0.5 text-[10px] font-bold {{ $row->paymentStatusBadgeClass() }}">{{ $row->paymentStatusLabel() }}</span>
                            </div>
                        </li>
                    @empty
                        <li class="py-4 text-center text-sm text-gray-500">Tidak ada jadwal pada periode ini.</li>
                    @endforelse
                </ul>
            </div>
            @if ($dueSummary['count'] > $dueSummary['shown'])
                <p class="border-t border-gray-100 px-4 py-2 text-[11px] text-gray-500">Menampilkan {{ $dueSummary['shown'] }} dari {{ number_format($dueSummary['count'], 0, ',', '.') }} baris terbesar.</p>
            @endif
        </div>

        <div class="flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                <div>
                    <p class="text-sm font-bold text-gray-800">Transaksi Terbaru</p>
                    <p class="mt-0.5 text-xs text-gray-500">{{ $recentTransactions->count() }} transaksi terakhir. Tanggal mendatang ditandai <span class="font-semibold text-amber-600">Terjadwal</span>.</p>
                </div>
                <a href="{{ route('cu.transactions.index') }}" class="shrink-0 text-xs font-semibold text-brand-primary hover:underline">Lihat semua</a>
            </div>
            <ul class="flex-1 divide-y divide-gray-100 px-4 text-sm">
                @forelse ($recentTransactions as $trx)
                    @php $isScheduled = $trx->trndt !== null && \Carbon\Carbon::parse($trx->trndt)->isFuture(); @endphp
                    <li class="flex items-center justify-between gap-3 py-2">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-gray-800">{{ $trx->member?->icunm ?? 'Tanpa Anggota' }}</p>
                            <p class="truncate text-[11px] text-gray-500"><span class="font-mono">{{ $trx->trnno }}</span> | {{ $trx->descr }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-bold {{ $trx->dbocr === 'D' ? 'text-green-600' : 'text-red-500' }}">{{ $trx->dbocr === 'D' ? '+' : '-' }}{{ number_format($trx->amount, 0, ',', '.') }}</p>
                            <p class="text-[10px] text-gray-500">
                                {{ $trx->trndt ? \Carbon\Carbon::parse($trx->trndt)->format('d M Y') : '-' }}
                                @if ($isScheduled)
                                    <span class="ml-1 rounded-full border border-amber-200 bg-amber-50 px-1.5 py-0.5 font-bold text-amber-700">Terjadwal</span>
                                @endif
                            </p>
                        </div>
                    </li>
                @empty
                    <li class="py-4 text-center text-sm text-gray-500">Belum ada transaksi.</li>
                @endforelse
            </ul>
        </div>

        <div class="flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                <div>
                    <p class="text-sm font-bold text-gray-800">Aktivitas Terbaru</p>
                    <p class="mt-0.5 text-xs text-gray-500">Perubahan terbaru pada modul credit union.</p>
                </div>
                <a href="{{ route('cu.audit-log.index') }}" class="shrink-0 text-xs font-semibold text-brand-primary hover:underline">Lihat semua</a>
            </div>
            <ul class="flex-1 divide-y divide-gray-100 px-4 text-sm">
                @forelse ($recentAuditLogs as $log)
                    <li class="py-2">
                        <p class="truncate font-semibold text-gray-800">{{ $log->account_nm ?: 'System / Unknown' }}</p>
                        <p class="mt-0.5 flex items-center justify-between gap-2 text-[11px] text-gray-600">
                            <span class="truncate">{{ $auditSummary($log) }}</span>
                            <span class="shrink-0 text-[10px] text-gray-500">{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y H:i') }}</span>
                        </p>
                    </li>
                @empty
                    <li class="py-4 text-center text-sm text-gray-500">Belum ada aktivitas.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @if ($hasChartData ?? false)
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            (function () {
                const rows = @json($chartSeries);
                const currentPeriod = @json($currentPeriod);
                const canvas = document.getElementById('cuMonthlyChart');
                if (!canvas || typeof Chart === 'undefined') return;

                const rupiah = (value) => 'Rp ' + Number(value || 0).toLocaleString('id-ID');
                const compact = (value) => {
                    const n = Math.abs(Number(value) || 0);
                    if (n >= 1000000000) return 'Rp ' + (value / 1000000000).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' M';
                    if (n >= 1000000) return 'Rp ' + (value / 1000000).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' jt';
                    if (n >= 1000) return 'Rp ' + Math.round(value / 1000).toLocaleString('id-ID') + ' rb';
                    return rupiah(value);
                };
                const barColor = (base, full) => (ctx) => (rows[ctx.dataIndex]?.periode === currentPeriod ? full : base);

                new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: rows.map((row) => row.short),
                        datasets: [
                            {
                                label: 'Simpanan',
                                data: rows.map((row) => row.setoran),
                                backgroundColor: barColor('rgba(29, 78, 216, 0.75)', 'rgba(29, 78, 216, 1)'),
                                borderRadius: 4,
                                barPercentage: 0.8,
                                categoryPercentage: 0.6,
                            },
                            {
                                label: 'Pinjaman',
                                data: rows.map((row) => row.pinjaman),
                                backgroundColor: barColor('rgba(245, 158, 11, 0.75)', 'rgba(245, 158, 11, 1)'),
                                borderRadius: 4,
                                barPercentage: 0.8,
                                categoryPercentage: 0.6,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 12 } },
                            tooltip: {
                                callbacks: {
                                    title: (items) => rows[items[0].dataIndex]?.label ?? '',
                                    label: (item) => ' ' + item.dataset.label + ': ' + rupiah(item.parsed.y),
                                    afterBody: (items) => {
                                        const index = items[0].dataIndex;
                                        const row = rows[index];
                                        if (!row) return '';

                                        const lines = [
                                            'Total Penarikan: ' + rupiah(row.penarikan),
                                            'Neto: ' + rupiah(row.neto),
                                            row.trx_count + ' transaksi simpanan',
                                        ];

                                        const prev = rows[index - 1];
                                        if (prev) {
                                            const delta = (value, before) => (value - before >= 0 ? '+' : '-') + rupiah(Math.abs(value - before));
                                            lines.push('Simpanan vs bulan lalu: ' + delta(row.setoran, prev.setoran));
                                            lines.push('Pinjaman vs bulan lalu: ' + delta(row.pinjaman, prev.pinjaman));
                                        }

                                        return lines;
                                    },
                                },
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { callback: (value) => compact(value) },
                                grid: { color: 'rgba(0, 0, 0, 0.06)' },
                            },
                            x: { grid: { display: false }, offset: true },
                        },
                        onClick: (event, elements) => {
                            if (!elements.length) return;
                            const row = rows[elements[0].index];
                            if (row && row.url) window.location.href = row.url;
                        },
                        onHover: (event, elements) => {
                            event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                        },
                    },
                });
            })();
        </script>
    @endif
@endpush
