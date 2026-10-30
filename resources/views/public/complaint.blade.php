<x-layouts.public :title="__('Report a problem')">
    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-semibold text-slate-900">{{ __('Report a problem') }}</h1>
        <p class="mt-2 text-slate-600">{{ __('Overpricing, no stock, poor quality or bad service? Tell us. You will get a ticket number.') }}</p>
        <form method="POST" action="{{ route('public.complaint') }}" class="mt-8 space-y-4 rounded-2xl border border-slate-200 p-6">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field :label="__('Your name (optional)')" name="name" />
                <x-field :label="__('Phone')" name="phone" required placeholder="0712345678" />
                <x-field :label="__('What is the problem?')" name="category" :options="$categories" required />
                <x-field :label="__('Region')" name="region_id" :options="$regions->pluck('name', 'id')" />
            </div>
            <x-field :label="__('Details (name of dealer / sale point, price charged, date)')" name="description" type="textarea" required />
            <button class="btn-primary w-full py-3">{{ __('Submit') }}</button>
        </form>
    </div>
</x-layouts.public>
