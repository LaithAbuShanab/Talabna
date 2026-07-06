<x-filament-widgets::widget>
    <x-filament::section heading="Best selling products">
        @if (empty($products))
            <p class="fi-ta-empty-state-description text-sm text-gray-500 dark:text-gray-400">
                No delivered orders in this period yet.
            </p>
        @else
            <table class="fi-ta-table w-full">
                <thead>
                    <tr>
                        <th class="fi-ta-header-cell px-3 py-2 text-start text-sm font-semibold">Product</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-end text-sm font-semibold">Qty sold</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-end text-sm font-semibold">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr class="fi-ta-row">
                            <td class="fi-ta-cell px-3 py-2 text-sm">{{ $product['product_name'] }}</td>
                            <td class="fi-ta-cell px-3 py-2 text-end text-sm">{{ $product['quantity'] }}</td>
                            <td class="fi-ta-cell px-3 py-2 text-end text-sm">{{ $product['revenue_formatted'] }} {{ $currencyCode }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
