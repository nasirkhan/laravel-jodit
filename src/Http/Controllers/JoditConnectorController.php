<?php

namespace Nasirkhan\LaravelJodit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Server-side connector for the Jodit file browser.
 *
 * Handles all actions sent by the Jodit editor's filebrowser / uploader
 * plugins. All files are stored on the configured disk under $basePath.
 *
 * Route: ANY {prefix}/connector  (name: jodit.connector by default)
 */
class JoditConnectorController extends Controller
{
    protected string $disk;

    protected string $basePath;

    public function __construct()
    {
        $this->disk = config('jodit.disk', 'public');
        $this->basePath = config('jodit.base_path', 'jodit');
    }

    // ---------------------------------------------------------------
    // Entry point
    // ---------------------------------------------------------------

    public function handle(Request $request): JsonResponse
    {
        $this->resolveInstanceConfig($request);

        $action = (string) ($request->input('action') ?: 'files');

        return match ($action) {
            'files' => $this->actionFiles($request),
            'folders' => $this->actionFolders($request),
            'upload' => $this->actionUpload($request),
            'remove' => $this->actionRemove($request),
            'rename' => $this->actionRename($request),
            'create' => $this->actionCreate($request),
            'move' => $this->actionMove($request),
            'resize' => $this->actionResize($request),
            'crop' => $this->actionCrop($request),
            default => $this->sourceResponse($this->resolvedPath($request), [], []),
        };
    }

    // ---------------------------------------------------------------
    // Instance configuration resolver
    // ---------------------------------------------------------------

    /**
     * Override disk and base path for this request when the caller passes
     * per-instance values. The disk is validated against the application's
     * configured filesystems to prevent arbitrary access.
     */
    protected function resolveInstanceConfig(Request $request): void
    {
        // Per-instance disk — must be one of the configured filesystems
        $requestedDisk = (string) $request->input('disk', '');
        $allowedDisks = array_keys(config('filesystems.disks', []));

        if ($requestedDisk !== '' && in_array($requestedDisk, $allowedDisks, true)) {
            $this->disk = $requestedDisk;
        }

        // Per-instance directory override
        $requestedDir = ltrim((string) $request->input('directory', ''), '/');
        $requestedDir = str_replace(['../', '..'.DIRECTORY_SEPARATOR, '..'], '', $requestedDir);

        if ($requestedDir !== '') {
            $this->basePath = trim($requestedDir, '/');
        }

        // Per-user directory scoping
        if (config('jodit.user_directory', false) && auth()->check()) {
            $this->basePath = trim($this->basePath.'/users/'.auth()->id(), '/');
        }
    }

    // ---------------------------------------------------------------
    // Actions
    // ---------------------------------------------------------------

