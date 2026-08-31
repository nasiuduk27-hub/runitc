@extends('layouts.app')

@section('title', 'RUN-ITC | CBT Ops')

@section('content')
@php
    $modules = [
        [
            'title' => 'Filing System',
            'description' => 'Dokumen, share code, upload, download, dan pengarsipan file CBT.',
            'icon' => 'fas fa-folder-open',
            'color' => 'blue',
            'url' => route('filing-system.index'),
            'status' => 'Laravel UI awal',
        ],
        [
            'title' => 'Test Plan',
            'description' => 'Kelola jadwal, ruang, dan konfigurasi rencana tes.',
            'icon' => 'fas fa-calendar-check',
            'color' => 'emerald',
            'url' => route('cbt-ops.test-plan.index'),
            'status' => 'Laravel UI awal',
        ],
        [
            'title' => 'Test Admin',
            'description' => 'Dashboard admin tes, rekap peserta, dan assignment operasional.',
            'icon' => 'fas fa-user-shield',
            'color' => 'indigo',
            'url' => route('cbt-ops.test-admin.index'),
            'status' => 'Laravel UI awal',
        ],
        [
            'title' => 'Test Watching',
            'description' => 'Monitoring live CBT, CRC, timer, foto peserta, dan outbound receiver.',
            'icon' => 'fas fa-desktop',
            'color' => 'amber',
            'url' => route('cbt-ops.test-watching.monitoring'),
            'status' => 'Laravel route awal',
        ],
    ];
@endphp

<div class="space-y-6">
    <div class="overflow-hidden rounded-2xl bg-slate-950 shadow-sm ring-1 ring-slate-900">
        <div class="relative p-6 sm:p-8">
            <div class="absolute right-0 top-0 h-40 w-40 translate-x-12 -translate-y-12 rounded-full bg-blue-500/20 blur-3xl"></div>
            <div class="absolute bottom-0 left-1/3 h-32 w-32 rounded-full bg-indigo-500/20 blur-3xl"></div>
            <div class="relative">
                <p class="text-xs font-black uppercase tracking-[0.25em] text-blue-300">CBT Operations</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight text-white">Pusat Operasional CBT</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">Hub sementara selama migrasi. Modul yang sudah dipindahkan diarahkan ke route Laravel, sisanya tetap ke legacy agar operasional tidak terganggu.</p>
            </div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($modules as $module)
            <a href="{{ $module['url'] }}" @if ($module['title'] === 'Test Watching') target="_blank" rel="noopener noreferrer" @endif class="group rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200 transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-{{ $module['color'] }}-50 text-{{ $module['color'] }}-600">
                        <i class="{{ $module['icon'] }} text-lg"></i>
                    </div>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-gray-500">{{ $module['status'] }}</span>
                </div>
                <h2 class="mt-5 text-lg font-black text-gray-900 group-hover:text-blue-700">{{ $module['title'] }}</h2>
                <p class="mt-2 text-sm leading-6 text-gray-500">{{ $module['description'] }}</p>
                <div class="mt-5 inline-flex items-center gap-2 text-xs font-black text-gray-400 group-hover:text-blue-600">
                    Buka modul
                    <i class="fas fa-arrow-right transition group-hover:translate-x-1"></i>
                </div>
            </a>
        @endforeach
    </div>

    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <i class="fas fa-info-circle mr-2"></i>
        Route legacy tetap dipertahankan sampai setiap fitur UI, action, dan endpoint teknis selesai diverifikasi.
    </div>
</div>
@endsection
