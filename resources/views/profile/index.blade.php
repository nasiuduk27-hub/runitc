@extends('layouts.app')

@section('title', 'RUN-ITC | Profile')
@section('page_title', 'Profile')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm md:flex-row md:items-center md:justify-between">
        <div class="flex items-center gap-4">
            <img src="{{ $photoUrl }}" alt="Profile" class="h-20 w-20 rounded-2xl object-cover ring-4 ring-blue-50">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-900">{{ $user->account_nm ?? 'User' }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ $user->account_id ?? '-' }}</p>
                <p class="mt-1 text-xs font-semibold text-blue-600">{{ $user->email_id ?? '' }}</p>
            </div>
        </div>
    </div>

    @if (session('success_msg'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('success_msg') }}</div>
    @endif
    @if (session('error_msg') || $errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ session('error_msg') ?: $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" class="space-y-5 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm lg:col-span-2">
            @csrf
            <h2 class="text-lg font-extrabold text-gray-900">Data Diri</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <label class="block text-sm font-bold text-gray-700">Nama Lengkap
                    <input name="account_nm" value="{{ old('account_nm', $user->account_nm ?? '') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500" required>
                </label>
                <label class="block text-sm font-bold text-gray-700">Tanggal Lahir
                    <input type="date" name="dob" value="{{ old('dob', isset($user->dob) ? substr((string) $user->dob, 0, 10) : '') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                </label>
                <label class="block text-sm font-bold text-gray-700">Gender
                    <select name="sexmf" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Pilih</option>
                        <option value="M" @selected(old('sexmf', $user->sexmf ?? '') === 'M')>Male</option>
                        <option value="F" @selected(old('sexmf', $user->sexmf ?? '') === 'F')>Female</option>
                    </select>
                </label>
                <label class="block text-sm font-bold text-gray-700">WhatsApp
                    <input name="whatsapp" value="{{ old('whatsapp', $user->whatsapp ?? '') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                </label>
                <label class="block text-sm font-bold text-gray-700 md:col-span-2">Alamat
                    <textarea name="address" rows="3" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">{{ old('address', $user->address ?? '') }}</textarea>
                </label>
                <label class="block text-sm font-bold text-gray-700">Provinsi
                    <select name="prov_cd" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Pilih provinsi</option>
                        @foreach ($provinces as $province)
                            <option value="{{ $province->rec_id }}" @selected((string) old('prov_cd', $user->prov_cd ?? '') === (string) $province->rec_id)>{{ $province->nama }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-bold text-gray-700">Kota/Kabupaten
                    <select name="kotakabupaten" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Pilih kota</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city->rec_id }}" @selected((string) old('kotakabupaten', $user->kotakabupaten ?? '') === (string) $city->rec_id)>{{ $city->nama }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="border-t border-gray-100 pt-5">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-extrabold text-gray-900">Rekening Bank</h2>
                        <p class="mt-1 text-xs font-medium text-gray-500">Tambahkan lebih dari satu rekening dan pilih satu sebagai rekening utama.</p>
                    </div>
                    <button type="button" onclick="addBankRow()" class="inline-flex items-center justify-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-4 py-2 text-xs font-extrabold text-blue-700 hover:bg-blue-100">
                        <i class="fas fa-plus text-[10px]"></i> Tambah Rekening
                    </button>
                </div>

                @php
                    $oldBanks = old('banks');
                    $bankRows = collect(is_array($oldBanks) ? $oldBanks : $banks->map(fn ($bank) => [
                        'rec_id' => $bank->rec_id,
                        'bank_code' => $bank->bnkcd,
                        'account_name' => $bank->accnm,
                        'account_no' => $bank->accno,
                        'is_default' => (int) $bank->asdefault === 1 ? '1' : '0',
                    ])->all());
                    if ($bankRows->isEmpty()) {
                        $bankRows = collect([['rec_id' => '', 'bank_code' => '', 'account_name' => '', 'account_no' => '', 'is_default' => '1']]);
                    }
                @endphp

                <div id="bankRows" class="space-y-3">
                    @foreach ($bankRows as $index => $bank)
                        <div class="bank-row rounded-xl border border-gray-200 bg-gray-50 p-4" data-bank-row>
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <p class="text-xs font-extrabold uppercase tracking-wide text-gray-500">Rekening {{ $loop->iteration }}</p>
                                <span class="bank-default-badge rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-blue-700 {{ (string) ($bank['is_default'] ?? '') === '1' ? '' : 'hidden' }}">Utama</span>
                            </div>
                            <input type="hidden" name="banks[{{ $index }}][rec_id]" value="{{ $bank['rec_id'] ?? '' }}">
                            <input type="hidden" name="banks[{{ $index }}][delete]" value="0" data-bank-delete>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_1fr_1fr_auto] md:items-center">
                                <select name="banks[{{ $index }}][bank_code]" class="bank-select rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Pilih bank</option>
                                    @foreach ($bankOptions as $code => $label)
                                        <option value="{{ $code }}" @selected((string) ($bank['bank_code'] ?? '') === (string) $code)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <input name="banks[{{ $index }}][account_name]" placeholder="Nama rekening" value="{{ $bank['account_name'] ?? '' }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                                <input name="banks[{{ $index }}][account_no]" placeholder="Nomor rekening" value="{{ $bank['account_no'] ?? '' }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                                <button type="button" onclick="removeBankRow(this)" class="rounded-lg border border-red-200 bg-white px-3 py-2 text-xs font-extrabold text-red-600 hover:bg-red-50">Hapus</button>
                            </div>
                            <label class="mt-3 inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-bold text-gray-600 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">
                                <input type="radio" name="bank_default_index" value="{{ $index }}" class="bank-default-radio" @checked((string) ($bank['is_default'] ?? '') === '1') onclick="syncBankDefault()" onchange="syncBankDefault()">
                                Jadikan rekening utama
                            </label>
                            <input type="hidden" name="banks[{{ $index }}][is_default]" value="{{ (string) ($bank['is_default'] ?? '') === '1' ? '1' : '0' }}" data-bank-default>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="border-t border-gray-100 pt-5">
                <h2 class="mb-4 text-lg font-extrabold text-gray-900">Foto Profil</h2>
                <input type="file" name="photo" accept="image/*" class="block w-full text-sm text-gray-600">
                <label class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-red-600"><input type="checkbox" name="delete_photo" value="1"> Hapus foto saat ini</label>
            </div>

            <button class="rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-extrabold text-white hover:bg-brand-primaryHover">Simpan Profile</button>
        </form>

        <div class="space-y-6">
            <form action="{{ route('profile.password') }}" method="POST" class="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                @csrf
                <h2 class="text-lg font-extrabold text-gray-900">Ganti Password</h2>
                <input type="password" name="new_password" placeholder="Password baru" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <input type="password" name="confirm_password" placeholder="Konfirmasi password" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <button class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-extrabold text-white hover:bg-gray-700">Update Password</button>
            </form>

            <form action="{{ route('profile.email.request') }}" method="POST" class="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                @csrf
                <h2 class="text-lg font-extrabold text-gray-900">Ganti Email</h2>
                <input type="email" name="new_email" placeholder="Email baru" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <button class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-extrabold text-white hover:bg-amber-600">Kirim OTP</button>
            </form>
        </div>
    </div>
</div>

<template id="bankRowTemplate">
    <div class="bank-row rounded-xl border border-gray-200 bg-gray-50 p-4" data-bank-row>
        <div class="mb-3 flex items-center justify-between gap-3">
            <p class="text-xs font-extrabold uppercase tracking-wide text-gray-500">Rekening baru</p>
            <span class="bank-default-badge hidden rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-blue-700">Utama</span>
        </div>
        <input type="hidden" data-name="banks[__INDEX__][rec_id]" value="">
        <input type="hidden" data-name="banks[__INDEX__][delete]" value="0" data-bank-delete>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_1fr_1fr_auto] md:items-center">
            <select data-name="banks[__INDEX__][bank_code]" class="bank-select rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Pilih bank</option>
                @foreach ($bankOptions as $code => $label)
                    <option value="{{ $code }}">{{ $label }}</option>
                @endforeach
            </select>
            <input data-name="banks[__INDEX__][account_name]" placeholder="Nama rekening" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
            <input data-name="banks[__INDEX__][account_no]" placeholder="Nomor rekening" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
            <button type="button" onclick="removeBankRow(this)" class="rounded-lg border border-red-200 bg-white px-3 py-2 text-xs font-extrabold text-red-600 hover:bg-red-50">Hapus</button>
        </div>
        <label class="mt-3 inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-bold text-gray-600 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">
            <input type="radio" name="bank_default_index" data-value="__INDEX__" class="bank-default-radio" onclick="syncBankDefault()" onchange="syncBankDefault()">
            Jadikan rekening utama
        </label>
        <input type="hidden" data-name="banks[__INDEX__][is_default]" value="0" data-bank-default>
    </div>
