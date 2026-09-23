@extends('layouts.app')

@section('title', 'RUN-ITC | Detail Refinancing')

@section('content')
@php
    use App\Support\CreditUnionAccess;
    $isOwnerOrMaker = CreditUnionAccess::isOwnerOrMaker($currentUserId, (int) $skip->maker_user_id, (int) $skip->member_rec_id);
    $canApproveAsAdmin = CreditUnionAccess::canApproveAsAdmin($currentUserId, (int) $skip->maker_user_id, (int) $skip->member_rec_id);
    $canApply = $canApproveAsAdmin && (($skip->mode === \App\Services\CreditUnion\LoanSkipService::MODE_TRANSFER && $skip->status === \App\Services\CreditUnion\LoanSkipService::STATUS_PAID)
        || ($skip->mode !== \App\Services\CreditUnion\LoanSkipService::MODE_TRANSFER && $skip->status === \App\Services\CreditUnion\LoanSkipService::STATUS_SUBMITTED));
    $canCancel = $skip->status === \App\Services\CreditUnion\LoanSkipService::STATUS_SUBMITTED && $isOwnerOrMaker;
    $isAccelerate = $skip->mode === \App\Services\CreditUnion\LoanSkipService::MODE_ACCELERATE;
    $isSavings = $skip->mode === \App\Services\CreditUnion\LoanSkipService::MODE_SAVINGS;
    $isTransfer = $skip->mode === \App\Services\CreditUnion\LoanSkipService::MODE_TRANSFER;
    $canVerify = $canApproveAsAdmin && $isTransfer && $skip->status === \App\Services\CreditUnion\LoanSkipService::STATUS_SUBMITTED;
    $modeLabel = $isAccelerate ? 'Percepatan' : ($isSavings ? 'Potong Simpanan' : ($isTransfer ? 'Transfer ke Rekening' : 'Skip Pokok'));
