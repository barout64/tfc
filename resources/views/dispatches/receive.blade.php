<x-layouts.app :title="__('Confirm delivery')">
    <x-page-header :title="__('Confirm delivery · :number', ['number' => $dispatch->number])" :subtitle="__('Count the bags off the truck. Good bags go into :warehouse stock; shortages and damage are charged to the transporter.', ['warehouse' => $dispatch->toWarehouse->name])" />
    <form method="POST" action="{{ route('dispatches.receive', $dispatch) }}" enctype="multipart/form-data" class="card max-w-4xl">
        @csrf
        <div class="grid gap-3 border-b border-slate-100 p-5 text-sm sm:grid-cols-3">
            <p><span class="text-slate-500">{{ __('From') }}:</span> {{ $dispatch->fromWarehouse->name }}</p>
            <p><span class="text-slate-500">{{ __('Vehicle') }}:</span> {{ $dispatch->vehicle?->plate_number }}</p>
            <p><span class="text-slate-500">{{ __('Driver') }}:</span> {{ $dispatch->driver?->name }}</p>
        </div>
        <table class="table">
            <thead><tr><th>{{ __('Product / batch') }}</th><th class="num">{{ __('Sent') }}</th><th class="w-40">{{ __('Received (all)') }}</th><th class="w-40">{{ __('of which damaged') }}</th></tr></thead>
            <tbody>
            @foreach ($dispatch->items as $item)
                <tr>
                    <td>{{ $item->batch->product->code }} <span class="font-mono text-xs text-slate-500">{{ $item->batch->batch_number }}</span></td>
                    <td class="num font-medium">{{ number_format($item->bags_sent) }}</td>
                    <td><input type="number" min="0" max="{{ $item->bags_sent }}" name="items[{{ $item->id }}][received]" value="{{ old("items.{$item->id}.received", $item->bags_sent) }}" class="input text-right" required></td>
                    <td><input type="number" min="0" name="items[{{ $item->id }}][damaged]" value="{{ old("items.{$item->id}.damaged", 0) }}" class="input text-right" required></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="grid gap-4 p-5 sm:grid-cols-2">
            <x-field :label="__('Received by (name)')" name="receiver_name" :value="auth()->user()->name" required />
            <div><label class="label">{{ __('Photo of delivery note / goods') }}</label><input type="file" name="photo" accept="image/*" capture="environment" class="input"></div>
            <x-field :label="__('Remarks')" name="notes" type="textarea" class="sm:col-span-2" />
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 p-5"><a href="{{ route('dispatches.show', $dispatch) }}" class="btn-secondary">{{ __('Back') }}</a><button class="btn-primary">{{ __('Confirm & add to stock') }}</button></div>
    </form>
</x-layouts.app>
