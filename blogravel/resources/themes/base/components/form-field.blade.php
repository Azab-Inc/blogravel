@props(['name', 'label', 'type' => 'text', 'placeholder' => '', 'required' => true, 'value' => ''])

<div class="form-group">
    <label for="{{ $name }}">{{ $label }}</label>
    @if($type === 'textarea')
        <textarea
            id="{{ $name }}"
            name="{{ $name }}"
            {{ $required ? 'required' : '' }}
            placeholder="{{ $placeholder }}"
        >{{ $value }}</textarea>
    @else
        <input
            type="{{ $type }}"
            id="{{ $name }}"
            name="{{ $name }}"
            {{ $required ? 'required' : '' }}
            placeholder="{{ $placeholder }}"
            value="{{ $value }}"
        >
    @endif
    @error($name)
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
