<x-layouts.app :title="$user->exists ? __('Edit user') : __('Add user')">
    <x-page-header :title="$user->exists ? __('Edit :name', ['name' => $user->name]) : __('Add user')" />
    <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="card max-w-3xl p-6" x-data="{ role: '{{ old('role', $user->role?->value) }}' }">
        @csrf @if ($user->exists) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field :label="__('Full name')" name="name" :value="$user->name" required />
            <x-field :label="__('Email')" name="email" type="email" :value="$user->email" required />
            <x-field :label="__('Phone')" name="phone" :value="$user->phone" />
            <div><label class="label">{{ __('Role') }} *</label><select name="role" x-model="role" class="input" required>@foreach ($roles as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
            <div x-show="!['agro_dealer', 'transporter'].includes(role)"><x-field :label="__('Warehouse (limits warehouse officers to it)')" name="warehouse_id" :value="$user->warehouse_id" :options="$warehouses->pluck('name', 'id')" /></div>
            <div x-show="role === 'agro_dealer'" x-cloak><x-field :label="__('Agro-dealer')" name="agro_dealer_id" :value="$user->agro_dealer_id" :options="$dealers->pluck('business_name', 'id')" /></div>
            <div x-show="role === 'transporter'" x-cloak><x-field :label="__('Transporter')" name="transporter_id" :value="$user->transporter_id" :options="$transporters->pluck('name', 'id')" /></div>
            <x-field :label="$user->exists ? __('New password (leave blank to keep)') : __('Password')" name="password" type="password" :required="! $user->exists" autocomplete="new-password" />
            <label class="flex items-center gap-2 self-end pb-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) class="rounded border-slate-300 text-brand-600"> {{ __('Account active') }}</label>
        </div>
        <div class="mt-6 flex justify-end gap-2"><a href="{{ route('users.index') }}" class="btn-secondary">{{ __('Cancel') }}</a><button class="btn-primary">{{ __('Save') }}</button></div>
    </form>
</x-layouts.app>
