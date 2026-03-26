# Laravel Jodit

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nasirkhan/laravel-jodit.svg?style=flat-square)](https://packagist.org/packages/nasirkhan/laravel-jodit)
[![Total Downloads](https://img.shields.io/packagist/dt/nasirkhan/laravel-jodit.svg?style=flat-square)](https://packagist.org/packages/nasirkhan/laravel-jodit)
[![License](https://img.shields.io/packagist/l/nasirkhan/laravel-jodit.svg?style=flat-square)](https://packagist.org/packages/nasirkhan/laravel-jodit)

A Laravel package that integrates the [Jodit](https://xdsoft.net/jodit/) WYSIWYG editor via a reusable Blade component. Works seamlessly in plain Blade templates, Blade view components, and Livewire components, with a built-in server-side file browser/uploader connector.

This package is used in [Laravel Starter](https://github.com/nasirkhan/laravel-starter) though it is framework-agnostic and can be dropped into any Laravel app.

Packagist: [nasirkhan/laravel-jodit](https://packagist.org/packages/nasirkhan/laravel-jodit)  
Tags: `laravel`, `jodit`, `wysiwyg`, `editor`, `blade`, `livewire`, `rich-text`, `file-browser`

---

## Features

- **One Blade component** — `<x-jodit::editor name="content" />` covers all use cases
- **Livewire-ready** — pass `wire-model` and the editor syncs with your Livewire component
- **File browser + uploads** — bundled connector controller; configurable storage disk and path
- **CDN assets** — loads Jodit CSS/JS from unpkg; no build step required
- **Fully configurable** — publish the config to override defaults, CDN URLs, middleware, etc.
- **Flexible toolbar buttons** — supports named profiles, custom button arrays, separators, and dropdown-friendly controls like `align`

## Requirements

- PHP ^8.2
- Laravel ^11.0 || ^12.0 || ^13.0
- `intervention/image-laravel ^1.5` — only required for image **resize** and **crop** features

## Installation

```bash
composer require nasirkhan/laravel-jodit
```

The service provider is auto-discovered. Optionally publish the config:

```bash
php artisan vendor:publish --tag=jodit-config
```

---

## Usage

### 1. Ensure your layout has asset stacks

Your main layout must include the stacks that the component pushes assets into.  
The default stack names are `after-styles` (CSS) and `after-scripts` (JS).  
Add these to your layout if they are not already present:

```blade
{{-- In <head> --}}
@stack('after-styles')

{{-- Before </body> --}}
@stack('after-scripts')
```

You can change the stack names in `config/jodit.php`.

### 2. Drop the component into any form

**Plain Blade**
```blade
<x-jodit::editor name="content" :value="old('content', $post->content ?? '')" />
```

**With required + placeholder**
```blade
<x-jodit::editor
    name="content"
    :value="old('content')"
    placeholder="Write your post here…"
    :required="true"
/>
```

**Livewire — two-way sync**
```blade
<x-jodit::editor name="content" :value="$content" wire-model="content" />
```

Inside a Livewire component the wrapper is automatically set to `wire:ignore` so Livewire's DOM diffing does not destroy the editor. Changes are flushed back to the Livewire component via the JavaScript API with a 300 ms debounce.

**Disable file browser**
```blade
<x-jodit::editor name="excerpt" :file-browser="false" />
```

**Custom height and connector URL**
```blade
<x-jodit::editor
    name="body"
    :height="600"
    connector-url="{{ route('admin.jodit.connector') }}"
/>
```

---

## Component Props

| Prop | Type | Default | Description |
|---|---|---|---|
| `name` | string | — | `<textarea name>` / form field name (required) |
| `id` | string | `jodit_{name}` | Custom HTML id for the textarea |
| `value` | string | `''` | Initial HTML content |
| `placeholder` | string | `null` | Textarea placeholder |
| `class` | string | `''` | Extra CSS classes on the textarea |
| `height` | int | config default (400) | Editor height in pixels |
| `file-browser` | bool | `true` | Enable Jodit file browser / uploader |
| `connector-url` | string | auto from config | Override the connector endpoint URL |
| `wire-model` | string | `null` | Livewire model property to keep in sync |
| `required` | bool | `false` | Add `required` attribute to the textarea |
| `buttons` | array\|string | config default | Custom toolbar button list (see [Buttons Reference](#buttons-reference)) |
| `debounce` | int | `300` | Livewire sync debounce in milliseconds |

### Passing buttons

You can pass the `buttons` prop as a **PHP array** (`:buttons=`), a **JSON string**, or a **PHP-style array string**:

```blade
{{-- PHP array (recommended) --}}
<x-jodit::editor name="content" :buttons="['bold', 'italic', 'underline', '|', 'link', 'image']" />

{{-- PHP-style array string (no colon prefix needed) --}}
<x-jodit::editor name="content" buttons="['bold', 'italic', 'underline', '|', 'link', 'image']" />

{{-- JSON string --}}
<x-jodit::editor name="content" buttons='["bold", "italic", "underline", "|", "link", "image"]' />
```

### Common toolbar examples

Use `align` when you want a single alignment dropdown instead of separate `left`, `center`, `right`, and `justify` buttons:

```blade
<x-jodit::editor
    name="content"
    :buttons="['bold', 'italic', '|', 'align', '|', 'ul', 'ol', '|', 'link', 'image']"
/>
```

Use the package's richer preset when you want the full toolbar profile:

```blade
<x-jodit::editor name="content" profile="full" />
```

---

## Configuration

```php
// config/jodit.php (after publishing)

return [
    // Jodit CDN URLs — change the version or point to a custom build
    'cdn_css' => 'https://unpkg.com/jodit@4.1.16/es2021/jodit.min.css',
    'cdn_js'  => 'https://unpkg.com/jodit@4.1.16/es2021/jodit.min.js',

    // Asset stacks used by the Blade component
    'assets' => [
        'styles_stack'  => 'after-styles',
        'scripts_stack' => 'after-scripts',
    ],

    // Connector route settings
    'route' => [
        'enabled'    => true,
        'prefix'     => 'jodit',
        'name'       => 'jodit.connector',
        'middleware' => ['web', 'auth'],
    ],

    // Storage
    'disk'      => 'public',
    'base_path' => 'jodit',

    // Upload constraints
    'max_file_size'  => 10240,   // kilobytes
    'allowed_mimes'  => 'jpeg,jpg,png,gif,webp,svg,pdf,doc,docx,xls,xlsx,zip,txt',

    // Default editor options (passed directly to Jodit)
    'defaults' => [
        'height'               => 400,
        'toolbarSticky'        => true,
        'toolbarButtonSize'    => 'large',
        'showCharsCounter'     => false,
        'showWordsCounter'     => false,
        'showXPathInStatusbar' => false,
        'defaultActionOnPaste' => 'insert_clear_html',
    ],

    // Default toolbar buttons
    'buttons' => [
        'bold', 'italic', 'underline', 'strikethrough', '|',
        'left', 'center', 'right', '|',
        'ul', 'ol', '|',
        'font', 'fontsize', 'paragraph', 'brush', '|',
        'link', 'image', 'video', 'file', '|',
        'undo', 'redo',
    ],
];
```

### Registering the connector under a custom route

If you want the connector to live under your admin prefix with your own middleware, disable the package route and register it yourself:

```php
// config/jodit.php
'route' => [
    'enabled' => false,
],
```

```php
// routes/web.php
use Nasirkhan\LaravelJodit\Http\Controllers\JoditConnectorController;

Route::middleware(['web', 'auth', 'role:admin'])
    ->prefix('admin')
    ->group(function () {
        Route::any('jodit-connector', [JoditConnectorController::class, 'handle'])
            ->name('backend.jodit.connector');
    });
```

Then tell the component which route to use:

```blade
<x-jodit::editor
    name="content"
    connector-url="{{ route('backend.jodit.connector') }}"
/>
```

Or set a global default in `config/jodit.php`:

```php
'route' => [
    'enabled' => false,
    'name'    => 'backend.jodit.connector',  // used by component when no connector-url prop
],
```

---

## Buttons Reference

Use any of the names below in your `buttons` array. Use `|` as a visual separator between groups.

### Text Formatting

| Name | Description |
|---|---|
| `bold` | Bold |
| `italic` | Italic |
| `underline` | Underline |
| `strikethrough` | Strikethrough |
| `superscript` | Superscript |
| `subscript` | Subscript |
| `eraser` | Clear formatting |

### Alignment

| Name | Description |
|---|---|
| `align` | Alignment dropdown (`left`, `center`, `right`, `justify`) |
| `left` | Align left |
| `center` | Align centre |
| `right` | Align right |
| `justify` | Justify |

### Lists & Indentation

| Name | Description |
|---|---|
| `ul` | Unordered list |
| `ol` | Ordered list |
| `indent` | Increase indent |
| `outdent` | Decrease indent |

### Block / Typography

| Name | Description |
|---|---|
| `paragraph` | Paragraph / Headings (H1–H6) |
| `font` | Font family |
| `fontsize` | Font size |
| `brush` | Text colour & background colour |
| `classSpan` | Apply CSS class to selection |

### Insert

| Name | Description |
|---|---|
| `link` | Insert / edit hyperlink |
| `image` | Insert image |
| `video` | Insert video (embed) |
| `file` | Insert file link |
| `table` | Insert table |
| `hr` | Horizontal rule |
| `symbols` | Special characters |

### Clipboard & History

| Name | Description |
|---|---|
| `undo` | Undo |
| `redo` | Redo |
| `cut` | Cut |
| `copy` | Copy |
| `paste` | Paste |
| `selectall` | Select all |

### View / Utility

| Name | Description |
|---|---|
| `source` | Toggle HTML source view |
| `fullsize` | Toggle fullscreen |
| `preview` | Live preview |
| `print` | Print |
| `find` | Find & replace |
| `spellcheck` | Spell check |
| `speech` | Speech recognition |

### Separators

| Name | Description |
|---|---|
| `\|` | Vertical separator bar |
| `\n` | Line break (start a new toolbar row) |

**Example — compact toolbar:**

```php
'buttons' => [
    'bold', 'italic', 'underline', 'strikethrough', 'eraser', '|',
    'ul', 'ol', '|',
    'paragraph', 'brush', '|',
    'link', 'image', '|',
    'undo', 'redo',
],
```

**Example — full editing toolbar:**

```php
'buttons' => [
    'source', '|',
    'bold', 'italic', 'underline', 'strikethrough', 'superscript', 'subscript', 'eraser', '|',
    'paragraph', 'font', 'fontsize', 'brush', 'classSpan', '|',
    'align', '|',
    'ul', 'ol', 'indent', 'outdent', '|',
    'cut', 'copy', 'paste', 'selectall', '|',
    'link', 'image', 'video', 'file', 'table', 'hr', 'symbols', '|',
    'undo', 'redo', '|',
    'find', 'spellcheck', 'speech', 'preview', 'print', 'fullsize',
],
```

---

## File Manager Backends

The `file_manager.backend` config key controls which file manager is wired up when `file-browser="true"` (the default).

### `builtin` (default)

Uses the package's own connector controller. No extra packages required.

```php
// config/jodit.php
'file_manager' => [
    'backend' => 'builtin',
],
```

### `unisharp` — UniSharp Laravel FileManager

Requires [`unisharp/laravel-filemanager`](https://github.com/UniSharp/laravel-filemanager) to be installed and its routes published.

```bash
composer require unisharp/laravel-filemanager
php artisan vendor:publish --tag=lfm_public
```

```php
// config/jodit.php
'file_manager' => [
    'backend' => 'unisharp',

    'unisharp' => [
        'browse_url'  => '/laravel-filemanager',
        'upload_url'  => '/laravel-filemanager/upload',
        'type'        => 'Images',   // 'Images' | 'Files'
        'window_size' => '900x600',
    ],
],
```

When this backend is active, a **File Manager** toolbar button replaces Jodit's native file browser. Clicking it opens the LFM popup; selecting a file inserts it into the editor automatically.

### `custom`

Point the editor at any server-side connector that speaks Jodit's filebrowser protocol. Pass the URL via the component's `connector-url` prop, or set `route.name` in the config:

```blade
<x-jodit::editor
    name="content"
    connector-url="{{ route('my.connector') }}"
/>
```

---

## License

MIT
