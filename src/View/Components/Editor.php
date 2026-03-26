<?php

namespace Nasirkhan\LaravelJodit\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Editor extends Component
{
    public readonly string $editorId;

    public readonly ?string $connectorUrl;

    public readonly int $resolvedHeight;

    public readonly array $resolvedButtons;

    public readonly ?string $resolvedLanguage;

    public readonly ?string $resolvedDisk;

    public readonly ?string $resolvedDirectory;

    /**
     * @param string            $name         textarea name attribute (required)
     * @param string|null       $value        initial HTML content
     * @param string|null       $id           custom HTML id; defaults to jodit_{name}
     * @param string|null       $placeholder  textarea placeholder text
     * @param string            $class        extra CSS classes on the textarea element
     * @param int               $height       editor height in px (0 = use config default)
     * @param bool              $fileBrowser  enable file browser / uploader
     * @param string|null       $connectorUrl override connector endpoint URL
     * @param string|null       $wireModel    Livewire model property name for two-way sync
     * @param bool              $required     add required attribute to textarea
     * @param string|array|null $buttons      toolbar buttons — array, JSON string, or PHP-style string (null = config default)
     * @param int               $debounce     Livewire sync debounce in milliseconds
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $value = null,
        public readonly ?string $id = null,
        public readonly ?string $placeholder = null,
        public readonly string $class = '',
        public readonly int $height = 0,
        public readonly bool $fileBrowser = true,
        ?string $connectorUrl = null,
        public readonly ?string $wireModel = null,
        public readonly bool $required = false,
        public readonly string|array|null $buttons = null,
        public readonly int $debounce = 300,
        public readonly ?string $disk = null,
        public readonly ?string $directory = null,
        public readonly ?string $profile = null,
        public readonly bool $readonly = false,
        public readonly bool $disabled = false,
        public readonly ?string $language = null,
    ) {
        $this->editorId = $id ?? 'jodit_'.preg_replace('/[^a-z0-9]/i', '_', $name);
        $this->resolvedHeight = $height > 0 ? $height : (int) config('jodit.defaults.height', 400);
        $this->resolvedButtons = self::parseButtons($buttons, $profile);
        $this->resolvedLanguage = $language ?? config('jodit.language');
        $this->resolvedDisk = $disk;
        $this->resolvedDirectory = $directory;

        if ($connectorUrl !== null) {
            $this->connectorUrl = $connectorUrl;
        } elseif ($fileBrowser) {
            $routeName = config('jodit.route.name', 'jodit.connector');

            try {
                $this->connectorUrl = route($routeName);
            } catch (\InvalidArgumentException) {
                $this->connectorUrl = null;
            }
        } else {
            $this->connectorUrl = null;
        }
    }

    public function render(): View
    {
        return view('jodit::components.editor');
    }

    /**
     * Parse buttons from a value that may be a PHP array, JSON string,
     * PHP-style single-quoted array string, or a comma-separated string.
     * When $buttons is null, tries a named profile then falls back to
     * the 'buttons' config array.
     *
     * @param string|null $profile named profile key from config('jodit.profiles')
     *
     * @return array<int, string>
     */
    private static function parseButtons(string|array|null $buttons, ?string $profile = null): array
    {
        // Explicit buttons prop always wins
        if ($buttons !== null) {
            if (is_array($buttons)) {
                return $buttons;
            }

            // Try standard JSON: ["bold", "italic"]
            $decoded = json_decode($buttons, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            // Try PHP-style single-quoted array: ['bold', 'italic']
            $asJson = str_replace("'", '"', $buttons);
            $decoded = json_decode($asJson, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            // Fallback: treat as a comma-separated string
            return array_values(array_filter(array_map('trim', explode(',', $buttons))));
        }

        // Named profile (prop or config default)
        $profileName = $profile ?? config('jodit.default_profile');

        if ($profileName) {
            $profiles = (array) config('jodit.profiles', []);

            if (isset($profiles[$profileName])) {
                return (array) $profiles[$profileName];
            }
        }

        // Fall back to the 'full' profile
        $profiles = (array) config('jodit.profiles', []);

        return (array) ($profiles['full'] ?? []);
    }
}
