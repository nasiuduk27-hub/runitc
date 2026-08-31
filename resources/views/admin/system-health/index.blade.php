@extends('layouts.app')

@section('title', 'RUN-ITC | System Health')

@section('content')
@php
    $allOk = collect($health['databases'])->every(fn ($db) => $db['status'] === 'ok') && $health['ftp']['status'] === 'ok';
    $badge = fn ($status) => $status === 'ok' ? 'bg-green-100 text-green-700' : ($status === 'warning' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700');
@endphp

<div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">System Health</h1>
            <p class="mt-0.5 text-sm text-gray-500">Monitor infrastructure & application health.</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-xs text-gray-400" id="lastCheckTime">Last check: {{ now()->format('H:i:s') }}</span>
            <button onclick="runHealthCheck()" class="rounded-lg bg-brand-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-primaryHover"><i class="fa-solid fa-rotate mr-1"></i>Run Check</button>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex items-center gap-4">
            <div class="h-4 w-4 rounded-full {{ $allOk ? 'bg-green-500' : 'bg-red-500' }}"></div>
            <span class="text-lg font-bold text-gray-900">Overall Status: <span class="{{ $allOk ? 'text-green-600' : 'text-red-600' }}">{{ $allOk ? 'All Systems Operational' : 'Issues Detected' }}</span></span>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4"><h2 class="font-bold text-gray-900"><i class="fa-solid fa-database mr-2 text-brand-primary"></i>Database Connections</h2></div>
            <div class="divide-y divide-gray-100">
                @foreach ($health['databases'] as $key => $db)
                    <div class="border-l-4 p-4 {{ $db['status'] === 'ok' ? 'border-green-600' : ($db['status'] === 'warning' ? 'border-amber-600' : 'border-red-600') }}">
                        <div class="flex items-start justify-between">
                            <div>
                                <span class="font-semibold text-gray-900">{{ $db['name'] }}</span>
                                <span class="ml-2 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $badge($db['status']) }}">{{ $db['status'] === 'ok' ? 'Connected' : ($db['status'] === 'failed' ? 'Failed' : 'Slow') }}</span>
                            </div>
                            <button onclick="testDb('{{ $key }}')" class="text-xs font-semibold text-brand-primary hover:text-brand-primaryHover">Test</button>
                        </div>
                        @if ($db['response_time'] !== null)<p class="mt-1 text-xs text-gray-500">Response: {{ $db['response_time'] }} ms</p>@endif
                        @if ($db['error'])<p class="mt-1 text-xs text-red-500">{{ $db['error'] }}</p>@endif
                        <p class="mt-0.5 text-xs text-gray-400" id="dbResult_{{ $key }}"></p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4"><h2 class="font-bold text-gray-900"><i class="fa-solid fa-cloud mr-2 text-brand-primary"></i>FTP / File System</h2></div>
            <div class="space-y-4 p-4">
                <div class="border-l-4 pl-4 {{ $health['ftp']['status'] === 'ok' ? 'border-green-600' : 'border-red-600' }}">
                    <div class="flex items-center justify-between">
                        <div><span class="font-semibold text-gray-900">FTP Server</span><span class="ml-2 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $badge($health['ftp']['status']) }}">{{ $health['ftp']['status'] === 'ok' ? 'Connected' : 'Failed' }}</span></div>
                        <button onclick="testFtp()" class="text-xs font-semibold text-brand-primary hover:text-brand-primaryHover">Test</button>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">File count: {{ $health['ftp']['file_count'] }}</p>
                    @if ($health['ftp']['error'])<p class="mt-1 text-xs text-red-500">{{ $health['ftp']['error'] }}</p>@endif
                    <p class="mt-0.5 text-xs text-gray-400" id="ftpResult"></p>
                </div>
                <div class="border-l-4 border-green-600 pl-4">
                    <span class="font-semibold text-gray-900">Local Storage</span>
                    <div class="mt-1 text-xs text-gray-500"><p>Available: {{ $health['storage']['available_space'] }} MB / {{ $health['storage']['total_space'] }} MB</p><p>Writable: {{ $health['storage']['writable'] ? 'Yes' : 'No' }}</p><p>Temp usage: {{ $health['storage']['temp_usage'] }}</p></div>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4"><h2 class="font-bold text-gray-900"><i class="fa-solid fa-gauge-high mr-2 text-brand-primary"></i>Performance</h2></div>
            <div class="grid grid-cols-2 gap-4 p-4 text-sm"><div><span class="text-gray-500">PHP Version</span><br><span class="font-semibold">{{ $perf['php_version'] }}</span></div><div><span class="text-gray-500">Memory</span><br><span class="font-semibold">{{ $perf['memory_usage'] }} MB</span></div><div><span class="text-gray-500">Peak Memory</span><br><span class="font-semibold">{{ $perf['peak_memory'] }} MB</span></div><div><span class="text-gray-500">Last check</span><br><span class="font-semibold">{{ $health['timestamp'] }}</span></div></div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4"><h2 class="font-bold text-gray-900"><i class="fa-solid fa-bug mr-2 text-brand-primary"></i>Recent PHP Errors</h2></div>
            <div class="max-h-48 overflow-y-auto p-4">
                @forelse ($health['logs'] as $log)<pre class="mb-1 overflow-x-auto rounded-lg bg-red-50 p-2 text-[10px] text-red-600">{{ $log }}</pre>@empty<p class="py-4 text-center text-xs text-gray-400">No recent errors</p>@endforelse
            </div>
        </div>
    </div>
</div>

<script>
function runHealthCheck() { document.querySelectorAll('[id^=dbResult_]').forEach(el => el.textContent = 'Checking...'); document.getElementById('ftpResult').textContent = 'Checking...'; fetch('{{ route('admin.system-health.api') }}?action=check_all').then(() => location.reload()).catch(() => {}); }
function testDb(key) { fetch('{{ route('admin.system-health.api') }}?action=test_db&db=' + key).then(r => r.json()).then(data => { const el = document.getElementById('dbResult_' + key); el.innerHTML = data.status === 'ok' ? '<span class="text-green-600">OK (' + data.response_time + 'ms)</span>' : '<span class="text-red-600">Failed: ' + (data.error || '') + '</span>'; }); }
function testFtp() { fetch('{{ route('admin.system-health.api') }}?action=test_ftp').then(r => r.json()).then(data => { const el = document.getElementById('ftpResult'); el.innerHTML = data.status === 'ok' ? '<span class="text-green-600">FTP OK (' + data.file_count + ' files)</span>' : '<span class="text-red-600">Failed: ' + (data.error || '') + '</span>'; }); }
</script>
@endsection
