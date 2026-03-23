<?php

namespace Nasirkhan\LaravelJodit\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Nasirkhan\LaravelJodit\Tests\TestCase;
use Orchestra\Testbench\Factories\UserFactory;

class JoditConnectorUploadTest extends TestCase
{
    use RefreshDatabase;

    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->user = UserFactory::new()->create();
    }

    protected function defineRoutes($router): void
    {
        // Provide a stub login route so the auth middleware can redirect unauthenticated users.
        $router->get('/login', fn () => response('Login', 200))->name('login');
    }

    // ---------------------------------------------------------------
    // Authentication
    // ---------------------------------------------------------------

    public function test_upload_requires_authentication(): void
    {
        $response = $this->post(route('jodit.connector'), [
            'action' => 'upload',
            'files'  => [UploadedFile::fake()->image('test.jpg')],
        ]);

        $response->assertRedirect('/login');
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    public function test_upload_succeeds_with_valid_image(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'upload',
                'files'  => [UploadedFile::fake()->image('photo.jpg', 100, 100)],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertNotEmpty($response->json('data.files'));
    }

    public function test_upload_returns_expected_json_structure(): void
    {
        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'fileUpload',
                'files'  => [UploadedFile::fake()->image('photo.png', 50, 50)],
            ])
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'baseurl',
                    'newfilename',
                    'files',
                    'isImages',
                ],
            ]);
    }

    public function test_upload_stores_file_on_configured_disk(): void
    {
        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'upload',
                'files'  => [UploadedFile::fake()->image('photo.jpg', 100, 100)],
            ])
            ->assertStatus(200);

        $this->assertNotEmpty(Storage::disk('public')->allFiles('uploads'));
    }

    public function test_upload_marks_images_correctly_in_response(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'upload',
                'files'  => [UploadedFile::fake()->image('photo.jpg', 100, 100)],
            ]);

        $response->assertStatus(200);

        $isImages = $response->json('data.isImages');
        $this->assertIsArray($isImages);
        $this->assertTrue($isImages[0]);
    }

    // ---------------------------------------------------------------
    // Validation — file size
    // ---------------------------------------------------------------

    public function test_upload_rejects_file_exceeding_max_size(): void
    {
        // Reduce the limit to 100 KB so we can create a small oversized file in tests.
        config(['jodit.max_file_size' => 100]);

        $this->actingAs($this->user)
            ->post(
                route('jodit.connector'),
                ['action' => 'upload', 'files' => [UploadedFile::fake()->create('large.jpg', 200, 'image/jpeg')]],
                ['Accept' => 'application/json'],
            )
            ->assertStatus(422);
    }

    // ---------------------------------------------------------------
    // Validation — MIME / extension
    // ---------------------------------------------------------------

    public function test_upload_rejects_disallowed_file_extension(): void
    {
        $this->actingAs($this->user)
            ->post(
                route('jodit.connector'),
                ['action' => 'upload', 'files' => [UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream')]],
                ['Accept' => 'application/json'],
            )
            ->assertStatus(422);
    }

    public function test_upload_rejects_php_file_regardless_of_extension(): void
    {
        // A PHP file renamed to .jpg should be rejected — either by Laravel's mimes
        // validation or by our secondary finfo check (returns 400).
        $tmpFile = tempnam(sys_get_temp_dir(), 'jodit_test_');
        file_put_contents($tmpFile, "<?php echo 'malicious'; ?>");
        $file = new UploadedFile($tmpFile, 'exploit.jpg', 'image/jpeg', null, true);

        $response = $this->actingAs($this->user)
            ->post(
                route('jodit.connector'),
                ['action' => 'upload', 'files' => [$file]],
                ['Accept' => 'application/json'],
            );

        // Either mimes validation (422) or finfo check (400) must reject the file.
        $this->assertContains($response->status(), [400, 422]);
        $this->assertFalse((bool) $response->json('success'));
    }

    public function test_upload_rejects_html_file_disguised_as_image(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'jodit_test_');
        file_put_contents($tmpFile, '<html><script>alert(1)</script></html>');
        $file = new UploadedFile($tmpFile, 'xss.jpg', 'image/jpeg', null, true);

        $response = $this->actingAs($this->user)
            ->post(
                route('jodit.connector'),
                ['action' => 'upload', 'files' => [$file]],
                ['Accept' => 'application/json'],
            );

        $this->assertContains($response->status(), [400, 422]);
        $this->assertFalse((bool) $response->json('success'));
    }

    // ---------------------------------------------------------------
    // preserve_file_names config
    // ---------------------------------------------------------------

    public function test_upload_uses_original_name_when_preserve_file_names_is_enabled(): void
    {
        config(['jodit.preserve_file_names' => true]);

        $response = $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'upload',
                'files'  => [UploadedFile::fake()->image('my-photo.jpg', 100, 100)],
            ]);

        $response->assertStatus(200);

        $this->assertStringContainsString('my-photo', $response->json('data.newfilename'));
    }

    public function test_upload_uses_hash_name_when_preserve_file_names_is_disabled(): void
    {
        config(['jodit.preserve_file_names' => false]);

        $response = $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'upload',
                'files'  => [UploadedFile::fake()->image('my-photo.jpg', 100, 100)],
            ]);

        $response->assertStatus(200);

        // Hashed names are UUIDs / random hex — definitely not the original slug.
        $this->assertStringNotContainsString('my-photo', $response->json('data.newfilename'));
    }

    // ---------------------------------------------------------------
    // Per-instance path scoping
    // ---------------------------------------------------------------

    public function test_upload_stores_file_under_custom_path(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action'    => 'upload',
                'directory' => 'custom-dir',
                'files'     => [UploadedFile::fake()->image('photo.jpg', 100, 100)],
            ]);

        $response->assertStatus(200);

        $this->assertNotEmpty(Storage::disk('public')->allFiles('uploads/custom-dir'));
    }
}
