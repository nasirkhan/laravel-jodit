<?php

namespace Nasirkhan\LaravelJodit\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Nasirkhan\LaravelJodit\Tests\TestCase;
use Orchestra\Testbench\Factories\UserFactory;

class JoditConnectorResizeCropTest extends TestCase
{
    use RefreshDatabase;

    private ?\Illuminate\Contracts\Auth\Authenticatable $user = null;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->user = UserFactory::new()->create();
    }

    protected function defineRoutes($router): void
    {
        $router->get('/login', fn () => response('Login', 200))->name('login');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Upload a real JPEG image and return the stored filename.
     */
    private function uploadImage(string $filename = 'photo.jpg', int $width = 200, int $height = 200): string
    {
        $file = UploadedFile::fake()->image($filename, $width, $height);
        Storage::disk('public')->put('uploads/'.$filename, file_get_contents($file->getPathname()));

        return $filename;
    }

    // ---------------------------------------------------------------
    // Resize — happy path
    // ---------------------------------------------------------------

    public function test_resize_with_width_only_succeeds(): void
    {
        $name = $this->uploadImage('photo.jpg', 400, 300);

        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'resize',
                'name'   => $name,
                'width'  => 100,
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_resize_with_height_only_succeeds(): void
    {
        $name = $this->uploadImage('photo.jpg', 400, 300);

        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'resize',
                'name'   => $name,
                'height' => 100,
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_resize_with_width_and_height_succeeds(): void
    {
        $name = $this->uploadImage('photo.jpg', 400, 300);

        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'resize',
                'name'   => $name,
                'width'  => 100,
                'height' => 80,
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    // ---------------------------------------------------------------
    // Crop — happy path
    // ---------------------------------------------------------------

    public function test_crop_succeeds(): void
    {
        $name = $this->uploadImage('photo.jpg', 400, 300);

        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'crop',
                'name'   => $name,
                'width'  => 100,
                'height' => 80,
                'x'      => 10,
                'y'      => 10,
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    // ---------------------------------------------------------------
    // Reject missing file
    // ---------------------------------------------------------------

    public function test_resize_returns_error_when_file_not_found(): void
    {
        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'resize',
                'name'   => 'nonexistent.jpg',
                'width'  => 100,
            ])
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_crop_returns_error_when_file_not_found(): void
    {
        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'crop',
                'name'   => 'nonexistent.jpg',
                'width'  => 100,
                'height' => 80,
            ])
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    // ---------------------------------------------------------------
    // Reject zero / negative dimensions
    // ---------------------------------------------------------------

    public function test_resize_returns_error_when_both_dimensions_are_zero(): void
    {
        $name = $this->uploadImage('photo.jpg');

        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'resize',
                'name'   => $name,
                'width'  => 0,
                'height' => 0,
            ])
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_crop_returns_error_when_dimensions_are_zero(): void
    {
        $name = $this->uploadImage('photo.jpg');

        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'crop',
                'name'   => $name,
                'width'  => 0,
                'height' => 0,
            ])
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_crop_returns_error_when_dimensions_are_negative(): void
    {
        $name = $this->uploadImage('photo.jpg');

        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'crop',
                'name'   => $name,
                'width'  => -10,
                'height' => -10,
            ])
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    // ---------------------------------------------------------------
    // Reject missing name
    // ---------------------------------------------------------------

    public function test_resize_returns_error_when_name_is_missing(): void
    {
        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'resize',
                'width'  => 100,
            ])
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_crop_returns_error_when_name_is_missing(): void
    {
        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'crop',
                'width'  => 100,
                'height' => 80,
            ])
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }
}
