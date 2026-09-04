@extends('layouts.app')

@section('title', 'RUN-ITC | Operational Control Center')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900">Operational Control Center</h1>
            <p class="mt-0.5 text-sm text-gray-500">Pantau room, peserta, SPV, CRC &amp; Berita Acara</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <form method="GET" class="flex items-center gap-2" id="filterForm">
                <input type="date" name="date" value="{{ $filterDate }}" class="w-36 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
                <select name="client_id" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
                    <option value="">Semua</option>
                    @foreach ($clientOptions as $cid => $cnm)
                        <option value="{{ $cid }}" @selected($filterClientId === $cid)>{{ $cnm }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg bg-brand-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-primaryHover">Filter</button>
            </form>
            <button type="button" onclick="manualRefresh()" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-200"><i class="fa-solid fa-rotate mr-1"></i>Refresh</button>
            <span class="text-xs text-gray-400"><span id="autoRefreshDot" class="mr-1 inline-block h-2 w-2 rounded-full bg-green-500"></span>Auto</span>
        </div>
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-2 gap-4 xl:grid-cols-5">
        <div class="rounded-2xl border border-l-4 border-l-green-500 border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Active Rooms</div>
            <div class="mt-1 text-2xl font-bold text-gray-900"><span id="statActiveRooms">{{ $roomSummary['active'] ?? 0 }}</span> <span class="text-base text-gray-400">/ <span id="statTotalRooms">{{ $roomSummary['total_rooms'] ?? 0 }}</span></span></div>
            <div class="mt-1 text-xs text-gray-400"><span id="statCompletedRooms">{{ $roomSummary['completed'] ?? 0 }}</span> selesai &middot; <span id="statErrorRooms">{{ $roomSummary['error'] ?? 0 }}</span> error</div>
        </div>
        <div class="rounded-2xl border border-b-4 border-b-indigo-400 border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Peserta</div>
            <div class="mt-1 text-2xl font-bold text-gray-900"><span id="statPartTotal">{{ $partSummary['total'] ?? 0 }}</span></div>
            <div class="mt-1 text-xs text-gray-400"><span id="statPartAssigned">{{ $partSummary['assigned'] ?? 0 }}</span> assigned &middot; <span id="statPartUnassigned">{{ $partSummary['unassigned'] ?? 0 }}</span> unassigned</div>
        </div>
        <div class="rounded-2xl border-l-4 border-l-amber-400 border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">SPV</div>
            <div class="mt-1 text-2xl font-bold text-gray-900"><span id="statActiveSpv">{{ $spvStatus['active_spv'] ?? 0 }}</span> <span class="text-base text-gray-400">/ <span id="statTotalSpv">{{ $spvStatus['total_spv'] ?? 0 }}</span></span></div>
            <div class="mt-1 text-xs text-gray-400"><span id="statPendingAssignment">{{ $spvStatus['rooms_pending_assignment'] ?? 0 }}</span> room pending</div>
        </div>
        <div class="rounded-2xl border-l-4 border-l-red-400 border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">CRC</div>
            <div class="mt-1 text-2xl font-bold text-gray-900"><span id="statCrcUploaded">{{ $crcStatus['uploaded'] ?? 0 }}</span> <span class="text-base text-gray-400">/ <span id="statCrcTotal">{{ $crcStatus['total_rooms'] ?? 0 }}</span></span></div>
            <div class="mt-1 text-xs text-gray-400"><span id="statCrcPending">{{ $crcStatus['pending'] ?? 0 }}</span> pending</div>
        </div>
        <div class="rounded-2xl border-l-4 border-l-purple-400 border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Berita Acara</div>
            <div class="mt-1 text-2xl font-bold text-gray-900"><span id="statBaComplete">{{ $baStatus['complete'] ?? 0 }}</span> <span class="text-base text-gray-400">/ <span id="statBaTotal">{{ $baStatus['total_rooms'] ?? 0 }}</span></span></div>
            <div class="mt-1 text-xs text-gray-400"><span id="statBaPending">{{ $baStatus['pending'] ?? 0 }}</span> pending</div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        {{-- Left Column --}}
        <div class="space-y-5">
            {{-- Room Grid --}}
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <h2 class="text-sm font-bold text-gray-900">Room Monitoring</h2>
                    <span class="text-xs text-gray-400">Legend: <span class="text-green-600">&#9632;</span> Active <span class="text-amber-500">&#9632;</span> Warning <span class="text-red-500">&#9632;</span> Error <span class="text-gray-400">&#9632;</span> Done</span>
                </div>
                <div class="p-4" id="roomGridContainer">
                    @forelse ($roomList['rooms'] ?? [] as $room)
                        @php
                            $statusClass = match($room['status']) {
                                'active' => 'bg-green-100 text-green-700 border-green-300',
                                'error' => 'bg-red-100 text-red-700 border-red-300',
                                'completed' => 'bg-gray-100 text-gray-500 border-gray-200',
                                default => 'bg-amber-100 text-amber-700 border-amber-300',
                            };
                            $totalP = (int) ($room['total_participants'] ?? 0);
                            $finishedP = (int) ($room['finished_participants'] ?? 0);
                        @endphp
                        @if ($loop->first)
                        <div class="grid grid-cols-6 gap-2" id="roomGrid">
                        @endif
                            <div class="cursor-pointer rounded-lg border p-2 text-center text-[11px] transition hover:shadow-sm {{ $statusClass }}" onclick="openRoomDetail('{{ $room['admin_no'] }}')">
                                <div class="text-xs font-bold">{{ $room['admin_no'] }}</div>
                                <div class="text-[10px] opacity-75">{{ $room['status'] }}</div>
                                <div class="text-[9px] opacity-60">{{ $finishedP }}/{{ $totalP }}
                                    {!! $room['is_spv_assigned'] ? 'S' : '<span class="text-red-500">S</span>' !!}
                                    {!! $room['is_crc_uploaded'] ? 'C' : '<span class="text-red-500">C</span>' !!}
                                    {!! $room['is_ba_done'] ? 'B' : '<span class="text-red-500">B</span>' !!}
                                </div>
                            </div>
                        @if ($loop->last)
                        </div>
                        @endif
                    @empty
                        <div class="py-8 text-center text-gray-400"><i class="fa-solid fa-building mb-2 text-3xl"></i><br>Tidak ada room untuk tanggal ini</div>
                    @endforelse
                </div>
            </div>

            {{-- Peserta by Client + SPV Assignment --}}
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-3">
                        <h2 class="text-sm font-bold text-gray-900">Peserta by Client</h2>
                    </div>
                    <div class="space-y-2 p-4 text-sm">
                        @php $maxLoc = !empty($locDist) ? max(array_column($locDist, 'total')) : 1; @endphp
                        @forelse ($locDist as $loc)
                            <div>
                                <div class="mb-0.5 flex justify-between text-xs text-gray-500">
                                    <span>{{ $loc['location'] }}</span><span>{{ $loc['total'] }}</span>
                                </div>
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                                    <div class="h-full rounded-full bg-brand-primary" style="width: {{ ($loc['total'] / max($maxLoc, 1)) * 100 }}%"></div>
                                </div>
                            </div>
                        @empty
                            <div class="py-4 text-center text-xs text-gray-400">Tidak ada data</div>
                        @endforelse
                    </div>
                </div>
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-3">
                        <h2 class="text-sm font-bold text-gray-900">SPV Assignment</h2>
                    </div>
                    <div class="p-4 text-center">
                        @php $assignedPct = ($partSummary['total'] ?? 0) > 0 ? round((($partSummary['assigned'] ?? 0) / ($partSummary['total'] ?? 1)) * 100) : 0; @endphp
                        <div class="text-3xl font-bold text-brand-primary">{{ $assignedPct }}%</div>
                        <div class="mt-1 text-xs text-gray-400">Assigned</div>
                        <div class="mt-2 text-xs text-gray-500">
                            <span class="text-brand-primary">&#9632;</span> Assigned: {{ $partSummary['assigned'] ?? 0 }} &nbsp;
                            <span class="text-gray-200">&#9632;</span> Unassigned: {{ $partSummary['unassigned'] ?? 0 }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Room Table --}}
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <h2 class="text-sm font-bold text-gray-900">Room List</h2>
                    <input type="text" id="tableSearchInput" placeholder="Cari room..." oninput="filterTable()" class="w-40 rounded-lg border border-gray-300 px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-gray-50 font-semibold text-gray-500">
                                <th class="px-3 py-2 text-left">Room</th>
                                <th class="px-3 py-2 text-left">Status</th>
                                <th class="px-3 py-2 text-left">Peserta</th>
                                <th class="px-3 py-2 text-left">SPV</th>
                                <th class="px-3 py-2 text-left">CRC</th>
                                <th class="px-3 py-2 text-left">BA</th>
                                <th class="px-3 py-2 text-left">Client</th>
                                <th class="px-3 py-2 text-left">Update</th>
                                <th class="px-3 py-2 text-left">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="roomTableBody" class="divide-y divide-gray-100">
                            @forelse ($roomList['rooms'] ?? [] as $room)
                                @php
                                    $badge = match($room['status']) {
                                        'active' => 'bg-green-100 text-green-700',
                                        'error' => 'bg-red-100 text-red-700',
                                        'completed' => 'bg-gray-100 text-gray-500',
                                        default => 'bg-amber-100 text-amber-700',
                                    };
                                    $lastLogin = !empty($room['lupdt']) && $room['lupdt'] !== '0000-00-00 00:00:00' ? date('H:i', strtotime($room['lupdt'])) : '-';
                                @endphp
                                <tr data-name="{{ strtolower($room['admin_no']) }}" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 font-semibold text-gray-800">{{ $room['admin_no'] }}</td>
                                    <td class="px-3 py-2"><span class="rounded px-1.5 py-0.5 text-[9px] font-bold {{ $badge }}">{{ $room['status'] }}</span></td>
                                    <td class="px-3 py-2 text-gray-500">{{ $room['finished_participants'] ?? 0 }}/{{ $room['total_participants'] ?? 0 }}</td>
                                    <td class="px-3 py-2">{!! $room['is_spv_assigned'] ? '<span class="text-green-600">&#10003;</span>' : '<span class="text-red-500">&#10007;</span>' !!}</td>
                                    <td class="px-3 py-2">{!! $room['is_crc_uploaded'] ? '<span class="text-green-600">&#10003;</span>' : '<span class="text-red-500">&#10007;</span>' !!}</td>
                                    <td class="px-3 py-2">{!! $room['is_ba_done'] ? '<span class="text-green-600">&#10003;</span>' : '<span class="text-red-500">&#10007;</span>' !!}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ $room['client_nm'] ?? '-' }}</td>
                                    <td class="px-3 py-2 font-mono text-[10px] text-gray-400">{{ $lastLogin }}</td>
                                    <td class="px-3 py-2"><a href="javascript:void(0)" onclick="openRoomDetail('{{ $room['admin_no'] }}')" class="text-[10px] font-semibold text-brand-primary">Detail</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="px-3 py-6 text-center italic text-gray-400">Tidak ada room untuk tanggal ini</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Right Column --}}
        <div class="space-y-5">
            {{-- Incidents --}}
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <h2 class="text-sm font-bold text-gray-900">Incidents</h2>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500" id="incidentCount">{{ count($incidents) }}</span>
                </div>
                <div class="max-h-60 divide-y divide-gray-100 overflow-y-auto" id="incidentList">
                    @forelse ($incidents as $inc)
                        <div class="flex items-start gap-3 px-4 py-2.5">
                            <div class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $inc['severity'] === 'critical' ? 'bg-red-500' : ($inc['severity'] === 'warning' ? 'bg-amber-400' : 'bg-green-500') }}"></div>
                            <div class="min-w-0 flex-1">
                                <span class="mr-1 rounded px-1.5 py-0.5 text-[9px] font-bold {{ $inc['severity'] === 'critical' ? 'bg-red-100 text-red-700' : ($inc['severity'] === 'warning' ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700') }}">{{ $inc['severity'] }}</span>
                                <span class="text-sm text-gray-700">{{ $inc['message'] }}</span>
                                <span class="ml-2 text-xs text-gray-400">{{ date('H:i', strtotime($inc['timestamp'])) }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-gray-400"><i class="fa-solid fa-check-circle mb-2 text-xl text-green-500"></i><br>Tidak ada incident</div>
                    @endforelse
                </div>
            </div>

            {{-- Recent Activity --}}
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-3">
                    <h2 class="text-sm font-bold text-gray-900">Recent Activity</h2>
                </div>
                <div class="max-h-60 divide-y divide-gray-100 overflow-y-auto" id="activityFeed">
                    @forelse ($activities as $act)
                        <div class="flex items-start gap-3 px-4 py-2.5">
                            <div class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ ($act['type'] === 'room_completed' || $act['type'] === 'crc_uploaded') ? 'bg-green-500' : 'bg-amber-400' }}"></div>
                            <div class="min-w-0 flex-1">
                                <span class="text-sm text-gray-700">{{ $act['message'] }}</span>
                                <span class="ml-2 text-xs text-gray-400">{{ date('H:i', strtotime($act['timestamp'])) }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-gray-400">Belum ada aktivitas</div>
                    @endforelse
                </div>
            </div>

            {{-- CRC Upload Summary --}}
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-3">
                    <h2 class="text-sm font-bold text-gray-900">CRC Upload</h2>
                </div>
                <div class="p-4 text-center">
                    <div class="flex justify-center gap-8">
                        <div><div class="text-2xl font-bold text-brand-primary">{{ $crcStatus['uploaded'] ?? 0 }}</div><div class="text-xs text-gray-400">Uploaded</div></div>
                        <div><div class="text-2xl font-bold text-amber-500">{{ $crcStatus['pending'] ?? 0 }}</div><div class="text-xs text-gray-400">Pending</div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Room Detail Modal --}}
