@extends('layouts.app')

@section('title', 'RUN-ITC | Detail Refinancing')

@section('content')
@php
    $isMaker = $currentUserId === $skip->maker_user_id;
    $canApply = $skip->status === \App\Services\Cooperative\LoanSkipService::STATUS_SUBMITTED && ! $isMaker;
    $canCancel = $skip->status === \App\Services\Cooperative\LoanSkipService::STATUS_SUBMITTED && $isMaker;
    $isAccelerate = $skip->mode === \App\Services\Cooperative\LoanSkipService::MODE_ACCELERATE;
    $isSavings = $skip->mode === \App\Services\Cooperative\LoanSkipService::MODE_SAVINGS;
@endphp
<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('cooperative.skips.index') }}" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $isAccelerate ? 'Percepatan' : ($isSavings ? 'Potong Simpanan' : 'Skip Pokok') }} #{{ $skip->id }}</h1>
            <p class="mt-0.5 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                <span class="font-semibold text-gray-700">{{ $skip->member_name }}</span>
                <span class="font-mono text-xs">{{ $skip->member_icuno }}</span>
                <span class="inline-block rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $isAccelerate ? 'bg-orange-50 text-orange-700 border-orange-200' : ($isSavings ? 'bg-teal-50 text-teal-700 border-teal-200' : 'bg-blue-50 text-blue-700 border-blue-200') }}">{{ $isAccelerate ? 'Percepat' : ($isSavings ? 'Potong Simpanan' : 'Skip Pokok') }}</span>
                <span class="inline-block rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $service->statusBadgeClass($skip->status) }}">{{ $service->statusLabel($skip->status) }}</span>
            </p>
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
            <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-400">{{ $isAccelerate ? 'Rencana Percepatan' : ($isSavings ? 'Rencana Potong Simpanan' : 'Rencana Skip') }}</p>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-2.5 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Pinjaman</dt><dd><a href="{{ route('cooperative.loans.detail', ['rec_id' => $skip->loan_rec_id]) }}" class="font-mono font-semibold text-brand-primary hover:underline">rec_id {{ $skip->loan_rec_id }}</a></dd></div>
                @if ($isSavings)
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Simpanan Dipakai</dt><dd class="font-bold text-gray-900">Rp {{ number_format($skip->principal_moved, 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Periode Dikurangi</dt><dd class="font-medium text-gray-800">{{ $skip->rows_skipped }} periode</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Potongan / Periode</dt><dd class="font-medium text-gray-800">Rp {{ number_format($skip->rows_skipped > 0 ? intdiv($skip->principal_moved, $skip->rows_skipped) : 0, 0, ',', '.') }}</dd></div>
                @elseif ($isAccelerate)
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Percepatan</dt><dd class="font-medium text-gray-800">−{{ $skip->months_count }} bln (s.d. {{ \App\Services\Cooperative\CooperativePeriod::label($plan['new_last_periode'] ?? $skip->start_period) }})</dd></div>
                @else
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Rentang</dt><dd class="font-medium text-gray-800">{{ \App\Services\Cooperative\CooperativePeriod::label($skip->start_period) }} s.d. {{ \App\Services\Cooperative\CooperativePeriod::label($plan['window_end'] ?? $skip->start_period) }} ({{ $skip->months_count }} bln)</dd></div>
                @endif
                @if ($isAccelerate)
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Baris Dihapus</dt><dd class="font-medium text-gray-800">{{ $skip->rows_skipped }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Baris Sisa</dt><dd class="font-medium text-gray-800">{{ $skip->new_term }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Pokok Dikompensasi</dt><dd class="font-bold text-gray-900">Rp {{ number_format($skip->principal_moved, 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Bunga Tetap Ditagih</dt><dd class="font-medium text-amber-600">Rp {{ number_format($skip->extra_interest, 0, ',', '.') }}</dd></div>
                @elseif (! $isSavings)
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Baris Diskip</dt><dd class="font-medium text-gray-800">{{ $skip->rows_skipped }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Pokok Dipindah</dt><dd class="font-bold text-gray-900">Rp {{ number_format($skip->principal_moved, 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Biaya Perpanjang</dt><dd class="font-medium text-amber-600">Rp {{ number_format($skip->extra_interest, 0, ',', '.') }}</dd></div>
                @endif
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Tenor Baru</dt><dd class="font-medium text-gray-800">{{ $skip->new_term }} bln</dd></div>
                @if ($skip->reason)
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Alasan</dt><dd class="max-w-56 truncate text-right text-gray-700" title="{{ $skip->reason }}">{{ $skip->reason }}</dd></div>
                @endif
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Diajukan</dt><dd class="font-medium text-gray-800">{{ $skip->created_at?->format('d M Y H:i') }}</dd></div>
                @if ($plan['new_last_periode'] ?? null)
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Jadwal Baru Berakhir</dt><dd class="font-mono font-medium text-gray-800">{{ \App\Services\Cooperative\CooperativePeriod::label($plan['new_last_periode']) }}</dd></div>
                @endif
            </dl>
            @if ($skip->decision_note)
                <p class="mt-3 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600"><span class="font-bold">Catatan keputusan:</span> {{ $skip->decision_note }}</p>
            @endif
        </div>

        <div class="space-y-4">
            @if ($canApply)
                <div class="rounded-2xl border border-blue-200 bg-blue-50/60 p-5 shadow-sm">
                    <p class="mb-1 text-xs font-bold uppercase tracking-wide text-blue-700">Persetujuan Khusus</p>
                    <p class="mb-3 text-xs text-blue-600">Menyetujui akan langsung mengubah jadwal di sistem lama (icu_dloan + term/endper).</p>
                    <form method="POST" action="{{ route('cooperative.skips.decide') }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="id" value="{{ $skip->id }}">
                        <textarea name="note" rows="2" maxlength="500" placeholder="Catatan (opsional)" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20"></textarea>
                        <button type="submit" name="decision" value="apply" onclick="return confirm('Setujui dan terapkan skip pokok ke jadwal sistem lama?')"
                                class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-primaryHover">
                            <i class="fas fa-check"></i> Setujui & Terapkan
                        </button>
                    </form>
                </div>
                <form method="POST" action="{{ route('cooperative.skips.decide') }}">
                    @csrf
                    <input type="hidden" name="id" value="{{ $skip->id }}">
                    <button type="submit" name="decision" value="reject" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700">
                        <i class="fas fa-times"></i> Tolak
                    </button>
                </form>
            @elseif ($canCancel)
                <form method="POST" action="{{ route('cooperative.skips.decide') }}" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    @csrf
                    <input type="hidden" name="id" value="{{ $skip->id }}">
                    <button type="submit" name="decision" value="cancel" onclick="return confirm('Batalkan pengajuan skip?')"
                            class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                        <i class="fas fa-ban"></i> Batalkan
                    </button>
                </form>
            @elseif ($isMaker && $skip->status === \App\Services\Cooperative\LoanSkipService::STATUS_SUBMITTED)
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs font-medium text-amber-700">Menunggu persetujuan pengguna lain (approval khusus).</div>
            @endif
        </div>
    </div>

    @if (($isAccelerate || $isSavings) && ! empty($plan['remaining_rows']))
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4"><p class="text-sm font-bold text-gray-800">Baris Tersisa yang Dikalkulasi Ulang</p></div>
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500"><tr>
                    <th class="px-5 py-2.5 font-bold">Periode</th><th class="px-5 py-2.5 text-right font-bold">Pokok (Rp)</th><th class="px-5 py-2.5 text-right font-bold">Bunga (Rp)</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($plan['remaining_rows'] as $row)
                        <tr><td class="px-5 py-2 font-mono text-xs text-gray-600">{{ \App\Services\Cooperative\CooperativePeriod::label($row['periode']) }}</td>
                        <td class="px-5 py-2 text-right text-gray-700">{{ number_format($row['amount'], 0, ',', '.') }}</td>
                        <td class="px-5 py-2 text-right text-gray-700">{{ number_format($row['int_amt'], 0, ',', '.') }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @elseif (! $isAccelerate && ! empty($plan['new_rows']))
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4"><p class="text-sm font-bold text-gray-800">Baris Baru yang Akan Ditambahkan</p></div>
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500"><tr>
                    <th class="px-5 py-2.5 font-bold">Periode</th><th class="px-5 py-2.5 text-right font-bold">Pokok (Rp)</th><th class="px-5 py-2.5 text-right font-bold">Bunga (Rp)</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($plan['new_rows'] as $newRow)
                        <tr><td class="px-5 py-2 font-mono text-xs text-gray-600">{{ \App\Services\Cooperative\CooperativePeriod::label($newRow['periode']) }}</td>
                        <td class="px-5 py-2 text-right text-gray-700">{{ number_format($newRow['amount'], 0, ',', '.') }}</td>
                        <td class="px-5 py-2 text-right text-gray-700">{{ number_format($newRow['int_amt'], 0, ',', '.') }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4"><p class="text-sm font-bold text-gray-800">Riwayat Aksi</p></div>
        <ol class="divide-y divide-gray-100 px-5 text-sm">
            @forelse ($actions as $action)
                <li class="flex items-start gap-3 py-3">
                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full {{ $action->action === 'applied' ? 'bg-green-500' : ($action->action === 'rejected' ? 'bg-red-500' : ($action->action === 'cancelled' ? 'bg-gray-400' : 'bg-blue-500')) }}"></span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-gray-800">{{ $action->actionLabel() }} <span class="font-normal text-gray-400">oleh {{ $action->actor_name }}</span></p>
                        @if ($action->note)<p class="mt-0.5 text-xs text-gray-500">{{ $action->note }}</p>@endif
                    </div>
                    <span class="whitespace-nowrap text-[11px] text-gray-400">{{ $action->created_at?->format('d M Y H:i') }}</span>
                </li>
            @empty
                <li class="py-4 text-center text-sm text-gray-400">Belum ada aksi.</li>
            @endforelse
        </ol>
    </div>
</div>
@endsection
