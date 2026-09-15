@extends('layouts.app')

@section('title', 'RUN-ITC | Detail Pembayaran')

@section('content')
@php
    $isMaker = $currentUserId === $payment->maker_user_id;
    $canVerify = $payment->status === \App\Services\Cooperative\LoanPaymentService::STATUS_SUBMITTED;
    $canCancel = $payment->status === \App\Services\Cooperative\LoanPaymentService::STATUS_SUBMITTED && $isMaker;
@endphp
<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('cooperative.payments.index') }}" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50" title="Kembali">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Pembayaran #{{ $payment->id }}</h1>
            <p class="mt-0.5 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                <span class="font-semibold text-gray-700">{{ $payment->member_name }}</span>
                <span class="font-mono text-xs">{{ $payment->member_icuno }}</span>
                @if ($payment->icu_trnno)
                    <span class="font-mono font-semibold text-brand-primary">{{ $payment->icu_trnno }}</span>
                @endif
                <span class="inline-block rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $service->statusBadgeClass($payment->status) }}">{{ $service->statusLabel($payment->status) }}</span>
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
            <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-400">Rincian Pembayaran</p>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-2.5 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Pinjaman</dt><dd><a href="{{ route('cooperative.loans.detail', ['rec_id' => $payment->loan_rec_id]) }}" class="font-mono font-semibold text-brand-primary hover:underline">Buka detail pinjaman</a></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Tanggal Bayar</dt><dd class="font-medium text-gray-800">{{ $payment->payment_date?->format('d M Y') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Nominal</dt><dd class="font-bold text-gray-900">Rp {{ number_format($payment->amount, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Metode</dt><dd class="font-medium text-gray-800">{{ ucfirst($payment->method) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Porsi Pokok</dt><dd class="font-medium text-gray-800">Rp {{ number_format($payment->principal_portion, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Porsi Bunga</dt><dd class="font-medium text-gray-800">Rp {{ number_format($payment->interest_portion, 0, ',', '.') }}</dd></div>
                @if ($payment->notes)
                    <div class="col-span-2 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600"><span class="font-bold">Catatan:</span> {{ $payment->notes }}</div>
                @endif
                @if ($payment->decision_note)
                    <div class="col-span-2 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600"><span class="font-bold">Catatan keputusan:</span> {{ $payment->decision_note }}</div>
                @endif
            </dl>
        </div>

        <div class="space-y-4">
            @if ($canVerify)
                <div class="rounded-2xl border border-green-200 bg-green-50/50 p-5 shadow-sm">
                    <p class="mb-1 text-xs font-bold uppercase tracking-wide text-green-700">Verifikasi</p>
                    <p class="mb-3 text-xs text-green-600">Menyetujui akan langsung memposting ke sistem lama (transaksi, jadwal, paid, outstanding).</p>
                    <form method="POST" action="{{ route('cooperative.payments.decide') }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="id" value="{{ $payment->id }}">
                        <textarea name="note" rows="2" maxlength="500" placeholder="Catatan (opsional)"
                                  class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20"></textarea>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" name="decision" value="verify"
                                    onclick="return confirm('Verifikasi dan posting pembayaran ini ke sistem lama?')"
                                    class="inline-flex items-center gap-2 rounded-xl bg-green-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-green-700">
                                <i class="fas fa-check"></i> Verifikasi & Posting
                            </button>
                            <button type="submit" name="decision" value="reject"
                                    class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700">
                                <i class="fas fa-times"></i> Tolak
                            </button>
                        </div>
                    </form>
                </div>
            @elseif ($canCancel)
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-400">Aksi</p>
                    <form method="POST" action="{{ route('cooperative.payments.decide') }}">
                        @csrf
                        <input type="hidden" name="id" value="{{ $payment->id }}">
                        <button type="submit" name="decision" value="cancel" onclick="return confirm('Batalkan pembayaran ini?')"
                                class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                            <i class="fas fa-ban"></i> Batalkan
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <p class="text-sm font-bold text-gray-800">Alokasi Cicilan</p>
                <p class="mt-0.5 text-xs text-gray-400">Baris bertanda Lunas akan ditandai lunas saat diposting; sisanya tercatat sebagai parsial.</p>
            </div>
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                    <tr><th class="px-5 py-2.5 font-bold">Cicilan</th><th class="px-5 py-2.5 font-bold">Periode</th><th class="px-5 py-2.5 text-right font-bold">Dibayarkan (Rp)</th><th class="px-5 py-2.5 font-bold">Status Baris</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($allocations as $allocation)
                        <tr>
                            <td class="px-5 py-2.5 text-gray-600">{{ $allocation->seqno }}</td>
                            <td class="px-5 py-2.5 font-mono text-xs text-gray-600">{{ isset($allocationContext[$allocation->dloan_rec_id]) ? \App\Services\Cooperative\CooperativePeriod::label($allocationContext[$allocation->dloan_rec_id]->periode) : '-' }}</td>
                            <td class="px-5 py-2.5 text-right font-semibold text-gray-800">{{ number_format($allocation->amount_applied, 0, ',', '.') }}</td>
                            <td class="px-5 py-2.5"><span class="whitespace-nowrap rounded-full px-2 py-0.5 text-[10px] font-bold {{ $allocation->covers_full ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' }}">{{ $allocation->covers_full ? 'Lunas' : 'Parsial' }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <p class="text-sm font-bold text-gray-800">Riwayat Aksi</p>
            </div>
            <ol class="divide-y divide-gray-100 px-5 text-sm">
                @forelse ($actions as $action)
                    <li class="flex items-start gap-3 py-3">
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full {{ $action->action === 'verified' ? 'bg-green-500' : ($action->action === 'rejected' ? 'bg-red-500' : ($action->action === 'cancelled' ? 'bg-gray-400' : 'bg-blue-500')) }}"></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-800">{{ $action->actionLabel() }} <span class="font-normal text-gray-400">oleh {{ $action->actor_name }} (user #{{ $action->actor_user_id }})</span></p>
                            @if ($action->note)
                                <p class="mt-0.5 text-xs text-gray-500">{{ $action->note }}</p>
                            @endif
                        </div>
                        <span class="whitespace-nowrap text-[11px] text-gray-400">{{ $action->created_at?->format('d M Y H:i') }}</span>
                    </li>
                @empty
                    <li class="py-4 text-center text-sm text-gray-400">Belum ada aksi.</li>
                @endforelse
            </ol>
        </div>
    </div>
</div>
@endsection
