@extends('layouts.app')

@section('title', 'RUN-ITC | Riwayat Simpanan')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.22em] text-brand-primary">Simpanan Saya</p>
            <h1 class="mt-1 text-2xl font-extrabold text-gray-900">Riwayat Simpanan</h1>
            <p class="mt-0.5 text-sm text-gray-500">Riwayat transaksi simpanan dan pengajuan withdraw Anda.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" id="togglePrivateAmounts" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50" aria-label="Sembunyikan nominal simpanan" aria-pressed="false">
                <i class="fas fa-eye"></i> Nominal
            </button>
            <a href="{{ route('cooperative.savings.index', request()->query('view') === 'mine' ? ['view' => 'mine'] : []) }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-primary/30 transition hover:bg-brand-primaryHover">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>

    @if ($member === null)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Data anggota Anda belum tersinkron ke sistem koperasi. Hubungi admin koperasi untuk menautkan akun Anda sebagai anggota.
        </div>
    @else
        <div class="flex flex-wrap gap-2 rounded-2xl border border-gray-200 bg-white p-2 shadow-sm">
            <a href="{{ route('cooperative.savings.history', array_filter(['tab' => 'all', 'view' => request()->query('view')])) }}" class="rounded-xl px-4 py-2 text-sm font-semibold transition {{ $tab === 'all' ? 'bg-brand-primary text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50' }}">Semua</a>
            <a href="{{ route('cooperative.savings.history', array_filter(['tab' => 'savings', 'view' => request()->query('view')])) }}" class="rounded-xl px-4 py-2 text-sm font-semibold transition {{ $tab === 'savings' ? 'bg-brand-primary text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50' }}">Riwayat Simpanan</a>
            <a href="{{ route('cooperative.savings.history', array_filter(['tab' => 'withdraw', 'view' => request()->query('view')])) }}" class="rounded-xl px-4 py-2 text-sm font-semibold transition {{ $tab === 'withdraw' ? 'bg-brand-primary text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50' }}">Pengajuan Withdraw</a>
        </div>

        @if (in_array($tab, ['all', 'savings'], true))
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
        @endif

        @if (in_array($tab, ['all', 'withdraw'], true))
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4">
                    <p class="text-sm font-bold text-gray-800">Pengajuan Withdraw</p>
                    <p class="mt-0.5 text-xs text-gray-400">Status penarikan simpanan Anda.</p>
                </div>
                <div class="divide-y divide-gray-100 px-5">
                    @forelse ($withdrawals as $withdrawal)
                        <div class="py-3 text-sm">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="font-bold text-gray-900" data-private-amount>Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</p>
                                    <p class="mt-0.5 text-xs text-gray-400">
                                        {{ $withdrawal->created_at?->format('d M Y H:i') }} |
                                        @if ($withdrawal->bank_bnkcd || $withdrawal->bank_accnm || $withdrawal->bank_accno)
                                            {{ $bankOptions[$withdrawal->bank_bnkcd] ?? $withdrawal->bank_bnkcd }} - {{ $withdrawal->bank_accnm }} ({{ $withdrawal->bank_accno }})
                                        @else
                                            {{ $withdrawal->bank_account ?: '-' }}
                                        @endif
                                        {{ $withdrawal->withdrawal_trnno ? '| '.$withdrawal->withdrawal_trnno : '' }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <span class="inline-block rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $withdrawal->statusBadgeClass() }}">{{ $withdrawal->statusLabel() }}</span>
                                    @if ($withdrawal->status === \App\Models\Cooperative\CooperativeSavingsWithdrawal::STATUS_SUBMITTED && (int) $withdrawal->maker_user_id === (int) session('user_id'))
                                        <form method="POST" action="{{ route('cooperative.savings.withdraw.decide') }}">
                                            @csrf
                                            <input type="hidden" name="id" value="{{ $withdrawal->id }}">
                                            <input type="hidden" name="decision" value="cancel">
                                            <button type="submit" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50">Batalkan</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="py-8 text-center text-sm text-gray-400">Belum ada pengajuan withdraw.</p>
                    @endforelse
                </div>
                <div class="border-t border-gray-100 px-5 py-3">{{ $withdrawals->links() }}</div>
                @if (isset($minimumSavingsBalance) && $minimumSavingsBalance > 0)
                    <div class="border-t border-gray-100 px-5 py-3">
                        <p class="text-xs text-gray-500">Saldo mengendap: Rp {{ number_format($minimumSavingsBalance, 0, ',', '.') }}</p>
                    </div>
                @endif
            </div>
        @endif
    @endif
</div>
@endsection

@push('scripts')
<script>
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
            return '*'.repeat(Math.min(Math.max(match.length, 4), 10));
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
