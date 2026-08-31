@extends('layouts.app')

@section('title', 'RUN-ITC | Detail Pengajuan')

@section('content')
@php
    use App\Services\Cooperative\LoanApplicationService;
    $isMaker = $currentUserId === $application->applicant_user_id;
    $canApprove = $application->status === LoanApplicationService::STATUS_SUBMITTED && ! $isMaker;
    $canCancel = $service->canCancel($application->applicant_user_id, $currentUserId, $application->status);
    $canPost = $application->status === LoanApplicationService::STATUS_APPROVED && ! $isMaker;
@endphp
<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('cooperative.applications.index') }}" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50" title="Kembali">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Pengajuan #{{ $application->id }}</h1>
            <p class="mt-0.5 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                <span class="font-semibold text-gray-700">{{ $application->member_name }}</span>
                <span class="font-mono text-xs">{{ $application->member_icuno }}</span>
                @if (! $isAdmin)
                    <span class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700"><i class="fas fa-lock"></i> Milik Anda</span>
                @endif
                <span class="inline-block rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $service->statusBadgeClass($application->status) }}">{{ $service->statusLabel($application->status) }}</span>
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
            <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-400">Rincian Pengajuan</p>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-2.5 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Jumlah Pinjaman</dt><dd class="font-bold text-gray-900">Rp {{ number_format($application->principal_amount, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Tenor</dt><dd class="font-medium text-gray-800">{{ $application->tenor_months }} bulan</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Bunga per Tahun</dt><dd class="font-medium text-gray-800">{{ number_format($application->annual_rate_percent, 2, ',', '.') }}%</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Metode</dt><dd class="font-medium uppercase text-gray-800">{{ $application->calculation_method }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Keperluan</dt><dd class="max-w-56 truncate font-medium text-gray-800" title="{{ $application->descr }}">{{ $application->descr ?: '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Penerimaan Dana</dt><dd class="font-medium text-gray-800">{{ $fundReleaseMethods[$application->fund_release_method] ?? $application->fund_release_method }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Biaya Admin</dt><dd class="font-medium text-gray-800">Rp {{ number_format($application->admin_fee, 0, ',', '.') }}</dd></div>
                @if ($application->fund_release_method === 'transfer')
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Bank Pencairan</dt><dd class="max-w-56 truncate font-medium text-gray-800">{{ ($bankLabels[$application->bank_bnkcd] ?? $application->bank_bnkcd) ?: '-' }} — {{ $application->bank_accnm ?? '-' }} ({{ $application->bank_accno ?? '-' }})</dd></div>
                @endif
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Diajukan</dt><dd class="font-medium text-gray-800">{{ $application->created_at?->format('d M Y H:i') }}</dd></div>
                @if ($application->reviewed_at)
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Diputuskan</dt><dd class="font-medium text-gray-800">{{ $application->reviewed_at?->format('d M Y H:i') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Reviewer User ID</dt><dd class="font-mono text-xs font-medium text-gray-800">{{ $application->reviewer_user_id }}</dd></div>
                @endif
                @if ($application->decision_note)
                    <div class="col-span-2 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600"><span class="font-bold">Catatan keputusan:</span> {{ $application->decision_note }}</div>
                @endif
            </dl>
        </div>

        <div class="space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-400">Ringkasan Simulasi</p>
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Cicilan Bulan I</dt><dd class="font-bold text-gray-900">Rp {{ number_format($application->monthly_installment, 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Total Bunga</dt><dd class="font-medium text-gray-800">Rp {{ number_format($application->total_interest, 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Total Pembayaran</dt><dd class="font-medium text-gray-800">Rp {{ number_format($application->total_payment, 0, ',', '.') }}</dd></div>
                </dl>
            </div>

            @if ($canPost)
                <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 shadow-sm">
                    <p class="mb-1 text-xs font-bold uppercase tracking-wide text-blue-700">Posting ke Pinjaman Aktual</p>
                    <p class="mb-3 text-xs text-blue-600">Membuat baris pinjaman di sistem lama (icu_mloan + icu_dloan) sesuai jadwal snapshot. Aksi ini menulis data produksi.</p>
                    <form method="POST" action="{{ route('cooperative.applications.post') }}" onsubmit="return confirm('Posting pengajuan ini menjadi pinjaman aktual di sistem lama? Lanjutkan hanya jika sudah yakin.')">
                        @csrf
                        <input type="hidden" name="id" value="{{ $application->id }}">
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-primaryHover">
                            <i class="fas fa-upload"></i> Posting Sekarang
                        </button>
                    </form>
                </div>
            @elseif ($application->posted_loan_rec_id)
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="mb-1 text-xs font-bold uppercase tracking-wide text-gray-400">Pinjaman Aktual</p>
                    <a href="{{ route('cooperative.loans.detail', ['rec_id' => $application->posted_loan_rec_id]) }}"
                       class="inline-flex items-center gap-2 text-sm font-bold text-brand-primary hover:underline">
                        <i class="fas fa-hand-holding-dollar"></i> Lihat pinjaman rec_id {{ $application->posted_loan_rec_id }}
                    </a>
                </div>
            @endif

            @if ($canApprove || $canCancel)
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-400">Keputusan</p>
                    <form method="POST" action="{{ route('cooperative.applications.decide') }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="id" value="{{ $application->id }}">
                        <textarea name="note" rows="2" maxlength="500" placeholder="Catatan keputusan (opsional)"
                                  class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20"></textarea>
                        <div class="flex flex-wrap gap-2">
                            @if ($canApprove)
                                <button type="submit" name="decision" value="approve"
                                        class="inline-flex items-center gap-2 rounded-xl bg-green-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-green-700">
                                    <i class="fas fa-check"></i> Setujui
                                </button>
                                <button type="submit" name="decision" value="reject"
                                        class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700">
                                    <i class="fas fa-times"></i> Tolak
                                </button>
                            @endif
                            @if ($canCancel)
                                <button type="submit" name="decision" value="cancel"
                                        onclick="return confirm('Batalkan pengajuan ini?')"
                                        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                                    <i class="fas fa-ban"></i> Batalkan
                                </button>
                            @endif
                        </div>
                    </form>
                </div>
            @elseif ($isMaker && $application->status === LoanApplicationService::STATUS_SUBMITTED)
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs font-medium text-amber-700">
                    Pengajuan menunggu persetujuan dari pengguna lain (maker-checker). Pembuat tidak dapat menyetujui sendiri.
                </div>
            @endif
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <p class="text-sm font-bold text-gray-800">Jadwal Angsuran Disetujui (snapshot simulasi)</p>
            <p class="mt-0.5 text-xs text-gray-400">Jadwal yang tersimpan saat pengajuan dibuat — acuan modul posting nantinya.</p>
        </div>
        <div class="max-h-72 overflow-y-auto">
            <table class="w-full min-w-[560px] text-left text-sm">
                <thead class="sticky top-0 bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-5 py-3 font-bold">#</th>
                        <th class="px-5 py-3 font-bold">Periode</th>
                        <th class="px-5 py-3 text-right font-bold">Pokok (Rp)</th>
                        <th class="px-5 py-3 text-right font-bold">Bunga (Rp)</th>
                        <th class="px-5 py-3 text-right font-bold">Total (Rp)</th>
                        <th class="px-5 py-3 text-right font-bold">Sisa Pokok (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($schedule as $row)
                        <tr class="{{ ($row['rounding'] ?? false) ? 'bg-amber-50/40' : '' }}">
                            <td class="px-5 py-2.5 text-gray-500">{{ $row['seqno'] }}</td>
                            <td class="px-5 py-2.5 font-mono text-xs text-gray-600">{{ $row['periode'] }}</td>
                            <td class="px-5 py-2.5 text-right text-gray-700">{{ number_format($row['amount'], 0, ',', '.') }}</td>
                            <td class="px-5 py-2.5 text-right text-gray-700">{{ number_format($row['int_amt'], 0, ',', '.') }}</td>
                            <td class="px-5 py-2.5 text-right font-semibold text-gray-800">{{ number_format($row['total'], 0, ',', '.') }}</td>
                            <td class="px-5 py-2.5 text-right text-gray-700">{{ number_format($row['outstand'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <p class="text-sm font-bold text-gray-800">Riwayat Aksi</p>
        </div>
        <ol class="divide-y divide-gray-100 px-5 text-sm">
            @forelse ($actions as $action)
                <li class="flex items-start gap-3 py-3">
                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full {{ $action->action === 'approved' ? 'bg-green-500' : ($action->action === 'rejected' ? 'bg-red-500' : ($action->action === 'cancelled' ? 'bg-gray-400' : 'bg-blue-500')) }}"></span>
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
@endsection
