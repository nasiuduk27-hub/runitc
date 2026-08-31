@extends('layouts.guest')

@section('title', 'RUN-ITC | Verifikasi OTP')

@section('content')
<div class="flex min-h-screen items-center justify-center bg-gray-50 p-4">
    <div class="relative w-full max-w-md overflow-hidden rounded-3xl border border-gray-100 bg-white p-10 shadow-xl">
        <div class="absolute left-0 top-0 h-2 w-full bg-gradient-to-r from-indigo-500 to-purple-500"></div>

        <div class="mb-8 text-center">
            <div class="mx-auto mb-5 flex h-20 w-20 items-center justify-center rounded-full border border-indigo-100 bg-indigo-50 text-indigo-600 shadow-sm">
                <i class="fas fa-shield-alt text-3xl"></i>
            </div>
            <h2 class="mb-2 text-2xl font-bold text-gray-800">Verifikasi OTP</h2>
            <p class="text-sm text-gray-500">Masukkan 5 digit kode yang telah kami kirimkan ke email atau aplikasi Smartcart Anda.</p>

            <div class="mt-4 inline-block rounded-full border border-red-100 bg-red-50 px-4 py-1.5 text-sm font-semibold text-red-600">
                <i class="far fa-clock mr-1"></i> Sisa Waktu: <span id="countdown">05:00</span>
            </div>
        </div>

        @if (session('success_msg'))
            <div class="mb-5 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">{!! session('success_msg') !!}</div>
        @endif

        @if ($errors->any())
            <div class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('verify-otp.submit') }}" class="w-full">
            @csrf
            <div id="otp-container" class="mb-8 flex justify-center gap-3">
                <input type="text" maxlength="1" class="otp-input w-14 h-16 border-2 border-gray-200 rounded-xl bg-gray-50 text-center text-3xl font-bold text-gray-800 outline-none transition-all focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-50" autofocus required>
                <input type="text" maxlength="1" class="otp-input w-14 h-16 border-2 border-gray-200 rounded-xl bg-gray-50 text-center text-3xl font-bold text-gray-800 outline-none transition-all focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-50" required>
                <input type="text" maxlength="1" class="otp-input w-14 h-16 border-2 border-gray-200 rounded-xl bg-gray-50 text-center text-3xl font-bold text-gray-800 outline-none transition-all focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-50" required>
                <input type="text" maxlength="1" class="otp-input w-14 h-16 border-2 border-gray-200 rounded-xl bg-gray-50 text-center text-3xl font-bold text-gray-800 outline-none transition-all focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-50" required>
                <input type="text" maxlength="1" class="otp-input w-14 h-16 border-2 border-gray-200 rounded-xl bg-gray-50 text-center text-3xl font-bold text-gray-800 outline-none transition-all focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-50" required>
            </div>

            <input type="hidden" name="full_token" id="full_token">

            <button type="submit" class="mb-6 flex w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3.5 font-semibold text-white shadow-md transition-colors hover:bg-indigo-700 hover:shadow-lg">
                Verifikasi Sekarang
            </button>
        </form>

        <div class="text-center">
            <a href="{{ route('login') }}" class="text-sm font-medium text-gray-400 transition-colors hover:text-gray-700">Batal & Kembali ke Beranda</a>
        </div>
    </div>
</div>

<script>
    const inputs = document.querySelectorAll('.otp-input');
    const hiddenToken = document.getElementById('full_token');

    function updateHiddenToken() {
        let otpCode = '';
        inputs.forEach(input => { otpCode += input.value; });
        hiddenToken.value = otpCode;
    }

    inputs.forEach((input, index) => {
        input.addEventListener('input', function (e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value !== '' && index < inputs.length - 1) inputs[index + 1].focus();
            updateHiddenToken();
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && this.value === '' && index > 0) inputs[index - 1].focus();
        });
        input.addEventListener('paste', function (e) {
            e.preventDefault();
            const pastedData = e.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 5);
            const pasteArr = pastedData.split('');
            inputs.forEach((inp, i) => { inp.value = pasteArr[i] || ''; });
            if (pasteArr.length > 0) inputs[Math.min(pasteArr.length - 1, 4)].focus();
            updateHiddenToken();
        });
    });

    let timeLeft = {{ (int) ($remainingSeconds ?? 0) }};
    const countdownEl = document.getElementById('countdown');

    const timer = setInterval(() => {
        if (timeLeft <= 0) {
            clearInterval(timer);
            countdownEl.innerText = '00:00';
            if (confirm('Waktu verifikasi telah habis. Kode OTP tidak lagi berlaku. Klik OK untuk kembali ke halaman login.')) {
                window.location.href = '{{ route('login', ['otp_expired' => 1]) }}';
            } else {
                window.location.href = '{{ route('login', ['otp_expired' => 1]) }}';
            }
        } else {
            let m = Math.floor(timeLeft / 60);
            let s = timeLeft % 60;
            countdownEl.innerText = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
            timeLeft--;
        }
    }, 1000);
</script>
@endsection
