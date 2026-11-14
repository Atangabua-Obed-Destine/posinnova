{{--
    One-click entries from a doctors' list (App\Utils\ClinicOptions) under a text field: a click adds the
    entry to the field (or takes it out again), "+ Add" puts a new entry in the list.
    $list: list key; $target: the field's selector; $sep: ", " or "\n" between entries
--}}
<div class="dl-picker" data-list="{{ $list }}" data-target="{{ $target }}" data-sep="{{ $sep == "\n" ? 'nl' : 'comma' }}"></div>
