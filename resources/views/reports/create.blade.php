@extends('layouts.app', ['title' => 'Daily Report'])

@section('content')
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('reports.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Daily Report</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Taarifa ya Mauzo ya Kila Siku</h1>
        <p class="mt-1 text-sm text-slate-500">What this station sold today, product by product. Saving again for the same station and date replaces that day's report.</p>

        <form method="POST" action="{{ route('reports.store') }}" class="card mt-6 space-y-5 p-6">
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="report_date">Date</label>
                    <input class="field" id="report_date" name="report_date" type="date" value="{{ old('report_date', $date) }}" max="{{ now()->toDateString() }}" required>
                    @error('report_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">Station</label>
                    @if ($station)
                        <p class="field flex items-center bg-slate-50 text-slate-700">{{ $station->name }}</p>
                    @else
                        <select class="field" id="station_id" name="station_id" required>
                            <option value="">Choose…</option>
                            @foreach ($stations as $st)
                                <option value="{{ $st->id }}" @selected((string) old('station_id') === (string) $st->id)>{{ $st->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    @error('station_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            @if ($existing)
                <p class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">A report already exists for this station and date. Its lines are loaded below — change and save to replace it.</p>
            @endif

            <div>
                <div class="flex items-center justify-between">
                    <label class="label">What was sold</label>
                    <button type="button" id="add-row" class="text-sm font-semibold text-brand-700 hover:underline">+ Add product</button>
                </div>

                <div class="mt-2 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th class="w-2/5 pb-2">Product</th>
                                <th class="pb-2">Unit</th>
                                <th class="pb-2">Quantity</th>
                                <th class="pb-2">Unit price (TZS)</th>
                                <th class="pb-2">Amount (TZS)</th>
                                <th class="pb-2"></th>
                            </tr>
                        </thead>
                        <tbody id="lines"></tbody>
                    </table>
                </div>
                <p id="lines-error" class="mt-1 hidden text-sm text-red-600">Add at least one product.</p>

                <p class="mt-3 flex justify-end gap-2 text-sm font-semibold text-slate-700">Total: <span id="total">0</span></p>
            </div>

            <div>
                <label class="label" for="notes">Notes (optional)</label>
                <textarea class="field" id="notes" name="notes" rows="3" maxlength="2000">{{ old('notes', $existing?->notes) }}</textarea>
                @error('notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('reports.index') }}" class="btn-light">Cancel</a>
                <button class="btn-primary">Save report</button>
            </div>
        </form>
    </div>

    <script>
        (function () {
            const body = document.getElementById('lines');
            const existing = @json(old('lines') ?: $existingLines);
            const products = @json($products->pluck('name'));

            function fmt(n) {
                return (Math.round(n * 100) / 100).toLocaleString('en-US', { maximumFractionDigits: 2 });
            }

            function addRow(data) {
                data = data || {};
                const i = body.children.length;
                const tr = document.createElement('tr');
                const known = data.product && products.includes(data.product);
                tr.innerHTML = `
                    <td class="py-1 pr-2">
                        <select class="field product-select">
                            <option value="">Choose…</option>
                            ${products.map(p => `<option ${p === data.product ? 'selected' : ''}>${p}</option>`).join('')}
                            <option value="__other__" ${data.product && !known ? 'selected' : ''}>Other…</option>
                        </select>
                        <input class="field product-other mt-1 ${data.product && !known ? '' : 'hidden'}" placeholder="Product name" maxlength="60" value="${!known ? (data.product ?? '') : ''}">
                        <input type="hidden" class="product-value" name="lines[${i}][product]" value="${data.product ?? ''}">
                    </td>
                    <td class="py-1 pr-2">
                        <select class="field" name="lines[${i}][unit]">
                            ${['Bags', 'Tonnes', 'Kg', 'Litres', 'Pieces'].map(u => `<option ${u === (data.unit ?? 'Bags') ? 'selected' : ''}>${u}</option>`).join('')}
                        </select>
                    </td>
                    <td class="py-1 pr-2"><input class="field qty" type="number" step="0.01" min="0" name="lines[${i}][quantity]" required value="${data.quantity ?? ''}"></td>
                    <td class="py-1 pr-2"><input class="field price" type="number" step="0.01" min="0" name="lines[${i}][unit_price]" value="${data.unit_price ?? ''}"></td>
                    <td class="py-1 pr-2"><input class="field amount" type="number" step="0.01" min="0" name="lines[${i}][amount]" required value="${data.amount ?? ''}"></td>
                    <td class="py-1"><button type="button" class="text-slate-400 hover:text-red-600" title="Remove">✕</button></td>
                `;
                body.appendChild(tr);

                const select = tr.querySelector('.product-select'), other = tr.querySelector('.product-other'), value = tr.querySelector('.product-value');
                const syncProduct = () => {
                    if (select.value === '__other__') {
                        other.classList.remove('hidden');
                        value.value = other.value;
                    } else {
                        other.classList.add('hidden');
                        value.value = select.value;
                    }
                };
                select.addEventListener('change', syncProduct);
                other.addEventListener('input', () => { value.value = other.value; });

                const qty = tr.querySelector('.qty'), price = tr.querySelector('.price'), amount = tr.querySelector('.amount');
                const recalc = () => {
                    if (qty.value !== '' && price.value !== '') amount.value = (parseFloat(qty.value) * parseFloat(price.value)).toFixed(2);
                    updateTotal();
                };
                qty.addEventListener('input', recalc);
                price.addEventListener('input', recalc);
                amount.addEventListener('input', updateTotal);
                tr.querySelector('button').addEventListener('click', () => { tr.remove(); updateTotal(); });
            }

            function updateTotal() {
                let total = 0;
                body.querySelectorAll('.amount').forEach(a => total += parseFloat(a.value || 0));
                document.getElementById('total').textContent = fmt(total);
            }

            document.getElementById('add-row').addEventListener('click', () => addRow());

            if (existing.length) {
                existing.forEach(addRow);
            } else {
                addRow();
            }
            updateTotal();

            document.querySelector('form').addEventListener('submit', (e) => {
                if (!body.children.length) {
                    e.preventDefault();
                    document.getElementById('lines-error').classList.remove('hidden');
                }
            });
        })();
    </script>
@endsection
