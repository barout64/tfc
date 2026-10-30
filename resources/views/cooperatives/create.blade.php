<x-layouts.app :title="__('Add cooperative')">
    <x-page-header :title="__('Add cooperative')" />
    <form method="POST" action="{{ route('cooperatives.store') }}" class="card max-w-3xl p-6" x-data="{ region: '{{ old('region_id') }}' }">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field :label="__('Name')" name="name" required class="sm:col-span-2" />
            <x-field :label="__('Registration no.')" name="registration_no" />
            <x-field :label="__('Main crop')" name="crop_id" :options="$crops->pluck('name', 'id')" />
            <div><label class="label">{{ __('Region') }} *</label><select name="region_id" x-model="region" class="input" required><option value="">{{ __('— Select —') }}</option>@foreach ($regions as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select></div>
            <div><label class="label">{{ __('District') }}</label><select name="district_id" class="input"><option value="">{{ __('— Select —') }}</option>@foreach ($districts as $d)<option value="{{ $d->id }}" x-show="region == '{{ $d->region_id }}'">{{ $d->name }}</option>@endforeach</select></div>
            <x-field :label="__('Chairperson')" name="chairperson" />
            <x-field :label="__('Phone')" name="phone" />
            <x-field :label="__('Number of members')" name="members_count" type="number" value="0" required />
        </div>
        <div class="mt-6 flex justify-end gap-2"><a href="{{ route('cooperatives.index') }}" class="btn-secondary">{{ __('Cancel') }}</a><button class="btn-primary">{{ __('Save') }}</button></div>
    </form>
</x-layouts.app>
