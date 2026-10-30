<x-layouts.public :title="__('Register as farmer')">
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-semibold text-slate-900">{{ __('Register as a farmer') }}</h1>
        <p class="mt-2 text-slate-600">{{ __('Free. You will receive your farmer number by SMS. No smartphone? Dial :ussd.', ['ussd' => config('tfc.ussd_code')]) }}</p>
        <form method="POST" action="{{ route('public.register') }}" class="mt-8 grid gap-4 rounded-2xl border border-slate-200 p-6 sm:grid-cols-2" x-data="{ region: '{{ old('region_id') }}' }">
            @csrf
            <x-field :label="__('First name')" name="first_name" required />
            <x-field :label="__('Last name')" name="last_name" required />
            <x-field :label="__('Mobile phone')" name="phone" required placeholder="0712345678" />
            <x-field :label="__('Gender')" name="gender" :options="['female' => __('Female'), 'male' => __('Male')]" />
            <x-field :label="__('NIDA number (optional)')" name="nida_number" />
            <x-field :label="__('Main crop')" name="main_crop_id" :options="$crops->pluck('name', 'id')" required />
            <div><label class="label">{{ __('Region') }} *</label><select name="region_id" x-model="region" class="input" required><option value="">{{ __('— Select —') }}</option>@foreach ($regions as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select></div>
            <div><label class="label">{{ __('District') }}</label><select name="district_id" class="input"><option value="">{{ __('— Select —') }}</option>@foreach ($districts as $d)<option value="{{ $d->id }}" x-show="region == '{{ $d->region_id }}'">{{ $d->name }}</option>@endforeach</select></div>
            <x-field :label="__('Village')" name="village" required />
            <x-field :label="__('Farm size (acres)')" name="farm_size_acres" type="number" step="0.25" min="0.25" required />
            <div class="sm:col-span-2"><button class="btn-primary w-full py-3 text-base">{{ __('Register') }}</button></div>
        </form>
    </div>
</x-layouts.public>
