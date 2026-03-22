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
    var FILE_MGR_BACKEND = @json($fileMgrBackend);
    var FILE_MGR_CONFIG  = @json($fileMgrConfig);

    // ------------------------------------------------------------------
    // Build the Jodit config object
    // ------------------------------------------------------------------
    function buildConfig(csrfToken) {
        var cfg = Object.assign({}, DEFAULTS, {
            height:  HEIGHT,
            buttons: BUTTONS,
        });

        if (WITH_BROWSER) {
            if (FILE_MGR_BACKEND === 'unisharp' && FILE_MGR_CONFIG) {
                // ── UniSharp LFM popup ─────────────────────────────────────
                // Opens LFM in a popup window; when the user picks a file the
                // global SetUrl callback inserts it into the editor.
                var lfmSize = (FILE_MGR_CONFIG.window_size || '900x600').split('x');
                var lfmW    = parseInt(lfmSize[0], 10) || 900;
                var lfmH    = parseInt(lfmSize[1], 10) || 600;

                cfg.extraButtons = [
                    {
                        name:    'lfmBrowse',
                        tooltip: 'File Manager',
                        icon:    'image',
                        exec:    function (editor) {
                            var lfmUrl = FILE_MGR_CONFIG.browse_url
                                + '?type=' + (FILE_MGR_CONFIG.type || 'Images');

                            var popup = window.open(
                                lfmUrl,
                                'lfm',
                                'scrollbars=1,width=' + lfmW + ',height=' + lfmH
                            );

                            window.SetUrl = function (url) {
                                var tag = /\.(png|jpe?g|gif|webp|svg|bmp)$/i.test(url)
                                    ? '<img src="' + url + '" alt="" />'
                                    : '<a href="' + url + '">' + url + '</a>';
                                editor.selection.insertHTML(tag);
                                if (popup) { popup.close(); }
                            };
                        },
                    },
                ];
            } else if (CONNECTOR) {
                // ── Built-in connector (or 'custom' backend via connector-url) ──
                cfg.uploader = {
                    url:     CONNECTOR + '?action=upload',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
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
                        isSuccess:  function (r) { return !!r.success; },
                        getMessage: function (r) { return r.message || ''; },
                    },
                    uploader: {
                        url:     CONNECTOR + '?action=upload',
                        headers: { 'X-CSRF-TOKEN': csrfToken },
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
        if (WITH_BROWSER && FILE_MGR_BACKEND !== 'unisharp' && CONNECTOR) {
            var fb = editor.filebrowser;
            if (fb && typeof fb.open === 'function') {
                var _origFbOpen = fb.open.bind(fb);
                fb.open = function (callback, onlyImages) {
                    if (fb.options && fb.options.ajax) {
                        fb.options.ajax.data = Object.assign(
                            {},
                            typeof fb.options.ajax.data === 'object' ? fb.options.ajax.data : {},
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
