@extends('layouts.app')

@section('title', 'RUN-ITC | Audit Log')

@section('content')
@php
    $getActionBadge = function (string $action): string {
        if (str_contains($action, 'denied') || str_contains($action, 'error')) {
            return 'bg-red-50 text-red-600 border-red-200';
        }
        if (str_contains($action, 'share')) {
            return 'bg-green-50 text-green-600 border-green-200';
        }
        if (str_contains($action, 'trash') || str_contains($action, 'delete')) {
            return 'bg-orange-50 text-orange-600 border-orange-200';
        }
        if (str_contains($action, 'archive')) {
            return 'bg-gray-100 text-gray-600 border-gray-200';
        }

        return 'bg-blue-50 text-blue-600 border-blue-200';
    };
    $queryFor = fn (array $overrides = []) => array_merge(request()->query(), $overrides);
    $colspan = $isAdmin ? ($filters['filing_id'] ? 5 : 6) : ($filters['filing_id'] ? 4 : 5);
@endphp

<div class="py-8 md:py-12">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h2 class="flex items-center gap-3 text-2xl font-extrabold tracking-tight text-gray-900">
                    <i class="fas fa-history text-indigo-600"></i>
                    Audit Log Aktivitas
                </h2>
                @if ($fileTitle)
                    <p class="mt-1 text-sm text-gray-500">Menampilkan histori untuk file: <strong class="text-gray-800">{{ $fileTitle }}</strong></p>
                @else
                    <p class="mt-1 text-sm text-gray-500">Log rekam jejak sistem penyimpanan dokumen</p>
                @endif
            </div>
            <div>
                <a href="{{ url('/filing-system') }}" class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">
                    <i class="fas fa-arrow-left"></i> Kembali ke Drive
                </a>
            </div>
        </div>

        <div class="mb-6 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <form method="GET" class="grid grid-cols-1 items-end gap-4 md:grid-cols-5">
                @if (! empty($filters['filing_id']))
                    <input type="hidden" name="filing_id" value="{{ $filters['filing_id'] }}">
                @endif

                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-bold text-gray-700">Cari Keyword / Catatan</label>
                    <input type="text" name="keyword" value="{{ $filters['keyword'] }}" placeholder="Pencarian bebas..." class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold text-gray-700">Jenis Aksi</label>
                    <select name="action" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Semua Aksi</option>
                        @foreach ($availableActions as $k => $v)
                            <option value="{{ $k }}" @selected($filters['action'] === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold text-gray-700">Dari Tanggal</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div class="flex gap-2">
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700"><i class="fas fa-search"></i></button>
                    @if (array_filter($filters))
                        <a href="{{ $filters['filing_id'] ? url('/modules/cbt_ops/filing_system/audit.php?filing_id='.$filters['filing_id']) : url('/modules/cbt_ops/filing_system/audit.php') }}" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-bold text-gray-600 transition hover:bg-gray-200"><i class="fas fa-times"></i></a>
                    @endif
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50 text-[11px] uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-6 py-4 font-bold">Waktu</th>
                            @if (empty($filters['filing_id']))
                                <th class="px-6 py-4 font-bold">Dokumen</th>
                            @endif
                            <th class="px-6 py-4 font-bold">User</th>
                            <th class="px-6 py-4 font-bold">Aksi</th>
                            <th class="px-6 py-4 font-bold">Catatan</th>
                            @if ($isAdmin)
                                <th class="px-6 py-4 font-bold">Security (Admin)</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse ($logs as $log)
                            <tr class="transition-colors hover:bg-gray-50/50">
                                <td class="whitespace-nowrap px-6 py-3 font-mono text-xs text-gray-600">
                                    {{ \Carbon\Carbon::parse($log['created_at'])->format('Y-m-d') }}<br>
                                    <span class="text-gray-400">{{ \Carbon\Carbon::parse($log['created_at'])->format('H:i:s') }}</span>
                                </td>

                                @if (empty($filters['filing_id']))
                                    <td class="max-w-[200px] truncate px-6 py-3">
                                        <a href="{{ url('/modules/cbt_ops/filing_system/audit.php?filing_id='.(int) $log['filing_id']) }}" class="font-bold text-indigo-600 hover:underline" title="{{ $log['file_name'] }}">
                                            {{ $log['file_name'] }}
                                        </a><br>
                                        <span class="font-mono text-[10px] text-gray-400">{{ $log['file_code'] }}</span>
                                    </td>
                                @endif

                                <td class="px-6 py-3">
                                    @if ($log['user_id'])
                                        <span class="font-medium text-gray-800">{{ $log['user_name'] ?? 'Unknown' }}</span>
                                    @else
                                        <span class="rounded border border-purple-100 bg-purple-50 px-2 py-0.5 text-xs font-bold text-purple-600">SYSTEM / CRON</span>
                                    @endif
                                </td>

                                <td class="whitespace-nowrap px-6 py-3">
                                    <span class="rounded border px-2 py-1 text-[10px] font-bold {{ $getActionBadge($log['action']) }}">
                                        {{ $formatActionLabel($log['action']) }}
                                    </span>
                                </td>

                                <td class="px-6 py-3 text-xs text-gray-600">{{ $log['notes'] }}</td>

                                @if ($isAdmin)
                                    <td class="px-6 py-3 font-mono text-[10px] text-gray-400">
                                        IP: {{ $log['ip_address'] ?? '-' }}<br>
                                        <span class="inline-block max-w-[150px] truncate" title="{{ $log['user_agent'] }}">{{ $log['user_agent'] }}</span>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $colspan }}" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-clipboard-list mb-3 block text-4xl text-gray-300"></i>
                                    Tidak ada data audit yang ditemukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($totalPages > 1)
                <div class="flex items-center justify-between border-t border-gray-100 bg-gray-50/50 px-6 py-4">
                    <p class="text-xs font-medium text-gray-500">Menampilkan {{ count($logs) }} dari {{ $totalItems }} record</p>
                    <div class="flex gap-1">
                        @if ($page > 1)
                            <a href="{{ url('/modules/cbt_ops/filing_system/audit.php') }}?{{ http_build_query($queryFor(['page' => $page - 1])) }}" class="rounded border border-gray-200 bg-white px-3 py-1 text-xs hover:bg-gray-50"><i class="fas fa-chevron-left"></i></a>
                        @endif

                        @php
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);
                        @endphp
                        @for ($i = $start; $i <= $end; $i++)
                            <a href="{{ url('/modules/cbt_ops/filing_system/audit.php') }}?{{ http_build_query($queryFor(['page' => $i])) }}" class="rounded px-3 py-1 text-xs font-bold transition-colors {{ $i == $page ? 'bg-indigo-600 text-white' : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50' }}">
                                {{ $i }}
                            </a>
                        @endfor

                        @if ($page < $totalPages)
                            <a href="{{ url('/modules/cbt_ops/filing_system/audit.php') }}?{{ http_build_query($queryFor(['page' => $page + 1])) }}" class="rounded border border-gray-200 bg-white px-3 py-1 text-xs hover:bg-gray-50"><i class="fas fa-chevron-right"></i></a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
