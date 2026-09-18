@props(['prefix' => ''])

@if(session('status'))
    <div class="alert alert--success" role="status" data-flash-message data-flash-tone="success"><x-icon name="check" /><p data-flash-text>{{ session('status') }}</p></div>
@endif
@if($errors->any())
    <div class="alert alert--error" role="alert" tabindex="-1" data-error-summary aria-labelledby="error-summary-title">
        <x-icon name="info" />
        <div><strong id="error-summary-title">Periksa kembali isian berikut.</strong><ul>@foreach($errors->messages() as $field => $messages)@foreach($messages as $message)<li><a href="#{{ $prefix.$field }}">{{ $message }}</a></li>@endforeach @endforeach</ul></div>
    </div>
@endif
