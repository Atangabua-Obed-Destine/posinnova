{{-- A value to copy into Meta's pages, with a Copy button. $text, optional $label, $multi (keeps line breaks). --}}
<div class="wa-copy {{ ! empty($multi) ? 'is-multi' : '' }}">
    @if (! empty($label))<div class="wa-copy-label">{{ $label }}</div>@endif
    <div class="wa-copy-row">
        <code class="wa-copy-text">{{ $text }}</code>
        <button type="button" class="btn btn-xs btn-default js-copy" data-copy="{{ $text }}"><i class="fas fa-copy"></i> @lang('clinic.wa_copy')</button>
    </div>
</div>
