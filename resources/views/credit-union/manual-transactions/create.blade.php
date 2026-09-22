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
    .total-bar { position: sticky; bottom: 0; z-index: 20; }
</style>

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Input Transaksi Manual</h1>
        <p class="mt-1 text-sm text-gray-500">Pencatatan transaksi data lampau: simpanan bulanan, simpanan sekali, penarikan, atau angsuran. Pilih jenis, isi data, lalu lihat mutasi anggota di bawah.</p>
    </div>
    @if (session('success')) <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('success') }}</div> @endif
    @if ($errors->any()) <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $errors->first() }}</div> @endif

    <form method="POST" action="{{ route('cu.manual-savings.store') }}" id="manualTrxForm" class="space-y-5">
        @csrf
        <input type="hidden" id="saving_type" name="saving_type" value="monthly">
        <div class="space-y-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label for="trx_type" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Jenis Transaksi <span class="text-red-500">*</span></label>
                    <select id="trx_type" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                        <option value="monthly_saving" selected>Monthly Saving (19)</option>
                        <option value="one_time_saving">One Time Saving (18)</option>
                        <option value="withdraw">Withdraw Money (22)</option>
                        <option value="loan_payment">Loan Payment (20)</option>
                    </select>
                </div>
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
                    <label for="cu_id_display" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">CU ID</label>
                    <input id="cu_id_display" readonly placeholder="-" class="w-full rounded-xl border border-gray-200 bg-gray-100 px-3 py-2.5 text-sm font-bold text-gray-700 outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label for="member_name" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nama Anggota <span class="text-red-500">*</span></label>
                    <input id="member_name" name="member_name" value="{{ old('member_name') }}" placeholder="contoh: BUDI SANTOSO" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                </div>
                <div>
                    <label for="amount" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500"><span id="amount_label">Nominal Simpanan Bulanan</span> <span class="text-red-500">*</span></label>
                    <input id="amount" name="amount" type="text" inputmode="numeric" autocomplete="off" value="{{ old('amount') }}" placeholder="contoh: 100.000" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                </div>
                <div>
                    <label for="trndt" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Tanggal Transaksi <span class="text-red-500">*</span></label>
                    <x-date-input name="trndt" id="trndt" value="{{ old('trndt', date('Y-m-d')) }}" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Periode (YYYYMM)</label>
                    <input type="hidden" id="pprd" name="pprd">
                    <div id="pprd_display" class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-bold text-gray-800">Otomatis dari tanggal transaksi</div>
                    <p class="mt-1 text-[11px] text-gray-400">Siklus tutup buku: tanggal 21 masuk periode bulan berikutnya.</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Arah Transaksi</label>
                    <div class="flex items-center gap-6 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                        <p class="text-sm text-gray-500" id="direction_label">Debit (menambah simpanan)</p>
                        <div class="ml-auto inline-flex items-center gap-4 text-xs font-bold uppercase tracking-wide text-gray-500">
                            <span>Debit <input type="radio" id="dbocr_d" disabled class="ml-1 align-middle"></span>
                            <span>Kredit <input type="radio" id="dbocr_c" disabled class="ml-1 align-middle"></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="submit" id="submit_btn" data-confirm="Simpan transaksi manual ini?" class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover"><i class="fas fa-save"></i> <span id="submit_label">Simpan Simpanan Bulanan</span></button>
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
        <div class="max-h-[60vh] overflow-auto">
            <table class="w-full text-left text-sm">
                <thead class="sticky top-0 z-10 bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-500">
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
            </table>
        </div>

        <div class="total-bar grid grid-cols-3 gap-4 border-t-2 border-brand-primary bg-gray-100 px-6 py-5 shadow-lg">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-widest text-gray-500">Debit</p>
                <p id="total_debit" class="mt-1 font-mono text-xl font-extrabold text-green-600">0</p>
            </div>
            <div class="border-l border-gray-300 pl-4">
                <p class="text-[11px] font-bold uppercase tracking-widest text-gray-500">Kredit</p>
                <p id="total_credit" class="mt-1 font-mono text-xl font-extrabold text-red-500">0</p>
            </div>
            <div class="border-l border-gray-300 pl-4">
                <p class="text-[11px] font-bold uppercase tracking-widest text-gray-500">Saldo (Debit - Kredit)</p>
                <p id="total_saldo" class="mt-1 font-mono text-2xl font-black text-gray-900">Rp 0</p>
            </div>
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
    const loanPaymentStore = @json(route('cu.manual-transactions.loan-payment'));
    const historyUrl = @json(route('cu.manual-transactions.history'));

    const date = document.getElementById('trndt_iso');
    const dateDisplay = document.getElementById('trndt');
    const pprd = document.getElementById('pprd');
    const pprdDisplay = document.getElementById('pprd_display');
    const amount = document.getElementById('amount');
    const memberSelect = document.getElementById('member_rec_id');
    const memberName = document.getElementById('member_name');
    const cuIdDisplay = document.getElementById('cu_id_display');
    const typeSelect = document.getElementById('trx_type');
    const savingType = document.getElementById('saving_type');
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

    const TYPES = {
        monthly_saving: { action: savingsStore, savingType: 'monthly', amountLabel: 'Nominal Simpanan Bulanan', submit: 'Simpan Simpanan Bulanan', direction: 'Debit (menambah simpanan)', debit: true },
        one_time_saving: { action: savingsStore, savingType: 'one_time', amountLabel: 'Nominal Simpanan Sekali', submit: 'Simpan Simpanan Sekali', direction: 'Debit (menambah simpanan)', debit: true },
        withdraw: { action: withdrawStore, savingType: '', amountLabel: 'Nominal Penarikan', submit: 'Simpan Withdraw Manual', direction: 'Kredit (mengurangi simpanan)', debit: false },
        loan_payment: { action: loanPaymentStore, savingType: '', amountLabel: 'Nominal Angsuran', submit: 'Simpan Angsuran Manual', direction: 'Debit (pembayaran angsuran)', debit: true },
    };

    const money = value => 'Rp ' + Number(value || 0).toLocaleString('id-ID');
    const plain = value => Number(value || 0).toLocaleString('id-ID');
    const dateFmt = value => { if (!value) return '-'; const [y, m, d] = value.split('-'); return `${d}/${m}/${y}`; };
    const period = value => { const d = new Date(value + 'T00:00:00'); if (d.getDate() > 20) d.setMonth(d.getMonth() + 1); return d.getFullYear() + String(d.getMonth() + 1).padStart(2, '0'); };

    const updatePeriod = () => { if (!date.value) return; pprd.value = period(date.value); pprdDisplay.textContent = pprd.value; };
    const updateType = () => {
        const config = TYPES[typeSelect.value] || TYPES.monthly_saving;
        form.action = config.action;
        savingType.value = config.savingType;
        amountLabel.textContent = config.amountLabel;
        submitLabel.textContent = config.submit;
        directionLabel.textContent = config.direction;
        dbocrD.checked = config.debit;
        dbocrC.checked = ! config.debit;
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
    dateDisplay.addEventListener('change', updatePeriod);
    typeSelect.addEventListener('change', updateType);
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
