@props(['name', 'label', 'type' => 'text', 'placeholder' => '', 'required' => true, 'value' => ''])

<div class="form-group">
    <label for="{{ $name }}">{{ $label }}</label>
    @if($type === 'textarea')
        <textarea
            id="{{ $name }}"
            name="{{ $name }}"
            {{ $required ? 'required' : '' }}
            placeholder="{{ $placeholder }}"
            @if($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
        >{{ $value }}</textarea>
    @else
        <input
            type="{{ $type }}"
            id="{{ $name }}"
            name="{{ $name }}"
            {{ $required ? 'required' : '' }}
            placeholder="{{ $placeholder }}"
            value="{{ $value }}"
            @if($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
        >
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="form-error" role="alert">{{ $message }}</p>
    @enderror
</div>
