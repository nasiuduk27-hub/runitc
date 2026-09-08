@extends('layouts.app')

@section('title', 'RUN-ITC | Monthly Processing')
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Monthly Processing</h1>
        <p class="mt-1 text-sm text-gray-500">Tagihan potongan gaji per anggota untuk HRD: simpanan wajib belum diposting + angsuran jatuh tempo belum dibayar. Baris yang sudah dibukukan tidak ditagih dua kali.</p>
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('cooperative.monthly-processing.index') }}" class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
        <div>
            <label for="period" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Periode</label>
            <input id="period" name="period" value="{{ $period }}" maxlength="6" pattern="[0-9]{6}" required
                   class="w-36 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold outline-none focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
        </div>
        <div class="sm:w-96">
            <label for="cmpcd" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Perusahaan</label>
            <select id="cmpcd" name="cmpcd" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold outline-none focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                <option value="">Pilih perusahaan</option>
                @foreach ($companies as $code => $name)
                    <option value="{{ $code }}" @selected($company === $code)>{{ $code }} - {{ $name }}</option>
                @endforeach
            </select>
        </div>
        <input type="hidden" name="generate" value="1">
        <button class="rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 hover:bg-brand-primaryHover">Generate</button>
    </form>

    @if ($recon)
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-bold text-gray-800">Rekonsiliasi Periode {{ $period }}</p>
                @if ($recon['tagihan'] > 0)
                    <span class="inline-block rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $recon['belum_diterima'] > 0 ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-green-200 bg-green-50 text-green-700' }}">
                        {{ $recon['belum_diterima'] > 0 ? 'Masih ada tagihan belum diterima' : 'Tagihan diterima penuh' }}
                    </span>
                @endif
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-xl border border-gray-100 bg-gray-50 p-3">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Tagihan ke HRD (icu_mtrx2hrd)</p>
                    <p class="mt-1 text-lg font-extrabold text-gray-900">{{ number_format($recon['tagihan'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-gray-50 p-3">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Diterima (icu_bank_trx)</p>
                    <p class="mt-1 text-lg font-extrabold text-blue-600">{{ number_format($recon['diterima'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-gray-50 p-3">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Belum diterima</p>
                    <p class="mt-1 text-lg font-extrabold {{ $recon['belum_diterima'] > 0 ? 'text-amber-600' : 'text-green-600' }}">{{ number_format($recon['belum_diterima'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-gray-50 p-3">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Dibukukan simpan-pinjam</p>
                    <p class="mt-1 text-lg font-extrabold text-green-600">{{ number_format($recon['detail_posted'], 0, ',', '.') }}</p>
                </div>
            </div>
            <p class="mt-3 text-[11px] leading-relaxed text-gray-400">
                Tagihan tersimpan saat klik <b>Save to Database</b>; "Diterima" = icu_bank_trx Debit yang mereferensikan nomor PMT tagihan.
                icu_bank_trx juga memuat mutasi di luar simpan-pinjam (biaya bank, koreksi, transfer), jadi selisih tidak harus nol selama dapat dijelaskan.
            </p>
        </div>
    @endif

    @if ($result)
        <div class="flex flex-wrap gap-3 print:hidden">
            <a href="{{ route('cooperative.monthly-processing.export', ['period' => $period, 'cmpcd' => $company]) }}" class="rounded-xl border border-green-200 bg-white px-4 py-2.5 text-sm font-semibold text-green-700 hover:bg-green-50"><i class="fas fa-file-excel"></i> Export XLSX</a>
            <form method="POST" action="{{ route('cooperative.monthly-processing.save') }}" onsubmit="return confirm('Simpan ulang agregat laporan ini ke database?')">
                @csrf
                <input type="hidden" name="period" value="{{ $period }}"><input type="hidden" name="cmpcd" value="{{ $company }}">
                <button class="rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-primaryHover"><i class="fas fa-database"></i> Save to Database</button>
            </form>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-3 text-sm text-gray-500">{{ $company }} | {{ \App\Services\Cooperative\CooperativePeriod::longLabel($period) }} | {{ number_format($result['rows']->count(), 0, ',', '.') }} anggota</div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-left text-sm">
                    <thead><tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500"><th class="px-5 py-3">Nama Member</th><th class="px-5 py-3 text-right">Saving</th><th class="px-5 py-3 text-right">Loan (Cicilan ke)</th><th class="px-5 py-3 text-right">Expense</th><th class="px-5 py-3 text-right">Total</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($result['rows'] as $row)
                            <tr class="hover:bg-gray-50"><td class="px-5 py-2.5"><div class="font-medium text-gray-800">{{ $row['member_name'] }}</div><div class="font-mono text-xs text-gray-400">{{ $row['member_icuno'] }}</div></td><td class="px-5 py-2.5 text-right">{{ number_format($row['saving'], 0, ',', '.') }}</td><td class="px-5 py-2.5 text-right">{{ number_format($row['loan'], 0, ',', '.') }} @if ($row['installments']) <span class="text-xs text-gray-400">({{ $row['installments'] }})</span> @endif</td><td class="px-5 py-2.5 text-right">{{ number_format($row['expense'], 0, ',', '.') }}</td><td class="px-5 py-2.5 text-right font-semibold">{{ number_format($row['total'], 0, ',', '.') }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">Tidak ada aktivitas pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot><tr class="bg-gray-50 font-bold text-gray-800"><td class="px-5 py-3">TOTAL</td><td class="px-5 py-3 text-right">{{ number_format($result['totals']['saving'], 0, ',', '.') }}</td><td class="px-5 py-3 text-right">{{ number_format($result['totals']['loan'], 0, ',', '.') }}</td><td class="px-5 py-3 text-right">{{ number_format($result['totals']['expense'], 0, ',', '.') }}</td><td class="px-5 py-3 text-right">{{ number_format($result['totals']['total'], 0, ',', '.') }}</td></tr></tfoot>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
