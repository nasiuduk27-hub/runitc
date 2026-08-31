@extends('layouts.app')

@section('title', 'RUN-ITC | Ajukan Refinancing')

@section('content')
@php
    $statusBadge = fn (string $status) => match ($status) {
        \App\Services\Cooperative\LoanSkipService::ROW_PAID => 'bg-green-50 text-green-700 border-green-200',
        \App\Services\Cooperative\LoanSkipService::ROW_DUE => 'bg-red-50 text-red-700 border-red-200',
        \App\Services\Cooperative\LoanSkipService::ROW_SKIP => 'bg-amber-50 text-amber-700 border-amber-200',
        \App\Services\Cooperative\LoanSkipService::ROW_NEW => 'bg-blue-50 text-blue-700 border-blue-200',
        default => 'bg-gray-100 text-gray-600 border-gray-200',
    };
    $isAccelerate = $mode === \App\Services\Cooperative\LoanSkipService::MODE_ACCELERATE;
@endphp
<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('cooperative.skips.index') }}" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Ajukan Refinancing</h1>
            <p class="mt-0.5 text-sm text-gray-500">Pilih mode: tunda pokok (skip) atau percepat pembayaran (perpendek).</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $errors->first() }}</div>
    @endif

    @if (! $isAdmin && ! $memberLinked)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700">
            Akun login Anda belum ditautkan ke data anggota koperasi, sehingga tidak dapat mengajukan refinancing. Hubungi admin untuk sinkronisasi akun.
        </div>
    @endif

    <div class="flex gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
        <a href="{{ route('cooperative.skips.create', ['mode' => \App\Services\Cooperative\LoanSkipService::MODE_SKIP, 'loan_rec_id' => request('loan_rec_id')]) }}"
           class="flex-1 rounded-xl border px-5 py-3 text-center text-sm font-semibold transition {{ $mode === \App\Services\Cooperative\LoanSkipService::MODE_SKIP ? 'border-brand-primary bg-brand-primary text-white' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' }}">
            <i class="fas fa-forward mr-1.5"></i> Skip Pokok
            <span class="block text-[11px] font-normal opacity-80">Tunda N bulan, tenor +N</span>
        </a>
        <a href="{{ route('cooperative.skips.create', ['mode' => \App\Services\Cooperative\LoanSkipService::MODE_ACCELERATE, 'loan_rec_id' => request('loan_rec_id')]) }}"
           class="flex-1 rounded-xl border px-5 py-3 text-center text-sm font-semibold transition {{ $mode === \App\Services\Cooperative\LoanSkipService::MODE_ACCELERATE ? 'border-orange-500 bg-orange-500 text-white' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' }}">
            <i class="fas fa-fast-forward mr-1.5"></i> Percepat
            <span class="block text-[11px] font-normal opacity-80">Perpendek N bulan, tenor -N</span>
        </a>
    </div>

    <form method="GET" action="{{ route('cooperative.skips.create') }}" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
        <input type="hidden" name="mode" value="{{ $mode }}">
        <label for="loan_rec_id" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Langkah 1 — Pilih Pinjaman Berjalan</label>
        <div class="flex flex-col gap-2 sm:flex-row">
            <select id="loan_rec_id" name="loan_rec_id" class="flex-1 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20">
                <option value="">-- Pilih Pinjaman --</option>
                @foreach ($loans as $loanOption)
                    <option value="{{ $loanOption->rec_id }}" {{ $loan?->rec_id === $loanOption->rec_id ? 'selected' : '' }}>{{ $loanOption->trnno }} | {{ $loanOption->member?->icunm }} ({{ $loanOption->member?->icuno }})</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-primaryHover">Tampilkan</button>
        </div>
    </form>

    @if ($loan)
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <p class="text-sm font-bold text-gray-800">Jadwal Saat Ini</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ $loan->trnno }} | {{ $loan->member?->icunm }} ({{ $loan->member?->icuno }})</p>
            </div>
            @include('cooperative.skips.partials.schedule-table', ['rows' => $scheduleRows, 'statusBadge' => $statusBadge])
        </div>

        <form method="GET" action="{{ route('cooperative.skips.create') }}" class="grid grid-cols-1 gap-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:grid-cols-2">
            <input type="hidden" name="mode" value="{{ $mode }}">
            <input type="hidden" name="loan_rec_id" value="{{ $loan->rec_id }}">
            @if ($mode === \App\Services\Cooperative\LoanSkipService::MODE_SKIP)
                <div>
                    <label for="start_period" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Mulai Skip</label>
                    <select id="start_period" name="start_period"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20">
                        @forelse ($availablePeriods as $period)
                            <option value="{{ $period['periode'] }}" {{ $selectedStartPeriod === $period['periode'] ? 'selected' : '' }}>{{ $period['label'] }}</option>
                        @empty
                            <option value="">-- tidak ada bulan tersisa --</option>
                        @endforelse
                    </select>
                </div>
            @endif
            <div>
                <label for="months_count" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">{{ $isAccelerate ? 'Perpendek (Bulan, 1–12)' : 'Lama Skip (Bulan, 1–12)' }}</label>
                <input type="number" id="months_count" name="months_count" min="1" max="12" value="{{ request('months_count', 1) }}"
                       class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full rounded-xl border border-brand-primary bg-white px-5 py-2.5 text-sm font-semibold text-brand-primary transition hover:bg-blue-50">{{ $isAccelerate ? 'Hitung Percepatan' : 'Hitung Pratinjau' }}</button>
            </div>
        </form>

        @if ($previewError)
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $previewError }}</div>
        @endif

        @if ($preview && ! empty($afterRows))
            <div class="space-y-3 rounded-2xl border border-blue-200 bg-blue-50/50 p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Ringkasan {{ $isAccelerate ? 'Percepatan' : 'Refinancing' }}</p>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    @if ($isAccelerate)
                        <div><p class="text-[10px] font-bold uppercase text-orange-500">Baris Dihapus</p><p class="text-sm font-extrabold">{{ $preview['removed_rows'] }} baris</p></div>
                        <div><p class="text-[10px] font-bold uppercase text-orange-500">Sisa Baris</p><p class="text-sm font-extrabold">{{ count($preview['remaining_rows']) }} baris</p></div>
                        <div><p class="text-[10px] font-bold uppercase text-orange-500">Pokok Dikompensasi</p><p class="text-sm font-extrabold">Rp {{ number_format($preview['moved_principal'], 0, ',', '.') }}</p></div>
                        <div><p class="text-[10px] font-bold uppercase text-orange-500">Bunga Tetap Ditagih</p><p class="text-sm font-extrabold text-amber-600">Rp {{ number_format($preview['retained_interest'], 0, ',', '.') }}</p></div>
                    @else
                        <div><p class="text-[10px] font-bold uppercase text-blue-500">Baris Diskip</p><p class="text-sm font-extrabold">{{ $preview['skipped_rows'] }} baris</p></div>
                        <div><p class="text-[10px] font-bold uppercase text-blue-500">Pokok Dipindah</p><p class="text-sm font-extrabold">Rp {{ number_format($preview['moved_principal'], 0, ',', '.') }}</p></div>
                        <div><p class="text-[10px] font-bold uppercase text-blue-500">Biaya Perpanjang</p><p class="text-sm font-extrabold text-amber-600">Rp {{ number_format($preview['extra_interest'], 0, ',', '.') }}</p></div>
                    @endif
                    <div><p class="text-[10px] font-bold uppercase text-blue-500">Tenor Baru</p><p class="text-sm font-extrabold">{{ $preview['new_term'] }} bln (s.d. {{ \App\Services\Cooperative\CooperativePeriod::label($preview['new_last_periode']) }})</p></div>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4"><p class="text-sm font-bold text-gray-800">Jadwal Setelah Refinancing</p></div>
                @include('cooperative.skips.partials.schedule-table', ['rows' => $afterRows, 'statusBadge' => $statusBadge])
            </div>

            <form method="POST" action="{{ route('cooperative.skips.store') }}" class="space-y-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                @csrf
                <input type="hidden" name="mode" value="{{ $mode }}">
                <input type="hidden" name="loan_rec_id" value="{{ $loan->rec_id }}">
                @if ($mode === \App\Services\Cooperative\LoanSkipService::MODE_SKIP)
                    <input type="hidden" name="start_period" value="{{ request('start_period') }}">
                @endif
                <input type="hidden" name="months_count" value="{{ request('months_count') }}">
                <button type="submit" onclick="return confirm('Ajukan refinancing ini? Perlu persetujuan pengguna lain.')"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">
                    <i class="fas fa-paper-plane"></i> Ajukan
                </button>
            </form>
        @endif
    @endif
</div>
@endsection
