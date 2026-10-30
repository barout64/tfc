<x-layouts.app :title="$transporter->exists ? __('Edit transporter') : __('Add transporter')">
    <x-page-header :title="$transporter->exists ? __('Edit :name', ['name' => $transporter->name]) : __('Add transporter')" />
    <form method="POST" action="{{ $transporter->exists ? route('transporters.update', $transporter) : route('transporters.store') }}" class="card max-w-3xl p-6">
        @csrf @if ($transporter->exists) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field :label="__('Company name')" name="name" :value="$transporter->name" required class="sm:col-span-2" />
            <x-field :label="__('TIN')" name="tin" :value="$transporter->tin" />
            <x-field :label="__('Contact person')" name="contact_person" :value="$transporter->contact_person" />
            <x-field :label="__('Phone')" name="phone" :value="$transporter->phone" />
            <x-field :label="__('Email')" name="email" type="email" :value="$transporter->email" />
            <x-field :label="__('Rate per tonne-km (TZS)')" name="rate_per_tonne_km" type="number" step="0.01" :value="$transporter->rate_per_tonne_km ?? 0" required />
            <x-field :label="__('Penalty per lost bag (TZS)')" name="loss_penalty_per_bag" type="number" step="0.01" :value="$transporter->loss_penalty_per_bag ?? 0" required />
            <x-field :label="__('Contract expires')" name="contract_expires_on" type="date" :value="$transporter->contract_expires_on" />
            <x-field :label="__('Status')" name="status" :value="$transporter->status" :options="['active' => __('Active'), 'suspended' => __('Suspended')]" required />
        </div>
        <div class="mt-6 flex justify-end gap-2"><a href="{{ url()->previous() }}" class="btn-secondary">{{ __('Cancel') }}</a><button class="btn-primary">{{ __('Save') }}</button></div>
    </form>
</x-layouts.app>
