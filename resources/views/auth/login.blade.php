@extends('layouts.guest')

@section('title', 'RUN-ITC | Login')

@section('content')
<div class="flex min-h-screen bg-white">
    <div class="relative hidden w-2/3 overflow-hidden bg-gray-900 lg:block">
        @forelse ($carousels ?? [] as $index => $item)
            <div class="slide absolute inset-0 transition-opacity duration-1000 {{ $index === 0 ? 'opacity-100 z-10' : 'opacity-0 z-0' }}" data-duration="{{ (int) ($item['duration'] ?? 5000) }}">
                @if (! empty($item['url']))
                    <a href="{{ $item['url'] }}" target="_blank" class="absolute inset-0 z-30 block" aria-label="Carousel link"></a>
                @endif
                <img src="{{ asset('assets/images/carousell/'.($item['file_name'] ?? '')) }}" alt="{{ $item['title'] ?? 'RUN-ITC' }}" class="h-full w-full object-cover">
                <div class="pointer-events-none absolute inset-x-0 bottom-0 z-20 bg-gradient-to-t from-black/80 to-transparent p-12 text-white">
                    @if (! empty($item['tag']))
                        <span class="mb-2 inline-block rounded bg-blue-600 px-2 py-1 text-xs font-bold uppercase shadow-md">{{ $item['tag'] }}</span>
                    @endif
                    <h2 class="mb-2 text-3xl font-bold drop-shadow-md">{{ $item['title'] ?? '' }}</h2>
                    <p class="text-sm text-gray-200 drop-shadow-md">{{ $item['caption'] ?? '' }}</p>
                </div>
            </div>
        @empty
            <div class="flex h-full items-center justify-center p-12 text-center text-white">
                <div>
                    <h2 class="text-3xl font-bold">Welcome to RUN-ITC</h2>
                    <p class="mt-2 text-gray-300">Integrated System</p>
                </div>
            </div>
        @endforelse
    </div>

    <div class="flex w-full items-center justify-center p-6 lg:w-1/3">
    <div class="w-full max-w-md rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-200 lg:shadow-none lg:ring-0">
        <div class="mb-8 text-center">
            <img src="{{ asset('assets/images/RUNITC_LOGO.png') }}" alt="RUN-ITC" class="mx-auto mb-5 h-10 w-auto">
            <h1 class="text-2xl font-semibold text-gray-900">Welcome to RUN-ITC</h1>
            <p class="mt-2 text-sm text-gray-500">Please sign in to your account</p>
        </div>

        @if ($success_msg ?? session('success_msg'))
            <div class="mb-5 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
                {!! $success_msg ?? session('success_msg') !!}
            </div>
        @endif

        @if ($error_msg ?? session('error_msg'))
            <div class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                {!! $error_msg ?? session('error_msg') !!}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.submit') }}" class="space-y-5">
            @csrf
            <div>
                <label for="account_id" class="mb-1 block text-sm font-medium text-gray-700">Account ID</label>
                <input id="account_id" name="account_id" type="text" required autofocus value="{{ old('account_id') }}" class="w-full rounded-md border border-gray-300 px-4 py-2 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
            </div>
            <div>
                <label for="passwd" class="mb-1 block text-sm font-medium text-gray-700">Password</label>
                <input id="passwd" name="passwd" type="password" required class="w-full rounded-md border border-gray-300 px-4 py-2 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
            </div>
            <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2.5 font-medium text-white hover:bg-indigo-700">Sign in</button>
            <a href="{{ route('forgot-password') }}" class="mt-3 block text-center text-xs font-semibold text-indigo-600 hover:text-indigo-800">Forgot Password?</a>
            <p class="mt-3 text-center text-sm text-gray-500">New on our platform? <a href="{{ route('register') }}" class="font-medium text-indigo-600 hover:text-indigo-800">Register an account</a></p>
        </form>
    </div>
    </div>
</div>

<script>
    const slides = document.querySelectorAll('.slide');
    let currentSlide = 0;

    function nextSlide() {
        if (slides.length <= 1) return;
        slides[currentSlide].classList.replace('opacity-100', 'opacity-0');
        slides[currentSlide].classList.replace('z-10', 'z-0');
        currentSlide = (currentSlide + 1) % slides.length;
        slides[currentSlide].classList.replace('opacity-0', 'opacity-100');
        slides[currentSlide].classList.replace('z-0', 'z-10');

        const duration = slides[currentSlide].getAttribute('data-duration') || 5000;
        setTimeout(nextSlide, Number.parseInt(duration, 10));
    }

    if (slides.length > 1) setTimeout(nextSlide, 5000);
</script>
@endsection
