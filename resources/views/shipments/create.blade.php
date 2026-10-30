<x-layouts.app :title="__('Register shipment')">
    <x-page-header :title="__('Register import shipment')" />
    <form method="POST" action="{{ route('shipments.store') }}" class="card max-w-3xl p-6">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field :label="__('Product')" name="product_id" :options="$products->pluck('name', 'id')" required />
            <x-field :label="__('Supplier')" name="supplier_id" :options="$suppliers->pluck('name', 'id')" />
            <x-field :label="__('Vessel name')" name="vessel_name" placeholder="MV Nord Santos" />
            <x-field :label="__('Port')" name="port" value="Dar es Salaam" required />
            <x-field :label="__('Ordered quantity (tonnes)')" name="ordered_tonnes" type="number" step="0.01" required />
            <x-field :label="__('Landed cost per tonne (TZS)')" name="unit_cost_per_tonne" type="number" step="0.01" />
            <x-field :label="__('Expected arrival (ETA)')" name="eta" type="date" required />
            <x-field :label="__('Planned receiving warehouse')" name="receiving_warehouse_id" :options="$warehouses->pluck('name', 'id')" />
            <x-field :label="__('Notes')" name="notes" type="textarea" class="sm:col-span-2" />
        </div>
        <div class="mt-6 flex justify-end gap-2"><a href="{{ route('shipments.index') }}" class="btn-secondary">{{ __('Cancel') }}</a><button class="btn-primary">{{ __('Save shipment') }}</button></div>
    </form>
</x-layouts.app>
