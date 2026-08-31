@extends('layouts.app')

@section('title', 'RUN-ITC | Detail Anggota')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('cooperative.members.index') }}" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50" title="Kembali">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $member->icunm }}</h1>
            <p class="mt-0.5 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                <span class="font-mono font-semibold text-brand-primary">{{ $member->icuno }}</span>
                <span class="inline-block rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $member->statusBadgeClass() }}">{{ $member->statusLabel() }}</span>
            </p>
        </div>
    </div>

    @if ($member->itc_user_id === 0)
        <div class="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-bold text-amber-800">Data anggota belum tersinkron ke akun RUNITC</p>
                <p class="mt-0.5 text-sm text-amber-700">Data riwayat CU ini baru bisa dilihat oleh pemilik akun setelah ditautkan. Pastikan anggota sudah mendaftar akun RUNITC, lalu lakukan sinkron.</p>
            </div>
            <a href="{{ route('cooperative.members.sync', ['member' => $member->rec_id]) }}"
               class="inline-flex items-center gap-2 self-start rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover sm:self-auto">
                <i class="fas fa-link"></i> Sinkronkan Akun
            </a>
        </div>
    @else
        <div class="rounded-2xl border border-green-200 bg-green-50 p-5 shadow-sm">
            <p class="text-sm font-bold text-green-800">Tersinkron dengan akun RUNITC #{{ $member->itc_user_id }}</p>
            <p class="mt-0.5 text-sm text-green-700">Pemilik akun ini dapat melihat seluruh riwayat CU miliknya di dashboard.</p>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-400">Informasi Keanggotaan</p>
            <dl class="space-y-2.5 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-gray-500">No. Anggota</dt><dd class="font-mono font-semibold text-gray-800">{{ $member->icuno }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Alias</dt><dd class="font-medium text-gray-800">{{ $member->alias_nm ?: '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Bergabung</dt><dd class="font-medium text-gray-800">{{ $member->joindt ? \Carbon\Carbon::parse($member->joindt)->format('d M Y') : '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Refno Pegawai</dt><dd class="font-mono text-xs font-medium text-gray-800">{{ $member->refno ?: '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">User RUNITC ID</dt><dd class="font-medium text-gray-800">{{ $member->itc_user_id > 0 ? $member->itc_user_id : '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Periode Referensi</dt><dd class="font-mono font-medium text-gray-800">{{ $member->pprdk ?: '-' }}</dd></div>
            </dl>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-400">Simpanan & Pinjaman</p>
            <dl class="space-y-2.5 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Simpanan Wajib (Rp)</dt><dd class="font-bold text-gray-900">{{ number_format($member->swajib, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Outstanding (field lama)</dt><dd class="font-bold text-gray-900">{{ number_format($member->outstanding, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Jumlah Pinjaman</dt><dd class="font-medium text-gray-800">{{ number_format($loanSummary['count'], 0, ',', '.') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Total Pokok Pinjam (Rp)</dt><dd class="font-medium text-gray-800">{{ number_format($loanSummary['total_principle'], 0, ',', '.') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Sisa Pokok Indikatif (Rp)</dt><dd class="font-bold text-brand-primary">{{ number_format($loanSummary['indicative_outstanding'], 0, ',', '.') }}</dd></div>
            </dl>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-400">Ringkasan Transaksi</p>
            <dl class="space-y-2.5 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Jumlah Transaksi</dt><dd class="font-medium text-gray-800">{{ number_format($transactionTotals['count'], 0, ',', '.') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Total Debit (D)</dt><dd class="font-bold text-green-600">{{ number_format($transactionTotals['debit'], 0, ',', '.') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Total Kredit (C)</dt><dd class="font-bold text-red-500">{{ number_format($transactionTotals['credit'], 0, ',', '.') }}</dd></div>
            </dl>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <p class="text-sm font-bold text-gray-800">Riwayat Pinjaman</p>
            <p class="mt-0.5 text-xs text-gray-400">Status lunas bersifat indikatif (paid >= totalloan) sampai makna field dikonfirmasi.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] text-left text-sm">
                <thead>
                    <tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                        <th class="px-5 py-3 font-bold">No. Transaksi</th>
                        <th class="px-5 py-3 font-bold">Tanggal</th>
                        <th class="px-5 py-3 font-bold">Keterangan</th>
                        <th class="px-5 py-3 text-right font-bold">Pokok (Rp)</th>
                        <th class="px-5 py-3 text-right font-bold">Total Tagihan (Rp)</th>
                        <th class="px-5 py-3 text-right font-bold">Terbayar (Rp)</th>
                        <th class="px-5 py-3 text-center font-bold">Tenor</th>
                        <th class="px-5 py-3 font-bold">Periode</th>
                        <th class="px-5 py-3 font-bold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($loans as $loan)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-mono text-xs font-semibold text-brand-primary">{{ $loan->trnno }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $loan->trndt ? \Carbon\Carbon::parse($loan->trndt)->format('d M Y') : '-' }}</td>
                            <td class="px-5 py-3 text-gray-700">{{ $loan->descr ?: '-' }}</td>
                            <td class="px-5 py-3 text-right text-gray-700">{{ number_format($loan->principle, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right text-gray-700">{{ number_format($loan->totalloan, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right text-gray-700">{{ number_format($loan->paid, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-center text-gray-600">{{ $loan->term }} bln</td>
                            <td class="px-5 py-3 font-mono text-xs text-gray-500">{{ $loan->startper }} - {{ $loan->endper }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-block rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $loan->statusBadgeClass() }}">{{ $loan->statusLabel() }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-5 py-8 text-center text-sm text-gray-400">Belum ada pinjaman.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <p class="text-sm font-bold text-gray-800">Transaksi Terakhir</p>
            <p class="mt-0.5 text-xs text-gray-400">15 transaksi terbaru dari total {{ number_format($transactionTotals['count'], 0, ',', '.') }} transaksi.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead>
                    <tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                        <th class="px-5 py-3 font-bold">No. Transaksi</th>
                        <th class="px-5 py-3 font-bold">Tanggal</th>
                        <th class="px-5 py-3 font-bold">Keterangan</th>
                        <th class="px-5 py-3 font-bold">Arah</th>
                        <th class="px-5 py-3 text-right font-bold">Pokok (Rp)</th>
                        <th class="px-5 py-3 text-right font-bold">Bunga (Rp)</th>
                        <th class="px-5 py-3 text-right font-bold">Total (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($transactions as $trx)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-mono text-xs font-semibold text-brand-primary">{{ $trx->trnno }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $trx->trndt ? \Carbon\Carbon::parse($trx->trndt)->format('d M Y') : '-' }}</td>
                            <td class="px-5 py-3 text-gray-700">{{ $trx->descr ?: '-' }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-block rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $trx->dbocr === 'D' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600' }}">{{ $trx->directionLabel() }}</span>
                            </td>
                            <td class="px-5 py-3 text-right text-gray-700">{{ number_format($trx->basic_amt, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right text-gray-700">{{ number_format($trx->int_amt, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right font-semibold text-gray-800">{{ number_format($trx->amount, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-8 text-center text-sm text-gray-400">Belum ada transaksi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
