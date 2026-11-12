{{--
    PosInnova: unfinished-purchase recovery (public/js/purchase-recovery.js).
    On Add Purchase it offers back a purchase lost to a reload; on the purchase list it
    forgets the copy once the save is confirmed (or keeps it when the save failed).
--}}
<div id="purchase_recovery" class="hide"
    data-page="{{ $recovery_page }}"
    data-user-id="{{ auth()->user()->id }}"
    data-business-id="{{ session('user.business_id') }}"
    data-max-age-mins="720"
    data-has-errors="{{ isset($errors) && $errors->any() ? 1 : 0 }}"
    data-lang-heading="{{ __('lang_v1.pr_recovery_heading') }}"
    data-lang-help="{{ __('lang_v1.pr_recovery_help', ['count' => '__COUNT__', 'supplier' => '__SUPPLIER__', 'time' => '__TIME__']) }}"
    data-lang-help-saving="{{ __('lang_v1.pr_recovery_help_saving', ['count' => '__COUNT__', 'supplier' => '__SUPPLIER__', 'time' => '__TIME__']) }}"
    data-lang-restore="{{ __('lang_v1.pr_recovery_restore') }}"
    data-lang-discard="{{ __('lang_v1.pr_recovery_discard') }}"
    data-lang-restoring="{{ __('lang_v1.pr_recovery_restoring') }}"
    data-lang-restored="{{ __('lang_v1.pr_recovery_restored', ['count' => '__COUNT__']) }}"
    data-lang-discarded="{{ __('lang_v1.pr_recovery_discarded') }}"
    data-lang-skipped="{{ __('lang_v1.pr_recovery_skipped', ['products' => '__PRODUCTS__']) }}"
    data-lang-document="{{ __('lang_v1.pr_recovery_document', ['name' => '__NAME__']) }}"
    data-lang-kept="{{ __('lang_v1.pr_recovery_kept') }}"
    data-lang-no-supplier="{{ __('lang_v1.pr_recovery_no_supplier') }}"></div>
<script src="{{ asset('js/purchase-recovery.js?v=' . $asset_v) }}"></script>
