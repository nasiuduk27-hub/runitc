@extends('layouts.app')

@section('title', 'RUN-ITC | Credit Union Saya')

@section('content')
<div class="mx-auto max-w-6xl space-y-3">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Credit Union Saya</h1>
            @if ($member === null)
                <p class="mt-0.5 text-xs text-gray-500">Data pribadi Anda | Periode berjalan: {{ $currentPeriodLabel }}</p>
            @else
                <p class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                    <span class="font-mono font-semibold text-brand-primary">{{ $member->icuno }}</span>
                    <span>|</span>
                    <span>{{ $member->icunm }}</span>
                    <span class="inline-block rounded-full border px-2 py-0.5 text-[10px] font-bold {{ $member->statusBadgeClass() }}">{{ $member->statusLabel() }}</span>
                </p>
            @endif
        </div>
        <p class="self-start rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-gray-500 shadow-sm sm:self-auto">
            Data pribadi Anda | Periode berjalan: {{ $currentPeriodLabel }}
        </p>
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>
    @endif

    @if ($member === null)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Data anggota Anda belum tersinkron ke sistem credit union. Hubungi admin credit union untuk menautkan akun Anda sebagai anggota.
        </div>
    @endif

    <div class="grid grid-cols-2 gap-3">
        <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
            <p class="text-[11px] font-extrabold uppercase tracking-wide text-gray-700">Sisa Pokok Saya (Rp)</p>
            @php
                $totalRemaining = collect($loanCards)->sum(fn ($card) => $card['remaining']);
            @endphp
            <p class="mt-0.5 text-base font-extrabold text-brand-primary">{{ number_format($totalRemaining, 0, ',', '.') }}</p>
            <p class="text-[10px] text-gray-400">indikatif dari principle - paid</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
            <p class="text-[11px] font-extrabold uppercase tracking-wide text-gray-700">Pinjaman Berjalan</p>
            @php
                $runningCount = collect($loanCards)->filter(fn ($card) => ! $card['loan']->isSettledIndicative())->count();
                $totalLoans = count($loanCards);
            @endphp
            <p class="mt-0.5 text-base font-extrabold text-blue-600">{{ number_format($runningCount, 0, ',', '.') }}</p>
            <p class="text-[10px] text-gray-400">dari {{ number_format($totalLoans, 0, ',', '.') }} pinjaman (indikatif)</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
        <div class="flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-2.5">
                <div>
                    <p class="text-sm font-bold text-gray-800">Cicilan Saya Periode {{ $currentPeriodLabel }}</p>
                    <p class="mt-0.5 text-[11px] text-gray-400">Menurut jadwal angsuran atas pinjaman Anda.</p>
                </div>
                <a href="{{ route('cu.loans.index') }}" class="shrink-0 text-xs font-semibold text-brand-primary hover:underline">Lihat semua</a>
            </div>
            <div class="flex-1 px-4 py-2">
                <div class="mb-1.5 flex items-baseline gap-2">
                    <p class="text-base font-extrabold text-gray-900">{{ number_format($dueSummary['count'], 0, ',', '.') }} baris cicilan</p>
                    <p class="text-[11px] text-gray-400">senilai Rp {{ number_format($dueSummary['total_due'], 0, ',', '.') }}</p>
                </div>
                <ul class="divide-y divide-gray-100 text-xs">
                    @forelse ($dueSummary['rows'] as $row)
                        <li class="flex items-center justify-between gap-3 py-1.5">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-gray-800">Cicilan {{ $row['installment'] }}</p>
                                <p class="truncate font-mono text-[10px] text-gray-400">{{ $row['trnno'] }} | pokok Rp {{ number_format($row['principal'], 0, ',', '.') }} + bunga Rp {{ number_format($row['interest'], 0, ',', '.') }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="whitespace-nowrap text-xs font-bold text-gray-700">Rp {{ number_format($row['total_due'], 0, ',', '.') }}</p>
                                <span class="inline-block whitespace-nowrap rounded-full border px-2 py-0.5 text-[10px] font-bold {{ $row['status_badge'] }}">{{ $row['status_label'] }}</span>
                            </div>
                        </li>
                    @empty
                        <li class="py-3 text-center text-xs text-gray-400">Tidak ada cicilan pada periode ini.</li>
                    @endforelse
                </ul>
            </div>
            @if (($dueSummary['count'] ?? 0) > ($dueSummary['shown'] ?? 0))
                <p class="border-t border-gray-100 px-4 py-1.5 text-[10px] text-gray-400">Menampilkan {{ $dueSummary['shown'] }} dari {{ number_format($dueSummary['count'], 0, ',', '.') }} baris.</p>
            @endif
        </div>

        <div class="flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-2.5">
                <div>
                    <p class="text-sm font-bold text-gray-800">Transaksi Terbaru Saya</p>
                    <p class="mt-0.5 text-[11px] text-gray-400">{{ $recentTransactions->count() }} transaksi terakhir akun anggota Anda.</p>
                </div>
                <a href="{{ route('cu.transactions.my') }}" class="shrink-0 text-xs font-semibold text-brand-primary hover:underline">Lihat semua</a>
            </div>
            <ul class="flex-1 divide-y divide-gray-100 px-4 text-xs">
                @forelse ($recentTransactions as $trx)
                    <li class="flex items-center justify-between gap-3 py-1.5">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-gray-800">{{ $trx->descr ?: 'Transaksi' }}</p>
                            <p class="truncate text-[10px] text-gray-400"><span class="font-mono">{{ $trx->trnno }}</span> | {{ $trx->directionLabel() }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-xs font-bold {{ $trx->dbocr === 'D' ? 'text-green-600' : 'text-red-500' }}">{{ $trx->dbocr === 'D' ? '+' : '-' }}{{ number_format($trx->amount, 0, ',', '.') }}</p>
                            <p class="text-[10px] text-gray-400">{{ $trx->trndt ? \Carbon\Carbon::parse($trx->trndt)->format('d M Y') : '-' }}</p>
                        </div>
                    </li>
                @empty
                    <li class="py-3 text-center text-xs text-gray-400">Belum ada transaksi.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-2.5">
            <div>
                <p class="text-sm font-bold text-gray-800">Pinjaman Saya</p>
                <p class="mt-0.5 text-[11px] text-gray-400">Status lunas bersifat indikatif (paid >= totalloan).</p>
            </div>
            <a href="{{ route('cu.loans.index') }}" class="shrink-0 text-xs font-semibold text-brand-primary hover:underline">Lihat semua</a>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse (array_slice($loanCards, 0, 5) as $card)
                @php
                    $loan = $card['loan'];
                @endphp
                <div class="flex flex-col gap-2 px-4 py-2.5 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('cu.loans.detail', ['rec_id' => $loan->rec_id]) }}" class="font-mono text-xs font-bold text-brand-primary hover:underline">{{ $loan->trnno }}</a>
                            <span class="inline-block rounded-full border px-2 py-0.5 text-[10px] font-bold {{ $loan->statusBadgeClass() }}">{{ $loan->statusLabel() }}</span>
                        </div>
                        <p class="mt-0.5 truncate text-[11px] text-gray-500" title="{{ $loan->descr }}">{{ $loan->descr ?: '-' }} | {{ $loan->term }} bulan | {{ $loan->startper }} - {{ $loan->endper }}</p>
                        <div class="mt-1 h-1.5 w-full max-w-md overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-brand-primary" style="width: {{ min(100, $card['progress']) }}%"></div>
                        </div>
                        <p class="mt-0.5 text-[10px] font-semibold text-gray-500">{{ number_format($card['progress'], 1, ',', '.') }}% terbayar</p>
                    </div>
                    <dl class="grid shrink-0 grid-cols-3 gap-x-5 gap-y-0.5 text-right text-[11px] sm:w-72">
                        <div><dt class="text-gray-400">Pokok</dt><dd class="font-bold text-gray-700">{{ number_format($loan->principle, 0, ',', '.') }}</dd></div>
                        <div><dt class="text-gray-400">Terbayar</dt><dd class="font-bold text-green-600">{{ number_format($loan->paid, 0, ',', '.') }}</dd></div>
                        <div><dt class="text-gray-400">Sisa Pokok</dt><dd class="font-bold text-brand-primary">{{ number_format($card['remaining'], 0, ',', '.') }}</dd></div>
                    </dl>
                </div>
            @empty
                <p class="px-4 py-6 text-center text-xs text-gray-400">Anda belum memiliki pinjaman.</p>
            @endforelse
        </div>
        @if (count($loanCards) > 5)
            <p class="border-t border-gray-100 px-4 py-1.5 text-[10px] text-gray-400">Menampilkan 5 dari {{ number_format(count($loanCards), 0, ',', '.') }} pinjaman.</p>
        @endif
    </div>
</div>
@endsection
