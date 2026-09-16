@extends('layouts.app')

@section('title', 'RUN-ITC | Input Transaksi Manual')

<style>
    .select2-container { width: 100% !important; }
    .select2-container .select2-selection--single {
        height: 42px !important;
        border-color: #E5E7EB !important;
        border-radius: 0.75rem !important;
        background-color: #F9FAFB !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 40px !important;
        padding-left: 12px !important;
        color: #1F2937 !important;
        font-size: 0.875rem !important;
        font-weight: 600 !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px !important; }
    .select2-container--default .select2-selection--single .select2-selection__placeholder { color: #6B7280 !important; font-weight: 500 !important; }
    .select2-dropdown { border-radius: 0.75rem !important; border-color: #E5E7EB !important; }
    .select2-search--dropdown .select2-search__field {
        border-radius: 0.5rem !important;
        border-color: #E5E7EB !important;
        outline: none !important;
        font-size: 0.875rem !important;
    }
</style>

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Input Transaksi Manual</h1>
        <p class="mt-1 text-sm text-gray-500">Pencatatan historical simpanan atau penarikan. Pilih jenis transaksi, isi data, lalu lihat mutasi anggota di bawah.</p>
    </div>
    @if (session('success')) <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('success') }}</div> @endif
    @if ($errors->any()) <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $errors->first() }}</div> @endif

    <form method="POST" action="{{ route('cu.manual-savings.store') }}" id="manualTrxForm" class="space-y-5">
        @csrf
        <div class="space-y-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Jenis Transaksi <span class="text-red-500">*</span></label>
                <div class="inline-flex overflow-hidden rounded-xl border border-gray-200">
                    <label class="type-toggle cursor-pointer">
                        <input type="radio" name="trx_type" value="savings" class="peer sr-only" checked>
                        <span class="flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-gray-600 peer-checked:bg-brand-primary peer-checked:text-white"><i class="fas fa-hand-holding-usd"></i> Simpanan</span>
                    </label>
                    <label class="type-toggle cursor-pointer border-l border-gray-200">
                        <input type="radio" name="trx_type" value="withdraw" class="peer sr-only">
                        <span class="flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-gray-600 peer-checked:bg-brand-primary peer-checked:text-white"><i class="fas fa-money-bill-wave"></i> Penarikan</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label for="member_rec_id" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Anggota Master <span class="text-gray-400">(opsional)</span></label>
                    <select id="member_rec_id" name="member_rec_id" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                        <option value="">Nama manual</option>
                        @foreach ($members as $member)
                            <option value="{{ $member->rec_id }}" data-name="{{ $member->icunm }}" data-icuno="{{ $member->icuno }}" @selected(old('member_rec_id') == $member->rec_id)>{{ $member->icuno }} - {{ $member->icunm }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-gray-400">Pilihan master mengisi nama & CU ID otomatis, nama tetap dapat diedit.</p>
                </div>
                <div>
                    <label for="member_name" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nama Anggota <span class="text-red-500">*</span></label>
                    <input id="member_name" name="member_name" value="{{ old('member_name') }}" placeholder="contoh: BUDI SANTOSO" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                </div>
                <div>
                    <label for="cu_id_display" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">CU ID</label>
                    <input id="cu_id_display" readonly placeholder="-" class="w-full rounded-xl border border-gray-200 bg-gray-100 px-3 py-2.5 text-sm font-bold text-gray-700 outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label for="amount" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500"><span id="amount_label">Nominal Simpanan</span> <span class="text-red-500">*</span></label>
                    <input id="amount" name="amount" type="text" inputmode="numeric" autocomplete="off" value="{{ old('amount') }}" placeholder="contoh: 100.000" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                </div>
                <div>
                    <label for="trndt" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Tanggal Transaksi <span class="text-red-500">*</span></label>
                    <input id="trndt" name="trndt" type="date" value="{{ old('trndt', date('Y-m-d')) }}" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Periode (YYYYMM)</label>
                    <input type="hidden" id="pprd" name="pprd">
                    <div id="pprd_display" class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-bold text-gray-800">Otomatis dari tanggal transaksi</div>
                    <p class="mt-1 text-[11px] text-gray-400">Siklus tutup buku: tanggal 21 masuk periode bulan berikutnya.</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                <p class="text-xs text-gray-500">Arah transaksi otomatis: <span class="font-bold text-gray-700" id="direction_label">Debit (menambah simpanan)</span></p>
                <div class="inline-flex items-center gap-4 text-xs font-bold uppercase tracking-wide text-gray-500">
                    <span>Debit <input type="radio" id="dbocr_d" disabled class="ml-1 align-middle"></span>
                    <span>Kredit <input type="radio" id="dbocr_c" disabled class="ml-1 align-middle"></span>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="submit" id="submit_btn" onclick="return confirm('Simpan transaksi manual ini?')" class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover"><i class="fas fa-save"></i> <span id="submit_label">Simpan Simpanan Manual</span></button>
                <button type="reset" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Batal</button>
            </div>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-bold text-gray-800">Mutasi Anggota</p>
                <p class="mt-0.5 text-xs text-gray-400" id="history_caption">Pilih anggota untuk melihat mutasi.</p>
            </div>
            <div class="inline-flex items-center gap-4 text-xs font-semibold text-gray-500">
                <label class="inline-flex items-center gap-1.5"><input type="radio" name="history_filter" value="all" checked> All</label>
                <label class="inline-flex items-center gap-1.5"><input type="radio" name="history_filter" value="D"> Debit</label>
                <label class="inline-flex items-center gap-1.5"><input type="radio" name="history_filter" value="C"> Kredit</label>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Pprd</th>
                        <th class="px-4 py-3">Trxcd</th>
                        <th class="px-4 py-3">Trx. No</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">CU ID</th>
                        <th class="px-4 py-3">Descr</th>
                        <th class="px-4 py-3 text-right">Debit</th>
                        <th class="px-4 py-3 text-right">Kredit</th>
                    </tr>
                </thead>
                <tbody id="history_body" class="divide-y divide-gray-100">
                    <tr><td colspan="8" class="px-4 py-8 text-center text-sm text-gray-400">Belum ada data.</td></tr>
                </tbody>
                <tfoot class="border-t border-gray-200 bg-gray-50 text-sm font-bold text-gray-800">
                    <tr>
                        <td colspan="6" class="px-4 py-3 text-right uppercase tracking-wide text-xs text-gray-500">Total</td>
                        <td class="px-4 py-3 text-right font-mono" id="total_debit">0</td>
                        <td class="px-4 py-3 text-right font-mono" id="total_credit">0</td>
                    </tr>
                    <tr>
                        <td colspan="7" class="px-4 py-3 text-right uppercase tracking-wide text-xs text-gray-500">Saldo (Debit - Kredit)</td>
                        <td class="px-4 py-3 text-right font-mono text-brand-primary" id="total_saldo">0</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('manualTrxForm');
    if (!form) return;

    const savingsStore = @json(route('cu.manual-savings.store'));
    const withdrawStore = @json(route('cu.manual-withdraw.store'));
    const historyUrl = @json(route('cu.manual-transactions.history'));

    const date = document.getElementById('trndt');
    const pprd = document.getElementById('pprd');
    const pprdDisplay = document.getElementById('pprd_display');
    const amount = document.getElementById('amount');
    const memberSelect = document.getElementById('member_rec_id');
    const memberName = document.getElementById('member_name');
    const cuIdDisplay = document.getElementById('cu_id_display');
    const amountLabel = document.getElementById('amount_label');
    const submitLabel = document.getElementById('submit_label');
    const directionLabel = document.getElementById('direction_label');
    const dbocrD = document.getElementById('dbocr_d');
    const dbocrC = document.getElementById('dbocr_c');
    const historyBody = document.getElementById('history_body');
    const historyCaption = document.getElementById('history_caption');
    const totalDebit = document.getElementById('total_debit');
    const totalCredit = document.getElementById('total_credit');
    const totalSaldo = document.getElementById('total_saldo');

    const money = value => 'Rp ' + Number(value || 0).toLocaleString('id-ID');
    const plain = value => Number(value || 0).toLocaleString('id-ID');
    const dateFmt = value => { if (!value) return '-'; const [y, m, d] = value.split('-'); return `${d}/${m}/${y}`; };
    const period = value => { const d = new Date(value + 'T00:00:00'); if (d.getDate() > 20) d.setMonth(d.getMonth() + 1); return d.getFullYear() + String(d.getMonth() + 1).padStart(2, '0'); };
    const type = () => form.querySelector('input[name="trx_type"]:checked').value;

    const updatePeriod = () => { if (!date.value) return; pprd.value = period(date.value); pprdDisplay.textContent = pprd.value; };
    const updateType = () => {
        const isWithdraw = type() === 'withdraw';
        form.action = isWithdraw ? withdrawStore : savingsStore;
        amountLabel.textContent = isWithdraw ? 'Nominal Penarikan' : 'Nominal Simpanan';
        submitLabel.textContent = isWithdraw ? 'Simpan Withdraw Manual' : 'Simpan Simpanan Manual';
        directionLabel.textContent = isWithdraw ? 'Kredit (mengurangi simpanan)' : 'Debit (menambah simpanan)';
        dbocrD.checked = !isWithdraw;
        dbocrC.checked = isWithdraw;
    };
    const updateMember = () => {
        const selected = memberSelect.selectedOptions[0];
        memberName.value = selected?.dataset.name || '';
        cuIdDisplay.value = selected?.dataset.icuno || '';
    };

    const renderHistory = (payload) => {
        const rows = payload.rows || [];
        if (!rows.length) {
            historyBody.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-sm text-gray-400">Belum ada mutasi.</td></tr>';
        } else {
            historyBody.innerHTML = rows.map(row => `
                <tr>
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">${row.pprd}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">${row.trncd}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-700">${row.trnno}</td>
                    <td class="px-4 py-3 text-xs text-gray-500">${dateFmt(row.trndt)}</td>
                    <td class="px-4 py-3 text-xs text-gray-500">${row.cu_id}</td>
                    <td class="px-4 py-3 text-gray-700">${row.descr}<span class="ml-1 block text-[10px] text-gray-400">${row.type_label}</span></td>
                    <td class="px-4 py-3 text-right font-mono ${row.debit ? 'text-green-600' : 'text-gray-300'}">${plain(row.debit)}</td>
                    <td class="px-4 py-3 text-right font-mono ${row.credit ? 'text-red-500' : 'text-gray-300'}">${plain(row.credit)}</td>
                </tr>`).join('');
        }
        totalDebit.textContent = plain(payload.totals?.debit || 0);
        totalCredit.textContent = plain(payload.totals?.credit || 0);
        totalSaldo.textContent = money(payload.totals?.saldo || 0);
    };

    const loadHistory = () => {
        const memberId = memberSelect.value;
        if (!memberId) {
            historyCaption.textContent = 'Pilih anggota untuk melihat mutasi.';
            historyBody.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-sm text-gray-400">Belum ada data.</td></tr>';
            totalDebit.textContent = totalCredit.textContent = totalSaldo.textContent = '0';
            return;
        }
        const direction = document.querySelector('input[name="history_filter"]:checked').value;
        const url = new URL(historyUrl, window.location.origin);
        url.searchParams.set('member_rec_id', memberId);
        url.searchParams.set('direction', direction);
        historyCaption.textContent = 'Memuat mutasi...';
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(payload => {
                historyCaption.textContent = 'Mutasi anggota terpilih.';
                renderHistory(payload);
            })
            .catch(() => { historyCaption.textContent = 'Gagal memuat mutasi.'; });
    };

    amount.addEventListener('input', () => { amount.value = amount.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); });
    date.addEventListener('change', updatePeriod);
    form.querySelectorAll('input[name="trx_type"]').forEach(el => el.addEventListener('change', updateType));
    document.querySelectorAll('input[name="history_filter"]').forEach(el => el.addEventListener('change', loadHistory));
    memberSelect.addEventListener('change', () => { updateMember(); loadHistory(); });

    if (window.jQuery && jQuery.fn.select2) {
        jQuery(memberSelect).select2({ placeholder: 'Cari nomor atau nama anggota...', allowClear: true, width: '100%' });
        jQuery(memberSelect).on('change', () => { updateMember(); loadHistory(); });
    }

    updatePeriod();
    updateType();
    updateMember();
    if (memberSelect.value) loadHistory();

    form.addEventListener('reset', () => {
        setTimeout(() => { updatePeriod(); updateType(); updateMember(); historyCaption.textContent = 'Pilih anggota untuk melihat mutasi.'; }, 0);
    });
    form.addEventListener('submit', () => { amount.value = amount.value.replace(/\D/g, ''); });
})();
</script>
@endpush
