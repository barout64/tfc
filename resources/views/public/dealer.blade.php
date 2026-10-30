<x-layouts.public :title="__('Become an agro-dealer')">
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-semibold text-slate-900">{{ __('Become a TFC agro-dealer') }}</h1>
        <p class="mt-2 text-slate-600">{{ __('You need a valid TFRA agro-dealer licence and a TIN. TFC verifies your documents and sends your login by SMS once approved.') }}</p>
        <form method="POST" action="{{ route('public.dealer') }}" class="mt-8 grid gap-4 rounded-2xl border border-slate-200 p-6 sm:grid-cols-2" x-data="{ region: '{{ old('region_id') }}', lat: '{{ old('lat') }}', lng: '{{ old('lng') }}' }">
            @csrf
            <x-field :label="__('Business name')" name="business_name" required class="sm:col-span-2" />
            <x-field :label="__('Owner name')" name="owner_name" required />
            <x-field :label="__('Phone')" name="phone" required />
            <x-field :label="__('Email')" name="email" type="email" />
            <x-field :label="__('TIN')" name="tin" required />
            <x-field :label="__('TFRA licence number')" name="tfra_license_no" required />
            <x-field :label="__('Licence expiry')" name="license_expiry" type="date" required />
            <div><label class="label">{{ __('Region') }} *</label><select name="region_id" x-model="region" class="input" required><option value="">{{ __('— Select —') }}</option>@foreach ($regions as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select></div>
            <div><label class="label">{{ __('District') }}</label><select name="district_id" class="input"><option value="">{{ __('— Select —') }}</option>@foreach ($districts as $d)<option value="{{ $d->id }}" x-show="region == '{{ $d->region_id }}'">{{ $d->name }}</option>@endforeach</select></div>
            <x-field :label="__('Ward')" name="ward" />
            <x-field :label="__('Shop address')" name="address" required />
            <input type="hidden" name="lat" :value="lat"><input type="hidden" name="lng" :value="lng">
            <div class="sm:col-span-2"><button type="button" class="btn-secondary btn-sm" @click="navigator.geolocation.getCurrentPosition(p => { lat = p.coords.latitude.toFixed(6); lng = p.coords.longitude.toFixed(6) })"><x-icon name="location" class="size-4" />{{ __('Add my shop location (GPS)') }}</button> <span class="text-xs text-slate-500" x-show="lat" x-text="lat + ', ' + lng"></span></div>
            <div class="sm:col-span-2"><button class="btn-primary w-full py-3">{{ __('Submit application') }}</button></div>
        </form>
    </div>
</x-layouts.public>
