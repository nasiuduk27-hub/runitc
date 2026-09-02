@extends('layouts.app')

@section('title', 'RUN-ITC | Pengajuan Pinjaman Baru')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('cooperative.applications.index') }}" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50" title="Kembali">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Pengajuan Pinjaman Baru</h1>
            <p class="mt-0.5 text-sm text-gray-500">Simulasikan terlebih dahulu, lalu ajukan. Jadwal hasil simulasi akan disimpan sebagai lampiran pengajuan.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('cooperative.applications.store') }}" id="applicationForm">
        @csrf
        <div class="space-y-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="member_rec_id" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Anggota</label>
                    @if ($isAdmin)
                        <select id="member_rec_id" name="member_rec_id" required
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                            <option value="">-- Pilih Anggota Aktif --</option>
                            @foreach ($memberOptions as $memberOption)
                                <option value="{{ $memberOption->rec_id }}" {{ old('member_rec_id') == $memberOption->rec_id ? 'selected' : '' }}>{{ $memberOption->icunm }} {{ $memberOption->icuno }}</option>
                            @endforeach
                        </select>
                    @elseif ($linkedMember !== null)
                        <input type="hidden" name="member_rec_id" value="{{ $linkedMember->rec_id }}">
                        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm">
                             <span class="font-medium text-gray-600">{{ $linkedMember->icunm }}</span>
                             <span class="font-mono font-semibold text-gray-700">{{ $linkedMember->icuno }}</span>
                        </div>
                        <p class="mt-1 text-[11px] text-gray-400">Pengajuan akan tercatat atas nama Anda.</p>
                    @else
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-800">
                            Data anggota Anda belum tersinkron ke sistem koperasi. Hubungi admin koperasi untuk menautkan akun Anda sebagai anggota.
                        </div>
                    @endif
                </div>
                <div>
                    <label for="principal_amount" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Jumlah Pinjaman (Rupiah)</label>
                    <input type="text" id="principal_amount" name="principal_amount" inputmode="numeric" autocomplete="off"
                           value="{{ old('principal_amount') }}"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" required>
                    <p class="mt-1 text-[11px] text-gray-400">Contoh: 1.000.000</p>
                </div>
                <div>
                    <label for="tenor_months" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Jangka Waktu (Bulan)</label>
                    <input type="number" id="tenor_months" name="tenor_months" min="1" max="120" step="1" value="{{ old('tenor_months') }}"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" required>
                </div>
                <div>
                    <label for="annual_rate_percent" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Bunga per Tahun (%)</label>
                    <input type="hidden" id="annual_rate_percent" name="annual_rate_percent" value="{{ $defaultRate }}">
                    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm">
                        <span class="font-bold text-gray-800">{{ number_format($defaultRate, 2, ',', '.') }}%</span>
                        <i class="fas fa-lock text-xs text-gray-400" title="Mengikuti pengaturan admin"></i>
                    </div>
                </div>
                <div>
                    <label for="calculation_method" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Metode Perhitungan</label>
                    <input type="hidden" id="calculation_method" name="calculation_method" value="{{ $defaultMethod }}">
                    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm">
                        <span class="font-bold uppercase text-gray-800">{{ $methods[$defaultMethod] ?? $defaultMethod }}</span>
                        <i class="fas fa-lock text-xs text-gray-400" title="Mengikuti pengaturan admin"></i>
                    </div>
                </div>
                <div class="sm:col-span-2">
                    <label for="descr" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Keperluan Pinjaman</label>
                    <input type="text" id="descr" name="descr" maxlength="100" value="{{ old('descr') }}" placeholder="contoh: Renovasi rumah"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" required>
                </div>
                <div>
                    <label for="fund_release_method" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Penerimaan Dana</label>
                    <input type="hidden" id="fund_release_method" name="fund_release_method" value="transfer">
                    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm">
                        <span class="font-bold text-gray-800">Transfer Bank</span>
                        <i class="fas fa-lock text-xs text-gray-400" title="Metode pencairan ditetapkan koperasi"></i>
                    </div>
                </div>
                <div>
                    <label for="admin_fee_type" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Tipe Biaya Admin</label>
                    <select id="admin_fee_type" name="admin_fee_type"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" required>
                        <option value="include" {{ old('admin_fee_type', 'include') === 'include' ? 'selected' : '' }}>Include, dipotong dari pencairan</option>
                        <option value="exclude" {{ old('admin_fee_type', 'include') === 'exclude' ? 'selected' : '' }}>Exclude, ditagih ke anggota</option>
                    </select>
                    <p id="adminFeeHelp" class="mt-1 text-[11px] text-gray-400">Dipotong dari pencairan (pokok + bunga tidak berubah).</p>
                </div>
                <div>
                    <label for="admin_fee_display" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Biaya Admin (Rp)</label>
                    <input type="hidden" id="admin_fee" name="admin_fee" value="{{ $defaultAdminFee }}">
                    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm">
                        <span id="admin_fee_display" class="font-bold text-gray-800">Rp {{ number_format($defaultAdminFee, 0, ',', '.') }}</span>
                        <i class="fas fa-lock text-xs text-gray-400" title="Mengikuti pengaturan koperasi"></i>
                    </div>
                    <p class="mt-1 text-[11px] text-gray-400">Mengikuti nominal default pada Pengaturan Koperasi.</p>
                </div>

                <div id="bankPanel" class="hidden sm:col-span-2 rounded-2xl border border-gray-200 bg-gray-50/60 p-4">
                    <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-500">Bank Pencairan</p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="bank_code" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Bank</label>
                            <select id="bank_code" name="bank_code"
                                    class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                                <option value="">-- Pilih Bank --</option>
                                @foreach ($bankOptions as $code => $label)
                                    <option value="{{ $code }}" @selected(old('bank_code', $defaultBank->bnkcd ?? '') === (string) $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="account_name" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nama Rekening</label>
                            <input type="text" id="account_name" name="account_name" maxlength="150" value="{{ old('account_name', $defaultBank->accnm ?? '') }}"
                                   class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="account_no" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nomor Rekening</label>
                            <input type="text" id="account_no" name="account_no" maxlength="80" value="{{ old('account_no', $defaultBank->accno ?? '') }}"
                                   class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                        </div>
                    </div>
                    @if ($defaultBank)
                        <p class="mt-3 text-xs text-gray-500">Bank default Anda: <span class="font-semibold">{{ $bankOptions[$defaultBank->bnkcd] ?? $defaultBank->bnkcd }}</span> — {{ $defaultBank->accnm }} ({{ $defaultBank->accno }}). Ubah di atas untuk pakai bank lain.</p>
                    @else
                        <p class="mt-3 text-xs text-amber-700">Anda belum memiliki data bank. Lengkapi di atas agar dana dapat ditransfer.</p>
                    @endif
                </div>
            </div>

            <div id="simulationPreview" class="hidden space-y-3 rounded-xl border border-blue-200 bg-blue-50/50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Pratinjau Simulasi</p>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
                    <div><p class="text-[10px] font-bold uppercase text-blue-500">Cicilan Bulan I</p><p id="prevFirst" class="text-sm font-extrabold text-gray-900">-</p></div>
                    <div><p class="text-[10px] font-bold uppercase text-blue-500">Total Bunga</p><p id="prevInterest" class="text-sm font-extrabold text-gray-900">-</p></div>
                    <div><p class="text-[10px] font-bold uppercase text-blue-500">Total Pembayaran</p><p id="prevTotal" class="text-sm font-extrabold text-gray-900">-</p></div>
                    <div><p class="text-[10px] font-bold uppercase text-blue-500">Jumlah Cicilan</p><p id="prevCount" class="text-sm font-extrabold text-gray-900">-</p></div>
                    <div class="sm:col-span-1"><p class="text-[10px] font-bold uppercase text-blue-500">Biaya Admin</p><p id="prevAdminFee" class="text-sm font-extrabold text-gray-900">-</p></div>
                    <div><p class="text-[10px] font-bold uppercase text-blue-500">Dana Diterima</p><p id="prevReceived" class="text-sm font-extrabold text-blue-700">-</p></div>
                </div>
                <div class="max-h-40 overflow-y-auto rounded-lg border border-blue-100 bg-white">
                    <table class="w-full text-left text-xs">
                        <thead class="sticky top-0 bg-gray-50 text-[10px] uppercase text-gray-500">
                            <tr><th class="px-3 py-2">#</th><th class="px-3 py-2">Periode</th><th class="px-3 py-2 text-right">Pokok</th><th class="px-3 py-2 text-right">Bunga</th><th class="px-3 py-2 text-right">Total</th></tr>
                        </thead>
                        <tbody id="prevSchedule" class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
                <p id="previewError" class="hidden text-xs font-semibold text-red-600"></p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" id="simulateButton"
                        class="inline-flex items-center gap-2 rounded-xl border border-brand-primary bg-white px-5 py-2.5 text-sm font-semibold text-brand-primary transition hover:bg-blue-50">
                    <i class="fas fa-calculator"></i> Simulasikan
                </button>
                <button type="submit" id="submitButton" disabled
                        class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover disabled:cursor-not-allowed disabled:opacity-50">
                    <i class="fas fa-paper-plane"></i> Ajukan
                </button>
            </div>
            @if (! $isAdmin && $linkedMember === null)
                <p class="text-xs font-semibold text-amber-700">Tidak dapat mengajukan karena akun Anda belum ditautkan ke data anggota. Hubungi admin koperasi.</p>
            @else
                <p class="text-xs text-gray-400">Tombol Ajukan aktif setelah simulasi dijalankan agar jadwal yang diajukan dan yang disetujui selalu identik.</p>
            @endif
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('applicationForm');
    const simulateButton = document.getElementById('simulateButton');
    const submitButton = document.getElementById('submitButton');
    const preview = document.getElementById('simulationPreview');
    const previewError = document.getElementById('previewError');
    const formatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    function payload() { return new FormData(form); }

    function formatRupiah(value) { return formatter.format(Number(value || 0)); }

    const principalInput = document.getElementById('principal_amount');

    function formatPrincipal(value) {
        const digits = String(value).replace(/\D/g, '').slice(0, 11);
        return digits === '' ? '' : Number(digits).toLocaleString('id-ID');
    }

    function normalizePrincipal() {
        principalInput.value = String(principalInput.value).replace(/\D/g, '');
    }

    const methodSelect = document.getElementById('fund_release_method');
    const bankPanel = document.getElementById('bankPanel');
    const adminFeeType = document.getElementById('admin_fee_type');
    const adminFeeHelp = document.getElementById('adminFeeHelp');

    function toggleBankPanel() {
        if (methodSelect.value === 'transfer') {
            bankPanel.classList.remove('hidden');
        } else {
            bankPanel.classList.add('hidden');
        }
    }

    methodSelect.addEventListener('change', toggleBankPanel);
    toggleBankPanel();

    function updateAdminFeeHelp() {
        adminFeeHelp.textContent = adminFeeType.value === 'include'
            ? 'Dipotong dari pencairan (pokok + bunga tidak berubah).'
            : 'Ditagih ke anggota dan ditambahkan ke cicilan pertama.';
    }

    adminFeeType.addEventListener('change', updateAdminFeeHelp);
    updateAdminFeeHelp();

    principalInput.addEventListener('input', function () {
        principalInput.value = formatPrincipal(principalInput.value);
    });

    form.addEventListener('submit', function () {
        normalizePrincipal();
    });

    simulateButton.addEventListener('click', async function () {
        normalizePrincipal();
        preview.classList.remove('hidden');
        previewError.classList.add('hidden');
        simulateButton.disabled = true;
        simulateButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menghitung...';

        try {
            const response = await fetch(@json(route('cooperative.applications.recalculate')), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: payload(),
            });
            const data = await response.json();

            if (!response.ok) {
                previewError.textContent = data.message || Object.values(data.errors || {}).flat().join(' ') || 'Gagal menghitung.';
                previewError.classList.remove('hidden');
                return;
            }

            document.getElementById('prevFirst').textContent = 'Rp ' + formatRupiah(data.summary.first_installment);
            document.getElementById('prevInterest').textContent = 'Rp ' + formatRupiah(data.summary.total_interest);
            document.getElementById('prevTotal').textContent = 'Rp ' + formatRupiah(data.summary.total_payment);
            document.getElementById('prevCount').textContent = data.schedule.length + ' bulan';

            const adminFee = Number(String(document.getElementById('admin_fee').value).replace(/\D/g, '') || 0);
            const principal = Number(data.summary.requested_principal || data.summary.principal || 0);
            document.getElementById('prevAdminFee').textContent = 'Rp ' + formatRupiah(adminFee);
            document.getElementById('prevReceived').textContent = 'Rp ' + formatRupiah(data.summary.net_disbursement ?? (adminFeeType.value === 'include' ? principal + adminFee : Math.max(0, principal - adminFee)));

            document.getElementById('prevSchedule').innerHTML = data.schedule.map(function (row) {
                const raw = String(row.periode);
                const periode = raw.length === 6 ? months[Number(raw.slice(4, 6)) - 1] + ' ' + raw.slice(0, 4) : raw;
                return '<tr>' +
                    '<td class="px-3 py-1.5 text-gray-500">' + row.seqno + '</td>' +
                    '<td class="px-3 py-1.5 text-gray-600">' + periode + '</td>' +
                    '<td class="px-3 py-1.5 text-right text-gray-600">' + formatRupiah(row.amount) + '</td>' +
                    '<td class="px-3 py-1.5 text-right text-gray-600">' + formatRupiah(row.int_amt) + '</td>' +
                    '<td class="px-3 py-1.5 text-right font-semibold text-gray-800">' + formatRupiah(row.total) + '</td>' +
                '</tr>';
            }).join('');

            submitButton.disabled = false;
        } catch (error) {
            previewError.textContent = 'Terjadi kesalahan jaringan.';
            previewError.classList.remove('hidden');
        } finally {
            simulateButton.disabled = false;
            simulateButton.innerHTML = '<i class="fas fa-calculator"></i> Simulasikan';
        }
    });
})();
</script>
@endpush
