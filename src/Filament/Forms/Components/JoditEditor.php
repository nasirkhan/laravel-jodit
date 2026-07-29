<?php

namespace Nasirkhan\LaravelJodit\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Forms\Components\Concerns\HasPlaceholder;
use Filament\Forms\Components\Field;
use LogicException;

if (! class_exists(Field::class)) {
    throw new LogicException(
        'The Filament Jodit editor requires filament/forms. Install it with: composer require filament/forms:^5.0',
    );
}

class JoditEditor extends Field
{
    use CanBeReadOnly;
    use HasPlaceholder;

    protected string $view = 'jodit::filament.forms.components.jodit-editor';

    protected string | array | Closure | null $buttons = null;

    protected string | Closure | null $connectorUrl = null;

    protected int | string | Closure | null $editorDebounce = 300;

    protected string | Closure | null $directory = null;

    protected string | Closure | null $disk = null;

    protected bool | Closure $fileBrowser = true;

    protected int | Closure $height = 0;

    protected string | Closure | null $language = null;

    protected string | Closure | null $profile = null;

    public function buttons(string | array | Closure | null $buttons): static
    {
        $this->buttons = $buttons;

        return $this;
    }

    /**
     * @return array<int, string> | string | null
     */
    public function getButtons(): array | string | null
    {
        return $this->evaluate($this->buttons);
    }

    public function connectorUrl(string | Closure | null $connectorUrl): static
    {
        $this->connectorUrl = $connectorUrl;

        return $this;
    }

    public function getConnectorUrl(): ?string
    {
        return $this->evaluate($this->connectorUrl);
    }

    public function debounce(int | string | Closure | null $delay = 500): static
    {
        $this->editorDebounce = $delay;

        if (! $delay instanceof Closure) {
            parent::debounce($delay);
        }

        return $this;
    }

    public function getDebounce(): int
    {
        $debounce = $this->evaluate($this->editorDebounce);

        if (is_numeric($debounce)) {
            return (int) $debounce;
        }

        if (str($debounce)->endsWith('ms')) {
            return (int) (string) str($debounce)->beforeLast('ms');
        }

        if (str($debounce)->endsWith('s')) {
            return ((int) (string) str($debounce)->beforeLast('s')) * 1000;
        }

        return (int) preg_replace('/[^0-9]/', '', (string) $debounce);
    }

    public function directory(string | Closure | null $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    public function getDirectory(): ?string
    {
        return $this->evaluate($this->directory);
    }

    public function disk(string | Closure | null $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function getDisk(): ?string
    {
        return $this->evaluate($this->disk);
    }

    public function fileBrowser(bool | Closure $fileBrowser = true): static
    {
        $this->fileBrowser = $fileBrowser;

        return $this;
    }

    public function hasFileBrowser(): bool
    {
        return (bool) $this->evaluate($this->fileBrowser);
    }

    public function height(int | Closure $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function getHeight(): int
    {
        return (int) $this->evaluate($this->height);
    }

    public function language(string | Closure | null $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->evaluate($this->language);
    }

    public function profile(string | Closure | null $profile): static
    {
        $this->profile = $profile;

        return $this;
    }

    public function getProfile(): ?string
    {
        return $this->evaluate($this->profile);
    }
}
