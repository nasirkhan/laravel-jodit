<?php

namespace Nasirkhan\LaravelJodit\View\Components;

use Illuminate\View\Component;

class Editor extends Component
{
    public readonly string $editorId;

    public readonly ?string $connectorUrl;

    public readonly int $resolvedHeight;

    public readonly array $resolvedButtons;

    /**
     * @param  string       $name          textarea name attribute (required)
     * @param  string|null  $value         initial HTML content
     * @param  string|null  $id            custom HTML id; defaults to jodit_{name}
     * @param  string|null  $placeholder   textarea placeholder text
     * @param  string       $class         extra CSS classes on the textarea element
     * @param  int          $height        editor height in px (0 = use config default)
     * @param  bool         $fileBrowser   enable file browser / uploader
     * @param  string|null  $connectorUrl  override connector endpoint URL
     * @param  string|null  $wireModel     Livewire model property name for two-way sync
     * @param  bool         $required      add required attribute to textarea
     * @param  array|null   $buttons       custom toolbar buttons (null = use config default)
     * @param  int          $debounce      Livewire sync debounce in milliseconds
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
        public readonly ?array $buttons = null,
        public readonly int $debounce = 300,
    ) {
        $this->editorId        = $id ?? 'jodit_'.preg_replace('/[^a-z0-9]/i', '_', $name);
        $this->resolvedHeight  = $height > 0 ? $height : (int) config('jodit.defaults.height', 400);
        $this->resolvedButtons = $buttons ?? (array) config('jodit.buttons', []);

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

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('jodit::components.editor');
    }
}
