{{--
    PosInnova desktop workspace: the parts of the window on a wide screen.
    Included by layouts/app.blade.php (not on the POS screen or the customer
    display, which keep the whole window). Drawn here so nothing jumps while
    the page loads; public/js/desktop.js fills the menus and runs the tabs,
    public/pi-desktop.css lays them out. Narrow screens never see them.

    $part: head | titlebar | tabs | status
--}}
@if ($part === 'head')
    {{-- ?v= carries each file's change time: the server lets browsers keep a versioned file a year. --}}
    <link rel="stylesheet" href="{{ asset('pi-desktop.css?v=' . $asset_v . '.' . @filemtime(public_path('pi-desktop.css'))) }}">
    {{-- Before the page draws: a screen shown inside a workspace tab draws
         without the window's parts (pi-framed); a wide window gets the
         workspace (pi-desk). --}}
    <script>
        (function () {
            var d = document.documentElement;
            try { if (window.parent !== window && window.parent.PI_DESK) d.classList.add('pi-framed'); } catch (e) {}
            if (!d.classList.contains('pi-framed') && window.innerWidth >= 1024) d.classList.add('pi-desk');
            try { if (localStorage.getItem('pi.desk.sideFull') === '1') d.classList.add('pi-side-full'); } catch (e) {}
        })();
    </script>
@elseif ($part === 'titlebar')
    <div id="pi-titlebar" class="pi-desk-only no-print">
        <a href="{{ route('home') }}" class="pi-tb-brand" title="{{ __('desktop.home') }}">
            <span class="pi-tb-dot"></span>
            <span class="pi-tb-name">{{ Session::get('business.name') }}</span>
        </a>
        <nav id="pi-menubar" role="menubar" aria-label="{{ __('desktop.menu') }}"></nav>
        <span class="pi-tb-title">@yield('title')</span>
    </div>
@elseif ($part === 'tabs')
    <nav id="pi-tabs" class="pi-desk-only no-print" role="tablist" aria-label="{{ __('desktop.open_screens') }}"></nav>
@elseif ($part === 'status')
    <footer id="pi-status" class="pi-desk-only no-print">
        <span id="pi-conn" data-state="online" title="{{ __('desktop.conn_title') }}"><span class="pi-dot"></span><span data-conn-text>{{ __('desktop.conn_online') }}</span></span>
        <span class="pi-sb-item"><i class="fas fa-store"></i> {{ Session::get('business.name') }}</span>
        <span class="pi-sb-item"><i class="fas fa-user"></i> {{ Auth::user()->first_name }} {{ Auth::user()->last_name }}</span>
        <span class="pi-sb-gap"></span>
        <button type="button" class="pi-sb-btn" data-pi="palette" title="{{ __('desktop.go_to_title') }}"><kbd>Ctrl K</kbd> {{ __('desktop.go_to') }}</button>
        <span class="pi-sb-item pi-sb-keys">{{ __('desktop.key_tabs') }}</span>
        <span id="pi-clock" class="pi-sb-item"></span>
    </footer>
    @php
        $__pi_desk = [
            'home' => route('home'),
            'base' => url('/'),
            'who' => auth()->id() . ':' . session('business.id'),
            'business' => (string) session('business.name'),
            'text' => __('desktop'),
        ];
    @endphp
    <script>
        window.PI_DESK_CFG = {!! json_encode($__pi_desk, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};
    </script>
    <script src="{{ asset('js/desktop.js?v=' . $asset_v . '.' . @filemtime(public_path('js/desktop.js'))) }}"></script>
@endif
