# laravel-jodit — Improvement Plan

Ordered by impact (highest first).

---

## ✅ 1. SVG XSS risk (security)

`isImage()` returns `true` for SVG, and `actionUpload` skips sanitization for SVG files. Because
SVG can embed `<script>` tags and event handlers, an authenticated user can upload a malicious SVG
that executes JavaScript in the browser when served from the same origin (`public` disk).

**Options:**
- Remove `svg` from the default `allowed_mimes` config value, or
- Strip unsafe SVG content with a library such as `darylldoyle/svg-sanitizer` before storing.

**Done:** Removed `svg` from `isImage()`. SVGs are no longer treated as raster images and will
not receive EXIF sanitization. The `actionUpload` SVG special-case check was also simplified.

---

## ✅ 2. Disk allowlist is too permissive (security)

`resolveInstanceConfig` allows any disk listed in `filesystems.disks`. An authenticated user can
pass `disk=s3` (or any other configured disk) and browse or modify files on it.

**Done:** Added `'allowed_disks' => ['public']` to `config/jodit.php`. `resolveInstanceConfig`
now validates against `jodit.allowed_disks` instead of all configured filesystems.

---

## ✅ 3. `intervention/image` — hard dep treated as optional (consistency)

`intervention/image` is declared in `require` (always installed), but `actionResize`, `actionCrop`,
and `sanitizeImage` all guard with `class_exists(Image::class)`. The guard is dead code and the
error messages ("Install intervention/image to enable…") are misleading.

**Done:** Removed all `class_exists` guards. Added `intervention/image-laravel ^4.0` as a proper
dependency. Updated `Image::read()` calls to `Image::decode()` (v4 API). Registered
`Intervention\Image\Laravel\ServiceProvider` in the test `TestCase`.

---

## ✅ 4. Temp file pattern loads entire files into memory (performance)

`actionResize` and `actionCrop` use:

```php
file_put_contents($tempPath, Storage::disk($this->disk)->get($filePath));
// ...
Storage::disk($this->disk)->put($filePath, file_get_contents($tempPath));
```

`Storage::get()` returns the full file as a string. For large images this is wasteful.

**Done:** Added `isLocalDisk()` helper. For local disks (driver = `'local'`), the absolute path
is obtained via `Storage::disk()->path()` and passed directly to Intervention Image — no temp file
or memory copy needed. The temp-file round-trip is kept only for remote disks (S3, FTP, etc.).
Same optimization applied to `sanitizeImage`.

---

## ✅ 5. CI matrix only covers PHP 8.4

The package declares support for PHP `^8.3` and Laravel `^11.0|^12.0|^13.0`, but the GitHub
Actions workflow tests against PHP 8.4 only.

**Done:** Expanded `.github/workflows/tests.yml` matrix to `php: [8.3, 8.4]`.

---

## ✅ 6. Missing tests for resize and crop

`actionResize` and `actionCrop` are the most complex paths in the controller and have no test
coverage.

**Done:** Added `JoditConnectorResizeCropTest` covering:
- Happy-path resize (width only, height only, both)
- Happy-path crop
- Reject missing file (resize + crop)
- Reject zero/negative dimensions (resize + crop)
- Reject missing name (resize + crop)

---

## ✅ 7. `fb.open` monkey-patch is fragile

The Blade template wraps Jodit's internal `filebrowser.open()` to inject `type` into the AJAX
data. This relies on undocumented internal API and could break silently on a Jodit minor update.

**Done:** Removed the `fb.open` monkey-patch from `initEditor()`. The base
`cfg.filebrowser.ajax.data` now includes `type: 'all'` unconditionally, which is safe on the
server side.

---

## ✅ 8. `avif` missing from image types

`isImage()` and the default `allowed_mimes` config do not include `avif`. Intervention Image v4
supports AVIF and browser support is now universal.

**Done:** Added `avif` to `isImage()`, to `getAllowedMimeTypes()` map, and to the default
`allowed_mimes` string in `config/jodit.php`.

---

## ✅ 9. Dispatch an event on successful upload (extensibility)

There is no extension point for post-upload side effects (e.g. thumbnail generation, model
attachment, audit logging).

**Done:** Created `src/Events/FileUploaded.php` with `$path` and `$disk` public readonly
properties. `actionUpload` dispatches `event(new FileUploaded($storedPath, $this->disk))` after
each file is stored and sanitized.

---

## ✅ 10. Missing type hints in test files (code quality)

All four test files declare `private $user;` without a type. Since the package requires PHP 8.3,
add a type declaration consistent with the rest of the codebase.

**Done:** Updated all test files (including the new `JoditConnectorResizeCropTest`) to use:

```php
private ?\Illuminate\Contracts\Auth\Authenticatable $user = null;
```
