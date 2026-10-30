<x-layouts.app :title="$warehouse->exists ? __('Edit warehouse') : __('Add warehouse')">
    <x-page-header :title="$warehouse->exists ? __('Edit :name', ['name' => $warehouse->name]) : __('Add warehouse')" />
    <form method="POST" action="{{ $warehouse->exists ? route('warehouses.update', $warehouse) : route('warehouses.store') }}" class="card max-w-4xl p-6" x-data="{ region: '{{ old('region_id', $warehouse->region_id) }}', lat: '{{ old('lat', $warehouse->lat) }}', lng: '{{ old('lng', $warehouse->lng) }}' }">
        @csrf @if ($warehouse->exists) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field :label="__('Code')" name="code" :value="$warehouse->code" required placeholder="TFC-MBY" />
            <x-field :label="__('Name')" name="name" :value="$warehouse->name" required />
            <x-field :label="__('Type')" name="type" :value="$warehouse->type" :options="$types" required />
            <x-field :label="__('Capacity (tonnes)')" name="capacity_tonnes" type="number" :value="$warehouse->capacity_tonnes" required min="0" />
            <div>
                <label class="label">{{ __('Region') }} <span class="text-red-500">*</span></label>
                <select name="region_id" x-model="region" class="input" required>
                    <option value="">{{ __('— Select —') }}</option>
                    @foreach ($regions as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label">{{ __('District') }}</label>
                <select name="district_id" class="input">
                    <option value="">{{ __('— Select —') }}</option>
                    @foreach ($districts as $d)<option value="{{ $d->id }}" x-show="region == '{{ $d->region_id }}'" @selected(old('district_id', $warehouse->district_id) == $d->id)>{{ $d->name }}</option>@endforeach
                </select>
            </div>
            <x-field :label="__('TFRA registration no.')" name="tfra_number" :value="$warehouse->tfra_number" />
            <x-field :label="__('Contact phone')" name="contact_phone" :value="$warehouse->contact_phone" />
            <x-field :label="__('Address')" name="address" :value="$warehouse->address" class="sm:col-span-2" />
            <div><label class="label">{{ __('Latitude') }} <span class="text-red-500">*</span></label><input name="lat" x-model="lat" class="input" required placeholder="-6.8160"></div>
            <div><label class="label">{{ __('Longitude') }} <span class="text-red-500">*</span></label><input name="lng" x-model="lng" class="input" required placeholder="39.2803"></div>
            <div class="sm:col-span-2"><button type="button" class="btn-secondary btn-sm" @click="navigator.geolocation.getCurrentPosition(p => { lat = p.coords.latitude.toFixed(6); lng = p.coords.longitude.toFixed(6) })"><x-icon name="location" class="size-4" />{{ __('Use my current location') }}</button></div>
            <x-field :label="__('Warehouse manager')" name="manager_id" :value="$warehouse->manager_id" :options="$managers->pluck('name', 'id')" />
            <div class="flex items-end gap-6 pb-2">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $warehouse->is_active)) class="rounded border-slate-300 text-brand-600"> {{ __('Active') }}</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_public" value="1" @checked(old('is_public', $warehouse->is_public)) class="rounded border-slate-300 text-brand-600"> {{ __('Show on public sale-point finder') }}</label>
            </div>
        </div>
        <div class="mt-6 flex justify-end gap-2"><a href="{{ url()->previous() }}" class="btn-secondary">{{ __('Cancel') }}</a><button class="btn-primary">{{ __('Save') }}</button></div>
    </form>
</x-layouts.app>
