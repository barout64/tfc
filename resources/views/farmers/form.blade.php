<x-layouts.app :title="$farmer->exists ? __('Edit farmer') : __('Register farmer')">
    <x-page-header :title="$farmer->exists ? __('Edit :name', ['name' => $farmer->fullName()]) : __('Register farmer')" :subtitle="__('Allowance is calculated from farm size and the crop\'s fertilizer recommendation.')" />
    <form method="POST" action="{{ $farmer->exists ? route('farmers.update', $farmer) : route('farmers.store') }}" class="card max-w-4xl p-6" x-data="{ region: '{{ old('region_id', $farmer->region_id) }}' }">
        @csrf @if ($farmer->exists) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-3">
            <x-field :label="__('First name')" name="first_name" :value="$farmer->first_name" required />
            <x-field :label="__('Last name')" name="last_name" :value="$farmer->last_name" required />
            <x-field :label="__('Gender')" name="gender" :value="$farmer->gender" :options="['female' => __('Female'), 'male' => __('Male')]" />
            <x-field :label="__('Mobile phone')" name="phone" :value="$farmer->phone" required placeholder="0712345678" />
            <x-field :label="__('NIDA number')" name="nida_number" :value="$farmer->nida_number" />
            <x-field :label="__('Date of birth')" name="date_of_birth" type="date" :value="$farmer->date_of_birth" />
            <div><label class="label">{{ __('Region') }} *</label><select name="region_id" x-model="region" class="input" required><option value="">{{ __('— Select —') }}</option>@foreach ($regions as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select></div>
            <div><label class="label">{{ __('District') }}</label><select name="district_id" class="input"><option value="">{{ __('— Select —') }}</option>@foreach ($districts as $d)<option value="{{ $d->id }}" x-show="region == '{{ $d->region_id }}'" @selected(old('district_id', $farmer->district_id) == $d->id)>{{ $d->name }}</option>@endforeach</select></div>
            <x-field :label="__('Ward')" name="ward" :value="$farmer->ward" />
            <x-field :label="__('Village')" name="village" :value="$farmer->village" />
            <x-field :label="__('Farm size (acres)')" name="farm_size_acres" type="number" step="0.25" :value="$farmer->farm_size_acres" required />
            <x-field :label="__('Main crop')" name="main_crop_id" :value="$farmer->main_crop_id" :options="$crops->pluck('name', 'id')" required />
            <x-field :label="__('Cooperative (AMCOS)')" name="cooperative_id" :value="$farmer->cooperative_id" :options="$cooperatives->pluck('name', 'id')" />
            <x-field :label="__('Farm latitude')" name="lat" :value="$farmer->lat" />
            <x-field :label="__('Farm longitude')" name="lng" :value="$farmer->lng" />
            @if ($farmer->exists)
                <x-field :label="__('Status')" name="status" :value="$farmer->status" :options="['active' => __('Active'), 'suspended' => __('Suspended')]" />
            @endif
        </div>
        <div class="mt-6 flex justify-end gap-2"><a href="{{ url()->previous() }}" class="btn-secondary">{{ __('Cancel') }}</a><button class="btn-primary">{{ __('Save') }}</button></div>
    </form>
</x-layouts.app>
