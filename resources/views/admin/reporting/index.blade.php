@extends('layouts.app')

@section('title', 'RUN-ITC | Reporting')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Reporting & Export</h1>
        <p class="mt-0.5 text-sm text-gray-500">Generate & export laporan audit, operasional, dan aktivitas user.</p>
    </div>

    <div class="flex gap-1 rounded-2xl border border-gray-200 bg-white p-1 shadow-sm" id="tabNav">
        <button onclick="switchTab('audit_log')" class="tab-btn active flex-1 rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white transition" data-tab="audit_log">Audit Log</button>
        <button onclick="switchTab('operational')" class="tab-btn flex-1 rounded-xl px-4 py-2.5 text-sm font-semibold transition" data-tab="operational">Operational</button>
        <button onclick="switchTab('user_activity')" class="tab-btn flex-1 rounded-xl px-4 py-2.5 text-sm font-semibold transition" data-tab="user_activity">User Activity</button>
    </div>

    @include('admin.reporting.partials.audit-tab', ['prefix' => 'al', 'type' => 'audit_log', 'title' => 'Audit Log Export'])
    @include('admin.reporting.partials.operational-tab')
    @include('admin.reporting.partials.audit-tab', ['prefix' => 'ua', 'type' => 'user_activity', 'title' => 'User Activity Report'])
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active', 'bg-brand-primary', 'text-white'));
    document.getElementById('tab_' + tab).classList.remove('hidden');
    document.querySelector('.tab-btn[data-tab="' + tab + '"]')?.classList.add('active', 'bg-brand-primary', 'text-white');
}
function previewReport(type) {
    const prefix = type === 'audit_log' ? 'al' : 'ua';
    const params = new URLSearchParams({preview: '1', type});
    params.set('date_from', document.getElementById(prefix + '_date_from').value);
    params.set('date_to', document.getElementById(prefix + '_date_to').value);
    params.set('action', document.getElementById(prefix + '_action').value);
    const container = document.getElementById(prefix + '_preview');
    const body = document.getElementById(prefix + '_preview_body');
    container.classList.remove('hidden');
    body.innerHTML = '<tr><td colspan="4" class="px-3 py-4 text-center text-gray-400">Loading...</td></tr>';
    fetch('{{ route('admin.reporting.index') }}?' + params.toString()).then(r => r.json()).then(data => {
        if (!data.rows || !data.rows.length) { body.innerHTML = '<tr><td colspan="4" class="px-3 py-4 text-center text-gray-400">Tidak ada data</td></tr>'; return; }
        body.innerHTML = data.rows.map(r => `<tr><td class="px-3 py-2 text-gray-600">${r.created_at || '-'}</td><td class="px-3 py-2 text-gray-800">${r.account_nm || 'Unknown'}</td><td class="px-3 py-2"><span class="rounded bg-gray-100 px-1.5 py-0.5 text-[9px] font-bold text-gray-700">${r.action || '-'}</span></td><td class="px-3 py-2 text-gray-500">${(r.target_type || '') + (r.target_id ? '#' + r.target_id : '')}</td></tr>`).join('');
    }).catch(() => body.innerHTML = '<tr><td colspan="4" class="px-3 py-4 text-center text-red-500">Error loading preview</td></tr>');
}
function exportReport(type, format) {
    const params = new URLSearchParams({export: '1', type, format});
    if (type === 'audit_log' || type === 'user_activity') {
        const prefix = type === 'audit_log' ? 'al' : 'ua';
        params.set('date_from', document.getElementById(prefix + '_date_from').value);
        params.set('date_to', document.getElementById(prefix + '_date_to').value);
        params.set('action', document.getElementById(prefix + '_action').value);
    } else if (type === 'operational') {
        params.set('date', document.getElementById('op_date').value);
    }
    window.open('{{ route('admin.reporting.index') }}?' + params.toString(), '_blank');
}
</script>
@endsection
