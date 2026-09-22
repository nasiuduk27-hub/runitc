@extends('layouts.app')

@section('title', 'RUN-ITC | Rekonsiliasi')
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Rekonsiliasi Credit Union</h1>
        <p class="mt-1 text-sm text-gray-500">Pilih jenis &amp; periode, scan, lalu ubah hanya baris yang selisih. Perubahan diajukan sebagai satu batch untuk diverifikasi admin lain.</p>
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if ($errors->any() || $error)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() ?: $error }}</div>
    @endif
    @if (session('batchErrors'))
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            <p class="font-semibold">Sebagian kasus gagal diproses:</p>
            <ul class="mt-1 list-inside list-disc">
                @foreach (session('batchErrors') as $batchError)
                    <li>{{ $batchError }}</li>
                @endforeach
            </ul>
        </div>
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
            <input name="period" value="{{ $period }}" list="period-options" pattern="\d{6}" maxlength="6"
                   class="w-36 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold">
            <datalist id="period-options">
                @foreach ($periodOptions as $option)
                    <option value="{{ $option }}"></option>
                @endforeach
            </datalist>
        </div>
        <input type="hidden" name="scan" value="1">
        <button class="rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-primaryHover">Scan</button>
    </form>

    @if ($snapshot)
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-bold text-gray-800">Hasil Scan — Bank {{ \App\Services\CreditUnion\CreditUnionPeriod::longLabel($period) }}</p>
            <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="rounded-xl bg-gray-50 p-3"><p class="text-[11px] font-bold uppercase text-gray-500">Tagihan HRD</p><p class="mt-1 text-lg font-extrabold">Rp {{ number_format($snapshot['expected'], 0, ',', '.') }}</p></div>
                <div class="rounded-xl bg-gray-50 p-3"><p class="text-[11px] font-bold uppercase text-gray-500">Diterima (nilai sistem)</p><p class="mt-1 text-lg font-extrabold">Rp {{ number_format($snapshot['actual'], 0, ',', '.') }}</p></div>
                <div class="rounded-xl bg-gray-50 p-3"><p class="text-[11px] font-bold uppercase text-gray-500">Detail posted</p><p class="mt-1 text-lg font-extrabold">Rp {{ number_format($snapshot['detail_posted'], 0, ',', '.') }}</p></div>
            </div>
            <form method="POST" action="{{ route('cu.reconciliation.store') }}" class="mt-5 grid gap-3 md:grid-cols-3">
                @csrf
                <input type="hidden" name="scope" value="{{ $scope }}"><input type="hidden" name="period" value="{{ $period }}">
                <div><label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nilai referensi</label><input type="text" name="expected_amount" inputmode="numeric" autocomplete="off" data-rupiah required class="w-full rounded-xl border border-gray-200 px-3 py-2.5" placeholder="Nilai yang benar"></div>
                <div class="md:col-span-2"><label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Alasan / sumber referensi</label><input name="reason" maxlength="500" required class="w-full rounded-xl border border-gray-200 px-3 py-2.5" placeholder="Contoh: laporan bank tanggal ..."></div>
                <div class="md:col-span-3"><button class="rounded-xl bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">Ajukan Koreksi</button></div>
            </form>
        </div>
    @endif

    @if ($memberRows !== [])
        <form method="POST" action="{{ route('cu.reconciliation.store') }}" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            @csrf
            <input type="hidden" name="scope" value="{{ $scope }}"><input type="hidden" name="period" value="{{ $period }}">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-gray-100 px-5 py-4">
                <div class="min-w-72 flex-1">
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Alasan / sumber referensi (berlaku untuk baris yang diubah)</label>
                    <input name="reason" maxlength="500" required class="w-full rounded-xl border border-gray-200 px-3 py-2.5" placeholder="Contoh: cocokkan dengan buku simpanan cetak ...">
                </div>
                <button class="rounded-xl bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">Ajukan Koreksi (baris yang berubah)</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead><tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500"><th class="px-5 py-3">Anggota</th><th class="px-5 py-3 text-right">Nilai sistem</th><th class="px-5 py-3 text-right">Nilai referensi</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($memberRows as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-2.5"><div class="font-medium text-gray-800">{{ $row['member_name'] }}</div><div class="font-mono text-xs text-gray-400">{{ $row['member_icuno'] }}</div></td>
                                <td class="px-5 py-2.5 text-right font-semibold text-gray-700">{{ number_format($row['actual'], 0, ',', '.') }}</td>
                                <td class="px-5 py-2.5 text-right"><input type="text" name="expected[{{ $row['member_rec_id'] }}]" value="{{ $row['actual'] }}" inputmode="numeric" autocomplete="off" data-rupiah class="w-40 rounded-lg border border-gray-200 px-3 py-1.5 text-right text-sm"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-5 py-3 text-xs text-gray-400">Baris yang nilainya sama dengan nilai sistem diabaikan otomatis.</div>
        </form>
    @endif

    <div class="space-y-4">
        <div class="text-sm font-bold text-gray-800">Menunggu Verifikasi</div>
        @php($pendingBatches = $pending->groupBy('batch_ref'))
        @forelse ($pendingBatches as $batchRef => $cases)
            <div class="overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-amber-100 bg-amber-50/60 px-5 py-3">
                    <p class="text-sm font-bold text-amber-800">
                        {{ $batchRef !== '' ? 'Batch '.$batchRef : 'Kasus '.$cases->first()->ref_no }}
                        <span class="font-normal text-amber-600">— {{ $cases->count() }} kasus</span>
                    </p>
                </div>
                <div class="overflow-x-auto"><table class="w-full min-w-[720px] text-left text-sm">
                    <thead><tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500"><th class="px-5 py-2">Referensi</th><th class="px-5 py-2">Anggota/Periode</th><th class="px-5 py-2 text-right">Sistem</th><th class="px-5 py-2 text-right">Referensi</th><th class="px-5 py-2 text-right">Selisih</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($cases as $case)
                            <tr><td class="px-5 py-2 font-mono text-xs">{{ $case->ref_no }}</td><td class="px-5 py-2">{{ $case->member_name ?: $case->period ?: '-' }}</td><td class="px-5 py-2 text-right">{{ number_format($case->actual_amount, 0, ',', '.') }}</td><td class="px-5 py-2 text-right">{{ number_format($case->expected_amount, 0, ',', '.') }}</td><td class="px-5 py-2 text-right font-semibold {{ $case->difference_amount < 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($case->difference_amount, 0, ',', '.') }}</td></tr>
                        @endforeach
                    </tbody>
                </table></div>
                @if ($batchRef !== '')
                    <div class="border-t border-amber-100 px-5 py-4">
                        <form method="POST" action="{{ route('cu.reconciliation.batch.decide', $batchRef) }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <div class="min-w-72 flex-1">
                                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Catatan verifikasi</label>
                                <input name="note" required maxlength="500" class="w-full rounded-xl border border-gray-200 px-3 py-2.5" placeholder="Catatan approval / alasan penolakan">
                            </div>
                            <button name="decision" value="approve" class="rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-700">Approve Batch</button>
                            <button name="decision" value="reject" class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-red-700">Reject Batch</button>
                        </form>
                    </div>
                @else
                    @php($case = $cases->first())
                    <div class="border-t border-amber-100 px-5 py-4">
                        <form method="POST" action="{{ route('cu.reconciliation.decide', $case->id) }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <div class="min-w-72 flex-1">
                                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Catatan verifikasi</label>
                                <input name="note" required maxlength="500" class="w-full rounded-xl border border-gray-200 px-3 py-2.5" placeholder="Catatan approval / alasan penolakan">
                            </div>
                            <button name="decision" value="approve" class="rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-700">Approve</button>
                            <button name="decision" value="reject" class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-red-700">Reject</button>
                        </form>
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-2xl border border-gray-200 bg-white px-5 py-6 text-center text-sm text-gray-400">Tidak ada kasus menunggu verifikasi.</div>
        @endforelse
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-3 text-sm font-bold text-gray-800">Riwayat Rekonsiliasi</div>
        <div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left text-sm">
            <thead><tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500"><th class="px-5 py-3">Referensi</th><th class="px-5 py-3">Jenis</th><th class="px-5 py-3">Anggota/Periode</th><th class="px-5 py-3 text-right">Sistem</th><th class="px-5 py-3 text-right">Referensi</th><th class="px-5 py-3 text-right">Selisih</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Koreksi</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($history as $case)
                <tr><td class="px-5 py-3 font-mono text-xs">{{ $case->ref_no }}</td><td class="px-5 py-3">{{ $scopes[$case->scope] ?? $case->scope }}</td><td class="px-5 py-3">{{ $case->member_name ?: $case->period ?: '-' }}</td><td class="px-5 py-3 text-right">{{ number_format($case->actual_amount, 0, ',', '.') }}</td><td class="px-5 py-3 text-right">{{ number_format($case->expected_amount, 0, ',', '.') }}</td><td class="px-5 py-3 text-right font-semibold">{{ number_format($case->difference_amount, 0, ',', '.') }}</td><td class="px-5 py-3"><span class="rounded-full border px-2 py-1 text-xs {{ $case->status === 'approved' ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700' }}">{{ ucfirst($case->status) }}</span></td><td class="px-5 py-3 font-mono text-xs text-gray-500">{{ $case->correction_trnno ?: '-' }}</td></tr>
            @empty
                <tr><td colspan="8" class="px-5 py-8 text-center text-gray-400">Belum ada riwayat rekonsiliasi.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="border-t border-gray-100 px-5 py-3">{{ $history->links() }}</div>
    </div>
</div>
@endsection
