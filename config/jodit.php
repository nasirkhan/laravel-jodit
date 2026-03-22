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
        'middleware' => ['web', 'auth'],
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

    'disk'      => 'public',
    'base_path' => 'jodit',

    /*
    |--------------------------------------------------------------------------
    | Upload Constraints
    |--------------------------------------------------------------------------
    */

    'max_file_size' => 10240,  // kilobytes
    'allowed_mimes' => 'jpeg,jpg,png,gif,webp,svg,pdf,doc,docx,xls,xlsx,zip,txt',

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
        'toolbarButtonSize'    => 'large',
        'showCharsCounter'     => false,
        'showWordsCounter'     => false,
        'showXPathInStatusbar' => false,
        'defaultActionOnPaste' => 'insert_clear_html',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Toolbar Buttons
    |--------------------------------------------------------------------------
    */

    'buttons' => [
        'bold', 'italic', 'underline', 'strikethrough', '|',
        'left', 'center', 'right', '|',
        'ul', 'ol', '|',
        'font', 'fontsize', 'paragraph', 'brush', '|',
        'link', 'image', 'video', 'file', '|',
        'undo', 'redo',
    ],

];
