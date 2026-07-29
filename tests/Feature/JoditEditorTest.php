<?php

namespace Nasirkhan\LaravelJodit\Tests\Feature;

use Nasirkhan\LaravelJodit\Filament\Forms\Components\JoditEditor;
use Nasirkhan\LaravelJodit\Tests\TestCase;

class JoditEditorTest extends TestCase
{
    public function test_filament_field_view_is_registered(): void
    {
        $field = JoditEditor::make('content');

        $this->assertSame('jodit::filament.forms.components.jodit-editor', $field->getView());
        $this->assertTrue(view()->exists($field->getView()));
    }

    public function test_filament_field_evaluates_its_configuration(): void
    {
        $field = JoditEditor::make('content')
            ->buttons(fn (): array => ['bold', 'italic'])
            ->connectorUrl(fn (): string => '/jodit/connector')
            ->debounce(fn (): string => '2s')
            ->directory(fn (): string => 'articles')
            ->disk(fn (): string => 'public')
            ->fileBrowser(fn (): bool => false)
            ->height(fn (): int => 640)
            ->language(fn (): string => 'sk')
            ->profile(fn (): string => 'simple');

        $this->assertSame(['bold', 'italic'], $field->getButtons());
        $this->assertSame('/jodit/connector', $field->getConnectorUrl());
        $this->assertSame(2000, $field->getDebounce());
        $this->assertSame('articles', $field->getDirectory());
        $this->assertSame('public', $field->getDisk());
        $this->assertFalse($field->hasFileBrowser());
        $this->assertSame(640, $field->getHeight());
        $this->assertSame('sk', $field->getLanguage());
        $this->assertSame('simple', $field->getProfile());
    }

    public function test_filament_field_normalizes_debounce_values(): void
    {
        $this->assertSame(300, JoditEditor::make('default')->getDebounce());
        $this->assertSame(450, JoditEditor::make('milliseconds')->debounce('450ms')->getDebounce());
        $this->assertSame(3000, JoditEditor::make('seconds')->debounce('3s')->getDebounce());
        $this->assertSame(750, JoditEditor::make('numeric')->debounce(750)->getDebounce());
    }

    public function test_filament_is_only_an_optional_dependency(): void
    {
        $composer = json_decode(
            file_get_contents(dirname(__DIR__, 2).'/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertArrayNotHasKey('filament/forms', $composer['require']);
        $this->assertSame('^5.0', $composer['require-dev']['filament/forms']);
        $this->assertArrayHasKey('filament/forms', $composer['suggest']);
    }
}
