<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Jodit CDN URLs
    |--------------------------------------------------------------------------
    |
    | The CSS and JS files are served from unpkg by default. Change the URLs
    | here to pin a different Jodit version or to self-host the assets.
    |
    */

    'cdn_css' => 'https://unpkg.com/jodit@4.1.16/es2021/jodit.min.css',
    'cdn_js'  => 'https://unpkg.com/jodit@4.1.16/es2021/jodit.min.js',

    /*
    |--------------------------------------------------------------------------
    | Asset Blade Stacks
    |--------------------------------------------------------------------------
    |
    | Names of the @stack() stacks that the editor component pushes the CSS
    | and JS tags into.  Make sure your layout calls @stack('after-styles')
    | inside <head> and @stack('after-scripts') just before </body>.
    |
    */

    'assets' => [
        'styles_stack'  => 'after-styles',
        'scripts_stack' => 'after-scripts',
    ],

    /*
    |--------------------------------------------------------------------------
    | Connector Route
    |--------------------------------------------------------------------------
    |
    | The package can register its own connector route automatically.
    | Set `enabled` to false and register the route yourself (see README) if
    | you need a custom prefix or middleware stack.
    |
    */

    'route' => [
        'enabled'    => true,
        'prefix'     => 'jodit',
        'name'       => 'jodit.connector',
        'middleware' => ['web', 'auth', 'throttle:60,1'],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Storage
    |--------------------------------------------------------------------------
    |
    | The disk and base path used for all Jodit uploads and file browser
    | listings.  The disk must exist in config/filesystems.php.
    |
    */

    'disk'           => 'public',
    'base_path'      => 'uploads',
    'user_directory' => false,

    /*
    |--------------------------------------------------------------------------
    | Upload Constraints
    |--------------------------------------------------------------------------
    */

    'max_file_size'       => 10240,  // kilobytes
    'allowed_mimes'       => 'jpeg,jpg,png,gif,webp,pdf,doc,docx,xls,xlsx,zip,txt',
    'preserve_file_names' => false,

    /*
    |--------------------------------------------------------------------------
    | Editor Language
    |--------------------------------------------------------------------------
    |
    | UI language for the Jodit toolbar and dialogs. Set to null to let the
    | browser decide (Jodit auto-detects from navigator.language).
    | Examples: 'en', 'de', 'fr', 'ar', 'zh_cn'.
    |
    */

    'language' => null,

    /*
    |--------------------------------------------------------------------------
    | Default Editor Options
    |--------------------------------------------------------------------------
    |
    | Any key/value pair here is merged into the Jodit config object before
    | the editor is instantiated.  See https://xdsoft.net/jodit/docs/ for all
    | available options.
    |
    */

    'defaults' => [
        'height'               => 400,
        'toolbarSticky'        => true,
        'toolbarButtonSize'    => 'middle',
        'showCharsCounter'     => true,
        'showWordsCounter'     => true,
        'showXPathInStatusbar' => true,
        'hidePoweredByJodit'   => true,
        'defaultActionOnPaste' => 'insert_clear_html',
    ],

    /*
    |--------------------------------------------------------------------------
    | Toolbar Profiles
    |--------------------------------------------------------------------------
    |
    | Named button sets. Select a profile per-instance with the `profile` prop:
    |   <x-jodit::editor name="body" profile="simple" />
    |
    | `default_profile` is used when no `buttons` or `profile` prop is given.
    | Set to null to fall back to the `buttons` array defined above.
    |
    */

    'default_profile' => 'full',

    'profiles' => [
        'full' => [
            'undo', 'redo', '|',
            'bold', 'italic', 'underline', 'strikethrough', 'superscript', 'subscript', 'eraser', '|',
            'paragraph', 'font', 'fontsize', 'brush', 'classSpan', '|',
            'align', 'ul', 'ol', 'indent', 'outdent', '|',
            'cut', 'copy', 'paste', 'selectall', '|',
            'link', 'image', 'video', 'file', 'table', 'hr', 'symbols', '|',
            'source', '|',
            'find', 'spellcheck', 'preview', 'fullsize',
        ],
        'simple' => [
            'bold', 'italic', 'eraser', '|',
            'paragraph', 'align', 'ul', 'ol', '|',
            'link', 'image', 'table', 'hr', '|',
            'source', '|',
            'undo', 'redo',
        ],
        'minimal' => [
            'bold', 'italic', 'eraser', '|',
            'source', '|',
            'link',
        ],
    ],

];