    protected function actionFiles(Request $request): JsonResponse
    {
        $path = $this->resolvedPath($request);
        $type = (string) $request->input('type', 'all');
        $search = (string) $request->input('search', '');
        $sortBy = (string) $request->input('sortBy', 'name');
        $order = strtolower((string) $request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';

        $this->ensureDirectory($path);

        $files = collect(Storage::disk($this->disk)->files($path))
            ->map(function (string $file): array {
                $name = basename($file);
                $isImage = $this->isImage($name);
                $bytes = Storage::disk($this->disk)->size($file);
                $modified = Storage::disk($this->disk)->lastModified($file);

                return [
                    'file' => $name,
                    'thumb' => $isImage ? $name : null,
                    'changed' => date('m/d/Y g:i A', $modified),
                    'changed_ts' => $modified,
                    'size' => $this->formatBytes($bytes),
                    'size_bytes' => $bytes,
                    'isImage' => $isImage,
                ];
            })
            ->filter(function (array $item) use ($type): bool {
                if ($type === 'images') {
                    return $item['isImage'];
                }

                if ($type === 'files') {
                    return ! $item['isImage'];
                }

                return true;
            })
            ->when($search !== '', fn ($c) => $c->filter(
                fn ($item) => str_contains(mb_strtolower($item['file']), mb_strtolower($search))
            ))
            ->sortBy(function (array $item) use ($sortBy): mixed {
                return match ($sortBy) {
                    'size' => $item['size_bytes'],
                    'changed' => $item['changed_ts'],
                    default => mb_strtolower($item['file']),
                };
            }, SORT_REGULAR, $order === 'desc')
            ->map(fn ($item) => array_diff_key($item, array_flip(['size_bytes', 'changed_ts'])))
            ->values()
            ->all();

        return $this->sourceResponse($path, $files, []);
    }

    protected function actionFolders(Request $request): JsonResponse
    {
        $path = $this->resolvedPath($request);
        $this->ensureDirectory($path);

        $folders = collect(Storage::disk($this->disk)->directories($path))
            ->map(fn (string $dir): string => basename($dir))
            ->values()
            ->all();

        return $this->sourceResponse($path, [], $folders);
    }

    protected function actionUpload(Request $request): JsonResponse
    {
        $maxSize = (int) config('jodit.max_file_size', 10240);
        $allowedMimes = config('jodit.allowed_mimes', 'jpeg,jpg,png,gif,webp,svg,pdf,doc,docx,xls,xlsx,zip,txt');

        $request->validate([
            'files' => 'required',
            'files.*' => "file|max:{$maxSize}|mimes:{$allowedMimes}",
        ]);

        $path = $this->resolvedPath($request);
        $this->ensureDirectory($path);

        $uploaded = [];
        $isImages = [];

        foreach ($request->file('files') as $file) {
            if (config('jodit.preserve_file_names', false)) {
                $ext = $file->getClientOriginalExtension();
                $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $name = Str::slug($base).'.'.$ext;
                $counter = 0;
                while (Storage::disk($this->disk)->exists($path.'/'.$name)) {
                    $counter++;
                    $name = Str::slug($base).'-'.$counter.'.'.$ext;
                }
            } else {
                $name = $file->hashName();
            }

            $file->storeAs($path, $name, $this->disk);
            $uploaded[] = '/storage/'.$path.'/'.$name;
            $isImages[] = $this->isImage($name);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'baseurl' => '',
                'newfilename' => basename($uploaded[0] ?? ''),
                'files' => $uploaded,
                'isImages' => $isImages,
            ],
        ]);
    }

    protected function actionRemove(Request $request): JsonResponse
    {
        $path = $this->resolvedPath($request);
        $name = basename((string) $request->input('name', ''));

        if (! $name) {
            return $this->error('Name is required.');
        }

        $target = $path.'/'.$name;

        if (Storage::disk($this->disk)->exists($target)) {
            Storage::disk($this->disk)->delete($target);

            return response()->json(['success' => true]);
        }

        if (Storage::disk($this->disk)->directoryExists($target)) {
            Storage::disk($this->disk)->deleteDirectory($target);

            return response()->json(['success' => true]);
        }

        return $this->error('File or folder not found.');
    }

    protected function actionRename(Request $request): JsonResponse
    {
        $path = $this->resolvedPath($request);
        $name = basename((string) $request->input('name', ''));
        $newName = basename((string) $request->input('newname', ''));

        if (! $name || ! $newName) {
            return $this->error('Both name and newname are required.');
        }

        $oldPath = $path.'/'.$name;
        $newPath = $path.'/'.$newName;

        if (! Storage::disk($this->disk)->exists($oldPath)) {
            return $this->error('File not found.');
        }

        Storage::disk($this->disk)->move($oldPath, $newPath);

        return response()->json(['success' => true]);
    }

    protected function actionCreate(Request $request): JsonResponse
    {
        $path = $this->resolvedPath($request);
        $name = Str::slug((string) $request->input('name', ''));

        if (! $name) {
            return $this->error('Folder name is required.');
        }

        Storage::disk($this->disk)->makeDirectory($path.'/'.$name);

        return response()->json(['success' => true]);
    }

    protected function actionMove(Request $request): JsonResponse
    {
        $path = $this->resolvedPath($request);
        $name = basename((string) $request->input('name', ''));

        $rawNewPath = ltrim((string) $request->input('newpath', '/'), '/');
        $rawNewPath = str_replace(['../', '..'.DIRECTORY_SEPARATOR, '..'], '', $rawNewPath);
        $newBasePath = trim($this->basePath.'/'.$rawNewPath, '/');

        if (! $name) {
            return $this->error('Name is required.');
        }

        $oldPath = $path.'/'.$name;
        $newPath = $newBasePath.'/'.$name;

        if (! Storage::disk($this->disk)->exists($oldPath)) {
            return $this->error('File not found.');
        }

        Storage::disk($this->disk)->move($oldPath, $newPath);

        return response()->json(['success' => true]);
    }

    protected function actionResize(Request $request): JsonResponse
    {
        if (! class_exists(Image::class)) {
            return $this->error('Install intervention/image-laravel to enable image resize.');
        }

        $path = $this->resolvedPath($request);
        $name = basename((string) $request->input('name', ''));
        $width = (int) $request->input('width', 0);
        $height = (int) $request->input('height', 0);

        if (! $name) {
            return $this->error('Name is required.');
        }

        $filePath = $path.'/'.$name;

        if (! Storage::disk($this->disk)->exists($filePath)) {
            return $this->error('File not found.');
        }

        $absolutePath = Storage::disk($this->disk)->path($filePath);
        $image = Image::read($absolutePath);

        if ($width && $height) {
            $image->scale(width: $width, height: $height);
        } elseif ($width) {
            $image->scale(width: $width);
        } elseif ($height) {
            $image->scale(height: $height);
        }

        $image->save($absolutePath);

        return response()->json(['success' => true]);
    }

    protected function actionCrop(Request $request): JsonResponse
    {
        if (! class_exists(Image::class)) {
            return $this->error('Install intervention/image-laravel to enable image crop.');
        }

        $path = $this->resolvedPath($request);
        $name = basename((string) $request->input('name', ''));
        $width = (int) $request->input('width', 0);
        $height = (int) $request->input('height', 0);
        $x = (int) $request->input('x', 0);
        $y = (int) $request->input('y', 0);

        if (! $name) {
            return $this->error('Name is required.');
        }

        $filePath = $path.'/'.$name;

        if (! Storage::disk($this->disk)->exists($filePath)) {
            return $this->error('File not found.');
        }

        $absolutePath = Storage::disk($this->disk)->path($filePath);
        $image = Image::read($absolutePath);
        $image->crop($width ?: 100, $height ?: 100, $x, $y);
        $image->save($absolutePath);

        return response()->json(['success' => true]);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Resolve the storage path for the current request.
     * Strips directory-traversal sequences from the provided path.
     */
    protected function resolvedPath(Request $request): string
    {
        $relative = ltrim((string) $request->input('path', '/'), '/');
        $relative = str_replace(['../', '..'.DIRECTORY_SEPARATOR, '..'], '', $relative);

        return trim($this->basePath.'/'.$relative, '/');
    }

    protected function ensureDirectory(string $path): void
    {
        if (! Storage::disk($this->disk)->exists($path)) {
            Storage::disk($this->disk)->makeDirectory($path);
        }
    }

    /**
     * Build the standard Jodit "sources" response consumed by the file browser.
     */
    protected function sourceResponse(string $storagePath, array $files, array $folders): JsonResponse
    {
        $displayPath = ltrim(Str::after($storagePath, $this->basePath), '/') ?: '/';
        $baseUrl = '/storage/'.rtrim($storagePath, '/').'/';
        $folderObjects = array_map(fn (string $name): array => ['name' => $name], $folders);

        return response()->json([
            'success' => true,
            'data' => [
                'sources' => [
                    [
                        'name' => 'default',
                        'path' => $displayPath,
                        'baseurl' => $baseUrl,
                        'files' => $files,
                        'folders' => $folderObjects,
                    ],
                ],
            ],
        ]);
    }

    protected function isImage(string $filename): bool
    {
        return in_array(
            strtolower(pathinfo($filename, PATHINFO_EXTENSION)),
            ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'],
            true
        );
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 2).' MB';
        }
        if ($bytes >= 1_024) {
            return round($bytes / 1_024, 2).' KB';
        }

        return $bytes.' B';
    }

    protected function error(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], 400);
    }
}
