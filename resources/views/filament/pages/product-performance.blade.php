<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Period --}}
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label for="pp-from" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">From</label>
                <input
                    id="pp-from"
                    type="date"
                    wire:model.live="from"
                    class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 text-sm"
                />
            </div>
            <div>
                <label for="pp-to" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">To</label>
                <input
                    id="pp-to"
                    type="date"
                    wire:model.live="to"
                    class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 text-sm"
                />
            </div>
        </div>

        {{-- Totals --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            @foreach ([
                ['Products sold', number_format($report['total_products'] ?? 0)],
                ['Units', number_format($report['total_units'] ?? 0)],
                ['Revenue', '£' . number_format((float) ($report['total_revenue'] ?? 0), 2)],
            ] as [$label, $value])
                <div class="fi-section rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        {{-- By product --}}
        <div class="fi-section rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-white/10">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">By product</h2>
            </div>

            @if (empty($report['products']) || count($report['products']) === 0)
                <p class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                    Nothing sold in this period.
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-2.5 text-left font-medium text-gray-500 dark:text-gray-400">Product</th>
                                <th class="px-4 py-2.5 text-left font-medium text-gray-500 dark:text-gray-400">Category</th>
                                <th class="px-4 py-2.5 text-right font-medium text-gray-500 dark:text-gray-400">Units</th>
                                <th class="px-4 py-2.5 text-right font-medium text-gray-500 dark:text-gray-400">Revenue</th>
                                <th class="px-4 py-2.5 text-right font-medium text-gray-500 dark:text-gray-400">Margin</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($report['products'] as $row)
                                <tr>
                                    <td class="px-4 py-2.5 font-medium text-gray-950 dark:text-white">{{ $row['name'] }}</td>
                                    <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300">{{ $row['category'] ?: '—' }}</td>
                                    <td class="px-4 py-2.5 text-right text-gray-600 dark:text-gray-300">{{ number_format($row['units']) }}</td>
                                    <td class="px-4 py-2.5 text-right text-gray-950 dark:text-white">£{{ number_format($row['revenue'], 2) }}</td>
                                    <td class="px-4 py-2.5 text-right {{ $row['margin'] === null ? 'text-gray-400' : 'text-gray-600 dark:text-gray-300' }}">
                                        {{-- Null margin is honest: no cost price recorded. --}}
                                        {{ $row['margin'] === null ? 'no cost set' : '£' . number_format($row['margin'], 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- By category --}}
        @if (! empty($report['by_category']) && count($report['by_category']) > 0)
            <div class="fi-section rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-white/10">
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white">By category</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[420px] text-sm">
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($report['by_category'] as $category => $totals)
                                <tr>
                                    <td class="px-4 py-2.5 text-gray-950 dark:text-white">{{ $category }}</td>
                                    <td class="px-4 py-2.5 text-right text-gray-600 dark:text-gray-300">{{ number_format($totals['units']) }} units</td>
                                    <td class="px-4 py-2.5 text-right text-gray-950 dark:text-white">£{{ number_format($totals['revenue'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
