@extends('layouts.app')

@section('title', 'RUN-ITC | Simulasi Kredit Koperasi')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="print:hidden">
        <h1 class="text-2xl font-bold text-gray-900">Simulasi Kredit</h1>
        <p class="mt-0.5 text-sm text-gray-500">Estimasi angsuran pinjaman koperasi dengan metode Flat, Efektif, atau Anuitas.</p>
    </div>

    <div class="hidden print:block">
        <h1 class="text-xl font-bold text-gray-900">Simulasi Kredit Koperasi</h1>
        <p class="mt-1 text-xs text-gray-500" id="printMeta"></p>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm print:hidden">
        <form id="simulationForm" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" novalidate>
            <div>
                <label for="principal" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Jumlah Kredit (Rupiah)</label>
                <input type="text" id="principal" name="principal" inputmode="numeric" autocomplete="off"
                       class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20"
                       placeholder="contoh: 1.000.000" required>
            </div>
            <div>
                <label for="tenor" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Jangka Waktu (Bulan)</label>
                <input type="number" id="tenor" name="tenor" min="{{ $minTenor }}" max="{{ $maxTenor }}" step="1"
                       class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20"
                       placeholder="contoh: 18" required>
            </div>
            <div>
                <label for="rate" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Bunga per Tahun (%)</label>
                <input type="number" id="rate" name="rate" min="0" max="100" step="0.01" value="{{ $defaultAnnualRate }}"
                       class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" required>
            </div>
            <div>
                <label for="method" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Jenis Kredit</label>
                <select id="method" name="method"
                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                    @foreach ($methods as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4">
                <button type="submit" id="calculateButton"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover disabled:cursor-not-allowed disabled:opacity-60">
                    <i class="fas fa-calculator"></i> Hitung
                </button>
                <button type="reset"
                        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                    Reset
                </button>
            </div>
        </form>

        <div id="formError" class="mt-4 hidden rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700"></div>
    </div>

    <div id="simulationResult" class="hidden space-y-6">
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-medium text-amber-800 print:border-amber-300">
            <i class="fas fa-circle-info mr-1"></i> Hasil simulasi bersifat estimasi dan bukan merupakan persetujuan pinjaman.
        </div>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Cicilan Pertama</p>
                <p class="mt-1 text-lg font-extrabold text-gray-900"><span id="sumFirstInstallment">-</span></p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Cicilan Terakhir</p>
                <p class="mt-1 text-lg font-extrabold text-gray-900"><span id="sumLastInstallment">-</span></p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Total Bunga</p>
                <p class="mt-1 text-lg font-extrabold text-gray-900"><span id="sumTotalInterest">-</span></p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Total Pembayaran</p>
                <p class="mt-1 text-lg font-extrabold text-brand-primary"><span id="sumTotalPayment">-</span></p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 print:hidden">
            <a id="exportLink" href="#"
               class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                <i class="fas fa-file-excel text-green-600"></i> Export Excel
            </a>
            <button type="button" onclick="window.print()"
                    class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                <i class="fas fa-print"></i> Cetak / PDF
            </button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <p class="text-sm font-bold text-gray-800">Tabel Angsuran</p>
                <p class="mt-0.5 text-xs text-gray-400" id="scheduleMeta"></p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-3 font-bold">#</th>
                            <th class="px-5 py-3 font-bold">Periode</th>
                            <th class="px-5 py-3 text-right font-bold">Pokok (Rp)</th>
                            <th class="px-5 py-3 text-right font-bold">Bunga (Rp)</th>
                            <th class="px-5 py-3 text-right font-bold">Total Cicilan (Rp)</th>
                            <th class="px-5 py-3 text-right font-bold">Sisa Pokok (Rp)</th>
                            <th class="px-5 py-3 font-bold">Ket.</th>
                        </tr>
                    </thead>
                    <tbody id="scheduleBody" class="divide-y divide-gray-100"></tbody>
                    <tfoot>
                        <tr class="bg-gray-50 font-bold text-gray-800">
                            <td colspan="2" class="px-5 py-3">Total</td>
                            <td class="px-5 py-3 text-right" id="footPrincipal">-</td>
                            <td class="px-5 py-3 text-right" id="footInterest">-</td>
                            <td class="px-5 py-3 text-right" id="footTotal">-</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

<style>
    @media print {
        body { display: block !important; height: auto !important; overflow: visible !important; }
        body > header, aside#sidebar, #sidebarOverlay, #global-loader { display: none !important; }
        main#main-content-area { overflow: visible !important; padding: 0 !important; }
    }
</style>

@push('scripts')
<script>
(function () {
    const form = document.getElementById('simulationForm');
    const resultCard = document.getElementById('simulationResult');
    const errorBox = document.getElementById('formError');
    const submitButton = document.getElementById('calculateButton');
    const exportLink = document.getElementById('exportLink');
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const formatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
    const principalInput = document.getElementById('principal');

    function formatRupiah(value) { return formatter.format(Number(value || 0)); }

    function digitsOf(value) { return String(value).replace(/\D/g, ''); }

    function formatPrincipal() {
        const digits = digitsOf(principalInput.value).replace(/^0+(?=\d)/, '');
        const selectionAtEnd = principalInput.selectionStart === principalInput.value.length;
        principalInput.value = digits ? formatter.format(Number(digits)) : '';
        if (selectionAtEnd) {
            principalInput.setSelectionRange(principalInput.value.length, principalInput.value.length);
        }
    }

    principalInput.addEventListener('input', formatPrincipal);
    principalInput.addEventListener('focus', function () {
        if (!principalInput.value) return;
        const end = principalInput.value.length;
        principalInput.setSelectionRange(end, end);
    });

    function buildFormData() {
        const data = new FormData(form);
        data.set('principal', digitsOf(principalInput.value));
        return data;
    }

    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.remove('hidden');
    }

    function hideError() { errorBox.classList.add('hidden'); }

    function setLoading(loading) {
        submitButton.disabled = loading;
        submitButton.innerHTML = loading
            ? '<i class="fas fa-spinner fa-spin"></i> Menghitung...'
            : '<i class="fas fa-calculator"></i> Hitung';
    }

    function formatPeriode(value) {
        const raw = String(value);
        if (raw.length !== 6) return raw;
        return months[Number(raw.slice(4, 6)) - 1] + ' ' + raw.slice(0, 4);
    }

    function renderResult(data) {
        document.getElementById('sumFirstInstallment').textContent = formatRupiah(data.summary.first_installment);
        document.getElementById('sumLastInstallment').textContent = formatRupiah(data.summary.last_installment);
        document.getElementById('sumTotalInterest').textContent = formatRupiah(data.summary.total_interest);
        document.getElementById('sumTotalPayment').textContent = formatRupiah(data.summary.total_payment);

        document.getElementById('scheduleMeta').textContent =
            data.summary.method_label + ' | ' +
            'Rp ' + formatRupiah(data.summary.principal) + ' | ' +
            data.summary.tenor_months + ' bulan | ' +
            data.summary.annual_rate + '% per tahun';

        let totalPrincipal = 0;
        let totalInterest = 0;
        let rowsHtml = '';

        data.schedule.forEach(function (row) {
            totalPrincipal += Number(row.amount);
            totalInterest += Number(row.int_amt);
            rowsHtml += '<tr class="hover:bg-gray-50">' +
                '<td class="px-5 py-2.5 text-gray-500">' + row.seqno + '</td>' +
                '<td class="px-5 py-2.5 font-medium text-gray-700">' + formatPeriode(row.periode) + '</td>' +
                '<td class="px-5 py-2.5 text-right text-gray-700">' + formatRupiah(row.amount) + '</td>' +
                '<td class="px-5 py-2.5 text-right text-gray-700">' + formatRupiah(row.int_amt) + '</td>' +
                '<td class="px-5 py-2.5 text-right font-semibold text-gray-800">' + formatRupiah(row.total) + '</td>' +
                '<td class="px-5 py-2.5 text-right text-gray-700">' + formatRupiah(row.outstand) + '</td>' +
                '<td class="px-5 py-2.5">' + (row.rounding ? '<span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">Rounding</span>' : '') + '</td>' +
            '</tr>';
        });

        document.getElementById('scheduleBody').innerHTML = rowsHtml;
        document.getElementById('footPrincipal').textContent = formatRupiah(totalPrincipal);
        document.getElementById('footInterest').textContent = formatRupiah(totalInterest);
        document.getElementById('footTotal').textContent = formatRupiah(totalPrincipal + totalInterest);

        const params = new URLSearchParams(buildFormData());
        exportLink.href = @json(route('cooperative.loan-simulation.export')) + '?' + params.toString();

        document.getElementById('printMeta').textContent =
            'Metode: ' + data.summary.method_label + ' | Jumlah: Rp ' + formatRupiah(data.summary.principal) +
            ' | Tenor: ' + data.summary.tenor_months + ' bulan | Bunga: ' + data.summary.annual_rate + '% per tahun' +
            ' | Dicetak: ' + new Date().toLocaleString('id-ID');

        resultCard.classList.remove('hidden');
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        hideError();
        setLoading(true);

        try {
            const response = await fetch(@json(route('cooperative.loan-simulation.calculate')), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: buildFormData(),
            });
            const data = await response.json();

            if (!response.ok) {
                const message = data && data.message
                    ? data.message
                    : (data.errors ? Object.values(data.errors).flat().join(' ') : 'Gagal menghitung simulasi.');
                showError(message);
                return;
            }

            renderResult(data);
        } catch (error) {
            showError('Terjadi kesalahan jaringan. Silakan coba lagi.');
        } finally {
            setLoading(false);
        }
    });
})();
</script>
@endpush
