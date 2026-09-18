@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'id' => null, 'required' => false, 'autocomplete' => null])

<div class="field">
    <label class="field-label" for="{{ $id ?? $name }}">{{ $label }}@if($required)<span class="field-required" aria-hidden="true">*</span>@endif</label>
    <div @class(['password-field' => $type === 'password'])>
        <input {{ $attributes->class(['field-input']) }} id="{{ $id ?? $name }}" name="{{ $name }}" type="{{ $type }}" @required($required)
            @if($type !== 'password') value="{{ is_scalar($value) ? $value : '' }}" @endif
            @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if($errors->has($name)) aria-invalid="true" aria-describedby="{{ ($id ?? $name).'-error' }}"
            @elseif($hint) aria-describedby="{{ ($id ?? $name).'-hint' }}" @endif>
        @if($type === 'password')<button type="button" class="password-toggle" data-password-toggle aria-controls="{{ $id ?? $name }}" aria-label="Tampilkan kata sandi" aria-pressed="false" hidden><x-icon name="eye" /></button>@endif
    </div>
    @if($hint)<p class="field-hint" id="{{ ($id ?? $name).'-hint' }}">{{ $hint }}</p>@endif
    @error($name)<p class="field-error" id="{{ ($id ?? $name).'-error' }}">{{ $message }}</p>@enderror
</div>
