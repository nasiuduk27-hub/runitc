@extends('layouts.app')

@section('title', $tad ? 'RUN-ITC | Edit TAD' : 'RUN-ITC | Tambah TAD')

@section('content')
@php
    $isEdit = ! empty($tad);
    $action = $isEdit ? route('cbt-ops.test-plan.update', (int) $tad['rec_id']) : route('cbt-ops.test-plan.store');
    $firstBank = $banks[0] ?? [];
@endphp

<div class="mx-auto max-w-5xl space-y-5">
    <div class="flex items-center justify-between rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
        <div>
            <h1 class="text-2xl font-black text-gray-900">{{ $isEdit ? 'Edit TAD / SPV' : 'Tambah TAD / SPV' }}</h1>
            <p class="mt-1 text-sm text-gray-500">Data profil ditarik dari akun ITC yang dipilih.</p>
        </div>
        <a href="{{ route('cbt-ops.test-plan.index') }}" class="rounded-xl bg-gray-100 px-4 py-2 text-xs font-black uppercase tracking-wide text-gray-600 hover:bg-gray-200">
            Kembali
        </a>
    </div>

    @if (session('error_msg'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ session('error_msg') }}</div>
    @endif

    <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="grid gap-5 lg:grid-cols-3">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="space-y-5 lg:col-span-2">
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <h2 class="mb-4 border-b border-gray-100 pb-3 text-lg font-black text-gray-800">Informasi Utama</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="md:col-span-2">
                        <span class="text-xs font-black uppercase tracking-wide text-gray-500">Akun ITC / Rec ID User</span>
                        <select name="itc_user_id" id="itc_user_id" required class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                            <option value="">-- Pilih akun TAD --</option>
                            @foreach ($itcUsers as $user)
                                <option value="{{ $user['rec_id'] }}"
                                    data-name="{{ $user['account_nm'] }}"
                                    data-alias="{{ $user['alias_nm'] }}"
                                    data-gender="{{ in_array(strtoupper((string) $user['sexmf']), ['F', 'P'], true) ? 'P' : 'L' }}"
                                    data-email="{{ $user['email_id'] }}"
                                    data-phone="{{ $user['whatsapp'] }}"
                                    data-address="{{ $user['address'] }}"
                                    data-city="{{ $user['kotakabupaten'] }}"
                                    data-province="{{ $user['prov_cd'] }}"
                                    data-bank-code="{{ $user['bank_code'] }}"
                                    data-bank-no="{{ $user['bank_acc_no'] }}"
                                    data-bank-name="{{ $user['bank_acc_name'] }}"
                                    @selected((int) old('itc_user_id', $tad['itc_usr_id'] ?? 0) === (int) $user['rec_id'])>
                                    {{ $user['account_nm'] }} ({{ $user['account_id'] }})
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span class="text-xs font-black uppercase tracking-wide text-gray-500">Nama</span>
                        <input type="text" id="name" value="{{ old('name', $tad['spv_name'] ?? '') }}" readonly class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-600">
                    </label>
                    <label>
                        <span class="text-xs font-black uppercase tracking-wide text-gray-500">Alias</span>
                        <input type="text" id="alias" value="{{ old('alias', $tad['spv_alias'] ?? '') }}" readonly class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-600">
                    </label>
                    <label>
                        <span class="text-xs font-black uppercase tracking-wide text-gray-500">Email</span>
                        <input type="email" id="email" value="{{ old('email', $tad['email'] ?? '') }}" readonly class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">
                    </label>
                    <label>
                        <span class="text-xs font-black uppercase tracking-wide text-gray-500">Whatsapp</span>
                        <input type="text" id="phone" value="{{ old('phone', $tad['phone'] ?? '') }}" readonly class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">
                    </label>
                    <label class="md:col-span-2">
                        <span class="text-xs font-black uppercase tracking-wide text-gray-500">Alamat</span>
                        <textarea id="address" rows="3" readonly class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">{{ old('address', $tad['address'] ?? '') }}</textarea>
                    </label>
                    <label>
                        <span class="text-xs font-black uppercase tracking-wide text-gray-500">Provinsi</span>
                        <select name="prov_cd" id="prov_cd" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                            <option value="">-- Pilih Provinsi --</option>
                            @foreach ($provinces as $province)
                                <option value="{{ $province->rec_id }}" @selected((string) old('prov_cd', $tad['prov_cd'] ?? '') === (string) $province->rec_id)>{{ $province->nama }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span class="text-xs font-black uppercase tracking-wide text-gray-500">Kota</span>
                        <select name="city_id" id="city_id" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                            <option value="">-- Pilih Kota --</option>
                            @foreach ($cities as $city)
                                <option value="{{ $city->rec_id }}" @selected((string) old('city_id', $tad['city_id'] ?? '') === (string) $city->rec_id)>{{ $city->nama }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <h2 class="mb-4 border-b border-gray-100 pb-3 text-lg font-black text-gray-800">Rekening Default</h2>
                <div class="grid gap-4 md:grid-cols-3">
                    <input type="hidden" name="is_default_bank" value="0">
                    <label>
                        <span class="text-xs font-black uppercase tracking-wide text-gray-500">Bank</span>
                        <select name="bank_code[]" id="bank_code" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                            <option value="">-- Pilih Bank --</option>
                            @foreach ($bankList as $bank)
                                <option value="{{ $bank->code }}" @selected((string) old('bank_code.0', $firstBank['bank_code'] ?? '') === (string) $bank->code)>{{ $bank->descr }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span class="text-xs font-black uppercase tracking-wide text-gray-500">No. Rekening</span>
                        <input type="text" name="bank_acc_no[]" id="bank_acc_no" value="{{ old('bank_acc_no.0', $firstBank['bank_acc_no'] ?? '') }}" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    </label>
                    <label>
                        <span class="text-xs font-black uppercase tracking-wide text-gray-500">Nama Rekening</span>
                        <input type="text" name="bank_acc_name[]" id="bank_acc_name" value="{{ old('bank_acc_name.0', $firstBank['bank_acc_name'] ?? '') }}" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm uppercase">
                    </label>
                </div>
            </div>
        </div>

        <div class="space-y-5">
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <h2 class="mb-4 border-b border-gray-100 pb-3 text-lg font-black text-gray-800">Status Operasional</h2>
                <div class="space-y-4">
                    <label>
                        <span class="text-xs font-black uppercase tracking-wide text-gray-500">Jabatan</span>
                        <select name="type" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                            <option value="SPV" @selected(old('type', (int) ($tad['captain'] ?? 0) === 1 ? 'CAP' : 'SPV') === 'SPV')>Supervisor</option>
                            <option value="CAP" @selected(old('type', (int) ($tad['captain'] ?? 0) === 1 ? 'CAP' : 'SPV') === 'CAP')>Captain</option>
                        </select>
                    </label>
                    <label>
                        <span class="text-xs font-black uppercase tracking-wide text-gray-500">Status</span>
                        <select name="status" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                            <option value="1" @selected((string) old('status', $tad['status'] ?? '1') === '1')>Active</option>
                            <option value="0" @selected((string) old('status', $tad['status'] ?? '1') === '0')>Suspend</option>
                        </select>
                    </label>
                    <label>
                        <span class="text-xs font-black uppercase tracking-wide text-gray-500">Level SPV</span>
                        <input type="number" name="lvl_spv" value="{{ old('lvl_spv', $tad['lvl_spv'] ?? 1) }}" min="1" max="9" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    </label>
                    <label>
                        <span class="text-xs font-black uppercase tracking-wide text-gray-500">Kategori</span>
                        <select name="spv_category" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                            <option value="">-- Pilih Kategori --</option>
                            @foreach (['REMOTE', 'ONSITE', 'TCA_SSW'] as $cat)
                                <option value="{{ $cat }}" @selected((string) old('spv_category', $tad['spv_category'] ?? '') === $cat)>{{ $cat }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span class="text-xs font-black uppercase tracking-wide text-gray-500">Kemampuan</span>
                        <textarea name="skills_notes" rows="4" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">{{ old('skills_notes', $tad['skills_notes'] ?? '') }}</textarea>
                    </label>
                    <label>
                        <span class="text-xs font-black uppercase tracking-wide text-gray-500">Foto TAD</span>
                        <input type="file" name="photo" accept="image/*" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    </label>
                </div>
            </div>

            <button type="submit" class="w-full rounded-2xl bg-blue-600 px-5 py-3 text-sm font-black uppercase tracking-wide text-white shadow-sm hover:bg-blue-700">
                {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Data TAD' }}
            </button>
        </div>
    </form>
</div>

<script>
const itcUserSelect = document.getElementById('itc_user_id');
function fillFromItcUser() {
    const option = itcUserSelect?.selectedOptions?.[0];
    if (!option) return;
    const map = {
        name: 'name', alias: 'alias', email: 'email', phone: 'phone', address: 'address', city: 'city_id', province: 'prov_cd',
        bankCode: 'bank_code', bankNo: 'bank_acc_no', bankName: 'bank_acc_name'
    };
    for (const [dataKey, elementId] of Object.entries(map)) {
        const element = document.getElementById(elementId);
        if (element && option.dataset[dataKey]) element.value = option.dataset[dataKey];
    }
}
itcUserSelect?.addEventListener('change', fillFromItcUser);
</script>
@endsection
