@props(['label', 'name', 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null, 'options' => null, 'placeholder' => null])
@php
    $id = $attributes->get('id', str_replace(['[', ']', '.'], '_', $name));
    $dotName = str_replace(['[', ']'], ['.', ''], $name);
    $current = old($dotName, $value instanceof \BackedEnum ? $value->value : $value);
@endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="label">{{ $label }} @if ($required)<span class="text-red-500">*</span>@endif</label>
    @if ($options !== null)
        <select id="{{ $id }}" name="{{ $name }}" class="input" @required($required) {{ $attributes->except(['class', 'id']) }}>
            <option value="">{{ $placeholder ?? __('— Select —') }}</option>
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>
    @elseif ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="4" class="input" placeholder="{{ $placeholder }}" @required($required) {{ $attributes->except(['class', 'id']) }}>{{ $current }}</textarea>
    @else
        <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ $current instanceof \DateTimeInterface ? $current->format('Y-m-d') : $current }}" placeholder="{{ $placeholder }}" class="input" @required($required) {{ $attributes->except(['class', 'id']) }}>
    @endif
    @if ($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
    @error($dotName)
        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
    @enderror
</div>