<div id="roomDetailModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4" onclick="if(event.target===this)closeDetail()">
    <div class="w-full max-w-3xl max-h-[90vh] overflow-y-auto rounded-2xl border border-gray-200 bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <h2 id="detailTitle" class="text-lg font-bold text-gray-900">Room Detail</h2>
            <button type="button" onclick="closeDetail()" class="text-xl leading-none text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <div id="detailContent" class="p-5 text-sm text-gray-700"></div>
    </div>
</div>

<script>
const API_BASE = '{{ route('admin.operational-dashboard.api') }}';
let refreshInterval = setInterval(pollSummary, 45000);

function manualRefresh() { refreshSummary(); pollIncidents(); }

function refreshSummary() {
    const params = new URLSearchParams(window.location.search);
    params.set('api', '1'); params.set('action', 'summary');
    fetch(API_BASE+'?'+params.toString()).then(r=>r.json()).then(d=>{ if (d.rooms) updateSummaryCards(d); }).catch(()=>{});
    pollIncidents();
}

function pollSummary() { refreshSummary(); }

function pollIncidents() {
    const params = new URLSearchParams(window.location.search);
    params.set('api', '1'); params.set('action', 'incidents');
    fetch(API_BASE+'?'+params.toString()).then(r=>r.json()).then(updateIncidentPanel).catch(()=>{});
}