@endphp
<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('cu.skips.index') }}" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $modeLabel }} #{{ $skip->id }}</h1>
            <p class="mt-0.5 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                <span class="font-semibold text-gray-700">{{ $skip->member_name }}</span>
                <span class="font-mono text-xs">{{ $skip->member_icuno }}</span>
                <span class="inline-block rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $isAccelerate ? 'bg-orange-50 text-orange-700 border-orange-200' : ($isSavings ? 'bg-teal-50 text-teal-700 border-teal-200' : ($isTransfer ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-blue-50 text-blue-700 border-blue-200')) }}">{{ $modeLabel }}</span>
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
            <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-400">{{ $isAccelerate ? 'Rencana Percepatan' : ($isSavings ? 'Rencana Potong Simpanan' : ($isTransfer ? 'Rencana Transfer ke Rekening' : 'Rencana Skip')) }}</p>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-2.5 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Pinjaman</dt><dd><a href="{{ route('cu.loans.detail', ['rec_id' => $skip->loan_rec_id]) }}" class="font-mono font-semibold text-brand-primary hover:underline">Buka detail pinjaman</a></dd></div>
                @if ($isSavings || $isTransfer)
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ $isTransfer ? 'Dana Diajukan' : 'Simpanan Dipakai' }}</dt><dd class="font-bold text-gray-900">Rp {{ number_format($skip->principal_moved, 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Periode Dikurangi</dt><dd class="font-medium text-gray-800">{{ $skip->rows_skipped }} periode</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Potongan / Periode</dt><dd class="font-medium text-gray-800">Rp {{ number_format($skip->rows_skipped > 0 ? intdiv($skip->principal_moved, $skip->rows_skipped) : 0, 0, ',', '.') }}</dd></div>
                @elseif ($isAccelerate)
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Percepatan</dt><dd class="font-medium text-gray-800">−{{ $skip->months_count }} bln (s.d. {{ \App\Services\CreditUnion\CreditUnionPeriod::label($plan['new_last_periode'] ?? $skip->start_period) }})</dd></div>
                @else
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Rentang</dt><dd class="font-medium text-gray-800">{{ \App\Services\CreditUnion\CreditUnionPeriod::label($skip->start_period) }} s.d. {{ \App\Services\CreditUnion\CreditUnionPeriod::label($plan['window_end'] ?? $skip->start_period) }} ({{ $skip->months_count }} bln)</dd></div>
                @endif
                @if ($isAccelerate)
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Baris Dihapus</dt><dd class="font-medium text-gray-800">{{ $skip->rows_skipped }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Baris Sisa</dt><dd class="font-medium text-gray-800">{{ $skip->new_term }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Pokok Dikompensasi</dt><dd class="font-bold text-gray-900">Rp {{ number_format($skip->principal_moved, 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Bunga Tetap Ditagih</dt><dd class="font-medium text-amber-600">Rp {{ number_format($skip->extra_interest, 0, ',', '.') }}</dd></div>
                @elseif (! $isSavings && ! $isTransfer)
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Baris Diskip</dt><dd class="font-medium text-gray-800">{{ $skip->rows_skipped }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Pokok Dipindah</dt><dd class="font-bold text-gray-900">Rp {{ number_format($skip->principal_moved, 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Biaya Perpanjang</dt><dd class="font-medium text-amber-600">Rp {{ number_format($skip->extra_interest, 0, ',', '.') }}</dd></div>
                @endif
                @if ($isTransfer)
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Nomor Referensi</dt><dd class="font-mono font-bold text-indigo-700">{{ $skip->reference_no ?? '-' }}</dd></div>
                    @if ($skip->paid_amount !== null)
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Dana Diterima</dt><dd class="font-bold text-gray-900">Rp {{ number_format($skip->paid_amount, 0, ',', '.') }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Tanggal Masuk</dt><dd class="font-medium text-gray-800">{{ $skip->paid_at?->format('d M Y') ?? '-' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">No. Mutasi Bank</dt><dd class="font-mono text-xs text-gray-700">{{ $skip->bank_trnno ?? '-' }}</dd></div>
                    @endif
                @endif
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Tenor Baru</dt><dd class="font-medium text-gray-800">{{ $skip->new_term }} bln</dd></div>
                @if ($skip->reason)
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Alasan</dt><dd class="max-w-56 truncate text-right text-gray-700" title="{{ $skip->reason }}">{{ $skip->reason }}</dd></div>
                @endif
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Diajukan</dt><dd class="font-medium text-gray-800">{{ $skip->created_at?->format('d M Y H:i') }}</dd></div>
                @if ($plan['new_last_periode'] ?? null)
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Jadwal Baru Berakhir</dt><dd class="font-mono font-medium text-gray-800">{{ \App\Services\CreditUnion\CreditUnionPeriod::label($plan['new_last_periode']) }}</dd></div>
                @endif
            </dl>
            @if ($skip->decision_note)
                <p class="mt-3 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600"><span class="font-bold">Catatan keputusan:</span> {{ $skip->decision_note }}</p>
            @endif
        </div>

        <div class="space-y-4">
            @if ($isTransfer && $skip->status === \App\Services\CreditUnion\LoanSkipService::STATUS_SUBMITTED)
                <div class="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-5 shadow-sm">
                    <p class="mb-1 text-xs font-bold uppercase tracking-wide text-indigo-700">Instruksi Transfer</p>
                    <p class="mb-3 text-xs text-indigo-600">Transfer sebesar <span class="font-bold">Rp {{ number_format($skip->principal_moved, 0, ',', '.') }}</span> ke rekening koperasi.</p>
                    @if ($bankAccount)
                        <dl class="space-y-1.5 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-indigo-700/80">Bank</dt><dd class="font-semibold text-gray-800">{{ $bankAccount['bank'] ?: '-' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-indigo-700/80">No. Rekening</dt><dd class="font-mono font-bold text-gray-900">{{ $bankAccount['account_no'] ?: '-' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-indigo-700/80">Atas Nama</dt><dd class="font-semibold text-gray-800">{{ $bankAccount['account_name'] ?: '-' }}</dd></div>
                        </dl>
                    @else
                        <p class="rounded-lg bg-white px-3 py-2 text-xs text-gray-600">Hubungi admin untuk nomor rekening tujuan transfer.</p>
                    @endif
                    <div class="mt-3 rounded-lg bg-white px-3 py-2">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-indigo-600">Berita / Memo Transfer</p>
                        <p class="font-mono text-base font-extrabold text-gray-900">{{ $skip->reference_no ?? '-' }}</p>
                        <p class="mt-0.5 text-[11px] text-gray-500">Cantumkan nomor ini agar admin mudah mencocokkan dana masuk.</p>
                    </div>
                </div>

                @if ($canVerify)
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-5 shadow-sm">
                        <p class="mb-1 text-xs font-bold uppercase tracking-wide text-emerald-700">Verifikasi Dana Masuk</p>
                        <p class="mb-3 text-xs text-emerald-700">Pastikan dana sudah masuk rekening koperasi sebelum verifikasi.</p>
                        <form method="POST" action="{{ route('cu.skips.verify') }}" class="space-y-3">
                            @csrf
                            <input type="hidden" name="id" value="{{ $skip->id }}">
                            <div>
                                <label for="paid_amount" class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500">Nominal Diterima (Rp)</label>
                                <input type="text" id="paid_amount" name="paid_amount" inputmode="numeric" autocomplete="off" data-rupiah value="{{ $skip->principal_moved }}"
                                       class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20">
                            </div>
                            <div>
                                <label for="paid_at" class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500">Tanggal Masuk</label>
                                <input type="date" id="paid_at" name="paid_at" value="{{ now()->toDateString() }}"
                                       class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20">
                            </div>
                            <textarea name="note" rows="2" maxlength="500" placeholder="Catatan investigasi (opsional)" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20"></textarea>
                            <button type="submit" data-confirm="Tandai dana sudah diterima dan catat ke buku rekening koperasi?"
                                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                                <i class="fas fa-magnifying-glass-dollar"></i> Verifikasi Dana Masuk
                            </button>
                        </form>
                    </div>
                @endif
            @endif

            @if ($isTransfer && $skip->status === \App\Services\CreditUnion\LoanSkipService::STATUS_PAID)
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-medium text-emerald-700">
                    Dana Rp {{ number_format($skip->paid_amount, 0, ',', '.') }} sudah diverifikasi masuk. Siap diterapkan ke jadwal.
                </div>
            @endif

            @if ($canApply)
                <div class="rounded-2xl border border-blue-200 bg-blue-50/60 p-5 shadow-sm">
                    <p class="mb-1 text-xs font-bold uppercase tracking-wide text-blue-700">Persetujuan Khusus</p>
                    <p class="mb-3 text-xs text-blue-600">Menyetujui akan langsung mengubah jadwal di sistem lama.</p>
                    <form method="POST" action="{{ route('cu.skips.decide') }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="id" value="{{ $skip->id }}">
                        <textarea name="note" rows="2" maxlength="500" placeholder="Catatan (opsional)" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20"></textarea>
                        <button type="submit" name="decision" value="apply" data-confirm="Setujui dan terapkan perubahan ke jadwal sistem lama?"
                                class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-primaryHover">
                            <i class="fas fa-check"></i> Setujui & Terapkan
                        </button>
                    </form>
                </div>
                <form method="POST" action="{{ route('cu.skips.decide') }}">
                    @csrf
                    <input type="hidden" name="id" value="{{ $skip->id }}">
                    <button type="submit" name="decision" value="reject" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700">
                        <i class="fas fa-times"></i> Tolak
                    </button>
                </form>
            @elseif ($canCancel)
                <form method="POST" action="{{ route('cu.skips.decide') }}" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    @csrf
                    <input type="hidden" name="id" value="{{ $skip->id }}">
                    <button type="submit" name="decision" value="cancel" data-confirm="Batalkan pengajuan skip?"
                            class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                        <i class="fas fa-ban"></i> Batalkan
                    </button>
                </form>
            @elseif ($isOwnerOrMaker && in_array($skip->status, [\App\Services\CreditUnion\LoanSkipService::STATUS_SUBMITTED, \App\Services\CreditUnion\LoanSkipService::STATUS_PAID], true))
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs font-medium text-amber-700">
                    Pengajuan refinancing milik Anda sendiri dan sedang menunggu proses persetujuan oleh admin lain.
                </div>
            @endif
        </div>
    </div>

    @if (($isAccelerate || $isSavings || $isTransfer) && ! empty($plan['remaining_rows']))
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4"><p class="text-sm font-bold text-gray-800">Baris Tersisa yang Dikalkulasi Ulang</p></div>
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500"><tr>
                    <th class="px-5 py-2.5 font-bold">Periode</th><th class="px-5 py-2.5 text-right font-bold">Pokok (Rp)</th><th class="px-5 py-2.5 text-right font-bold">Bunga (Rp)</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($plan['remaining_rows'] as $row)
                        <tr><td class="px-5 py-2 font-mono text-xs text-gray-600">{{ \App\Services\CreditUnion\CreditUnionPeriod::label($row['periode']) }}</td>
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
                        <tr><td class="px-5 py-2 font-mono text-xs text-gray-600">{{ \App\Services\CreditUnion\CreditUnionPeriod::label($newRow['periode']) }}</td>
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
                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full {{ $action->action === 'applied' ? 'bg-green-500' : ($action->action === 'rejected' ? 'bg-red-500' : ($action->action === 'cancelled' ? 'bg-gray-400' : ($action->action === 'verified' ? 'bg-emerald-500' : 'bg-blue-500'))) }}"></span>
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
