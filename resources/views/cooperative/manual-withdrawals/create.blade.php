@extends('layouts.app')

@section('title', 'RUN-ITC | Input Withdraw Manual')

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
<div class="mx-auto max-w-7xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Input Withdraw Manual</h1>
        <p class="mt-1 text-sm text-gray-500">Pencatatan penarikan simpanan historical. Langsung tercatat tanpa proses approval dan langsung mengurangi saldo.</p>
    </div>
    @if (session('success')) <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('success') }}</div> @endif
    @if ($errors->any()) <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $errors->first() }}</div> @endif
    <form method="POST" action="{{ route('cooperative.manual-withdraw.store') }}" id="manualWithdrawForm" class="space-y-5">
        @csrf
        <div class="space-y-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label for="member_rec_id" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Anggota Master <span class="text-gray-400">(opsional)</span></label>
                    <select id="member_rec_id" name="member_rec_id" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                        <option value="">Nama manual</option>
                        @foreach ($members as $member)
                            <option value="{{ $member->rec_id }}" data-name="{{ $member->icunm }}" @selected(old('member_rec_id') == $member->rec_id)>{{ $member->icuno }} - {{ $member->icunm }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-gray-400">Pilihan master mengisi nama otomatis, nama tetap dapat diedit.</p>
                </div>
                <div>
                    <label for="member_name" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nama Anggota <span class="text-red-500">*</span></label>
                    <input id="member_name" name="member_name" value="{{ old('member_name') }}" placeholder="contoh: BUDI SANTOSO" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                </div>
                <div>
                    <label for="amount" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nominal Penarikan <span class="text-red-500">*</span></label>
                    <input id="amount" name="amount" type="text" inputmode="numeric" autocomplete="off" value="{{ old('amount') }}" placeholder="contoh: 100.000" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label for="trndt" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Tanggal Transaksi <span class="text-red-500">*</span></label>
                    <input id="trndt" name="trndt" type="date" value="{{ old('trndt', date('Y-m-d')) }}" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                </div>
                <div>
                    <label for="pprd" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Periode (YYYYMM)</label>
                    <input type="hidden" id="pprd" name="pprd">
                    <div id="pprd_display" class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-bold text-gray-800">Otomatis dari tanggal transaksi</div>
                    <p class="mt-1 text-[11px] text-gray-400">Siklus tutup buku: tanggal 21 masuk periode bulan berikutnya.</p>
                </div>
                <div>
                    <label for="bank_code" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Bank Tujuan <span class="text-gray-400">(opsional)</span></label>
                    <select id="bank_code" name="bank_code" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20"><option value="">-</option>@foreach ($bankOptions as $code => $label)<option value="{{ $code }}" @selected(old('bank_code') == $code)>{{ $label }}</option>@endforeach</select>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="account_name" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nama Rekening</label>
                    <input id="account_name" name="account_name" value="{{ old('account_name') }}" maxlength="150" placeholder="contoh: BUDI SANTOSO" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                </div>
                <div>
                    <label for="account_no" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nomor Rekening</label>
                    <input id="account_no" name="account_no" value="{{ old('account_no') }}" maxlength="80" placeholder="contoh: 1234567890" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 font-mono text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                    <p class="mt-1 text-[11px] text-gray-400">Jika salah satu data rekening diisi, semuanya wajib lengkap.</p>
                </div>
            </div>
            <div>
                <label for="reason" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Alasan <span class="text-gray-400">(opsional)</span></label>
                <textarea id="reason" name="reason" rows="2" placeholder="contoh: penarikan tunai bulan berjalan" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">{{ old('reason') }}</textarea>
            </div>
            <div class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 text-xs text-gray-500">
                Tercatat langsung ke sistem lama tanpa approval dan langsung mengurangi saldo.
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" onclick="return confirm('Simpan withdraw manual ini?')" class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover"><i class="fas fa-save"></i> Simpan Withdraw Manual</button>
                <button type="reset" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Batal</button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('manualWithdrawForm');
    if (!form) return;
    const date = document.getElementById('trndt');
    const pprd = document.getElementById('pprd');
    const pprdDisplay = document.getElementById('pprd_display');
    const amount = document.getElementById('amount');
    const memberSelect = document.getElementById('member_rec_id');
    const memberName = document.getElementById('member_name');
    const period = value => { const d = new Date(value + 'T00:00:00'); if (d.getDate() > 20) d.setMonth(d.getMonth() + 1); return d.getFullYear() + String(d.getMonth() + 1).padStart(2, '0'); };
    const updatePeriod = () => { if (!date.value) return; pprd.value = period(date.value); pprdDisplay.textContent = pprd.value; };
    amount.addEventListener('input', () => { amount.value = amount.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); });
    date.addEventListener('change', updatePeriod);
    memberSelect.addEventListener('change', () => { memberName.value = memberSelect.selectedOptions[0]?.dataset.name || ''; });
    if (window.jQuery && jQuery.fn.select2) {
        jQuery(memberSelect).select2({ placeholder: 'Cari nomor atau nama anggota...', allowClear: true, width: '100%' });
        jQuery(memberSelect).on('change', () => { memberName.value = memberSelect.selectedOptions[0]?.dataset.name || ''; });
    }
    updatePeriod();
    form.addEventListener('reset', updatePeriod);
    form.addEventListener('submit', () => { amount.value = amount.value.replace(/\D/g, ''); });
})();
</script>
@endpush