@extends('layouts.app')

@section('title', 'RUN-ITC | Koperasi Saya')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Koperasi Saya</h1>
            @if ($member === null)
                <p class="mt-0.5 text-sm text-gray-500">Data pribadi Anda | Periode berjalan: {{ $currentPeriodLabel }}</p>
            @else
                <p class="mt-0.5 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                    <span class="font-mono font-semibold text-brand-primary">{{ $member->icuno }}</span>
                    <span>|</span>
                    <span>{{ $member->icunm }}</span>
                    <span class="inline-block rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $member->statusBadgeClass() }}">{{ $member->statusLabel() }}</span>
                </p>
            @endif
        </div>
        <p class="self-start rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-500 shadow-sm sm:self-auto">
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
            Data anggota Anda belum tersinkron ke sistem koperasi. Hubungi admin koperasi untuk menautkan akun Anda sebagai anggota.
        </div>
    @endif

    <div class="grid grid-cols-2 gap-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Sisa Pokok Saya (Rp)</p>
            @php
                $totalRemaining = collect($loanCards)->sum(fn ($card) => $card['remaining']);
            @endphp
            <p class="mt-1 text-lg font-extrabold text-brand-primary">{{ number_format($totalRemaining, 0, ',', '.') }}</p>
            <p class="mt-0.5 text-[11px] text-gray-400">indikatif dari principle - paid</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Pinjaman Berjalan</p>
            @php
                $runningCount = collect($loanCards)->filter(fn ($card) => ! $card['loan']->isSettledIndicative())->count();
                $totalLoans = count($loanCards);
            @endphp
            <p class="mt-1 text-lg font-extrabold text-blue-600">{{ number_format($runningCount, 0, ',', '.') }}</p>
            <p class="mt-0.5 text-[11px] text-gray-400">dari {{ number_format($totalLoans, 0, ',', '.') }} pinjaman (indikatif)</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <p class="text-sm font-bold text-gray-800">Cicilan Saya Periode {{ $currentPeriodLabel }}</p>
                <p class="mt-0.5 text-xs text-gray-400">Menurut jadwal icu_dloan atas pinjaman Anda.</p>
            </div>
            <div class="px-5 py-3">
                <div class="mb-3 flex items-baseline gap-2">
                    <p class="text-xl font-extrabold text-gray-900">{{ number_format($dueSummary['count'], 0, ',', '.') }} baris cicilan</p>
                    <p class="text-xs text-gray-400">senilai Rp {{ number_format($dueSummary['total_due'], 0, ',', '.') }}</p>
                </div>
                <ul class="divide-y divide-gray-100 text-sm">
                    @forelse ($dueSummary['rows'] as $row)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-gray-800">Cicilan {{ $row['installment'] }}</p>
                                <p class="font-mono text-[10px] text-gray-400">{{ $row['trnno'] }} | pokok Rp {{ number_format($row['principal'], 0, ',', '.') }} + bunga Rp {{ number_format($row['interest'], 0, ',', '.') }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="whitespace-nowrap text-sm font-bold text-gray-700">Rp {{ number_format($row['total_due'], 0, ',', '.') }}</p>
                                <span class="inline-block whitespace-nowrap rounded-full border px-2 py-0.5 text-[10px] font-bold {{ $row['status_badge'] }}">{{ $row['status_label'] }}</span>
                            </div>
                        </li>
                    @empty
                        <li class="py-4 text-center text-sm text-gray-400">Tidak ada cicilan pada periode ini.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
                <div>
                    <p class="text-sm font-bold text-gray-800">Transaksi Terbaru Saya</p>
                    <p class="mt-0.5 text-xs text-gray-400">10 transaksi terakhir akun anggota Anda.</p>
                </div>
                <a href="{{ route('cooperative.transactions.my') }}" class="shrink-0 text-xs font-semibold text-brand-primary hover:underline">Lihat semua</a>
            </div>
            <ul class="divide-y divide-gray-100 px-5 text-sm">
                @forelse ($recentTransactions as $trx)
                    <li class="flex items-center justify-between gap-3 py-2.5">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-gray-800">{{ $trx->descr ?: 'Transaksi' }}</p>
                            <p class="truncate text-[11px] text-gray-400"><span class="font-mono">{{ $trx->trnno }}</span> | {{ $trx->directionLabel() }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-bold {{ $trx->dbocr === 'D' ? 'text-green-600' : 'text-red-500' }}">{{ $trx->dbocr === 'D' ? '+' : '-' }}{{ number_format($trx->amount, 0, ',', '.') }}</p>
                            <p class="text-[10px] text-gray-400">{{ $trx->trndt ? \Carbon\Carbon::parse($trx->trndt)->format('d M Y') : '-' }}</p>
                        </div>
                    </li>
                @empty
                    <li class="py-4 text-center text-sm text-gray-400">Belum ada transaksi.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <p class="text-sm font-bold text-gray-800">Pinjaman Saya</p>
            <p class="mt-0.5 text-xs text-gray-400">Status lunas bersifat indikatif (paid >= totalloan).</p>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse ($loanCards as $card)
                @php
                    $loan = $card['loan'];
                @endphp
                <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('cooperative.loans.detail', ['rec_id' => $loan->rec_id]) }}" class="font-mono text-sm font-bold text-brand-primary hover:underline">{{ $loan->trnno }}</a>
                            <span class="inline-block rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $loan->statusBadgeClass() }}">{{ $loan->statusLabel() }}</span>
                        </div>
                        <p class="mt-0.5 truncate text-xs text-gray-500" title="{{ $loan->descr }}">{{ $loan->descr ?: '-' }} | {{ $loan->term }} bulan | {{ $loan->startper }} - {{ $loan->endper }}</p>
                        <div class="mt-2 h-2 w-full max-w-md overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-brand-primary" style="width: {{ min(100, $card['progress']) }}%"></div>
                        </div>
                        <p class="mt-1 text-[11px] font-semibold text-gray-500">{{ number_format($card['progress'], 1, ',', '.') }}% terbayar</p>
                    </div>
                    <dl class="grid shrink-0 grid-cols-3 gap-x-5 gap-y-1 text-right text-xs sm:w-72">
                        <div><dt class="text-gray-400">Pokok</dt><dd class="font-bold text-gray-700">{{ number_format($loan->principle, 0, ',', '.') }}</dd></div>
                        <div><dt class="text-gray-400">Terbayar</dt><dd class="font-bold text-green-600">{{ number_format($loan->paid, 0, ',', '.') }}</dd></div>
                        <div><dt class="text-gray-400">Sisa Pokok</dt><dd class="font-bold text-brand-primary">{{ number_format($card['remaining'], 0, ',', '.') }}</dd></div>
                    </dl>
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-gray-400">Anda belum memiliki pinjaman.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
