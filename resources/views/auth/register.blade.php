@extends('layouts.guest')

@section('title', 'RUN-ITC | Registrasi')

@section('content')
<div class="flex min-h-screen bg-gray-50">
    <div class="flex w-full items-center justify-center p-4 sm:p-6">
        <div class="w-full max-w-2xl">
            <div class="mb-6">
                <a href="{{ route('login') }}" class="flex w-fit items-center gap-2 text-sm font-medium text-blue-600 transition-colors hover:text-blue-800">
                    <i class="fas fa-arrow-left"></i> Sudah punya akun? Login
                </a>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-xl">
                <div class="px-6 py-5 sm:px-8">
                    <h3 class="text-2xl font-bold text-gray-800">Account Information</h3>
                    <p class="text-sm text-gray-500">Buat ID Akun dan Password Anda</p>
                </div>

                @if (session('error_msg'))
                    <div class="mx-6 mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{!! session('error_msg') !!}</div>
                @endif

                @if ($errors->any())
                    <div class="mx-6 mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('register.submit') }}" class="px-6 pb-8 sm:px-8">
                    @csrf
                    <div class="mb-6 grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div>
                            <label for="account_id" class="mb-1 block text-sm font-medium text-gray-700">Account ID <span class="text-red-500">*</span></label>
                            <input id="account_id" name="account_id" type="text" required minlength="6" value="{{ old('account_id') }}" placeholder="Masukkan ID Akun" class="w-full rounded-md border border-gray-300 px-4 py-2 outline-none transition-colors focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                            <p class="mt-1 text-xs text-gray-500">Gunakan huruf & angka, min. 6 karakter.</p>
                        </div>

                        <div>
                            <label for="email_id" class="mb-1 block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                            <input id="email_id" name="email_id" type="email" required value="{{ old('email_id') }}" placeholder="email@domain.com" class="w-full rounded-md border border-gray-300 px-4 py-2 outline-none transition-colors focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label for="password_id" class="mb-1 block text-sm font-medium text-gray-700">Password <span class="text-red-500">*</span></label>
                            <input id="password_id" name="password_id" type="password" required minlength="6" maxlength="16" placeholder="************" class="w-full rounded-md border border-gray-300 px-4 py-2 outline-none transition-colors focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                            <p class="mt-1 text-xs text-gray-500">Minimal 6 karakter. Maksimal 16 karakter.</p>
                        </div>

                        <div>
                            <label for="retype_password" class="mb-1 block text-sm font-medium text-gray-700">Re-Type Password <span class="text-red-500">*</span></label>
                            <input id="retype_password" name="retype_password" type="password" required minlength="6" maxlength="16" placeholder="************" class="w-full rounded-md border border-gray-300 px-4 py-2 outline-none transition-colors focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="mb-6 border-t border-gray-200 pt-6">
                        <p class="mb-4 text-sm font-bold text-gray-800">Data Diri</p>
                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label for="account_nm" class="mb-1 block text-sm font-medium text-gray-700">Nama Lengkap <span class="text-red-500">*</span></label>
                                <input id="account_nm" name="account_nm" type="text" required value="{{ old('account_nm') }}" placeholder="Nama Lengkap" class="w-full rounded-md border border-gray-300 px-4 py-2 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                            </div>

                            <div>
                                <label for="alias" class="mb-1 block text-sm font-medium text-gray-700">Nama Panggilan <span class="text-red-500">*</span></label>
                                <input id="alias" name="alias" type="text" required value="{{ old('alias') }}" placeholder="Nama Panggilan" class="w-full rounded-md border border-gray-300 px-4 py-2 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                            </div>

                            <div>
                                <label for="dob" class="mb-1 block text-sm font-medium text-gray-700">Tanggal Lahir <span class="text-red-500">*</span></label>
                                <input id="dob" name="dob" type="date" required max="{{ \Carbon\Carbon::now()->subYears(17)->format('Y-m-d') }}" value="{{ old('dob') }}" class="w-full rounded-md border border-gray-300 bg-white px-4 py-2 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                                <p class="mt-1 text-xs text-gray-400">Usia yang diizinkan minimal 17 tahun.</p>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Jenis Kelamin <span class="text-red-500">*</span></label>
                                <div class="grid grid-cols-2 gap-4">
                                    <label class="flex cursor-pointer items-center justify-center rounded-md border border-gray-300 px-4 py-2 transition-all hover:border-blue-400 hover:bg-blue-50">
                                        <input type="radio" name="sexmf" value="M" class="h-4 w-4 cursor-pointer accent-blue-600" @checked(old('sexmf') === 'M')>
                                        <span class="ml-2 text-sm font-medium text-gray-700">Laki-laki</span>
                                    </label>
                                    <label class="flex cursor-pointer items-center justify-center rounded-md border border-gray-300 px-4 py-2 transition-all hover:border-pink-400 hover:bg-pink-50">
                                        <input type="radio" name="sexmf" value="F" class="h-4 w-4 cursor-pointer accent-pink-500" @checked(old('sexmf') === 'F')>
                                        <span class="ml-2 text-sm font-medium text-gray-700">Perempuan</span>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label for="wa_number" class="mb-1 block text-sm font-medium text-gray-700">Nomor WhatsApp <span class="text-red-500">*</span></label>
                                <div class="flex">
                                    <select name="wa_code" class="w-24 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="62" @selected(old('wa_code') === '62')>+62</option>
                                        <option value="1" @selected(old('wa_code') === '1')>+1</option>
                                        <option value="60" @selected(old('wa_code') === '60')>+60</option>
                                        <option value="65" @selected(old('wa_code') === '65')>+65</option>
                                    </select>
                                    <input id="wa_number" name="wa_number" type="text" required value="{{ old('wa_number') }}" placeholder="8123456789" class="w-full rounded-r-md border border-gray-300 px-4 py-2 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500" oninput="this.value=this.value.replace(/^0+/,'').replace(/\D/g,'')">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6 rounded-xl border border-blue-100 bg-blue-50/50 p-4">
                        <input type="hidden" name="is_individu" id="is_individu" value="1">

                        <div id="instansi_input_container" class="hidden">
                            <label for="instansi" class="mb-1 block text-sm font-bold text-gray-800">Asal Instansi / Perusahaan / Sekolah <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="text" name="instansi" id="instansi" autocomplete="off" value="{{ old('instansi', 'Individu') }}" placeholder="Ketik nama instansi..." class="w-full rounded-md border border-gray-300 px-4 py-2 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500" oninput="handleInstansi(this.value)">
                                <div id="instansi_dropdown" class="absolute z-10 mt-1 hidden max-h-48 w-full overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg"></div>
                            </div>
                        </div>

                        <label class="mt-3 flex w-max cursor-pointer items-center">
                            <input type="checkbox" id="check_instansi" value="1" class="h-4 w-4 cursor-pointer rounded border-gray-300 text-blue-600 focus:ring-blue-500" onchange="toggleInstansi()">
                            <span class="ml-2 text-sm text-gray-600">Saya mendaftar dengan Instansi / Perusahaan / Sekolah</span>
                        </label>

                        <div id="emp_no_container" class="mt-4 hidden border-t border-blue-200 pt-4">
                            <label for="emp_no" class="mb-1 block text-sm font-bold text-gray-800">Employee No. <span class="text-red-500">*</span></label>
                            <input type="text" name="emp_no" id="emp_no" value="{{ old('emp_no') }}" placeholder="Masukkan Nomor Karyawan Anda" class="w-full rounded-md border border-gray-300 px-4 py-2 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                            <p class="mt-1 text-xs text-blue-600"><i class="fas fa-info-circle"></i> Wajib diisi untuk pegawai internal PT INTERNATIONAL TEST CENTER.</p>
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-gray-200 pt-6">
                        <button type="submit" class="flex items-center gap-2 rounded-lg bg-blue-600 px-8 py-2.5 font-medium text-white shadow-md transition-all hover:bg-blue-700">
                            Daftar <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleInstansi() {
        const checked = document.getElementById('check_instansi').checked;
        const container = document.getElementById('instansi_input_container');
        const isIndividu = document.getElementById('is_individu');
        const empContainer = document.getElementById('emp_no_container');
        const instansiInput = document.getElementById('instansi');

        if (checked) {
            container.classList.remove('hidden');
            isIndividu.value = '0';
            if (instansiInput.value === 'Individu') instansiInput.value = '';
            document.getElementById('instansi_dropdown').classList.add('hidden');
        } else {
            container.classList.add('hidden');
            empContainer.classList.add('hidden');
            isIndividu.value = '1';
            instansiInput.value = 'Individu';
        }
    }

    function isInternalInstitution(val) {
        const normalized = String(val).toLowerCase().replace(/[^a-z0-9]+/g, ' ').replace(/\s+/g, ' ').trim();
        return normalized === 'pt international test center'
            || normalized === 'international test center'
            || normalized === 'pt itc';
    }

    function updateInternalState(val) {
        document.getElementById('emp_no_container').classList.toggle('hidden', !isInternalInstitution(val));
    }

    let searchTimer = null;

    function handleInstansi(val) {
        clearTimeout(searchTimer);
        const q = val.trim();
        updateInternalState(q);

        if (q.length < 2) {
            document.getElementById('instansi_dropdown').classList.add('hidden');
            return;
        }

        searchTimer = setTimeout(() => {
            fetch('{{ route('register.client-search') }}?q=' + encodeURIComponent(q))
                .then(response => response.json())
                .then(data => {
                    const dropdown = document.getElementById('instansi_dropdown');
                    const isInternal = isInternalInstitution(q);
                    document.getElementById('emp_no_container').classList.toggle('hidden', !isInternal);

                    dropdown.innerHTML = '';
                    if (data.length === 0) {
                        dropdown.classList.add('hidden');
                        return;
                    }
                    data.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'cursor-pointer px-3 py-2 text-sm text-gray-700 hover:bg-blue-50';
                        div.textContent = item.clientnm;
                        div.addEventListener('click', () => {
                            document.getElementById('instansi').value = item.clientnm;
                            dropdown.classList.add('hidden');
                            updateInternalState(item.clientnm);
                        });
                        dropdown.appendChild(div);
                    });
                    dropdown.classList.remove('hidden');
                })
                .catch(() => { document.getElementById('instansi_dropdown').classList.add('hidden'); });
        }, 300);
    }

    document.addEventListener('click', function (e) {
        const dropdown = document.getElementById('instansi_dropdown');
        const input = document.getElementById('instansi');
        if (dropdown && input && !input.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
</script>
@endsection