function updateSummaryCards(d) {
    const s = d.rooms; setText('statActiveRooms', s.active ?? 0); setText('statTotalRooms', s.total_rooms ?? 0);
    setText('statCompletedRooms', s.completed ?? 0); setText('statErrorRooms', s.error ?? 0);
    const p = d.participants; setText('statPartTotal', p.total ?? 0); setText('statPartAssigned', p.assigned ?? 0); setText('statPartUnassigned', p.unassigned ?? 0);
    const spv = d.spv; setText('statActiveSpv', spv.active_spv ?? 0); setText('statTotalSpv', spv.total_spv ?? 0); setText('statPendingAssignment', spv.rooms_pending_assignment ?? 0);
    const c = d.crc; setText('statCrcUploaded', c.uploaded ?? 0); setText('statCrcTotal', c.total_rooms ?? 0); setText('statCrcPending', c.pending ?? 0);
    const b = d.ba; setText('statBaComplete', b.complete ?? 0); setText('statBaTotal', b.total_rooms ?? 0); setText('statBaPending', b.pending ?? 0);
}

function updateIncidentPanel(incidents) {
    const list = document.getElementById('incidentList'), cnt = document.getElementById('incidentCount');
    if (!list) return; cnt.textContent = incidents.length;
    if (!incidents.length) { list.innerHTML = '<div class="p-6 text-center text-gray-400"><i class="fa-solid fa-check-circle mb-2 text-xl text-green-500"></i><br>Tidak ada incident</div>'; return; }
    list.innerHTML = incidents.map(i => {
        const sev = i.severity || 'info';
        const dotCls = sev === 'critical' ? 'bg-red-500' : (sev === 'warning' ? 'bg-amber-400' : 'bg-green-500');
        const badgeCls = sev === 'critical' ? 'bg-red-100 text-red-700' : (sev === 'warning' ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700');
        return '<div class="flex items-start gap-3 px-4 py-2.5"><div class="mt-1.5 h-2 w-2 shrink-0 rounded-full '+dotCls+'"></div><div class="min-w-0 flex-1"><span class="mr-1 rounded px-1.5 py-0.5 text-[9px] font-bold '+badgeCls+'">'+escHtml(sev)+'</span><span class="text-sm text-gray-700">'+escHtml(i.message)+'</span><span class="ml-2 text-xs text-gray-400">'+formatTime(i.timestamp)+'</span></div></div>';
    }).join('');
}

function formatTime(t) {
    if (!t) return '-';
    const d = new Date(t.replace(' ', 'T'));
    if (isNaN(d)) return t;
    return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

function setText(id, v) { const e = document.getElementById(id); if (e) e.textContent = v; }

function openRoomDetail(adminNo) {
    document.getElementById('detailTitle').textContent = 'Room ' + adminNo;
    document.getElementById('detailContent').innerHTML = '<div class="py-8 text-center text-gray-400"><i class="fa-solid fa-spinner fa-spin text-xl"></i></div>';
    document.getElementById('roomDetailModal').classList.remove('hidden');
    document.getElementById('roomDetailModal').classList.add('flex');
    const p = new URLSearchParams(); p.set('api', '1'); p.set('action', 'room_detail'); p.set('admin_no', adminNo);
    fetch(API_BASE+'?'+p.toString()).then(r=>r.json()).then(renderRoomDetail).catch(() => {
        document.getElementById('detailContent').innerHTML = '<div class="py-8 text-center text-red-500">Gagal memuat data</div>';
    });
}

function renderRoomDetail(room) {
    const totalP = parseInt(room.total_participants) || 0, finishedP = parseInt(room.finished_participants) || 0, activeP = parseInt(room.active_participants) || 0;
    const statusColor = totalP === 0 ? 'text-amber-500' : (finishedP === totalP ? 'text-green-600' : (activeP > 0 ? 'text-green-600' : 'text-amber-500'));
    const statusText = totalP === 0 ? 'Waiting' : (finishedP === totalP ? 'Completed' : (activeP > 0 ? 'Active' : 'Pending'));
    document.getElementById('detailContent').innerHTML = `
        <div class="mb-4 grid grid-cols-4 gap-3">
            <div class="rounded-xl bg-gray-50 p-3"><div class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Status</div><div class="mt-1 font-bold ${statusColor}">${statusText}</div></div>
            <div class="rounded-xl bg-gray-50 p-3"><div class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Peserta</div><div class="mt-1 font-bold">${finishedP}/${totalP}</div></div>
            <div class="rounded-xl bg-gray-50 p-3"><div class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Client</div><div class="mt-1 font-bold">${escHtml(room.client_nm || '-')}</div></div>
            <div class="rounded-xl bg-gray-50 p-3"><div class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Koneksi</div><div class="mt-1 font-bold">${parseInt(room.conn_type) === 2 ? 'Hybrid' : 'Online'}</div></div>
        </div>
        <div class="mb-4"><h4 class="mb-2 text-sm font-bold text-gray-800">SPV Assignment</h4>` +
        (room.batches && room.batches.length
            ? '<table class="w-full text-xs"><thead><tr class="bg-gray-50 font-semibold text-gray-500"><th class="px-2 py-1.5 text-left">Batch</th><th class="px-2 py-1.5 text-left">SPV</th><th class="px-2 py-1.5 text-left">Jumlah</th></tr></thead><tbody class="divide-y divide-gray-100">'+room.batches.map(b => '<tr><td class="px-2 py-1.5">'+escHtml(b.batch_no || '-')+'</td><td class="px-2 py-1.5">'+escHtml(b.spv_name || '-')+'</td><td class="px-2 py-1.5">'+(b.authorize_amt || 0)+'</td></tr>').join('')+'</tbody></table>'
            : '<div class="text-xs text-gray-400">Belum ada SPV</div>') + `
        </div>
        <div class="mb-4"><h4 class="mb-2 text-sm font-bold text-gray-800">CRC</h4>` +
        (room.crc ? '<div class="rounded-xl border border-green-200 bg-green-50 p-3 text-xs"><div><span class="text-gray-500">File:</span> '+escHtml(room.crc.file_name || '-')+'</div><div><span class="text-gray-500">Upload:</span> '+(room.crc.uploaded_at || '-')+'</div><div><span class="text-gray-500">Oleh:</span> '+escHtml(room.crc.input_by || '-')+'</div></div>' : '<div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-700">CRC belum diupload</div>') + `
        </div>
        <div class="mb-4"><h4 class="mb-2 text-sm font-bold text-gray-800">Berita Acara</h4>` +
        (room.berita_acara && room.berita_acara.file_id ? '<div class="rounded-xl border border-green-200 bg-green-50 p-3 text-xs">Lengkap dengan PDF</div>' : '<div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-700">Berita Acara belum dibuat</div>') + `
        </div>
        <div class="mb-4"><h4 class="mb-2 text-sm font-bold text-gray-800">Peserta (${room.participants ? room.participants.length : 0})</h4>
            <div class="max-h-48 overflow-y-auto rounded-xl border border-gray-200">
                <table class="w-full text-[11px]">
                    <thead><tr class="bg-gray-50 text-[10px] font-semibold text-gray-500"><th class="sticky top-0 bg-gray-50 px-2 py-1.5 text-left">ID</th><th class="sticky top-0 bg-gray-50 px-2 py-1.5 text-left">Status</th><th class="sticky top-0 bg-gray-50 px-2 py-1.5 text-left">Mulai</th><th class="sticky top-0 bg-gray-50 px-2 py-1.5 text-left">Selesai</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                ` + (room.participants && room.participants.length ? room.participants.map(p => {
                    const done = ['7','8','9','c','C'].includes(p.statrec);
                    return '<tr><td class="px-2 py-1.5 font-mono">'+escHtml(p.authorize || '-')+'</td><td class="px-2 py-1.5"><span class="rounded px-1 py-0.5 text-[9px] font-bold '+(done ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700')+'">'+escHtml(p.statrec)+'</span></td><td class="px-2 py-1.5 text-gray-500">'+formatTime(p.start_time)+'</td><td class="px-2 py-1.5 text-gray-500">'+formatTime(p.end_time)+'</td></tr>';
                }).join('') : '<tr><td colspan="4" class="px-2 py-4 text-center text-gray-400">Tidak ada peserta</td></tr>') + `
                </tbody></table>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="../cbt_ops/test_admin/index.php?search="+encodeURIComponent(room.admin_no)+"" target="_blank" class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-200">Manage SPV</a>
            <a href="../cbt_ops/filing_system/main.php" target="_blank" class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-200">Upload CRC</a>
            <button type="button" onclick="closeDetail()" class="ml-auto rounded-lg bg-brand-primary px-4 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-primaryHover">Tutup</button>
        </div>
    `;
}

function closeDetail() { document.getElementById('roomDetailModal').classList.add('hidden'); document.getElementById('roomDetailModal').classList.remove('flex'); }

function filterTable() {
    const kw = document.getElementById('tableSearchInput').value.toLowerCase().trim();
    document.querySelectorAll('#roomTableBody tr').forEach(r => { r.style.display = r.dataset.name.includes(kw) ? '' : 'none'; });
}

function escHtml(s) { if (!s) return ''; const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

document.addEventListener('click', function (e) {
    const modal = document.getElementById('roomDetailModal');
    if (modal && modal.classList.contains('flex') && e.target === modal) closeDetail();
});
</script>
@endsection