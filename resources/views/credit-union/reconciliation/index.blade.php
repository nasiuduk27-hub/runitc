@extends('layouts.app')

@section('title', 'RUN-ITC | Rekonsiliasi')
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Rekonsiliasi Credit Union</h1>
        <p class="mt-1 text-sm text-gray-500">Bandingkan nilai sistem dengan nilai referensi, lalu ajukan koreksi append-only untuk diverifikasi admin lain.</p>
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if ($errors->any() || $error)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() ?: $error }}</div>
    @endif

    <form method="GET" action="{{ route('cu.reconciliation.index') }}" class="flex flex-wrap items-end gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
        <div>
            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Jenis</label>
            <select name="scope" class="w-56 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold">
                @foreach ($scopes as $key => $label)
                    <option value="{{ $key }}" @selected($scope === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Periode</label>
            <input name="period" value="{{ $period }}" pattern="\d{6}" class="w-36 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold">
        </div>
        <div class="min-w-64">
            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Anggota (untuk simpanan/pinjaman)</label>
            <select name="member_rec_id" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold">
                <option value="">Pilih anggota</option>
                @foreach ($members as $member)
                    <option value="{{ $member->rec_id }}" @selected($memberId === $member->rec_id)>{{ $member->icuno }} - {{ $member->icunm }}</option>
                @endforeach
            </select>
        </div>
        <input type="hidden" name="scan" value="1">
        <button class="rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-primaryHover">Scan</button>
    </form>

    @if ($snapshot)
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-bold text-gray-800">Hasil Scan</p>
            <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="rounded-xl bg-gray-50 p-3"><p class="text-[11px] font-bold uppercase text-gray-500">Nilai sistem</p><p class="mt-1 text-lg font-extrabold">Rp {{ number_format($snapshot['actual'], 0, ',', '.') }}</p></div>
                @if (isset($snapshot['detail_posted']))
                    <div class="rounded-xl bg-gray-50 p-3"><p class="text-[11px] font-bold uppercase text-gray-500">Detail posted</p><p class="mt-1 text-lg font-extrabold">Rp {{ number_format($snapshot['detail_posted'], 0, ',', '.') }}</p></div>
                @endif
            </div>
            <form method="POST" action="{{ route('cu.reconciliation.store') }}" class="mt-5 grid gap-3 md:grid-cols-3">
                @csrf
                <input type="hidden" name="scope" value="{{ $scope }}"><input type="hidden" name="period" value="{{ $period }}"><input type="hidden" name="member_rec_id" value="{{ $memberId }}">
                <div><label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nilai referensi</label><input type="number" name="expected_amount" min="0" required class="w-full rounded-xl border border-gray-200 px-3 py-2.5" placeholder="Nilai yang benar"></div>
                <div class="md:col-span-2"><label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Alasan / sumber referensi</label><input name="reason" maxlength="500" required class="w-full rounded-xl border border-gray-200 px-3 py-2.5" placeholder="Contoh: laporan bank tanggal ..."></div>
                <div class="md:col-span-3"><button class="rounded-xl bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">Ajukan Koreksi</button></div>
            </form>
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-3 text-sm font-bold text-gray-800">Kasus Rekonsiliasi</div>
        <div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left text-sm">
            <thead><tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500"><th class="px-5 py-3">Referensi</th><th class="px-5 py-3">Jenis</th><th class="px-5 py-3 text-right">Sistem</th><th class="px-5 py-3 text-right">Referensi</th><th class="px-5 py-3 text-right">Selisih</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Aksi</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($cases as $case)
                <tr><td class="px-5 py-3"><b>{{ $case->ref_no }}</b><div class="text-xs text-gray-400">{{ $case->member_name ?: $case->period ?: '-' }}</div></td><td class="px-5 py-3">{{ $scopes[$case->scope] ?? $case->scope }}</td><td class="px-5 py-3 text-right">{{ number_format($case->actual_amount, 0, ',', '.') }}</td><td class="px-5 py-3 text-right">{{ number_format($case->expected_amount, 0, ',', '.') }}</td><td class="px-5 py-3 text-right font-semibold {{ $case->difference_amount < 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($case->difference_amount, 0, ',', '.') }}</td><td class="px-5 py-3"><span class="rounded-full border px-2 py-1 text-xs">{{ ucfirst($case->status) }}</span></td><td class="px-5 py-3">
                    @if ($case->status === 'submitted')
                        <form method="POST" action="{{ route('cu.reconciliation.decide', $case->id) }}" class="space-y-2"><input type="hidden" name="note" value="Disetujui setelah verifikasi dokumen.">@csrf<button name="decision" value="approve" class="rounded-lg bg-green-600 px-3 py-1.5 text-xs font-bold text-white">Approve</button><button name="decision" value="reject" class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-bold text-white">Reject</button></form>
                    @else <span class="text-xs text-gray-400">{{ $case->correction_trnno ?: '-' }}</span> @endif
                </td></tr>
            @empty
                <tr><td colspan="7" class="px-5 py-8 text-center text-gray-400">Belum ada kasus rekonsiliasi.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="border-t border-gray-100 px-5 py-3">{{ $cases->links() }}</div>
    </div>
</div>
@endsection
