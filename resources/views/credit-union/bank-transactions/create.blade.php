@extends('layouts.app')

@section('title', 'RUN-ITC | Tambah Transaksi Bank')

<style>
    .select2-container { width: 100% !important; }
    .select2-container .select2-selection--single {
        height: 42px !important;
        border-color: #E5E7EB !important;
        border-radius: 0.75rem !important;
        background-color: #F9FAFB !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 40px !important;
        padding-left: 12px !important;
        color: #1F2937 !important;
        font-size: 0.875rem !important;
        font-weight: 600 !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px !important; }
    .select2-container--default .select2-selection--single .select2-selection__placeholder { color: #6B7280 !important; font-weight: 500 !important; }
    .select2-dropdown { border-radius: 0.75rem !important; border-color: #E5E7EB !important; }
    .select2-search--dropdown .select2-search__field {
        border-radius: 0.5rem !important;
        border-color: #E5E7EB !important;
        outline: none !important;
        font-size: 0.875rem !important;
    }
</style>

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('cu.bank-transactions.index') }}" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50" title="Kembali">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Tambah Transaksi Bank</h1>
            <p class="mt-0.5 text-sm text-gray-500">
                Pilih kategori transaksi: mutasi umum (tagihan PMT / operasional), Biaya Administrasi Bank, atau Pendapatan Bunga Bank.
            </p>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('cu.bank-transactions.store') }}" class="space-y-5" id="bankTrxForm">
        @csrf
        <div class="space-y-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            @php $selectedCategory = old('category', ''); @endphp

            <div>
                <label class="mb-2 block text-xs font-bold uppercase tracking-wide text-gray-500">Kategori Transaksi <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 bg-gray-50 p-3.5 transition has-[:checked]:border-brand-primary has-[:checked]:bg-brand-primary/5 has-[:checked]:ring-1 has-[:checked]:ring-brand-primary">
                        <input type="radio" name="category" value="" class="mt-0.5 h-4 w-4 accent-brand-primary category-radio" {{ $selectedCategory === '' ? 'checked' : '' }}>
                        <div>
                            <span class="block text-sm font-bold text-gray-800">Umum / Tagihan HRD</span>
                            <span class="mt-0.5 block text-xs text-gray-500">Penerimaan PMT, koreksi, transfer, dan mutasi kas lainnya.</span>
                        </div>
                    </label>

                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 bg-gray-50 p-3.5 transition has-[:checked]:border-rose-500 has-[:checked]:bg-rose-50/50 has-[:checked]:ring-1 has-[:checked]:ring-rose-500">
                        <input type="radio" name="category" value="admin_fee" class="mt-0.5 h-4 w-4 accent-rose-600 category-radio" {{ $selectedCategory === 'admin_fee' ? 'checked' : '' }}>
                        <div>
                            <span class="block text-sm font-bold text-gray-800">Biaya Admin Bank</span>
                            <span class="mt-0.5 block text-xs text-gray-500">Beban administrasi bulanan rekening bank (mengurangi saldo kas).</span>
                        </div>
                    </label>

                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 bg-gray-50 p-3.5 transition has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50/50 has-[:checked]:ring-1 has-[:checked]:ring-emerald-500">
                        <input type="radio" name="category" value="interest" class="mt-0.5 h-4 w-4 accent-emerald-600 category-radio" {{ $selectedCategory === 'interest' ? 'checked' : '' }}>
                        <div>
                            <span class="block text-sm font-bold text-gray-800">Pendapatan Bunga Bank</span>
                            <span class="mt-0.5 block text-xs text-gray-500">Bunga giro/tabungan bruto & pajak (neto menambah saldo kas).</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Rekening Bank Koperasi Snapshot (Tampil pada Admin Fee & Interest) -->
            <div id="bankAccountCard" class="hidden rounded-xl border border-blue-200 bg-blue-50/60 p-4">
                <div class="flex items-center gap-2 text-xs font-bold text-blue-900">
                    <i class="fas fa-building-columns"></i>
                    <span>Rekening Bank Koperasi Terdaftar</span>
                </div>
                <div class="mt-1 text-sm font-semibold text-blue-950">
                    {{ $bankAccount['bank'] ?? 'Bank Mandiri' }} &middot; {{ $bankAccount['account_no'] ?? '-' }} &middot; a.n. {{ $bankAccount['account_name'] ?? '-' }}
                </div>
                <p class="mt-1 text-[11px] text-blue-700">Entri ini dicatat terikat pada rekening koperasi sesuai pengaturan sistem.</p>
            </div>

            <!-- Section 1: Form Khusus Umum / Tagihan HRD -->
            <div id="sectionGeneral" class="space-y-4">
                <div>
                    <label for="req_frm_trxno" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nomor Referensi <span class="text-gray-400">(opsional)</span></label>
                    <select id="req_frm_trxno" name="req_frm_trxno"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                        <option value="">Tanpa referensi (mutasi di luar tagihan HRD)</option>
                        @foreach ($references as $ref)
                            <option value="{{ $ref['trxno'] }}"
                                    data-amount="{{ $ref['amount'] }}"
                                    data-date="{{ $ref['date'] }}"
                                    data-cmpcd="{{ $ref['cmpcd'] }}"
                                    data-cmpnm="{{ $ref['cmpnm'] }}"
                                    data-pprdk="{{ $ref['pprdk'] }}"
                                    {{ old('req_frm_trxno') === $ref['trxno'] ? 'selected' : '' }}>
                                {{ $ref['trxno'] }} · Rp {{ number_format($ref['amount'], 0, ',', '.') }} ({{ $ref['cmpcd'] }} · {{ $ref['date'] }})
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-gray-400">Ketik untuk mencari nomor PMT. Amount & perusahaan terisi otomatis saat referensi dipilih.</p>
                    <div id="refInfo" class="mt-2 hidden rounded-xl border border-blue-100 bg-blue-50/60 px-4 py-2 text-xs text-blue-800"></div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="amount" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Amount (Rupiah) <span class="text-red-500">*</span></label>
                        <input type="text" id="amount" name="amount" inputmode="numeric" autocomplete="off" data-rupiah value="{{ old('amount') }}"
                               placeholder="nominal transaksi"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Jenis <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-600 transition has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50 has-[:checked]:text-blue-700">
                                <input type="radio" name="dbocr" value="D" {{ old('dbocr', 'D') === 'D' ? 'checked' : '' }} class="h-4 w-4 accent-brand-primary">
                                Debit (Masuk)
                            </label>
                            <label class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-600 transition has-[:checked]:border-green-600 has-[:checked]:bg-green-50 has-[:checked]:text-green-700">
                                <input type="radio" name="dbocr" value="C" {{ old('dbocr') === 'C' ? 'checked' : '' }} class="h-4 w-4 accent-brand-primary">
                                Kredit (Keluar)
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Form Khusus Biaya Admin Bank -->
            <div id="sectionAdminFee" class="hidden space-y-4">
                <div class="rounded-xl border border-rose-200 bg-rose-50/50 p-4">
                    <div class="flex items-center gap-2 text-xs font-bold text-rose-800">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>Pencatatan Biaya Administrasi Bank</span>
                    </div>
                    <p class="mt-1 text-xs text-rose-700">
                        Otomatis dicatat sebagai <b>Kredit (Keluar)</b> pada buku kas bank &mdash; mengurangi saldo kas koperasi.
                    </p>
                </div>

                <div>
                    <label for="admin_fee_amount" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nominal Biaya Admin Bank (Rp) <span class="text-red-500">*</span></label>
                    <input type="text" id="admin_fee_amount" name="admin_fee_amount" inputmode="numeric" autocomplete="off" data-rupiah value="{{ old('admin_fee_amount') }}"
                           placeholder="contoh: 25.000"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-rose-500 focus:bg-white focus:ring-2 focus:ring-rose-500/20">
                </div>
            </div>

            <!-- Section 3: Form Khusus Pendapatan Bunga Bank -->
            <div id="sectionInterest" class="hidden space-y-4">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4">
                    <div class="flex items-center gap-2 text-xs font-bold text-emerald-800">
                        <i class="fas fa-coins"></i>
                        <span>Pencatatan Pendapatan Bunga Bank</span>
                    </div>
                    <p class="mt-1 text-xs text-emerald-700">
                        Bruto dan pajak dicatat agar cocok dengan rekening koran. Buku kas bank bertambah sebesar <b>Bunga Neto (Debit/Masuk)</b>.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="gross_amount" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Bunga Bruto (Rp) <span class="text-red-500">*</span></label>
                        <input type="text" id="gross_amount" name="gross_amount" inputmode="numeric" autocomplete="off" data-rupiah value="{{ old('gross_amount') }}"
                               placeholder="contoh: 150.000"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/20">
                    </div>

                    <div>
                        <label for="tax_amount" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Pajak Bunga (Rp)</label>
                        <input type="text" id="tax_amount" name="tax_amount" inputmode="numeric" autocomplete="off" data-rupiah value="{{ old('tax_amount', 0) }}"
                               placeholder="contoh: 30.000 (0 bila tanpa pajak)"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/20">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Bunga Neto (Rp) <span class="text-emerald-600 font-semibold">(Menambah Kas)</span></label>
                        <div id="netAmountDisplay" class="flex h-[42px] items-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 font-mono text-sm font-bold text-emerald-800">
                            Rp 0
                        </div>
                    </div>
                </div>
            </div>

            <!-- Field Bersama -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 pt-2 border-t border-gray-100">
                <div>
                    <label for="trnno" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nomor Transaksi <span class="text-red-500">*</span></label>
                    <input type="text" id="trnno" name="trnno" maxlength="12" value="{{ old('trnno', $defaultTrnno) }}"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 font-mono text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" required>
                    <p class="mt-1 text-[11px] text-gray-400">Pola legacy ({{ $defaultTrnno }}), urut dan unik.</p>
                </div>

                <div>
                    <label for="trndt" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Tanggal Transaksi <span class="text-red-500">*</span></label>
                    <x-date-input name="trndt" id="trndt" value="{{ old('trndt', $defaultTrndt) }}" required
                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" />
                    <p class="mt-1 text-[11px] text-gray-400">Tanggal mutasi rekening koran menentukan periode laporan bank.</p>
                </div>

                <div class="sm:col-span-2">
                    <label for="descr" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Deskripsi <span class="text-red-500">*</span></label>
                    <input type="text" id="descr" name="descr" maxlength="100" value="{{ old('descr', $defaultDescr) }}"
                           placeholder="Keterangan transaksi..."
                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" required>
                </div>

                <div class="sm:col-span-2">
                    <label for="notes" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Notes / Keterangan Tambahan <span class="text-gray-400">(opsional)</span></label>
                    <input type="text" id="notes" name="notes" maxlength="50" value="{{ old('notes') }}"
                           placeholder="Catatan tambahan bila diperlukan..."
                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                </div>
            </div>

            <div class="flex flex-wrap gap-2 pt-2">
                <button type="submit"
                        data-confirm="Simpan transaksi bank ke sistem?"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">
                    <i class="fas fa-save"></i> Simpan Transaksi
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
        const $ref = $('#req_frm_trxno');
        const $amount = $('#amount');
        const $notes = $('#notes');
        const $descr = $('#descr');
        const $refInfo = $('#refInfo');

        const $secGeneral = $('#sectionGeneral');
        const $secAdminFee = $('#sectionAdminFee');
        const $secInterest = $('#sectionInterest');
        const $bankAccountCard = $('#bankAccountCard');

        const $gross = $('#gross_amount');
        const $tax = $('#tax_amount');
        const $netDisplay = $('#netAmountDisplay');

        const bankName = "{{ $bankAccount['bank'] ?? 'Bank Mandiri' }}";
        const defaultDescr = @json($defaultDescr);

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

        function updateCategoryView(cat) {
            if (cat === 'admin_fee') {
                $secGeneral.addClass('hidden');
                $secInterest.addClass('hidden');
                $secAdminFee.removeClass('hidden');
                $bankAccountCard.removeClass('hidden');
                if (!$descr.val() || $descr.val() === defaultDescr || $descr.val().startsWith('Pendapatan Bunga')) {
                    $descr.val('Biaya Administrasi Bank - ' + bankName);
                }
            } else if (cat === 'interest') {
                $secGeneral.addClass('hidden');
                $secAdminFee.addClass('hidden');
                $secInterest.removeClass('hidden');
                $bankAccountCard.removeClass('hidden');
                updateNetInterest();
                if (!$descr.val() || $descr.val() === defaultDescr || $descr.val().startsWith('Biaya Administrasi')) {
                    $descr.val('Pendapatan Bunga Bank - ' + bankName);
                }
            } else {
                $secAdminFee.addClass('hidden');
                $secInterest.addClass('hidden');
                $secGeneral.removeClass('hidden');
                $bankAccountCard.addClass('hidden');
                if (!$descr.val() || $descr.val().startsWith('Biaya Administrasi') || $descr.val().startsWith('Pendapatan Bunga')) {
                    $descr.val(defaultDescr);
                }
            }
        }

        $('.category-radio').on('change', function () {
            updateCategoryView($(this).val());
        });

        const initialCat = $('.category-radio:checked').val() || '';
        updateCategoryView(initialCat);

        if ($ref.length && $ref.is('select')) {
            $ref.select2({
                placeholder: '-- Cari & pilih nomor referensi --',
                allowClear: true,
                width: '100%',
            });

            function applyReference() {
                const option = $ref.find('option:selected');
                const amount = option.data('amount');

                if (amount !== undefined && amount !== '') {
                    $amount.val(amount).trigger('input');
                    if (option.data('cmpnm')) {
                        $notes.val(option.data('cmpnm'));
                    }
                    $refInfo.removeClass('hidden').html(
                        'Amount referensi: <b>Rp ' + Number(amount).toLocaleString('id-ID') + '</b>' +
                        (option.data('date') ? ' &middot; Tanggal: ' + option.data('date') : '') +
                        (option.data('cmpnm') ? ' &middot; Perusahaan: <b>' + option.data('cmpnm') + '</b> (' + option.data('cmpcd') + ')' : '') +
                        (option.data('pprdk') ? ' &middot; Periode: ' + option.data('pprdk') : '')
                    );
                } else {
                    $refInfo.addClass('hidden').html('');
                }
            }

            $ref.on('change', applyReference);
            if ($ref.val()) {
                applyReference();
            }
        }
    });
</script>
@endpush
