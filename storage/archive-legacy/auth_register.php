<?php
// File: modules/auth/register.php

// 1. PAKSA TAMPILKAN ERROR (Agar kalau ada salah, tidak cuma layar putih)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Panggil config.php dari root (session_start sudah di-handle config.php)
require_once '../../config.php';

// Hitung rentang umur
$max_dob = date('d/m/Y', strtotime('-17 years'));
$min_dob = date('d/m/Y', strtotime('-100 years'));
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <title>Registration - ITC Portal</title>

    <?php include BASE_PATH.'/includes/head.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <?php if (isset($_SESSION['error_message']) && ! empty($_SESSION['error_message'])) { ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Pendaftaran Gagal',
                html: <?php echo json_encode($_SESSION['error_message']); ?>,
                confirmButtonColor: '#2563EB'
            });
        });
    </script>
    <?php unset($_SESSION['error_message']);
    } ?>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .fade-enter {
            opacity: 0;
            transform: translateX(20px);
            transition: all 0.4s ease-out;
        }

        .fade-enter-active {
            opacity: 1;
            transform: translateX(0);
        }

        .fade-exit {
            opacity: 1;
            transform: translateX(0);
            transition: all 0.3s ease-in;
        }

        .fade-exit-active {
            opacity: 0;
            transform: translateX(-20px);
        }

        .d-none {
            display: none;
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-800 font-sans h-screen overflow-hidden flex">

    <div class="hidden lg:flex lg:w-1/3 bg-white border-r border-gray-200 relative items-center justify-center p-12">
        <div class="flex flex-col items-center text-center">
            <a href="<?php echo isset($_SESSION['user_id']) ? BASE_URL.'dashboard.php' : BASE_URL.'index.php' ?>" class="cursor-pointer hover:opacity-80 transition-opacity">
                <img src="<?php echo BASE_URL ?>assets/RUNITC_LOGO.png" onerror="this.src='<?php echo BASE_URL ?>assets/images/RUNITC_LOGO.png'" alt="Logo RUN-ITC" class="w-64 h-auto mb-6">
            </a>
            <p class="text-gray-600 text-base font-medium">Integrated System Registration</p>
        </div>
    </div>

    <div class="w-full lg:w-2/3 flex items-center justify-center bg-gray-50 p-8 overflow-y-auto relative">
        <div id="view-form" class="w-full max-w-3xl fade-enter fade-enter-active">

            <div class="flex items-center justify-between mb-8 border-b border-gray-200 pb-6">
                <div class="flex items-center cursor-pointer hover:opacity-80 transition-opacity" id="indicator-step-1" onclick="goToStep(1)">
                    <div class="w-10 h-10 bg-blue-600 text-white rounded-lg flex items-center justify-center font-bold shadow-md transition-colors" id="icon-step-1">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="ml-3">
                        <h4 class="text-gray-800 font-bold text-sm">Account</h4>
                        <p class="text-gray-500 text-xs">Account Details</p>
                    </div>
                </div>
                <div class="flex-grow border-t-2 border-gray-200 mx-4 transition-colors duration-300" id="line-step-1-2"></div>
                <div class="flex items-center opacity-50 cursor-pointer hover:opacity-80 transition-opacity" id="indicator-step-2" onclick="goToStep(2)">
                    <div class="w-10 h-10 bg-gray-200 text-gray-600 rounded-lg flex items-center justify-center font-bold transition-colors" id="icon-step-2">
                        <i class="far fa-user"></i>
                    </div>
                    <div class="ml-3">
                        <h4 class="text-gray-800 font-bold text-sm">Personal</h4>
                        <p class="text-gray-500 text-xs">Enter Information</p>
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <a href="<?php echo BASE_URL ?>index.php" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 text-sm font-medium transition-colors w-fit">
                    <i class="fas fa-arrow-left"></i> Sudah punya akun? Login
                </a>
            </div>

            <div class="mb-6">
                <h3 class="text-2xl font-bold text-gray-800" id="form-title">Account Information</h3>
                <p class="text-gray-500 text-sm" id="form-subtitle">Buat ID Akun dan Password Anda</p>
            </div>

            <form id="registrationForm" method="POST" action="process_register.php">
    <?php // DEBUG ONLY: CSRF token dimatikan sementara.?>
    <?php // echo Csrf::html();?>

                <div id="step-1-content" class="fade-enter fade-enter-active">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">

                        <div>
                            <label class="block text-gray-700 text-sm font-medium mb-1">Account ID <span class="text-red-500">*</span></label>
                            <input type="text" name="account_id" id="reg_account_id" required minlength="6" placeholder="Masukkan ID Akun" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none transition-colors" oninput="validateStep1()">
                            <p id="acc_msg" class="text-xs mt-1 text-gray-500">Gunakan huruf & angka, min. 6 karakter.</p>
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-medium mb-1">Email <span class="text-red-500">*</span></label>
                            <input type="email" name="email_id" id="reg_email_id" required placeholder="email@domain.com" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none transition-colors" oninput="validateStep1()">
                            <p id="email_msg" class="text-xs mt-1 d-none"></p>

                            <div class="mt-2 bg-blue-50 border-l-4 border-blue-500 p-2 text-xs text-blue-700 rounded shadow-sm">
                                <i class="fas fa-info-circle mr-1"></i> Disarankan menggunakan <b>Email yang terdaftar di aplikasi Smartcart by ITC</b>. Jika belum, silakan
                                <button type="button" onclick="openAppModal()" class="font-bold underline text-blue-800 hover:text-blue-600 transition-colors bg-transparent border-none p-0 cursor-pointer">unduh di sini</button>.
                            </div>
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-medium mb-1">Password <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="password" name="password_id" id="reg_password" required minlength="6" maxlength="16" placeholder="************" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none pr-10 transition-colors" oninput="validateStep1()">
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 cursor-pointer text-gray-400 hover:text-gray-600" onclick="toggleRegPassword('reg_password', 'toggleIconPwd')">
                                    <i class="fas fa-eye-slash" id="toggleIconPwd"></i>
                                </span>
                            </div>
                            <p id="pwd_msg" class="text-xs mt-1 text-gray-500">Minimal 6 karakter. Maksimal 16 karakter.</p>
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-medium mb-1">Re-Type Password <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="password" name="retype_password" id="reg_retype" required minlength="6" maxlength="16" placeholder="************" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none pr-10 transition-colors" oninput="validateStep1()">
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 cursor-pointer text-gray-400 hover:text-gray-600" onclick="toggleRegPassword('reg_retype', 'toggleIconRetype')">
                                    <i class="fas fa-eye-slash" id="toggleIconRetype"></i>
                                </span>
                            </div>
                            <p id="pwd_match_msg" class="text-xs mt-1 d-none"></p>
                        </div>
                    </div>

                    <div class="flex justify-end items-center mt-8 border-t border-gray-200 pt-6">
                        <button type="button" onclick="goToStep(2)" id="btn_next_step1" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-2.5 rounded-lg shadow-md transition-all font-medium flex items-center gap-2 focus:ring-4 focus:ring-blue-300 opacity-50 cursor-not-allowed" disabled>
                            Next <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <div id="step-2-content" class="d-none fade-enter">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">

                        <div class="md:col-span-2">
                            <label class="block text-gray-700 text-sm font-medium mb-1">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" name="account_nm" required placeholder="Nama Lengkap" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-medium mb-1">Short Name <span class="text-red-500">*</span></label>
                            <input type="text" name="alias" required placeholder="Nama Panggilan" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-medium mb-1">Date of Birth <span class="text-red-500">*</span></label>

                            <input type="text" id="dob-input" name="dob" required placeholder="dd/mm/yyyy" maxlength="10"
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none bg-white">

                            <p class="text-xs text-gray-400 mt-1">Usia yang diizinkan minimal 17 tahun.</p>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-medium mb-1">Sex <span class="text-red-500">*</span></label>
                            <div class="grid grid-cols-2 gap-4">
                                <label class="flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md cursor-pointer hover:bg-blue-50 hover:border-blue-400 transition-all group">
                                    <input type="radio" name="sexmf" value="M" class="w-4 h-4 accent-blue-600 cursor-pointer" required>
                                    <span class="ml-2 text-sm text-gray-700 group-hover:text-blue-700 font-medium">Male</span>
                                </label>
                                <label class="flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md cursor-pointer hover:bg-pink-50 hover:border-pink-400 transition-all group">
                                    <input type="radio" name="sexmf" value="F" class="w-4 h-4 accent-pink-500 cursor-pointer" required>
                                    <span class="ml-2 text-sm text-gray-700 group-hover:text-pink-700 font-medium">Female</span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-medium mb-1">Whatsapp No <span class="text-red-500">*</span></label>
                            <div class="flex">
                                <select name="wa_code" class="px-3 py-2 border border-gray-300 rounded-l-md focus:ring-2 focus:ring-blue-500 outline-none bg-gray-50 border-r-0 w-24">
                                    <option value="62">+62</option>
                                    <option value="1">+1</option>
                                    <option value="60">+60</option>
                                    <option value="65">+65</option>
                                </select>
                                <input type="text" name="wa_number" required placeholder="8123456789" class="w-full px-4 py-2 border border-gray-300 rounded-r-md focus:ring-2 focus:ring-blue-500 outline-none transition-colors" oninput="this.value = this.value.replace(/^0+/, '').replace(/\D/g, '')">
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-2 bg-blue-50/50 p-4 rounded-xl border border-blue-100" id="instansi_wrapper">
                        <input type="hidden" name="is_individu" id="reg_is_individu" value="1">
                        <div id="instansi_input_container" class="d-none">
                            <label class="block text-gray-800 font-bold text-sm mb-1">Asal Instansi / Perusahaan / Sekolah <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="hidden" name="client_rec_id" id="reg_client_rec_id" value="">
                                <input type="hidden" name="requires_emp_no" id="reg_requires_emp_no" value="0">
                                <input type="text" name="instansi" id="reg_instansi" autocomplete="off" value="Individu" placeholder="Ketik nama instansi... (Contoh: PT. International Test Center)" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none transition-colors" oninput="handleInstansiInput(this.value)">
                                <div id="instansi_dropdown" class="absolute z-10 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto mt-1 d-none"></div>
                            </div>
                        </div>

                        <label class="flex items-center mt-3 cursor-pointer w-max group">
                            <input type="checkbox" name="is_with_instansi" id="check_instansi" value="1" class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500 border-gray-300 cursor-pointer" onclick="toggleInstansi()">
                            <span class="ml-2 text-sm text-gray-600 group-hover:text-blue-700 transition-colors">Saya mendaftar dengan Instansi / Perusahaan / Sekolah</span>
                        </label>

                        <div id="emp_no_container" class="mt-4 d-none border-t border-blue-200 pt-4">
                            <label class="block text-gray-800 font-bold text-sm mb-1">Employee No. <span class="text-red-500">*</span></label>
                            <input type="text" name="emp_no" id="reg_emp_no" placeholder="Masukkan Nomor Karyawan Anda" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none transition-colors" oninput="validateEmployeeNoInput()">
                            <p id="emp_no_msg" class="text-xs text-blue-600 mt-1"><i class="fas fa-info-circle"></i> Wajib diisi untuk pegawai internal PT INTERNATIONAL TEST CENTER.</p>
                        </div>
                    </div>

                    <div class="flex justify-between mt-8 border-t border-gray-200 pt-6">
                        <button type="button" onclick="goToStep(1)" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-6 rounded-md transition-colors flex items-center">
                            <i class="fas fa-arrow-left mr-2"></i> Previous
                        </button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-md shadow-sm transition-colors flex items-center">
                            <i class="fas fa-check-circle mr-2"></i> Sign Up
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="smartcartModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-gray-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl transform scale-95 transition-transform duration-300 relative flex flex-col md:flex-row overflow-hidden" id="smartcartModalContent">
            <button type="button" onclick="closeAppModal()" class="absolute top-4 right-5 text-gray-400 hover:text-red-500 transition-colors focus:outline-none z-10"><i class="fas fa-times text-2xl"></i></button>
            <div class="w-full md:w-3/5 p-8 flex flex-col justify-center">
                <h3 class="text-2xl font-bold text-gray-800 mb-2">Unduh Smartcart by ITC</h3>
                <p class="text-gray-500 text-sm mb-6">Pilih platform perangkat Anda untuk mengunduh aplikasi Smartcart by ITC.</p>
                <div class="space-y-4">
                    <a href="https://play.google.com/store/apps/details?id=com.smartcart.itc&hl=id" target="_blank" class="flex items-center justify-center w-full bg-gray-50 hover:bg-green-50 border border-gray-200 hover:border-green-200 text-gray-800 font-semibold py-3 px-4 rounded-xl transition-all hover:shadow-md group">
                        <i class="fab fa-google-play text-green-500 text-3xl mr-4 group-hover:scale-110 transition-transform"></i>
                        <div class="text-left w-32">
                            <div class="text-[10px] leading-tight text-gray-500 font-normal uppercase tracking-wider">Get it on</div>
                            <div class="text-base leading-tight font-bold">Google Play</div>
                        </div>
                    </a>
                    <a href="https://apps.apple.com/id/app/smartcart-by-itc/id6444688627?l=id" target="_blank" class="flex items-center justify-center w-full bg-gray-50 hover:bg-blue-50 border border-gray-200 hover:border-blue-200 text-gray-800 font-semibold py-3 px-4 rounded-xl transition-all hover:shadow-md group">
                        <i class="fab fa-apple text-gray-800 text-4xl mr-4 mb-1 group-hover:scale-110 transition-transform"></i>
                        <div class="text-left w-32">
                            <div class="text-[10px] leading-tight text-gray-500 font-normal uppercase tracking-wider">Download on the</div>
                            <div class="text-base leading-tight font-bold">App Store</div>
                        </div>
                    </a>
                </div>
                <div class="mt-6"><button type="button" onclick="closeAppModal()" class="text-sm font-medium text-gray-500 hover:text-gray-800 transition-colors">Tutup</button></div>
            </div>
            <div class="w-full md:w-2/5 bg-gray-50 flex items-center justify-center p-8 border-t md:border-t-0 md:border-l border-gray-100">
                <img src="<?php echo BASE_URL ?>assets/smartcart_logo.png" onerror="this.src='<?php echo BASE_URL ?>assets/images/smartcart_logo.png'" alt="Logo Smartcart" class="w-40 md:w-48 h-auto object-contain drop-shadow-md hover:scale-105 transition-transform duration-500">
            </div>
        </div>
    </div>

    <script>
        const dobInput = document.getElementById('dob-input');

        // --- FITUR AUTO-SLASH (MASKING) ---
        dobInput.addEventListener('input', function(e) {
            if (e.inputType === 'deleteContentBackward') return;
            let val = this.value.replace(/\D/g, '');
            if (val.length >= 4) {
                this.value = val.slice(0, 2) + '/' + val.slice(2, 4) + '/' + val.slice(4, 8);
            } else if (val.length >= 2) {
                this.value = val.slice(0, 2) + '/' + val.slice(2);
            } else {
                this.value = val;
            }
        });

        // --- INISIALISASI FLATPICKR ---
        flatpickr("#dob-input", {
            dateFormat: "d/m/Y",
            allowInput: true,
            minDate: "<?php echo $min_dob ?>",
            maxDate: "<?php echo $max_dob ?>",
            disableMobile: true
        });

        // --- INSTANSI YANG WAJIB EMPLOYEE NO ---
        // Normalisasi membuat "PT. International Test Center", "PT INTERNATIONAL TEST CENTER",
        // dan "pt international test center" dianggap sama.
        const employeeNoRequiredInstitutions = ["pt international test center"];

        function normalizeText(value) {
            return String(value || '')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        }

        function isEmployeeNoRequiredInstitution(value) {
            const normalized = normalizeText(value);
            return employeeNoRequiredInstitutions.includes(normalized);
        }

        function setEmployeeNoRequirement(isRequired) {
            const empContainer = document.getElementById('emp_no_container');
            const empInput = document.getElementById('reg_emp_no');
            const requiresInput = document.getElementById('reg_requires_emp_no');
            const empMsg = document.getElementById('emp_no_msg');

            requiresInput.value = isRequired ? '1' : '0';

            if (isRequired && document.getElementById('check_instansi').checked) {
                empContainer.classList.remove('d-none');
                empInput.setAttribute('required', 'required');
                empMsg.innerHTML = '<i class="fas fa-info-circle"><\/i> Wajib diisi. Employee No akan dicek ke database.';
                empMsg.className = 'text-xs text-blue-600 mt-1';
            } else {
                empContainer.classList.add('d-none');
                empInput.removeAttribute('required');
                empInput.value = '';
                empInput.classList.remove('border-red-500', 'border-green-500');
                empMsg.innerHTML = '<i class="fas fa-info-circle"><\/i> Wajib diisi untuk pegawai internal PT INTERNATIONAL TEST CENTER.';
                empMsg.className = 'text-xs text-blue-600 mt-1';
            }
        }

        function checkSisterCompany(val) {
            setEmployeeNoRequirement(isEmployeeNoRequiredInstitution(val));
        }

        function validateEmployeeNoInput() {
            const empInput = document.getElementById('reg_emp_no');
            const empMsg = document.getElementById('emp_no_msg');
            const isRequired = document.getElementById('reg_requires_emp_no').value === '1';

            if (!isRequired) return true;

            if (empInput.value.trim().length === 0) {
                empInput.classList.add('border-red-500');
                empInput.classList.remove('border-green-500');
                empMsg.innerHTML = '<i class="fas fa-times-circle"><\/i> Employee No wajib diisi.';
                empMsg.className = 'text-xs text-red-500 mt-1';
                return false;
            }

            empInput.classList.remove('border-red-500');
            empInput.classList.add('border-green-500');
            empMsg.innerHTML = '<i class="fas fa-check-circle"><\/i> Employee No terisi. Validasi kecocokan dilakukan saat Sign Up.';
            empMsg.className = 'text-xs text-green-600 mt-1';
            return true;
        }

        function toggleInstansi() {
            const check = document.getElementById('check_instansi');
            const inputContainer = document.getElementById('instansi_input_container');
            const input = document.getElementById('reg_instansi');
            const isIndividuInput = document.getElementById('reg_is_individu');
            const empContainer = document.getElementById('emp_no_container');
            const empInput = document.getElementById('reg_emp_no');

            if (check.checked) {
                inputContainer.classList.remove('d-none');
                isIndividuInput.value = '0';
                input.value = '';
                document.getElementById('reg_client_rec_id').value = '';
                document.getElementById('reg_requires_emp_no').value = '0';
                input.setAttribute('required', 'required');
                input.focus();

                empContainer.classList.add('d-none');
                empInput.removeAttribute('required');
                empInput.value = '';
            } else {
                inputContainer.classList.add('d-none');
                isIndividuInput.value = '1';
                input.value = 'Individu';
                document.getElementById('reg_client_rec_id').value = '';
                document.getElementById('reg_requires_emp_no').value = '0';
                input.removeAttribute('required');

                empContainer.classList.add('d-none');
                empInput.removeAttribute('required');
                empInput.value = '';
            }
        }

        const step1Content = document.getElementById('step-1-content');
        const step2Content = document.getElementById('step-2-content');
        const formTitle = document.getElementById('form-title');
        const formSubtitle = document.getElementById('form-subtitle');

        let instansiSearchTimer = null;
        let instansiAbortController = null;

        function handleInstansiInput(val) {
    const dropdown = document.getElementById('instansi_dropdown');
    const empContainer = document.getElementById('emp_no_container');
    const empInput = document.getElementById('reg_emp_no');
    const requiresEmpNo = document.getElementById('reg_requires_emp_no');

    if (val.trim().length < 2) {
        dropdown.classList.add('d-none');
        return;
    }

    // Deteksi manual kecocokan nama instansi internal secara realtime saat mengetik
    const internalCompanies = [
        'pt. international test center',
        'pt international test center',
        'international test center',
        'pt. itc',
        'pt itc'
    ];

    if (internalCompanies.includes(val.toLowerCase().trim())) {
        empContainer.classList.remove('d-none');
        empInput.setAttribute('required', 'required');
        requiresEmpNo.value = "1";
    } else {
        // Jika nama instansi dikosongkan atau diubah ke instansi lain, sembunyikan input Employee No
        empContainer.classList.add('d-none');
        empInput.removeAttribute('required');
        requiresEmpNo.value = "0";
    }

    // Jalankan fetch AJAX bawaan Anda untuk memunculkan auto-complete dropdown dari database
    fetch(`process_register.php?ajax=client_search&q=${encodeURIComponent(val)}`)
        .then(response => response.json())
        .then(data => {
            if (data.length === 0) {
                dropdown.classList.add('d-none');
                return;
            }
            let html = '';
            data.forEach(item => {
                html += `<div class="px-4 py-2 hover:bg-gray-100 cursor-pointer text-sm" onclick="selectInstansi('${escapeHtml(item.clientnm)}', '${item.rec_id}')">${escapeHtml(item.clientnm)}</div>`;
            });
            dropdown.innerHTML = html;
            dropdown.classList.remove('d-none');
        })
        .catch(err => console.error('Error fetching instansi:', err));
}

function selectInstansi(name, id) {
    const input = document.getElementById('reg_instansi');
    const dropdown = document.getElementById('instansi_dropdown');
    const clientRecId = document.getElementById('reg_client_rec_id');

    input.value = name;
    clientRecId.value = id;
    dropdown.classList.add('d-none');

    // Panggil ulang pengecekan setelah instansi dipilih dari dropdown
    const internalCompanies = [
        'pt. international test center',
        'pt international test center',
        'international test center',
        'pt. itc',
        'pt itc'
    ];

    const empContainer = document.getElementById('emp_no_container');
    const empInput = document.getElementById('reg_emp_no');
    const requiresEmpNo = document.getElementById('reg_requires_emp_no');

    if (internalCompanies.includes(name.toLowerCase().trim())) {
        empContainer.classList.remove('d-none');
        empInput.setAttribute('required', 'required');
        requiresEmpNo.value = "1";
        empInput.focus();
    } else {
        empContainer.classList.add('d-none');
        empInput.removeAttribute('required');
        requiresEmpNo.value = "0";
    }
}

        function escapeHtml(value) {
            return value
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        document.addEventListener('click', function(e) {
            const instansiInput = document.getElementById('reg_instansi');
            const dropdown = document.getElementById('instansi_dropdown');

            if (!instansiInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.add('d-none');
            }
        });

        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const isRequired = document.getElementById('reg_requires_emp_no').value === '1';

            if (isRequired && !validateEmployeeNoInput()) {
                e.preventDefault();

                Swal.fire({
                    icon: 'warning',
                    title: 'Employee No Wajib Diisi',
                    text: 'Untuk PT INTERNATIONAL TEST CENTER, Employee No harus diisi dan sesuai database.',
                    confirmButtonColor: '#1D4ED8',
                    confirmButtonText: 'Baik, Saya Perbaiki',
                    heightAuto: false
                }).then(() => {
                    document.getElementById('reg_emp_no').focus();
                });
            }
        });

        function openAppModal() {
            const modal = document.getElementById('smartcartModal');
            const content = document.getElementById('smartcartModalContent');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modal.classList.add('opacity-100');
                content.classList.remove('scale-95');
                content.classList.add('scale-100');
            }, 10);
        }

        function closeAppModal() {
            const modal = document.getElementById('smartcartModal');
            const content = document.getElementById('smartcartModalContent');
            modal.classList.remove('opacity-100');
            modal.classList.add('opacity-0');
            content.classList.remove('scale-100');
            content.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.remove('flex');
                modal.classList.add('hidden');
            }, 300);
        }

        function toggleRegPassword(inputId, iconId) {
            const pwd = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            } else {
                pwd.type = 'password';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            }
        }

        function validateStep1() {
            const acc = document.getElementById('reg_account_id');
            const email = document.getElementById('reg_email_id');
            const pwd = document.getElementById('reg_password');
            const retype = document.getElementById('reg_retype');
            const accMsg = document.getElementById('acc_msg');
            const emailMsg = document.getElementById('email_msg');
            const pwdMsg = document.getElementById('pwd_msg');
            const matchMsg = document.getElementById('pwd_match_msg');
            const btnNext = document.getElementById('btn_next_step1');
            let isValid = true;
            let errors = [];
            let firstInvalidElement = null;

            if (acc.value.length === 0) {
                isValid = false;
                accMsg.innerHTML = 'Gunakan huruf & angka, min. 6 karakter.';
                accMsg.className = 'text-xs mt-1 text-gray-500';
                acc.classList.remove('border-red-500', 'border-green-500');
            } else if (acc.value.length < 6) {
                isValid = false;
                accMsg.innerHTML = '<i class="fas fa-times-circle"><\/i> Minimal 6 karakter';
                accMsg.className = 'text-xs mt-1 text-red-500';
                acc.classList.add('border-red-500');
                errors.push("Account ID harus minimal 6 karakter.");
                if (!firstInvalidElement) firstInvalidElement = acc;
            } else {
                accMsg.innerHTML = '<i class="fas fa-check-circle"><\/i> Memenuhi syarat';
                accMsg.className = 'text-xs mt-1 text-green-600';
                acc.classList.replace('border-red-500', 'border-green-500');
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (email.value.length === 0) {
                isValid = false;
                emailMsg.classList.add('d-none');
                email.classList.remove('border-red-500', 'border-green-500');
            } else if (!emailRegex.test(email.value)) {
                isValid = false;
                emailMsg.classList.remove('d-none');
                emailMsg.innerHTML = '<i class="fas fa-times-circle"><\/i> Format email tidak valid';
                emailMsg.className = 'text-xs mt-1 text-red-500';
                email.classList.add('border-red-500');
                errors.push("Format Email tidak valid.");
                if (!firstInvalidElement) firstInvalidElement = email;
            } else {
                emailMsg.classList.remove('d-none');
                emailMsg.innerHTML = '<i class="fas fa-check-circle"><\/i> Email valid';
                emailMsg.className = 'text-xs mt-1 text-green-600';
                email.classList.replace('border-red-500', 'border-green-500');
            }

            if (pwd.value.length === 0) {
                isValid = false;
                pwdMsg.innerHTML = 'Minimal 6 karakter. Maksimal 16 karakter.';
                pwdMsg.className = 'text-xs mt-1 text-gray-500';
                pwd.classList.remove('border-red-500', 'border-green-500');
            } else if (pwd.value.length < 6) {
                isValid = false;
                pwdMsg.innerHTML = '<i class="fas fa-times-circle"><\/i> Minimal 6 karakter';
                pwdMsg.className = 'text-xs mt-1 text-red-500';
                pwd.classList.add('border-red-500');
                errors.push("Password harus minimal 6 karakter.");
                if (!firstInvalidElement) firstInvalidElement = pwd;
            } else {
                pwdMsg.innerHTML = '<i class="fas fa-check-circle"><\/i> Memenuhi syarat';
                pwdMsg.className = 'text-xs mt-1 text-green-600';
                pwd.classList.replace('border-red-500', 'border-green-500');
            }

            if (retype.value.length === 0) {
                isValid = false;
                matchMsg.classList.add('d-none');
                retype.classList.remove('border-red-500', 'border-green-500');
            } else if (pwd.value !== retype.value) {
                isValid = false;
                matchMsg.classList.remove('d-none');
                matchMsg.innerHTML = '<i class="fas fa-times-circle"><\/i> Passwords do not match';
                matchMsg.className = 'text-xs mt-1 text-red-500';
                retype.classList.add('border-red-500');
                errors.push("Re-Type Password tidak sama.");
                if (!firstInvalidElement) firstInvalidElement = retype;
            } else {
                matchMsg.classList.remove('d-none');
                matchMsg.innerHTML = '<i class="fas fa-check-circle"><\/i> Passwords match';
                matchMsg.className = 'text-xs mt-1 text-green-600';
                retype.classList.replace('border-red-500', 'border-green-500');
            }

            if (isValid) {
                btnNext.disabled = false;
                btnNext.classList.remove('opacity-50', 'cursor-not-allowed');
                btnNext.classList.add('hover:bg-blue-700');
            } else {
                btnNext.disabled = true;
                btnNext.classList.add('opacity-50', 'cursor-not-allowed');
                btnNext.classList.remove('hover:bg-blue-700');
            }
            return {
                isValid,
                errors,
                firstInvalidElement
            };
        }

        function goToStep(stepNumber, animate = true) {
            if (stepNumber === 2) {
                const validation = validateStep1();

                if (!validation.isValid) {
                    let errorHtml = '<ul class="text-left text-sm text-red-500 list-disc pl-5 mt-3 space-y-1.5 font-medium">';
                    validation.errors.forEach(err => {
                        errorHtml += `<li>${err}</li>`;
                    });
                    errorHtml += '</ul>';

                    Swal.fire({
                        icon: 'warning',
                        title: 'Oops! Data Belum Lengkap',
                        html: '<div class="text-left text-sm text-gray-600">Mohon perbaiki isian berikut sebelum melanjutkan:</div>' + errorHtml,
                        confirmButtonColor: '#1D4ED8',
                        confirmButtonText: 'Baik, Saya Perbaiki',
                        background: '#ffffff',
                        backdrop: `rgba(17, 24, 39, 0.55)`,
                        heightAuto: false,
                        customClass: {
                            popup: 'rounded-2xl shadow-2xl border border-gray-100 p-6',
                            title: 'text-xl font-bold text-gray-800 mb-2',
                            confirmButton: 'rounded-xl px-6 py-2.5 font-semibold text-white transition-all shadow-md hover:bg-blue-800'
                        }
                    }).then(() => {
                        if (validation.firstInvalidElement) validation.firstInvalidElement.focus();
                    });

                    return;
                }
            }

            const ind1 = document.getElementById('indicator-step-1');
            const ind2 = document.getElementById('indicator-step-2');
            const icon1 = document.getElementById('icon-step-1');
            const icon2 = document.getElementById('icon-step-2');
            const line1 = document.getElementById('line-step-1-2');

            if (stepNumber === 1) {
                ind1.classList.remove('opacity-50');
                ind2.classList.add('opacity-50');
                icon1.classList.replace('bg-white', 'bg-blue-600');
                icon1.classList.replace('text-blue-600', 'text-white');
                icon1.classList.replace('border', 'border-transparent');
                icon2.classList.replace('bg-blue-600', 'bg-gray-200');
                icon2.classList.replace('text-white', 'text-gray-600');
                line1.classList.remove('border-blue-600');
                formTitle.innerText = "Account Information";
                formSubtitle.innerText = "Buat ID Akun dan Password Anda";

                if (animate) {
                    step2Content.classList.remove('fade-enter-active');
                    setTimeout(() => {
                        step2Content.classList.add('d-none');
                        step1Content.classList.remove('d-none');
                        void step1Content.offsetWidth;
                        step1Content.classList.add('fade-enter-active');
                    }, 200);
                } else {
                    step2Content.classList.add('d-none');
                    step1Content.classList.remove('d-none');
                    step1Content.classList.add('fade-enter-active');
                }
            } else if (stepNumber === 2) {
                ind1.classList.add('opacity-50');
                ind2.classList.remove('opacity-50');
                icon1.classList.replace('bg-blue-600', 'bg-white');
                icon1.classList.replace('text-white', 'text-blue-600');
                icon1.classList.add('border', 'border-blue-600');
                icon2.classList.replace('bg-gray-200', 'bg-blue-600');
                icon2.classList.replace('text-gray-600', 'text-white');
                line1.classList.add('border-blue-600');
                formTitle.innerText = "Personal Information";
                formSubtitle.innerText = "Lengkapi Data Diri Anda";

                if (animate) {
                    step1Content.classList.remove('fade-enter-active');
                    setTimeout(() => {
                        step1Content.classList.add('d-none');
                        step2Content.classList.remove('d-none');
                        void step2Content.offsetWidth;
                        step2Content.classList.add('fade-enter-active');
                    }, 200);
                } else {
                    step1Content.classList.add('d-none');
                    step2Content.classList.remove('d-none');
                    step2Content.classList.add('fade-enter-active');
                }
            }
        }
    </script>


    <?php if (isset($_SESSION['old_input'])) { ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById('instansi_search');
    const resultsDiv = document.getElementById('search_results');
    const valueInput = document.getElementById('instansi_value');

    searchInput.addEventListener('input', function() {
        let q = this.value.trim();
        if (q.length < 2) {
            resultsDiv.innerHTML = '';
            resultsDiv.classList.add('hidden');
            return;
        }

        // Jalankan AJAX Search sesuai endpoint di process_register.php
        fetch(`process_register.php?ajax=client_search&q=${encodeURIComponent(q)}`)
            .then(response => response.json())
            .then(data => {
                resultsDiv.innerHTML = '';
                if (data.length === 0) {
                    resultsDiv.innerHTML = `<div class="p-3 text-sm text-gray-500">Instansi tidak ditemukan. Gunakan pilihan Individu jika tidak terdaftar.</div>`;
                    resultsDiv.classList.remove('hidden');
                    return;
                }

                data.forEach(item => {
                    let div = document.createElement('div');
                    div.className = 'p-3 text-sm hover:bg-blue-50 cursor-pointer transition-colors border-b border-gray-100';
                    div.innerText = item.clientnm;
                    div.addEventListener('click', function() {
                        searchInput.value = item.clientnm;
                        valueInput.value = item.clientnm; // Kirim nama instansi ke process_register.php
                        resultsDiv.innerHTML = '';
                        resultsDiv.classList.add('hidden');
                    });
                    resultsDiv.appendChild(div);
                });
                resultsDiv.classList.remove('hidden');
            })
            .catch(err => console.error("Error fetching clients:", err));
    });

    // Menutup dropdown jika klik di luar area pencarian
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
            resultsDiv.classList.add('hidden');
        }
    });
});

function toggleIndividu(checkbox) {
    const searchInput = document.getElementById('instansi_search');
    const valueInput = document.getElementById('instansi_value');
    if (checkbox.checked) {
        searchInput.disabled = true;
        searchInput.value = '';
        valueInput.value = '';
        searchInput.classList.add('bg-gray-100');
    } else {
        searchInput.disabled = false;
        searchInput.classList.remove('bg-gray-100');
    }
}
        </script>
    <?php unset($_SESSION['old_input']);
    } ?>
</body>

</html>
