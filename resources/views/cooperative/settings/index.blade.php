@extends('layouts.app')

@section('title', 'RUN-ITC | Pengaturan Koperasi')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('cooperative.dashboard') }}" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50" title="Kembali">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Pengaturan Koperasi</h1>
            <p class="mt-0.5 text-sm text-gray-500">Atur default bunga, metode perhitungan, dan biaya admin pengajuan baru.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('cooperative.settings.update') }}" class="space-y-6">
        @csrf
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="mb-4 text-xs font-bold uppercase tracking-wide text-gray-400">Default Pengajuan Pinjaman</p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="default_rate" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Bunga per Tahun (%)</label>
                    <input type="number" id="default_rate" name="default_rate" min="0" max="100" step="0.01" value="{{ old('default_rate', $defaultRate) }}"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" required>
                </div>
                <div>
                    <label for="default_admin_fee" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Biaya Admin Default (Rp)</label>
                    <input type="text" id="default_admin_fee_display" inputmode="numeric" value="{{ number_format((int) old('default_admin_fee', $defaultAdminFee), 0, ',', '.') }}" data-rupiah-input="default_admin_fee"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" required>
                    <input type="hidden" id="default_admin_fee" name="default_admin_fee" value="{{ (int) old('default_admin_fee', $defaultAdminFee) }}">
                </div>
                <div>
                    <label for="default_method" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Metode Perhitungan</label>
                    <select id="default_method" name="default_method"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" required>
                        @foreach ($methods as $value => $label)
                            <option value="{{ $value }}" {{ old('default_method', $defaultMethod) === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="mt-4 text-xs text-gray-400">Bunga, metode, dan nominal biaya admin default dikunci di form pengajuan. Tipe biaya admin dipilih oleh pengaju.</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="mb-4 text-xs font-bold uppercase tracking-wide text-gray-400">Pengaturan Simpanan</p>
            <div>
                <label for="minimum_savings_balance" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Saldo Minimum Mengendap (Rp)</label>
                <input type="text" id="minimum_savings_balance_display" inputmode="numeric" value="{{ number_format((int) old('minimum_savings_balance', $minimumSavingsBalance), 0, ',', '.') }}" data-rupiah-input="minimum_savings_balance"
                       class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20" required>
                <input type="hidden" id="minimum_savings_balance" name="minimum_savings_balance" value="{{ (int) old('minimum_savings_balance', $minimumSavingsBalance) }}">
                <p class="mt-2 text-xs text-gray-400">Nominal ini wajib tetap mengendap di saldo simpanan anggota dan tidak dapat ditarik.</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">
                <i class="fas fa-save"></i> Simpan Pengaturan
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const inputs = Array.from(document.querySelectorAll('[data-rupiah-input]'));

    function digitsOnly(value) {
        return String(value || '').replace(/\D/g, '');
    }

    function formatRupiah(value) {
        const digits = digitsOnly(value);
        return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    inputs.forEach(function (input) {
        const hidden = document.getElementById(input.dataset.rupiahInput || '');
        if (!hidden) {
            return;
        }

        function sync() {
            const digits = digitsOnly(input.value);
            input.value = formatRupiah(digits);
            hidden.value = digits;
        }

        input.addEventListener('input', sync);
        input.form?.addEventListener('submit', sync);
        sync();
    });
})();
</script>
@endpush
