@php
    $__i = 0;
@endphp
<div class="overflow-x-auto">
    <table class="w-full min-w-[700px] text-left text-sm">
        <thead>
            <tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                <th class="px-5 py-2.5 font-bold">#</th>
                <th class="px-5 py-2.5 font-bold">Periode</th>
                <th class="px-5 py-2.5 font-bold">Status</th>
                <th class="px-5 py-2.5 text-right font-bold">Pokok (Rp)</th>
                <th class="px-5 py-2.5 text-right font-bold">Bunga (Rp)</th>
                <th class="px-5 py-2.5 text-right font-bold">Total (Rp)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($rows as $row)
                <tr>
                    <td class="px-5 py-2 text-gray-500">{{ ++$__i }}</td>
                    <td class="px-5 py-2 font-mono text-xs text-gray-600">{{ \App\Services\Cooperative\CooperativePeriod::label($row['periode']) }}</td>
                    <td class="px-5 py-2"><span class="inline-block whitespace-nowrap rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $statusBadge($row['status']) }}">{{ $row['status'] }}</span></td>
                    <td class="px-5 py-2 text-right text-gray-700">{{ number_format($row['amount'], 0, ',', '.') }}</td>
                    <td class="px-5 py-2 text-right text-gray-700">{{ number_format($row['int_amt'], 0, ',', '.') }}</td>
                    <td class="px-5 py-2 text-right font-semibold text-gray-800">{{ number_format($row['amount'] + $row['int_amt'] + $row['others'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-6 text-center text-sm text-gray-400">Belum ada jadwal.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
