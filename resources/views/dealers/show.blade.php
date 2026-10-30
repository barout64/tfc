<x-layouts.app :title="$dealer->business_name">
    <x-page-header :title="$dealer->business_name" :subtitle="$dealer->dealer_number.' · '.$dealer->owner_name.' · '.$dealer->phone">
        <x-slot:actions><x-badge :color="$dealer->status->color()" class="text-sm">{{ $dealer->status->label() }}</x-badge></x-slot:actions>
    </x-page-header>
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="card">
                <div class="card-header"><h2 class="card-title">{{ __('Outlet stock') }}</h2></div>
                <table class="table"><thead><tr><th>{{ __('Product') }}</th><th>{{ __('Batch') }}</th><th class="num">{{ __('Bags') }}</th></tr></thead><tbody>
                @forelse ($stock as $b)<tr><td>{{ $b->batch->product->code }}</td><td class="font-mono text-xs">{{ $b->batch->batch_number }}</td><td class="num">{{ number_format($b->bags) }}</td></tr>
                @empty<tr><td colspan="3" class="text-center text-slate-500">{{ __('No stock') }}</td></tr>@endforelse
                </tbody></table>
            </div>
            <div class="card">
                <div class="card-header"><h2 class="card-title">{{ __('Purchases from TFC') }}</h2></div>
                <table class="table"><thead><tr><th>{{ __('Sale') }}</th><th>{{ __('Date') }}</th><th>{{ __('From') }}</th><th class="num">{{ __('Bags') }}</th><th>{{ __('Payment') }}</th></tr></thead><tbody>
                @forelse ($purchases as $s)<tr><td><a class="link" href="{{ route('sales.show', $s) }}">{{ $s->number }}</a></td><td class="text-xs">{{ $s->sold_at->format('d M Y') }}</td><td>{{ $s->warehouse->name }}</td><td class="num">{{ number_format($s->total_bags) }}</td><td><x-badge>{{ $s->payment_status }}</x-badge></td></tr>
                @empty<tr><td colspan="5" class="text-center text-slate-500">{{ __('No purchases') }}</td></tr>@endforelse
                </tbody></table>
            </div>
            <div class="card">
                <div class="card-header"><h2 class="card-title">{{ __('Sales to farmers') }}</h2></div>
                <table class="table"><thead><tr><th>{{ __('Sale') }}</th><th>{{ __('Farmer') }}</th><th class="num">{{ __('Bags') }}</th><th>{{ __('Date') }}</th></tr></thead><tbody>
                @forelse ($farmerSales as $s)<tr><td>{{ $s->number }}</td><td>{{ $s->farmer?->fullName() }}</td><td class="num">{{ $s->total_bags }}</td><td class="text-xs">{{ $s->sold_at->format('d M Y') }}</td></tr>
                @empty<tr><td colspan="4" class="text-center text-slate-500">{{ __('No sales recorded') }}</td></tr>@endforelse
                </tbody></table>
            </div>
        </div>
        <div class="space-y-6">
            <div class="card p-5 text-sm">
                <dl class="space-y-2.5">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">TIN</dt><dd>{{ $dealer->tin ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('TFRA licence') }}</dt><dd>{{ $dealer->tfra_license_no }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Licence expiry') }}</dt><dd @class(['text-red-600 font-medium' => $dealer->isLicenseExpired()])>{{ $dealer->license_expiry?->format('d M Y') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Location') }}</dt><dd class="text-right">{{ $dealer->region->name }}{{ $dealer->district ? ', '.$dealer->district->name : '' }}<br>{{ $dealer->address }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Credit limit') }}</dt><dd>TZS {{ number_format($dealer->credit_limit) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Portal login') }}</dt><dd>{{ $user?->email ?? '—' }}</dd></div>
                </dl>
                @if ($dealer->review_notes)<p class="mt-3 border-t border-slate-100 pt-3 text-slate-600">{{ $dealer->review_notes }}</p>@endif
            </div>
            @if (auth()->user()->hasAbility('approve'))
                <form method="POST" action="{{ route('dealers.review', $dealer) }}" class="card space-y-3 p-5">
                    @csrf
                    <h3 class="card-title">{{ __('Review application') }}</h3>
                    <x-field :label="__('Decision')" name="status" :value="$dealer->status" :options="collect(\App\Enums\DealerStatus::options())->except('pending')" required />
                    <x-field :label="__('Credit limit (TZS)')" name="credit_limit" type="number" :value="$dealer->credit_limit" />
                    <x-field :label="__('Notes')" name="review_notes" type="textarea" :value="$dealer->review_notes" />
                    <button class="btn-primary w-full">{{ __('Save decision') }}</button>
                    <p class="text-xs text-slate-500">{{ __('Approval creates the dealer\'s outlet and portal login, sent by SMS.') }}</p>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
