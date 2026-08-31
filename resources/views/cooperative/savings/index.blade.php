@extends('layouts.app')

@section('title', 'RUN-ITC | Simpanan')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $errors->first() }}</div>
    @endif

    @if ($isAdmin && ! $showPersonalView)
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.22em] text-brand-primary">Approval Center</p>
                <h1 class="mt-1 text-2xl font-extrabold text-gray-900">Withdraw Simpanan</h1>
                <p class="mt-0.5 text-sm text-gray-500">Review pengajuan penarikan simpanan anggota sebelum diposting sebagai transaksi kredit.</p>
            </div>
            @if ($member !== null)
                <a href="{{ route('cooperative.savings.index', ['view' => 'mine']) }}" class="inline-flex items-center gap-2 self-start rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover sm:self-auto">
                    <i class="fas fa-wallet"></i> Lihat Tabungan Saya
                </a>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-amber-700">Menunggu Approval</p>
                <p class="mt-1 text-2xl font-extrabold text-amber-800">{{ number_format($withdrawalStats['pending_count'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Total Pending (Rp)</p>
                <p class="mt-1 text-xl font-extrabold text-gray-900">{{ number_format($withdrawalStats['pending_amount'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-2xl border border-green-200 bg-green-50 p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-green-700">Approved Bulan Ini</p>
                <p class="mt-1 text-xl font-extrabold text-green-700">{{ number_format($withdrawalStats['approved_month_amount'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-red-600">Ditolak Bulan Ini</p>
                <p class="mt-1 text-2xl font-extrabold text-red-600">{{ number_format($withdrawalStats['rejected_month_count'], 0, ',', '.') }}</p>
            </div>
        </div>

        <div class="space-y-3">
            @forelse ($withdrawals as $withdrawal)
                <div class="rounded-2xl border {{ $withdrawal->status === \App\Models\Cooperative\CooperativeSavingsWithdrawal::STATUS_SUBMITTED ? 'border-amber-200 bg-white' : 'border-gray-200 bg-white' }} p-5 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-lg font-extrabold text-gray-900">{{ $withdrawal->member_name }}</p>
                                <span class="font-mono text-xs font-bold text-brand-primary">{{ $withdrawal->member_icuno }}</span>
                                <span class="inline-block rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $withdrawal->statusBadgeClass() }}">{{ $withdrawal->statusLabel() }}</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">Rekening tujuan: <span class="font-semibold text-gray-700">{{ $withdrawal->bank_account ?: '-' }}</span></p>
                            <p class="mt-1 text-xs text-gray-400">Diajukan {{ $withdrawal->created_at?->format('d M Y H:i') }} {{ $withdrawal->withdrawal_trnno ? '| '.$withdrawal->withdrawal_trnno : '' }}</p>
                        </div>

                        <div class="grid grid-cols-3 gap-3 text-right lg:w-[28rem]">
                            <div class="rounded-xl bg-gray-50 p-3">
                                <p class="text-[10px] font-bold uppercase text-gray-400">Saldo</p>
                                <p class="mt-1 text-sm font-bold text-gray-900">{{ number_format((int) $withdrawal->current_balance, 0, ',', '.') }}</p>
                            </div>
                            <div class="rounded-xl bg-red-50 p-3">
                                <p class="text-[10px] font-bold uppercase text-red-500">Withdraw</p>
                                <p class="mt-1 text-sm font-bold text-red-600">{{ number_format($withdrawal->amount, 0, ',', '.') }}</p>
                            </div>
                            <div class="rounded-xl bg-green-50 p-3">
                                <p class="text-[10px] font-bold uppercase text-green-600">Sisa</p>
                                <p class="mt-1 text-sm font-bold text-green-700">{{ number_format((int) $withdrawal->after_withdrawal_balance, 0, ',', '.') }}</p>
                            </div>
                        </div>
                    </div>

                    @if ($withdrawal->status === \App\Models\Cooperative\CooperativeSavingsWithdrawal::STATUS_SUBMITTED)
                        <div class="mt-4 flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-4">
                            @if ((int) $withdrawal->maker_user_id !== (int) session('user_id'))
                                <form method="POST" action="{{ route('cooperative.savings.withdraw.decide') }}">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ $withdrawal->id }}">
                                    <input type="hidden" name="decision" value="approve">
                                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700">
                                        <i class="fas fa-check"></i> Setujui
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('cooperative.savings.withdraw.decide') }}">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ $withdrawal->id }}">
                                    <input type="hidden" name="decision" value="reject">
                                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-5 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-100">
                                        <i class="fas fa-xmark"></i> Tolak
                                    </button>
                                </form>
                            @else
                                <span class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs font-semibold text-amber-700">Pengaju tidak dapat approve sendiri.</span>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl border border-gray-200 bg-white p-10 text-center text-sm text-gray-400 shadow-sm">Belum ada pengajuan withdraw simpanan.</div>
            @endforelse
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white px-5 py-3 shadow-sm">
            {{ $withdrawals->links() }}
        </div>
    @else
        @if ($isAdmin)
            <div class="flex justify-end">
                <a href="{{ route('cooperative.savings.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 shadow-sm transition hover:bg-gray-50">
                    <i class="fas fa-arrow-left"></i> Kembali ke Approval
                </a>
            </div>
        @endif

        <div class="rounded-[2rem] bg-gradient-to-br from-brand-primary via-blue-700 to-slate-900 p-6 text-white shadow-xl shadow-brand-primary/20">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-white/70">Simpanan Saya</p>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <h1 class="text-3xl font-extrabold" data-private-amount>Rp {{ number_format($totals['balance'], 0, ',', '.') }}</h1>
                        <button type="button" id="togglePrivateAmounts" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20" aria-label="Sembunyikan nominal simpanan" aria-pressed="false">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <p class="mt-2 text-sm text-white/70">Saldo dari transaksi simpanan bulanan koperasi.</p>
                </div>
                @if ($member !== null)
                    <div class="rounded-2xl bg-white/10 p-4 text-sm backdrop-blur">
                        <p class="font-mono font-bold">{{ $member->icuno }}</p>
                        <p class="mt-1 font-semibold">{{ $member->icunm }}</p>
                        <p class="mt-1 text-xs text-white/70">Saldo tersedia: <span data-private-amount>Rp {{ number_format($availableBalance, 0, ',', '.') }}</span></p>
                    </div>
                @endif
            </div>
        </div>

        @if ($member === null)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Data anggota Anda belum tersinkron ke sistem koperasi. Hubungi admin koperasi untuk menautkan akun Anda sebagai anggota.
            </div>
        @else
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Setoran (Rp)</p>
                    <p class="mt-1 text-lg font-extrabold text-green-600" data-private-amount>{{ number_format($totals['debit'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Penarikan (Rp)</p>
                    <p class="mt-1 text-lg font-extrabold text-red-500" data-private-amount>{{ number_format($totals['credit'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Wajib Bulanan (Rp)</p>
                    <p class="mt-1 text-lg font-extrabold text-gray-900" data-private-amount>{{ number_format($member->swajib, 0, ',', '.') }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Saldo Tersedia (Rp)</p>
                    <p class="mt-1 text-lg font-extrabold text-brand-primary" data-private-amount>{{ number_format($availableBalance, 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <form method="POST" action="{{ route('cooperative.savings.update') }}" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    @csrf
                    <p class="mb-1 text-sm font-bold text-gray-800">Atur Simpanan Wajib</p>
                    <p class="mb-4 text-xs text-gray-400">Nominal baru berlaku untuk simpanan yang belum diposting admin.</p>
                    <label for="swajib" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nominal Bulanan</label>
                    <div class="flex items-center overflow-hidden rounded-xl border border-gray-200 bg-gray-50 focus-within:border-brand-primary focus-within:bg-white focus-within:ring-2 focus-within:ring-brand-primary/20">
                        <span class="px-3 text-sm font-bold text-gray-500">Rp</span>
                        <input type="text" id="swajib_display" inputmode="numeric" value="{{ number_format((int) old('swajib', $member->swajib), 0, ',', '.') }}" data-rupiah-input="swajib" class="w-full border-0 bg-transparent px-0 py-2.5 text-sm font-bold text-gray-900 outline-none" required>
                        <input type="hidden" id="swajib" name="swajib" value="{{ (int) old('swajib', $member->swajib) }}">
                    </div>
                    <button type="submit" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">
                        <i class="fas fa-save"></i> Simpan Nominal
                    </button>
                </form>

                <form method="POST" action="{{ route('cooperative.savings.withdraw') }}" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    @csrf
                    <p class="mb-1 text-sm font-bold text-gray-800">Withdraw Simpanan</p>
                    <p class="mb-4 text-xs text-gray-400">Pengajuan menunggu persetujuan admin koperasi.</p>
                    <div class="space-y-4">
                        <div>
                            <label for="amount" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nominal Withdraw</label>
                            <div class="flex items-center overflow-hidden rounded-xl border border-gray-200 bg-gray-50 focus-within:border-brand-primary focus-within:bg-white focus-within:ring-2 focus-within:ring-brand-primary/20">
                                <span class="px-3 text-sm font-bold text-gray-500">Rp</span>
                                <input type="text" id="amount_display" inputmode="numeric" value="{{ old('amount') !== null ? number_format((int) old('amount'), 0, ',', '.') : '' }}" data-rupiah-input="amount" class="w-full border-0 bg-transparent px-0 py-2.5 text-sm font-bold text-gray-900 outline-none" required>
                                <input type="hidden" id="amount" name="amount" value="{{ old('amount') !== null ? (int) old('amount') : '' }}">
                            </div>
                            <p class="mt-1 text-xs text-gray-400">Maksimal Rp {{ number_format($availableBalance, 0, ',', '.') }}</p>
                        </div>
                        <div>
                            <label for="bank_account" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Rekening Tujuan</label>
                            <input type="text" id="bank_account" name="bank_account" value="{{ old('bank_account') }}" maxlength="120" placeholder="Bank, nomor rekening, nama pemilik" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-primary focus:bg-white focus:ring-2 focus:ring-brand-primary/20">
                        </div>
                    </div>
                    <button type="submit" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white shadow-md transition hover:bg-slate-800">
                        <i class="fas fa-money-bill-transfer"></i> Ajukan Withdraw
                    </button>
                </form>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <p class="text-sm font-bold text-gray-800">Riwayat Simpanan</p>
                        <p class="mt-0.5 text-xs text-gray-400">Hanya transaksi dengan kode simpanan bulanan.</p>
                    </div>
                    <div class="divide-y divide-gray-100 px-5">
                        @forelse ($transactions as $trx)
                            <div class="flex items-center justify-between gap-3 py-3 text-sm">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-gray-800">{{ $trx->descr ?: 'Simpanan Bulanan' }}</p>
                                    <p class="font-mono text-[11px] text-gray-400">{{ $trx->trnno }} | {{ $trx->pprd ?: '-' }} | {{ $trx->trndt ? \Carbon\Carbon::parse($trx->trndt)->format('d M Y') : '-' }}</p>
                                </div>
                                <p class="shrink-0 text-sm font-bold {{ $trx->dbocr === 'D' ? 'text-green-600' : 'text-red-500' }}" data-private-amount>{{ $trx->dbocr === 'D' ? '+' : '-' }}{{ number_format($trx->amount, 0, ',', '.') }}</p>
                            </div>
                        @empty
                            <p class="py-8 text-center text-sm text-gray-400">Belum ada transaksi simpanan bulanan.</p>
                        @endforelse
                    </div>
                    <div class="border-t border-gray-100 px-5 py-3">{{ $transactions->links() }}</div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <p class="text-sm font-bold text-gray-800">Pengajuan Withdraw</p>
                        <p class="mt-0.5 text-xs text-gray-400">Status penarikan simpanan Anda.</p>
                    </div>
                    <div class="divide-y divide-gray-100 px-5">
                        @forelse ($withdrawals as $withdrawal)
                            <div class="py-3 text-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="font-bold text-gray-900" data-private-amount>Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</p>
                                        <p class="mt-0.5 text-xs text-gray-400">{{ $withdrawal->created_at?->format('d M Y H:i') }} | {{ $withdrawal->bank_account ?: '-' }}</p>
                                    </div>
                                    <span class="inline-block rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $withdrawal->statusBadgeClass() }}">{{ $withdrawal->statusLabel() }}</span>
                                </div>
                                @if ($withdrawal->status === \App\Models\Cooperative\CooperativeSavingsWithdrawal::STATUS_SUBMITTED && (int) $withdrawal->maker_user_id === (int) session('user_id'))
                                    <form method="POST" action="{{ route('cooperative.savings.withdraw.decide') }}" class="mt-3">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $withdrawal->id }}">
                                        <input type="hidden" name="decision" value="cancel">
                                        <button type="submit" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50">Batalkan</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <p class="py-8 text-center text-sm text-gray-400">Belum ada pengajuan withdraw.</p>
                        @endforelse
                    </div>
                    <div class="border-t border-gray-100 px-5 py-3">{{ $withdrawals->links() }}</div>
                </div>
            </div>
        @endif
    @endif
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

(function () {
    const button = document.getElementById('togglePrivateAmounts');
    const amounts = Array.from(document.querySelectorAll('[data-private-amount]'));

    if (!button || amounts.length === 0) {
        return;
    }

    const icon = button.querySelector('i');
    const storageKey = 'cooperative.savings.amountsHidden';

    amounts.forEach(function (amount) {
        amount.dataset.privateOriginal = amount.textContent.trim();
    });

    function maskValue(value) {
        return value.replace(/[0-9.]+/g, function (match) {
            return '•'.repeat(Math.min(Math.max(match.length, 4), 10));
        });
    }

    function applyState(hidden) {
        amounts.forEach(function (amount) {
            amount.textContent = hidden ? maskValue(amount.dataset.privateOriginal || '') : amount.dataset.privateOriginal;
        });

        button.setAttribute('aria-pressed', hidden ? 'true' : 'false');
        button.setAttribute('aria-label', hidden ? 'Tampilkan nominal simpanan' : 'Sembunyikan nominal simpanan');

        if (icon) {
            icon.className = hidden ? 'fas fa-eye-slash' : 'fas fa-eye';
        }
    }

    let hidden = window.localStorage.getItem(storageKey) === '1';
    applyState(hidden);

    button.addEventListener('click', function () {
        hidden = !hidden;
        window.localStorage.setItem(storageKey, hidden ? '1' : '0');
        applyState(hidden);
    });
})();
</script>
@endpush