</template>

<script>
    let bankIndex = {{ $bankRows->count() }};

    function initBankSelect(row = document) {
        if (!window.jQuery || !jQuery.fn.select2) return;

        jQuery(row).find('.bank-select').each(function () {
            if (jQuery(this).hasClass('select2-hidden-accessible')) return;

            jQuery(this).select2({
                width: '100%',
                placeholder: 'Pilih bank',
                allowClear: true,
                minimumResultsForSearch: 0,
            });
        });
    }

    function applyBankRowNames(row) {
        row.querySelectorAll('[data-name]').forEach((field) => {
            field.name = field.dataset.name.replaceAll('__INDEX__', bankIndex);
            field.removeAttribute('data-name');
        });

        row.querySelectorAll('[data-value]').forEach((field) => {
            field.value = field.dataset.value.replaceAll('__INDEX__', bankIndex);
            field.removeAttribute('data-value');
        });
    }

    function addBankRow() {
        const template = document.getElementById('bankRowTemplate');
        const row = template.content.firstElementChild.cloneNode(true);
        applyBankRowNames(row);
        document.getElementById('bankRows').appendChild(row);
        initBankSelect(row);
        bankIndex++;
        syncBankDefault();
    }

    function removeBankRow(button) {
        const row = button.closest('[data-bank-row]');
        const deleteField = row.querySelector('[data-bank-delete]');
        const recId = row.querySelector('input[name$="[rec_id]"]')?.value || '';

        if (recId !== '') {
            deleteField.value = '1';
            row.classList.add('hidden');
        } else {
            row.remove();
        }

        ensureBankDefault();
    }

    function ensureBankDefault() {
        const visibleRows = [...document.querySelectorAll('[data-bank-row]:not(.hidden)')];
        if (visibleRows.length === 0) {
            addBankRow();
            return;
        }

        if (!visibleRows.some((row) => row.querySelector('.bank-default-radio')?.checked)) {
            visibleRows[0].querySelector('.bank-default-radio').checked = true;
        }

        syncBankDefault();
    }

    function syncBankDefault() {
        document.querySelectorAll('[data-bank-row]').forEach((row) => {
            const isDeleted = row.querySelector('[data-bank-delete]')?.value === '1';
            const radio = row.querySelector('.bank-default-radio');
            const defaultField = row.querySelector('[data-bank-default]');
            const isDefault = !isDeleted && radio?.checked;
            defaultField.value = isDefault ? '1' : '0';
            row.querySelector('.bank-default-badge')?.classList.toggle('hidden', !isDefault);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initBankSelect();
        ensureBankDefault();
    });
</script>

<style>
    .bank-row .select2-container .select2-selection--single { height: 38px !important; border-color: #D1D5DB !important; border-radius: 0.5rem !important; }
    .bank-row .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; padding-left: 12px !important; color: #111827 !important; font-size: 0.875rem !important; }
    .bank-row .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
    .bank-row .select2-dropdown { border-color: #D1D5DB !important; border-radius: 0.75rem !important; overflow: hidden; }
    .bank-row .select2-search--dropdown { padding: 8px !important; }
    .bank-row .select2-search--dropdown .select2-search__field { border-color: #D1D5DB !important; border-radius: 0.5rem !important; padding: 6px 10px !important; font-size: 0.875rem !important; outline: none !important; }
</style>
@endsection
