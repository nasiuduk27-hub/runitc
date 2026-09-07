@extends('layouts.app')

@php $trx = $trx ?? null; @endphp

@section('title', $trx ? 'RUN-ITC | Edit Transaksi Bank' : 'RUN-ITC | Tambah Transaksi Bank')

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
        <a href="{{ route('cooperative.bank-transactions.index') }}" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50" title="Kembali">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $trx ? 'Edit Transaksi Bank' : 'Tambah Transaksi Bank' }}</h1>
            <p class="mt-0.5 text-sm text-gray-500">
                @if ($trx)
                    Ubah amount, jenis, tanggal, deskripsi, atau perusahaan. Nomor transaksi & referensi tidak bisa diganti.
                @else
                    Pilih nomor referensi dari icu_mtrx2hrd, amount terisi otomatis namun tetap bisa disesuaikan.
                @endif
            </p>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ $trx ? route('cooperative.bank-transactions.update', $trx->rec_id) : route('cooperative.bank-transactions.store') }}" class="space-y-5">
        @csrf
        @if ($trx)
            @method('PUT')
        @endif
        <div class="space-y-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            @if ($trx)
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
            @else
                <div class="sm:col-span-2">
                    <label for="req_frm_trxno" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nomor Referensi <span class="text-red-500">*</span></label>
                    <select id="req_frm_trxno" name="req_frm_trxno" required
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                        <option value=""></option>
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
                    <p class="mt-1 text-[11px] text-gray-400">Ketik untuk mencari nomor PMT. Amount & periode referensi terisi otomatis saat dipilih.</p>
                    <div id="refInfo" class="mt-2 hidden rounded-xl border border-blue-100 bg-blue-50/60 px-4 py-2 text-xs text-blue-800"></div>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="amount" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Amount (Rupiah) <span class="text-red-500">*</span></label>
                    <input type="number" id="amount" name="amount" min="1" step="1" value="{{ old('amount', $trx?->amount) }}"
                           placeholder="auto isi dari referensi"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" required>
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Jenis <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-600 transition has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50 has-[:checked]:text-blue-700">
                            <input type="radio" name="dbocr" value="D" {{ old('dbocr', $trx?->dbocr) === 'D' ? 'checked' : '' }} class="h-4 w-4 accent-brand-primary" required>
                            Debit
                        </label>
                        <label class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-600 transition has-[:checked]:border-green-600 has-[:checked]:bg-green-50 has-[:checked]:text-green-700">
                            <input type="radio" name="dbocr" value="C" {{ old('dbocr', $trx?->dbocr) === 'C' ? 'checked' : '' }} class="h-4 w-4 accent-brand-primary" required>
                            Kredit
                        </label>
                    </div>
                </div>

                @if (! $trx)
                    <div>
                        <label for="trnno" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nomor Transaksi <span class="text-red-500">*</span></label>
                        <input type="text" id="trnno" name="trnno" maxlength="12" value="{{ old('trnno', $defaultTrnno) }}"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 font-mono text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" required>
                        <p class="mt-1 text-[11px] text-gray-400">Default mengikuti pola legacy ({{ $defaultTrnno }}), boleh disesuaikan. Harus unik.</p>
                    </div>
                @endif

                <div>
                    <label for="trndt" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Tanggal Transaksi <span class="text-red-500">*</span></label>
                    <input type="date" id="trndt" name="trndt" value="{{ old('trndt', $trx?->trndt ?? $defaultTrndt) }}"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" required>
                </div>

                <div class="sm:col-span-2">
                    <label for="descr" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Deskripsi <span class="text-red-500">*</span></label>
                    <input type="text" id="descr" name="descr" maxlength="100" value="{{ old('descr', $trx?->descr ?? $defaultDescr) }}"
                           placeholder="contoh: Transfer dana talangan batch Agustus"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" required>
                    @if (! $trx)
                        <p class="mt-1 text-[11px] text-gray-400">Default mengikuti pola existing, boleh disesuaikan.</p>
                    @endif
                </div>

                <div class="sm:col-span-2">
                    <label for="notes" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Notes (Perusahaan) <span class="text-red-500">*</span></label>
                    <input type="text" id="notes" name="notes" maxlength="50" value="{{ old('notes', $trx?->notes) }}"
                           placeholder="auto isi dari nama perusahaan referensi"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" required>
                    @if (! $trx)
                        <p class="mt-1 text-[11px] text-gray-400">Auto-fill nama perusahaan dari master cmpcd saat referensi dipilih, boleh disesuaikan.</p>
                    @endif
                </div>
            </div>

            <div class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 text-xs text-gray-500">
                Disimpan ke tabel legacy <span class="font-bold text-gray-700">icu_bank_trx</span> | periode <span class="font-bold text-gray-700">{{ $trx?->pprdk ?? $defaultPprdk }}</span> |
                referensi divalidasi terhadap <span class="font-bold text-gray-700">icu_mtrx2hrd</span>.
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="submit"
                        onclick="return confirm('{{ $trx ? 'Simpan perubahan transaksi bank?' : 'Simpan transaksi bank ke sistem lama?' }}')"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">
                    <i class="fas fa-save"></i> {{ $trx ? 'Simpan Perubahan' : 'Simpan Transaksi' }}
                </button>
                <a href="{{ route('cooperative.bank-transactions.index') }}"
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
        if (!$ref.length || !$ref.is('select')) {
            return;
        }

        const $amount = $('#amount');
        const $notes = $('#notes');
        const $refInfo = $('#refInfo');

        $ref.select2({
            placeholder: '-- Cari & pilih nomor referensi --',
            allowClear: true,
            width: '100%',
        });

        function applyReference() {
            const option = $ref.find('option:selected');
            const amount = option.data('amount');

            if (amount !== undefined && amount !== '') {
                $amount.val(amount);
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
    });
</script>
@endpush