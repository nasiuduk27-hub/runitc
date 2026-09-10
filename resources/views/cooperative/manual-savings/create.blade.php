@extends('layouts.app')

@section('title', 'RUN-ITC | Input Simpanan Manual')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Input Simpanan Manual</h1>
        <p class="mt-1 text-sm text-gray-500">Pencatatan simpanan historical tanpa proses bulanan. Boleh lebih dari satu transaksi pada periode yang sama.</p>
    </div>
    @if (session('success')) <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('success') }}</div> @endif
    @if ($errors->any()) <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $errors->first() }}</div> @endif
    <form method="POST" action="{{ route('cooperative.manual-savings.store') }}" id="manualSavingsForm" class="space-y-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        @csrf
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2"><label for="member_name" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nama Anggota</label><input id="member_name" name="member_name" value="{{ old('member_name') }}" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm"></div>
            <div><label for="member_rec_id" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Anggota Master (opsional)</label><select id="member_rec_id" name="member_rec_id" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm"><option value="">Nama manual</option>@foreach ($members as $member)<option value="{{ $member->rec_id }}" data-name="{{ $member->icunm }}" @selected(old('member_rec_id') == $member->rec_id)>{{ $member->icuno }} - {{ $member->icunm }}</option>@endforeach</select><p class="mt-1 text-[11px] text-gray-400">Pilihan master mengisi nama otomatis, tetapi nama tetap dapat diedit.</p></div>
            <div><label for="member_status" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Status Anggota Baru</label><select id="member_status" name="member_status" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm"><option value="inactive" @selected(old('member_status', 'inactive') === 'inactive')>Tidak Aktif</option><option value="active" @selected(old('member_status') === 'active')>Aktif</option></select><p class="mt-1 text-[11px] text-gray-400">Hanya dipakai saat membuat anggota baru (tanpa master).</p></div>
            <div><label for="trndt" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Tanggal Transaksi</label><input id="trndt" name="trndt" type="date" value="{{ old('trndt', date('Y-m-d')) }}" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm"></div>
            <div><label for="pprd" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Periode (YYYYMM)</label><input id="pprd" name="pprd" placeholder="200801" pattern="[0-9]{6}" value="{{ old('pprd') }}" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm"><p class="mt-1 text-[11px] text-gray-400">Otomatis dari tanggal transaksi, tetapi dapat diedit.</p></div>
            <div><label for="amount" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nominal Simpanan</label><input id="amount" name="amount" type="text" inputmode="numeric" autocomplete="off" value="{{ old('amount') }}" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm"></div>
            <div><label for="method" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Metode</label><select id="method" name="method" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm"><option value="tunai" @selected(old('method', 'tunai') === 'tunai')>Tunai</option><option value="transfer" @selected(old('method') === 'transfer')>Transfer</option></select></div>
            <div class="sm:col-span-2"><label for="notes" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Catatan (opsional)</label><textarea id="notes" name="notes" rows="2" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm">{{ old('notes') }}</textarea></div>
        </div>
        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-primaryHover"><i class="fas fa-save"></i> Simpan Simpanan Manual</button>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('manualSavingsForm');
    if (!form) return;
    const date = document.getElementById('trndt');
    const pprd = document.getElementById('pprd');
    const amount = document.getElementById('amount');
    const memberSelect = document.getElementById('member_rec_id');
    const memberName = document.getElementById('member_name');
    const period = value => { const d = new Date(value + 'T00:00:00'); if (d.getDate() > 20) d.setMonth(d.getMonth() + 1); return d.getFullYear() + String(d.getMonth() + 1).padStart(2, '0'); };
    const updatePeriod = () => { if (!date.value) return; pprd.value = period(date.value); };
    amount.addEventListener('input', () => { amount.value = amount.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); });
    date.addEventListener('change', updatePeriod);
    memberSelect.addEventListener('change', () => { memberName.value = memberSelect.selectedOptions[0]?.dataset.name || ''; });
    if (window.jQuery && jQuery.fn.select2) {
        jQuery(memberSelect).select2({ placeholder: 'Cari nomor atau nama anggota...', allowClear: true, width: '100%' });
        jQuery(memberSelect).on('change', () => { memberName.value = memberSelect.selectedOptions[0]?.dataset.name || ''; });
    }
    form.addEventListener('submit', () => { amount.value = amount.value.replace(/\D/g, ''); });
})();
</script>
@endpush