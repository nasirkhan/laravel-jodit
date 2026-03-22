# laravel-jodit

A Laravel package that integrates the [Jodit](https://xdsoft.net/jodit/) WYSIWYG editor via a reusable Blade component. Works seamlessly in plain Blade templates, Blade view components, and Livewire components, with a built-in server-side file browser/uploader connector.

---

## Features

- **One Blade component** — `<x-jodit::editor name="content" />` covers all use cases
- **Livewire-ready** — pass `wire-model` and the editor syncs with your Livewire component
- **File browser + uploads** — bundled connector controller; configurable storage disk and path
- **CDN assets** — loads Jodit CSS/JS from unpkg; no build step required
- **Fully configurable** — publish the config to override defaults, CDN URLs, middleware, etc.

---

## Requirements

- PHP 8.2+
- Laravel 11 / 12 / 13
- `intervention/image-laravel ^1.5` — only required for image **resize** and **crop** features

---

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
| `buttons` | array | config default | Custom toolbar button list |
| `debounce` | int | `300` | Livewire sync debounce in milliseconds |

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

## License

MIT
