@extends('layouts.app')

@section('title', 'RUN-ITC | Detail Pengajuan')

@section('content')
@php
    use App\Services\CreditUnion\LoanApplicationService;
    use App\Support\CreditUnionAccess;
    $personal = $personal ?? false;
    $viewParams = $personal ? ['view' => 'mine'] : [];
    $isOwnerOrMaker = CreditUnionAccess::isOwnerOrMaker($currentUserId, $application->applicant_user_id, (int) $application->member_rec_id);
    $canApprove = $isAdmin && $application->status === LoanApplicationService::STATUS_SUBMITTED && ! $isOwnerOrMaker;
    $canCancel = $service->canCancel($application->applicant_user_id, $currentUserId, $application->status, (int) $application->member_rec_id);
    $canStartTransfer = $isAdmin && $application->status === LoanApplicationService::STATUS_APPROVED && ! $isOwnerOrMaker;
    $canReturnTransfer = $isAdmin && $application->status === LoanApplicationService::STATUS_TRANSFER_PROCESSING && ! $isOwnerOrMaker;
    $canPost = $isAdmin && $application->status === LoanApplicationService::STATUS_TRANSFER_PROCESSING && ! $isOwnerOrMaker;
@endphp
<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('cu.applications.index', $viewParams) }}" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50" title="Kembali">
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
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Tipe Biaya Admin</dt><dd class="font-medium text-gray-800">{{ $application->admin_fee_type === 'include' ? 'Include, dipotong dari pencairan' : 'Exclude, ditagih ke anggota' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Biaya Admin</dt><dd class="font-medium text-gray-800">Rp {{ number_format($application->admin_fee, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Dana Diterima</dt><dd class="font-bold text-gray-900">Rp {{ number_format($application->admin_fee_type === 'include' ? max(0, $application->principal_amount - $application->admin_fee) : $application->principal_amount, 0, ',', '.') }}</dd></div>
                @if ($application->fund_release_method === 'transfer')
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Bank Pencairan</dt><dd class="max-w-56 truncate font-medium text-gray-800">{{ ($bankLabels[$application->bank_bnkcd] ?? $application->bank_bnkcd) ?: '-' }} — {{ $application->bank_accnm ?? '-' }} ({{ $application->bank_accno ?? '-' }})</dd></div>
                @endif
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Diajukan</dt><dd class="font-medium text-gray-800">{{ $application->created_at?->format('d M Y H:i') }}</dd></div>
                @if ($application->reviewed_at)
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Diputuskan</dt><dd class="font-medium text-gray-800">{{ $application->reviewed_at?->format('d M Y H:i') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Reviewer User ID</dt><dd class="font-mono text-xs font-medium text-gray-800">{{ $application->reviewer_user_id }}</dd></div>
                @endif
                @if ($application->transfer_started_at)
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Proses Transfer Dimulai</dt><dd class="font-medium text-gray-800">{{ $application->transfer_started_at?->format('d M Y H:i') }}</dd></div>
                @endif
                @if ($application->transfer_reference)
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Referensi Transfer</dt><dd class="font-medium text-gray-800">{{ $application->transfer_reference }}</dd></div>
                @endif
                @if ($application->disbursed_at)
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Tanggal Cair</dt><dd class="font-medium text-gray-800">{{ $application->disbursed_at?->format('d M Y H:i') }}</dd></div>
                @endif
                @if ($application->decision_note)
                    <div class="col-span-2 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600"><span class="font-bold">Catatan keputusan:</span> {{ $application->decision_note }}</div>
                @endif
            </dl>
        </div>

        @if ($application->transfer_proof_path)
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Bukti Transfer Pencairan</p>
                        <p class="text-sm font-semibold text-gray-800">{{ $application->transfer_proof_original_name }}</p>
                        <p class="text-xs text-gray-400">
                            {{ round(((int) $application->transfer_proof_size) / 1024, 1) }} KB
                            @if ($application->transfer_proof_uploaded_at)
                                • Diunggah {{ $application->transfer_proof_uploaded_at?->format('d M Y H:i') }}
                            @endif
                            @if ($application->transfer_proof_uploaded_by)
                                • User #{{ $application->transfer_proof_uploaded_by }}
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('cu.applications.transferProof', ['id' => $application->id]) }}" target="_blank"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-50">
                            <i class="fas fa-eye"></i> Pratinjau
                        </a>
                        <a href="{{ route('cu.applications.transferProof', ['id' => $application->id, 'download' => 1]) }}"
                           class="inline-flex items-center gap-1.5 rounded-xl bg-brand-primary px-3 py-2 text-xs font-semibold text-white transition hover:bg-brand-primaryHover">
                            <i class="fas fa-download"></i> Unduh
                        </a>
                    </div>
                </div>

                <div class="mt-4">
                    @php
                        $isImage = str_starts_with((string) $application->transfer_proof_mime, 'image/')
                            || preg_match('/\.(jpg|jpeg|png)$/i', (string) $application->transfer_proof_original_name);
                    @endphp
                    @if ($isImage)
                        <div class="flex justify-center rounded-xl bg-gray-50 p-3">
                            <img src="{{ route('cu.applications.transferProof', ['id' => $application->id]) }}"
                                 alt="Bukti Transfer"
                                 class="max-h-96 rounded-lg object-contain shadow-sm">
                        </div>
                    @else
                        <div class="flex items-center gap-3 rounded-xl bg-gray-50 p-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-100 text-red-600">
                                <i class="fas fa-file-pdf text-2xl"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-800">{{ $application->transfer_proof_original_name }}</p>
                                <p class="text-xs text-gray-500">Dokumen PDF bukti transfer pencairan</p>
                            </div>
                            <a href="{{ route('cu.applications.transferProof', ['id' => $application->id]) }}" target="_blank"
                               class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                                <i class="fas fa-arrow-up-right-from-square"></i> Buka PDF
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <div class="space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-400">Ringkasan Simulasi</p>
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Cicilan Bulan I</dt><dd class="font-bold text-gray-900">Rp {{ number_format($application->monthly_installment, 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Total Bunga</dt><dd class="font-medium text-gray-800">Rp {{ number_format($application->total_interest, 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Total Pembayaran</dt><dd class="font-medium text-gray-800">Rp {{ number_format($application->total_payment, 0, ',', '.') }}</dd></div>
                </dl>
            </div>

            @if ($canStartTransfer)
                <div class="rounded-2xl border border-purple-200 bg-purple-50 p-5 shadow-sm">
                    <p class="mb-1 text-xs font-bold uppercase tracking-wide text-purple-700">Proses Transfer</p>
                    <p class="mb-3 text-xs text-purple-600">Memulai proses transfer bank ke rekening anggota. Setelah proses ini dimulai, pengajuan dikunci dan tidak dapat dibatalkan.</p>
                    <form method="POST" action="{{ route('cu.applications.startTransfer') }}" data-confirm="Mulai proses transfer pengajuan ini? Pengajuan tidak dapat dibatalkan lagi sesudah tahap ini dimulai.">
                        @csrf
                        <input type="hidden" name="id" value="{{ $application->id }}">
                        @if ($personal)<input type="hidden" name="view" value="mine">@endif
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-purple-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-purple-700">
                            <i class="fas fa-money-bill-transfer"></i> Mulai Proses Transfer
                        </button>
                    </form>
                </div>
            @endif

            @if ($canPost)
                <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 shadow-sm">
                    <p class="mb-1 text-xs font-bold uppercase tracking-wide text-blue-700">Konfirmasi Transfer & Posting</p>
                    <p class="mb-3 text-xs text-blue-600">Klik tombol ini HANYA sesudah dana benar-benar ditransfer ke rekening anggota. Anggota akan menerima pemberitahuan email pencairan.</p>
                    @if ($application->transfer_started_at)
                        <div class="mb-3 rounded-lg border border-blue-100 bg-white/80 p-2.5 text-xs text-blue-900">
                            <p><span class="font-semibold">Proses transfer dimulai:</span> {{ $application->transfer_started_at?->format('d M Y H:i') }}</p>
                            @if ($application->transfer_started_by)
                                <p class="text-[11px] text-blue-700">Oleh User ID #{{ $application->transfer_started_by }}</p>
                            @endif
                        </div>
                    @endif
                    <form method="POST" action="{{ route('cu.applications.post') }}" enctype="multipart/form-data" data-confirm="Konfirmasi dana sudah ditransfer ke anggota dan posting ke pinjaman? Anggota akan menerima email pemberitahuan pencairan. Lanjutkan?">
                        @csrf
                        <input type="hidden" name="id" value="{{ $application->id }}">
                        @if ($personal)<input type="hidden" name="view" value="mine">@endif
                        <div class="mb-3 space-y-3">
                            @if ($application->fund_release_method === 'transfer')
                                <div>
                                    <label for="transfer_proof" class="mb-1 block text-xs font-semibold text-blue-800">
                                        Berkas Bukti Transfer <span class="text-red-500">*</span>
                                    </label>
                                    <input type="file" id="transfer_proof" name="transfer_proof" required accept=".jpg,.jpeg,.png,.pdf"
                                           class="w-full rounded-xl border border-blue-200 bg-white px-3 py-2 text-xs font-medium text-gray-800 outline-none transition file:mr-3 file:rounded-lg file:border-0 file:bg-blue-100 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-blue-700 hover:file:bg-blue-200 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20">
                                    <p class="mt-1 text-[11px] text-blue-700">Format: JPG, JPEG, PNG, PDF (Maksimal 5 MB)</p>
                                </div>
                            @endif
                            <div>
                                <label for="transfer_reference" class="mb-1 block text-xs font-semibold text-blue-800">Nomor Referensi Transfer (opsional)</label>
                                <input type="text" id="transfer_reference" name="transfer_reference" maxlength="100" placeholder="Contoh: TRX-12345678"
                                       class="w-full rounded-xl border border-blue-200 bg-white px-3 py-2 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20">
                            </div>
                            <div>
                                <label for="post_note" class="mb-1 block text-xs font-semibold text-blue-800">Catatan Tambahan (opsional)</label>
                                <textarea id="post_note" name="note" rows="2" maxlength="500" placeholder="Catatan konfirmasi (opsional)"
                                          class="w-full rounded-xl border border-blue-200 bg-white px-3 py-2 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20"></textarea>
                            </div>
                        </div>
                        <button type="submit"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-primaryHover">
                            <i class="fas fa-check-circle"></i> Konfirmasi Sudah Ditransfer
                        </button>
                    </form>

                    <div class="mt-4 border-t border-blue-200 pt-3">
                        <p class="mb-2 text-xs font-semibold text-gray-700">Kendala Transfer Bank?</p>
                        <form method="POST" action="{{ route('cu.applications.returnTransfer') }}" data-confirm="Kembalikan status pengajuan ke Disetujui?">
                            @csrf
                            <input type="hidden" name="id" value="{{ $application->id }}">
                            @if ($personal)<input type="hidden" name="view" value="mine">@endif
                            <textarea name="note" rows="2" maxlength="500" required placeholder="Alasan pengembalian (wajib, misal rekening bermasalah/transfer gagal)..."
                                      class="mb-2 w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-800 outline-none transition focus:border-red-400 focus:ring-2 focus:ring-red-200"></textarea>
                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                <i class="fas fa-rotate-left"></i> Kembalikan ke Disetujui
                            </button>
                        </form>
                    </div>
                </div>
            @elseif ($application->posted_loan_rec_id)
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="mb-1 text-xs font-bold uppercase tracking-wide text-gray-400">Pinjaman Aktual</p>
                    <a href="{{ route('cu.loans.detail', ['rec_id' => $application->posted_loan_rec_id]) }}"
                       class="inline-flex items-center gap-2 text-sm font-bold text-brand-primary hover:underline">
                        <i class="fas fa-hand-holding-dollar"></i> Lihat pinjaman aktual
                    </a>
                </div>

                @if ($isAdmin)
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                        <p class="mb-1 text-xs font-bold uppercase tracking-wide text-gray-400">Ganti Bukti Transfer</p>
                        <p class="mb-3 text-xs text-gray-500">Admin CU dapat mengganti berkas bukti transfer jika diperlukan. Penggantian tercatat dalam riwayat aksi.</p>
                        <form method="POST" action="{{ route('cu.applications.replaceTransferProof') }}" enctype="multipart/form-data" data-confirm="Ganti berkas bukti transfer pengajuan ini?">
                            @csrf
                            <input type="hidden" name="id" value="{{ $application->id }}">
                            @if ($personal)<input type="hidden" name="view" value="mine">@endif
                            <div class="mb-3">
                                <label for="replace_transfer_proof" class="mb-1 block text-xs font-semibold text-gray-700">Berkas Baru</label>
                                <input type="file" id="replace_transfer_proof" name="transfer_proof" required accept=".jpg,.jpeg,.png,.pdf"
                                       class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-800 outline-none transition file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-gray-700 hover:file:bg-gray-200 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20">
                                <p class="mt-1 text-[11px] text-gray-400">JPG, JPEG, PNG, PDF (Maks 5 MB)</p>
                            </div>
                            <button type="submit"
                                    class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl border border-gray-300 bg-white px-4 py-2 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
                                <i class="fas fa-arrow-right-arrow-left"></i> Ganti Bukti Transfer
                            </button>
                        </form>
                    </div>
                @endif
            @endif

            @if ($canApprove || $canCancel)
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-400">Keputusan</p>
                    <form method="POST" action="{{ route('cu.applications.decide') }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="id" value="{{ $application->id }}">
                        @if ($personal)<input type="hidden" name="view" value="mine">@endif
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
                                        data-confirm="Batalkan pengajuan ini?"
                                        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                                    <i class="fas fa-ban"></i> Batalkan
                                </button>
                            @endif
                        </div>
                    </form>
                </div>
            @elseif ($isOwnerOrMaker && $application->status === LoanApplicationService::STATUS_SUBMITTED)
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs font-medium text-amber-700">
                    Pengajuan menunggu persetujuan admin lain. Anda tidak dapat menyetujui pengajuan pinjaman Anda sendiri.
                </div>
            @elseif ($isOwnerOrMaker && $application->status === LoanApplicationService::STATUS_APPROVED)
                <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-xs font-medium text-blue-700">
                    Pengajuan telah disetujui dan menunggu posting/pencairan oleh admin lain.
                </div>
            @elseif ($isOwnerOrMaker && $application->status === LoanApplicationService::STATUS_TRANSFER_PROCESSING)
                <div class="rounded-2xl border border-purple-200 bg-purple-50 p-4 text-xs font-medium text-purple-700">
                    Dana pinjaman sedang diproses transfer oleh admin. Pengajuan tidak dapat dibatalkan pada tahap ini.
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
                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full {{ match ($action->action) {
                        'approved' => 'bg-green-500',
                        'transfer_processing' => 'bg-purple-500',
                        'transfer_returned' => 'bg-amber-500',
                        'proof_replaced' => 'bg-indigo-500',
                        'rejected' => 'bg-red-500',
                        'cancelled' => 'bg-gray-400',
                        default => 'bg-blue-500',
                    } }}"></span>
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
