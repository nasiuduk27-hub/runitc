@extends('layouts.app')

@section('title', 'RUN-ITC | Edit Transaksi Bank')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('cu.bank-transactions.index') }}" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50" title="Kembali">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Edit Transaksi Bank</h1>
            <p class="mt-0.5 text-sm text-gray-500">
                @if ($detail)
                    Edit transaksi {{ $detail->categoryLabel() }}. Nomor transaksi dan kategori dikunci demi konsistensi audit.
                @else
                    Edit transaksi umum. Nomor transaksi & referensi tidak dapat diganti.
                @endif
            </p>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('cu.bank-transactions.update', $trx->rec_id) }}" class="space-y-5">
        @csrf
        @method('PUT')

        <div class="space-y-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            @if ($detail && $detail->category === 'admin_fee')
                <!-- Kategori Biaya Admin Bank -->
                <div class="flex items-center justify-between rounded-xl border border-rose-200 bg-rose-50/60 p-4">
                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-bold text-rose-800">
                            <i class="fas fa-file-invoice-dollar mr-1"></i> Biaya Administrasi Bank
                        </span>
                        <span class="text-xs font-medium text-rose-700">Arah: <b>Kredit (Keluar)</b> &mdash; Mengurangi kas bank</span>
                    </div>
                    <div class="text-xs font-semibold text-gray-600">
                        {{ $detail->bankAccountDisplay() }}
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="trnno" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nomor Transaksi</label>
                        <input type="text" id="trnno" value="{{ $trx->trnno }}" readonly
                               class="w-full rounded-xl border border-gray-200 bg-gray-100 px-3 py-2.5 font-mono text-sm font-semibold text-gray-500 outline-none">
                    </div>

                    <div>
                        <label for="admin_fee_amount" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nominal Biaya Admin Bank (Rp) <span class="text-red-500">*</span></label>
                        <input type="text" id="admin_fee_amount" name="admin_fee_amount" inputmode="numeric" autocomplete="off" data-rupiah
                               value="{{ old('admin_fee_amount', $detail->net_amount ?: $trx->amount) }}"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-rose-500 focus:bg-white focus:ring-2 focus:ring-rose-500/20" required>
                    </div>

                    <div>
                        <label for="trndt" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Tanggal Transaksi <span class="text-red-500">*</span></label>
                        <x-date-input name="trndt" id="trndt" value="{{ old('trndt', $trx->trndt) }}" required
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" />
                        <p class="mt-1 text-[11px] text-gray-400">Periode laporan otomatis menyesuaikan bulan transaksi ini.</p>
                    </div>

                    <div>
                        <label for="notes" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Notes / Keterangan Tambahan <span class="text-gray-400">(opsional)</span></label>
                        <input type="text" id="notes" name="notes" maxlength="50" value="{{ old('notes', $detail->notes ?: $trx->notes) }}"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="descr" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Deskripsi <span class="text-red-500">*</span></label>
                        <input type="text" id="descr" name="descr" maxlength="100" value="{{ old('descr', $trx->descr) }}"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" required>
                    </div>
                </div>

            @elseif ($detail && $detail->category === 'interest')
                <!-- Kategori Pendapatan Bunga Bank -->
                <div class="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">
                            <i class="fas fa-coins mr-1"></i> Pendapatan Bunga Bank
                        </span>
                        <span class="text-xs font-medium text-emerald-700">Arah: <b>Debit (Masuk)</b> &mdash; Bunga Neto menambah kas bank</span>
                    </div>
                    <div class="text-xs font-semibold text-gray-600">
                        {{ $detail->bankAccountDisplay() }}
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="trnno" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nomor Transaksi</label>
                        <input type="text" id="trnno" value="{{ $trx->trnno }}" readonly
                               class="w-full rounded-xl border border-gray-200 bg-gray-100 px-3 py-2.5 font-mono text-sm font-semibold text-gray-500 outline-none">
                    </div>

                    <div>
                        <label for="trndt" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Tanggal Transaksi <span class="text-red-500">*</span></label>
                        <x-date-input name="trndt" id="trndt" value="{{ old('trndt', $trx->trndt) }}" required
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" />
                        <p class="mt-1 text-[11px] text-gray-400">Periode laporan otomatis menyesuaikan bulan transaksi ini.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="gross_amount" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Bunga Bruto (Rp) <span class="text-red-500">*</span></label>
                        <input type="text" id="gross_amount" name="gross_amount" inputmode="numeric" autocomplete="off" data-rupiah
                               value="{{ old('gross_amount', $detail->gross_amount) }}"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/20" required>
                    </div>

                    <div>
                        <label for="tax_amount" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Pajak Bunga (Rp)</label>
                        <input type="text" id="tax_amount" name="tax_amount" inputmode="numeric" autocomplete="off" data-rupiah
                               value="{{ old('tax_amount', $detail->tax_amount) }}"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/20">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Bunga Neto (Rp) <span class="text-emerald-600 font-semibold">(Buku Kas)</span></label>
                        <div id="netAmountDisplay" class="flex h-[42px] items-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 font-mono text-sm font-bold text-emerald-800">
                            Rp {{ number_format(max(0, $detail->gross_amount - $detail->tax_amount), 0, ',', '.') }}
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="descr" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Deskripsi <span class="text-red-500">*</span></label>
                        <input type="text" id="descr" name="descr" maxlength="100" value="{{ old('descr', $trx->descr) }}"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" required>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="notes" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Notes / Keterangan Tambahan <span class="text-gray-400">(opsional)</span></label>
                        <input type="text" id="notes" name="notes" maxlength="50" value="{{ old('notes', $detail->notes ?: $trx->notes) }}"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                    </div>
                </div>

            @else
                <!-- Form Transaksi Umum Existing -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="req_frm_trxno" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nomor Referensi</label>
                        <input type="text" id="req_frm_trxno" value="{{ $trx->req_frm_trxno }}" readonly
                               class="w-full rounded-xl border border-gray-200 bg-gray-100 px-3 py-2.5 font-mono text-sm font-semibold text-gray-500 outline-none">
                    </div>
                    <div>
                        <label for="trnno" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nomor Transaksi</label>
                        <input type="text" id="trnno" value="{{ $trx->trnno }}" readonly
                               class="w-full rounded-xl border border-gray-200 bg-gray-100 px-3 py-2.5 font-mono text-sm font-semibold text-gray-500 outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="amount" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Amount (Rupiah) <span class="text-red-500">*</span></label>
                        <input type="text" id="amount" name="amount" inputmode="numeric" autocomplete="off" data-rupiah value="{{ old('amount', $trx->amount) }}"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" required>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Jenis <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-600 transition has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50 has-[:checked]:text-blue-700">
                                <input type="radio" name="dbocr" value="D" {{ old('dbocr', $trx->dbocr) === 'D' ? 'checked' : '' }} class="h-4 w-4 accent-brand-primary" required>
                                Debit
                            </label>
                            <label class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-600 transition has-[:checked]:border-green-600 has-[:checked]:bg-green-50 has-[:checked]:text-green-700">
                                <input type="radio" name="dbocr" value="C" {{ old('dbocr', $trx->dbocr) === 'C' ? 'checked' : '' }} class="h-4 w-4 accent-brand-primary" required>
                                Kredit
                            </label>
                        </div>
                    </div>

                    <div>
                        <label for="trndt" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Tanggal Transaksi <span class="text-red-500">*</span></label>
                        <x-date-input name="trndt" id="trndt" value="{{ old('trndt', $trx->trndt) }}" required
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" />
                    </div>

                    <div>
                        <label for="notes" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Notes (Perusahaan) <span class="text-gray-400">(opsional)</span></label>
                        <input type="text" id="notes" name="notes" maxlength="50" value="{{ old('notes', $trx->notes) }}"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="descr" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Deskripsi <span class="text-red-500">*</span></label>
                        <input type="text" id="descr" name="descr" maxlength="100" value="{{ old('descr', $trx->descr) }}"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" required>
                    </div>
                </div>
            @endif

            <div class="flex flex-wrap gap-2 pt-2 border-t border-gray-100">
                <button type="submit"
                        data-confirm="Simpan perubahan transaksi bank?"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
                <a href="{{ route('cu.bank-transactions.index') }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Batal</a>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    $(function () {
        const $gross = $('#gross_amount');
        const $tax = $('#tax_amount');
        const $netDisplay = $('#netAmountDisplay');

        if ($gross.length) {
            function parseNum(val) {
                return parseInt(String(val || '').replace(/\D/g, ''), 10) || 0;
            }

            function updateNetInterest() {
                const grossVal = parseNum($gross.val());
                const taxVal = parseNum($tax.val());
                const netVal = Math.max(0, grossVal - taxVal);
                $netDisplay.text('Rp ' + netVal.toLocaleString('id-ID'));
            }

            $gross.on('input', updateNetInterest);
            $tax.on('input', updateNetInterest);
            updateNetInterest();
        }
    });
</script>
@endpush
