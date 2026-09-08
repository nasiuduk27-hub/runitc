@extends('layouts.app')

@section('title', 'RUN-ITC | Transaksi Bank')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Transaksi Bank</h1>
            <p class="mt-0.5 text-sm text-gray-500">Buku rekening koperasi: seluruh mutasi masuk/keluar (icu_bank_trx) — penerimaan potong gaji (referensi icu_mtrx2hrd) dan transaksi di luar simpan-pinjam (pencairan, biaya bank, koreksi, transfer).</p>
        </div>
        @if ($isCoopAdmin)
            <a href="{{ route('cooperative.bank-transactions.create') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">
                <i class="fas fa-plus"></i> Tambah Transaksi Bank
            </a>
        @endif
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('cooperative.bank-transactions.index') }}"
          class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
        <div class="flex-1">
            <label for="q" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Cari</label>
            <input type="text" id="q" name="q" value="{{ $filters['q'] }}" placeholder="No transaksi, referensi, deskripsi, atau perusahaan..."
                   class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
        </div>
        <div class="w-full sm:w-44">
            <label for="dbocr" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Jenis</label>
            <select id="dbocr" name="dbocr"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                <option value="">Semua</option>
                <option value="D" {{ $filters['dbocr'] === 'D' ? 'selected' : '' }}>Debit</option>
                <option value="C" {{ $filters['dbocr'] === 'C' ? 'selected' : '' }}>Kredit</option>
            </select>
        </div>
        <div class="w-full sm:w-44">
            <label for="period" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Periode</label>
            <select id="period" name="period"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                <option value="">Semua</option>
                @foreach ($periods as $period)
                    <option value="{{ $period }}" {{ $filters['period'] === $period ? 'selected' : '' }}>{{ $period }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">
            <i class="fas fa-search"></i> Filter
        </button>
        @if ($filters['q'] !== '' || $filters['dbocr'] !== '' || $filters['period'] !== '')
            <a href="{{ route('cooperative.bank-transactions.index') }}"
               class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Reset</a>
        @endif
    </form>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Masuk (Debit)</p>
            <p class="mt-1 text-lg font-extrabold text-blue-600">Rp {{ number_format($debitTotal, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Keluar (Kredit)</p>
            <p class="mt-1 text-lg font-extrabold text-green-600">Rp {{ number_format($creditTotal, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Saldo Mutasi (D − C)</p>
            <p class="mt-1 text-lg font-extrabold text-gray-800">Rp {{ number_format($debitTotal - $creditTotal, 0, ',', '.') }}</p>
        </div>
    </div>
    <p class="-mt-2 text-[11px] text-gray-400">
        Total mengikuti filter pencarian/periode (tidak mengikuti filter jenis). Mutasi di luar simpan-pinjam wajar membuat saldo mutasi berbeda dari total detail simpan-pinjam yang dibukukan — pastikan tiap selisih dapat dijelaskan.
    </p>

    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] text-left text-sm">
                <thead>
                    <tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                        <th class="px-5 py-3 font-bold">Tanggal</th>
                        <th class="px-5 py-3 font-bold">No. Transaksi</th>
                        <th class="px-5 py-3 font-bold">No. Referensi</th>
                        <th class="px-5 py-3 font-bold">Jenis</th>
                        <th class="px-5 py-3 text-right font-bold">Amount (Rp)</th>
                        <th class="px-5 py-3 font-bold">Perusahaan</th>
                        <th class="px-5 py-3 font-bold">Deskripsi</th>
                        <th class="px-5 py-3 font-bold">Periode</th>
                        <th class="px-5 py-3 text-right font-bold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($transactions as $trx)
                        <tr class="transition hover:bg-gray-50/60">
                            <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-gray-600">{{ $trx->trndt }}</td>
                            <td class="whitespace-nowrap px-5 py-3 font-mono text-xs font-bold text-gray-800">{{ $trx->trnno }}</td>
                            <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-gray-600">{{ $trx->req_frm_trxno }}</td>
                            <td class="px-5 py-3">
                                @if ($trx->dbocr === 'D')
                                    <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-[10px] font-bold text-blue-700">Debit</span>
                                @else
                                    <span class="rounded-full bg-green-50 px-2.5 py-0.5 text-[10px] font-bold text-green-700">Kredit</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-gray-800">{{ number_format($trx->amount, 0, ',', '.') }}</td>
                            <td class="max-w-[160px] truncate px-5 py-3 text-xs font-medium text-gray-600" title="{{ $trx->notes }}">{{ $trx->notes ?: '-' }}</td>
                            <td class="max-w-[220px] truncate px-5 py-3 text-xs text-gray-500" title="{{ $trx->descr }}">{{ $trx->descr }}</td>
                            <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-gray-400">{{ $trx->pprdk }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="{{ route('cooperative.bank-transactions.edit', $trx->rec_id) }}"
                                       class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:border-brand-primary hover:text-brand-primary" title="Edit">
                                        <i class="fas fa-pen text-xs"></i>
                                    </a>
                                    <form method="POST" action="{{ route('cooperative.bank-transactions.destroy', $trx->rec_id) }}" onsubmit="return confirm('Hapus transaksi bank {{ $trx->trnno }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:border-red-300 hover:text-red-600" title="Hapus">
                                            <i class="fas fa-trash text-xs"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-10 text-center text-sm text-gray-400">Belum ada transaksi bank.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($transactions->hasPages())
            <div class="border-t border-gray-100 px-5 py-3">
                {{ $transactions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection