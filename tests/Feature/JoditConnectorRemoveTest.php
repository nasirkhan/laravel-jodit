<?php

namespace Nasirkhan\LaravelJodit\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Nasirkhan\LaravelJodit\Tests\TestCase;
use Orchestra\Testbench\Factories\UserFactory;

class JoditConnectorRemoveTest extends TestCase
{
    use RefreshDatabase;

    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->user = UserFactory::new()->create();
    }

    public function test_fileRemove_action_deletes_file(): void
    {
        Storage::disk('public')->put('uploads/test-image.jpg', 'fake-image-content');

        $response = $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'fileRemove',
                'name'   => 'test-image.jpg',
                'path'   => '/',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        Storage::disk('public')->assertMissing('uploads/test-image.jpg');
    }

    public function test_remove_action_also_deletes_file(): void
    {
        Storage::disk('public')->put('uploads/test-image.jpg', 'fake-image-content');

        $response = $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'remove',
                'name'   => 'test-image.jpg',
                'path'   => '/',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        Storage::disk('public')->assertMissing('uploads/test-image.jpg');
    }

    public function test_folderRemove_action_deletes_directory(): void
    {
        Storage::disk('public')->put('uploads/myfolder/file.txt', 'content');

        $response = $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'folderRemove',
                'name'   => 'myfolder',
                'path'   => '/',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        Storage::disk('public')->assertMissing('uploads/myfolder');
    }

    public function test_fileRemove_returns_error_when_file_not_found(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'fileRemove',
                'name'   => 'nonexistent.jpg',
                'path'   => '/',
            ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_fileRemove_returns_error_when_name_is_missing(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'fileRemove',
                'path'   => '/',
            ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_success_response_includes_data_key(): void
    {
        Storage::disk('public')->put('uploads/test-image.jpg', 'fake-image-content');

        $response = $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'fileRemove',
                'name'   => 'test-image.jpg',
                'path'   => '/',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }
}
