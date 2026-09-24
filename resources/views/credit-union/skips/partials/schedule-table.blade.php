@php
    $__i = 0;
@endphp
<div class="overflow-x-auto">
    <table class="w-full min-w-[520px] text-left text-xs">
        <thead>
            <tr class="bg-gray-50 text-[10px] uppercase tracking-wide text-gray-500">
                <th class="px-3.5 py-2 font-bold">#</th>
                <th class="px-3.5 py-2 font-bold">Periode</th>
                <th class="px-3.5 py-2 font-bold">Status</th>
                <th class="px-3.5 py-2 text-right font-bold">Pokok (Rp)</th>
                <th class="px-3.5 py-2 text-right font-bold">Bunga (Rp)</th>
                <th class="px-3.5 py-2 text-right font-bold">Total (Rp)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($rows as $row)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-3.5 py-1.5 text-gray-400">{{ ++$__i }}</td>
                    <td class="px-3.5 py-1.5 font-mono text-[11px] text-gray-600">{{ \App\Services\CreditUnion\CreditUnionPeriod::label($row['periode']) }}</td>
                    <td class="px-3.5 py-1.5"><span class="inline-block whitespace-nowrap rounded-full border px-2 py-0.5 text-[9px] font-bold {{ $statusBadge($row['status']) }}">{{ $row['status'] }}</span></td>
                    <td class="px-3.5 py-1.5 text-right text-gray-700">{{ number_format($row['amount'], 0, ',', '.') }}</td>
                    <td class="px-3.5 py-1.5 text-right text-gray-700">{{ number_format($row['int_amt'], 0, ',', '.') }}</td>
                    <td class="px-3.5 py-1.5 text-right font-semibold text-gray-800">{{ number_format($row['amount'] + $row['int_amt'] + $row['others'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-3.5 py-4 text-center text-xs text-gray-400">Belum ada jadwal.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
