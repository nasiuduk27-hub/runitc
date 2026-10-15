@extends('layouts.filing')

@section('title', 'RUN-ITC | Anggota '.$group['group_name'])

@section('content')
@php
    $roleLabels = ['manager' => 'Pengelola', 'contributor' => 'Kontributor', 'viewer' => 'Penonton'];
@endphp
<div class="mx-auto max-w-3xl p-4 sm:p-6">
    <a href="{{ route('filing-system.groups.show', $group['rec_id']) }}" class="mb-4 inline-flex items-center gap-2 text-xs font-semibold text-gray-500 hover:text-blue-600">
        <i class="fas fa-arrow-left"></i> Kembali ke grup
    </a>

    <h1 class="mb-1 text-lg font-bold text-gray-800">Anggota — {{ $group['group_name'] }}</h1>
    <p class="mb-5 text-xs text-gray-400">Undang user atau divisi. Anggota divisi mengikuti keanggotaan divisi secara otomatis.</p>

    @if (session('success_msg'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">{{ session('success_msg') }}</div>
    @endif
    @if (session('error_msg'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ session('error_msg') }}</div>
    @endif

    @if ($canManage)
        <form method="POST" action="{{ route('filing-system.groups.members.add', $group['rec_id']) }}" class="mb-6 grid grid-cols-1 gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:grid-cols-4">
            @csrf
            <div>
                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500">Tipe</label>
                <select name="member_type" id="memberType" onchange="toggleMemberValue()" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                    <option value="user">User</option>
                    <option value="division">Divisi</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500">User</label>
                <select name="member_value" id="memberUser" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                    <option value="">Pilih user…</option>
                    @foreach ($userOptions as $opt)
                        <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500">Divisi</label>
                <select id="memberDivision" disabled class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm disabled:bg-gray-50">
                    <option value="">Pilih divisi…</option>
                    @foreach ($divisionOptions as $opt)
                        <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500">Peran</label>
                <div class="flex gap-2">
                    <select name="role" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                        <option value="contributor">Kontributor</option>
                        <option value="viewer">Penonton</option>
                        <option value="manager">Pengelola</option>
                    </select>
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700"><i class="fas fa-plus"></i></button>
                </div>
            </div>
        </form>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-100 text-xs text-gray-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Anggota</th>
                    <th class="px-4 py-3 font-medium">Tipe</th>
                    <th class="px-4 py-3 font-medium">Peran</th>
                    @if ($canManage)<th class="px-4 py-3 text-right font-medium">Aksi</th>@endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr>
                    <td class="px-4 py-3 font-semibold text-gray-800">Pemilik Grup</td>
                    <td class="px-4 py-3 text-gray-500">Owner</td>
                    <td class="px-4 py-3"><span class="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-600">Pengelola</span></td>
                    @if ($canManage)<td></td>@endif
                </tr>
                @forelse ($members as $member)
                    <tr>
                        <td class="px-4 py-3 text-gray-700">{{ $member['label'] }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $member['member_type'] === 'division' ? 'Divisi' : 'User' }}</td>
                        <td class="px-4 py-3">
                            @if ($canManage)
                                <form method="POST" action="{{ route('filing-system.groups.members.role', [$group['rec_id'], $member['rec_id']]) }}" class="inline-flex items-center gap-2">
                                    @csrf
                                    <select name="role" onchange="this.form.submit()" class="rounded-lg border border-gray-200 px-2 py-1 text-xs">
                                        @foreach ($roleLabels as $val => $label)
                                            <option value="{{ $val }}" @selected($member['role'] === $val)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            @else
                                <span class="text-xs text-gray-600">{{ $roleLabels[$member['role']] ?? $member['role'] }}</span>
                            @endif
                        </td>
                        @if ($canManage)
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('filing-system.groups.members.remove', [$group['rec_id'], $member['rec_id']]) }}" data-confirm="Keluarkan anggota ini?">
                                    @csrf
                                    <button type="submit" class="text-red-500 hover:text-red-700" title="Keluarkan"><i class="fas fa-user-minus"></i></button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-sm text-gray-400">Belum ada anggota tambahan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleMemberValue() {
    const type = document.getElementById('memberType').value;
    const user = document.getElementById('memberUser');
    const division = document.getElementById('memberDivision');
    const isUser = type === 'user';
    user.disabled = ! isUser;
    division.disabled = isUser;
    user.name = isUser ? 'member_value' : '';
    division.name = isUser ? '' : 'member_value';
}
toggleMemberValue();
</script>
@endsection
