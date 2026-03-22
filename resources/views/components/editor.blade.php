{{--
    Jodit WYSIWYG Editor Component
    ================================
    Usage:
        <x-jodit::editor name="content" />
        <x-jodit::editor name="content" :value="$post->content" wire-model="content" />
        <x-jodit::editor name="excerpt" :file-browser="false" :height="250" />

    Required layout stacks (add to your layout if not already present):
        @stack('after-styles')   — inside <head>
        @stack('after-scripts')  — before </body>

    Override stack names in config/jodit.php → assets.styles_stack / assets.scripts_stack
--}}

{{-- ── Load Jodit CSS once per page ────────────────────────────────── --}}
@once
    @push(config('jodit.assets.styles_stack', 'after-styles'))
        <link rel="stylesheet" href="{{ config('jodit.cdn_css') }}" />
    @endpush
@endonce

{{-- ── Load Jodit JS once per page ─────────────────────────────────── --}}
@once
    @push(config('jodit.assets.scripts_stack', 'after-scripts'))
        <script src="{{ config('jodit.cdn_js') }}"></script>
    @endpush
@endonce

{{--
    ── Editor wrapper ────────────────────────────────────────────────
    wire:ignore prevents Livewire's DOM diffing from destroying the
    editor after every re-render.  It is harmless in non-Livewire
    contexts (the attribute is simply ignored by the browser).
--}}
<div @if($wireModel) wire:ignore @endif class="jodit-wrapper">
    <textarea
        id="{{ $editorId }}"
        name="{{ $name }}"
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        @if($required) required @endif
        @if($readonly) readonly @endif
        @if($disabled) disabled @endif
        class="{{ $class }}"
    >{{ $value ?? '' }}</textarea>
</div>

{{-- ── Per-instance initialisation script ─────────────────────────── --}}
@push(config('jodit.assets.scripts_stack', 'after-scripts'))
<script>
(function () {
    'use strict';

    var EDITOR_ID        = @json($editorId);
    var WIRE_MODEL       = @json($wireModel);
    var HEIGHT           = @json($resolvedHeight);
    var WITH_BROWSER     = @json($fileBrowser);
    var CONNECTOR        = @json($connectorUrl);
    var BUTTONS          = @json($resolvedButtons);
    var DEBOUNCE_MS      = @json($debounce);
    var DEFAULTS         = @json(config('jodit.defaults'));
    var READONLY         = @json($readonly);
    var DISABLED         = @json($disabled);
    var LANGUAGE         = @json($resolvedLanguage);
    var INSTANCE_DISK    = @json($resolvedDisk);
    var INSTANCE_DIR     = @json($resolvedDirectory);

    // Returns extra data fields to scope requests to this editor instance.
    function instanceData() {
        var d = {};
        if (INSTANCE_DISK) { d.disk = INSTANCE_DISK; }
        if (INSTANCE_DIR)  { d.directory = INSTANCE_DIR; }
        return d;
    }

    // ------------------------------------------------------------------
    // Build the Jodit config object
    // ------------------------------------------------------------------
    function buildConfig(csrfToken) {
        var cfg = Object.assign({}, DEFAULTS, {
            height:  HEIGHT,
            buttons: BUTTONS,
        });

        if (READONLY) { cfg.readonly = true; }
        if (DISABLED) { cfg.disabled = true; }
        if (LANGUAGE) { cfg.language = LANGUAGE; }

        if (WITH_BROWSER) {
            if (CONNECTOR) {
                // ── Built-in connector (or 'custom' backend via connector-url) ──
                cfg.uploader = {
                    url:     CONNECTOR + '?action=upload',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    data:    instanceData(),
                    isSuccess: function (r) { return !!r.success; },
                    getMessage: function (r) { return r.message || ''; },
                    process: function (r) {
                        return {
                            files:       r.data.files,
                            baseurl:     r.data.baseurl,
                            newfilename: r.data.newfilename,
                            isImages:    r.data.isImages,
                            error:       r.success ? 0 : 1,
                            msg:         r.message || '',
                        };
                    },
                };

                cfg.filebrowser = {
                    ajax: {
                        url:        CONNECTOR,
                        headers:    { 'X-CSRF-TOKEN': csrfToken },
                        data:       instanceData(),
                        isSuccess:  function (r) { return !!r.success; },
                        getMessage: function (r) { return r.message || ''; },
                    },
                    uploader: {
                        url:     CONNECTOR + '?action=upload',
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                        data:    instanceData(),
                    },
                };
            }
        }

        return cfg;
    }

    // ------------------------------------------------------------------
    // Livewire v3 sync helper
    // ------------------------------------------------------------------
    function syncLivewire(el, value) {
        if (!WIRE_MODEL || !window.Livewire) return;

        var wireEl = el.closest('[wire\\:id]');
        if (!wireEl) return;

        var component = window.Livewire.find(wireEl.getAttribute('wire:id'));
        if (component) {
            component.set(WIRE_MODEL, value);
        }
    }

    // ------------------------------------------------------------------
    // Initialise (or re-initialise) the editor
    // ------------------------------------------------------------------
    function initEditor() {
        if (typeof Jodit === 'undefined') return;

        var el = document.getElementById(EDITOR_ID);
        if (!el) return;

        // Tear down any existing instance before re-mounting
        if (el._jodit && typeof el._jodit.destruct === 'function') {
            el._jodit.destruct();
        }

        var metaEl    = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = metaEl ? metaEl.getAttribute('content') : '';
        var editor    = Jodit.make(el, buildConfig(csrfToken));

        // Patch the filebrowser's open() so each invocation tells the backend
        // whether it was triggered from the image button (type=images) or the
        // file button (type=files), enabling server-side filtering.
        if (WITH_BROWSER && CONNECTOR) {
            var fb = editor.filebrowser;
            if (fb && typeof fb.open === 'function') {
                var _origFbOpen = fb.open.bind(fb);
                fb.open = function (callback, onlyImages) {
                    if (fb.options && fb.options.ajax) {
                        fb.options.ajax.data = Object.assign(
                            {},
                            instanceData(),
                            { type: onlyImages ? 'images' : 'files' }
                        );
                    }
                    return _origFbOpen(callback, onlyImages);
                };
            }
        }

        // Sync content changes back to Livewire (debounced)
        if (WIRE_MODEL && window.Livewire) {
            var timer = null;

            editor.events.on('change', function (newValue) {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    syncLivewire(el, newValue);
                }, DEBOUNCE_MS);
            });
        }
    }

    // ------------------------------------------------------------------
    // Bootstrap
    // ------------------------------------------------------------------

    // Run when the DOM (and Jodit script) are ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEditor);
    } else {
        initEditor();
    }

    // Re-initialise after Livewire SPA navigations
    document.addEventListener('livewire:navigated', initEditor);
}());
</script>
@endpush
