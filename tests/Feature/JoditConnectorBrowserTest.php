<?php

namespace Nasirkhan\LaravelJodit\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Nasirkhan\LaravelJodit\Tests\TestCase;
use Orchestra\Testbench\Factories\UserFactory;

class JoditConnectorBrowserTest extends TestCase
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
        $router->get('/login', fn () => response('Login', 200))->name('login');
    }

    // ---------------------------------------------------------------
    // Authentication
    // ---------------------------------------------------------------

    public function test_connector_requires_authentication(): void
    {
        $this->post(route('jodit.connector'), ['action' => 'files'])
            ->assertRedirect('/login');
    }

    public function test_editor_configures_filebrowser_response_handlers_at_filebrowser_level(): void
    {
        $html = (string) $this->blade(
            '<x-jodit::editor name="content" /> @stack("after-scripts")',
        );

        $this->assertMatchesRegularExpression(
            '/cfg\.filebrowser\s*=\s*\{\s*isSuccess:\s*function.*?getMessage:\s*function.*?ajax:\s*\{/s',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/ajax:\s*\{.*?isSuccess:\s*function.*?getMessage:\s*function/s',
            $html,
        );
    }

    // ---------------------------------------------------------------
    // Files listing
    // ---------------------------------------------------------------

    public function test_files_action_returns_file_list(): void
    {
        Storage::disk('public')->put('uploads/image.jpg', 'content');
        Storage::disk('public')->put('uploads/document.pdf', 'content');

        $this->actingAs($this->user)
            ->post(route('jodit.connector'), ['action' => 'files'])
            ->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'sources' => [
                        ['name', 'path', 'baseurl', 'files', 'folders'],
                    ],
                ],
            ]);
    }

    public function test_files_action_filters_images_by_type(): void
    {
        Storage::disk('public')->put('uploads/photo.jpg', 'content');
        Storage::disk('public')->put('uploads/file.pdf', 'content');

        $response = $this->actingAs($this->user)
            ->post(route('jodit.connector'), ['action' => 'files', 'type' => 'images']);

        $response->assertStatus(200);

        $files = $response->json('data.sources.0.files');
        $this->assertCount(1, $files);
        $this->assertSame('photo.jpg', $files[0]['file']);
    }

    public function test_files_action_keeps_folder_in_file_url(): void
    {
        Storage::disk('public')->put('uploads/gallery/photo.jpg', 'content');

        $response = $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'files',
                'path'   => '/gallery/',
                'type'   => 'images',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.sources.0.baseurl', '/storage/uploads/')
            ->assertJsonPath('data.sources.0.path', 'gallery')
            ->assertJsonPath('data.sources.0.files.0.file', 'photo.jpg');

        $source = $response->json('data.sources.0');
        $fileUrl = implode('/', [
            rtrim($source['baseurl'], '/'),
            trim($source['path'], '/'),
            $source['files'][0]['file'],
        ]);

        $this->assertSame('/storage/uploads/gallery/photo.jpg', $fileUrl);
    }

    public function test_files_action_creates_directory_when_missing(): void
    {
        $this->actingAs($this->user)
            ->post(route('jodit.connector'), ['action' => 'files'])
            ->assertStatus(200);

        Storage::disk('public')->assertExists('uploads');
    }

    // ---------------------------------------------------------------
    // Folders listing
    // ---------------------------------------------------------------

    public function test_folders_action_returns_folder_list(): void
    {
        Storage::disk('public')->makeDirectory('uploads/images');
        Storage::disk('public')->makeDirectory('uploads/docs');

        $response = $this->actingAs($this->user)
            ->post(route('jodit.connector'), ['action' => 'folders']);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $folders = $response->json('data.sources.0.folders');

        $this->assertContains('images', $folders);
        $this->assertContains('docs', $folders);
        $this->assertNotContains('..', $folders);

        foreach ($folders as $folder) {
            $this->assertIsString($folder);
        }
    }

    public function test_folders_action_includes_parent_folder_outside_root(): void
    {
        Storage::disk('public')->makeDirectory('uploads/images/originals');

        $response = $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'folders',
                'path'   => '/images/',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.sources.0.path', 'images')
            ->assertJsonPath('data.sources.0.folders.0', '..');

        $this->assertContains(
            'originals',
            $response->json('data.sources.0.folders'),
        );
    }

    // ---------------------------------------------------------------
    // Rename
    // ---------------------------------------------------------------

    public function test_rename_action_renames_file(): void
    {
        Storage::disk('public')->put('uploads/old-name.jpg', 'content');

        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action'  => 'fileRename',
                'name'    => 'old-name.jpg',
                'newname' => 'new-name.jpg',
                'path'    => '/',
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        Storage::disk('public')->assertMissing('uploads/old-name.jpg');
        Storage::disk('public')->assertExists('uploads/new-name.jpg');
    }

    public function test_rename_action_returns_error_when_file_not_found(): void
    {
        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action'  => 'fileRename',
                'name'    => 'nonexistent.jpg',
                'newname' => 'other.jpg',
                'path'    => '/',
            ])
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_folder_rename_action_renames_folder(): void
    {
        Storage::disk('public')->put('uploads/old-folder/file.txt', 'content');

        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action'  => 'folderRename',
                'name'    => 'old-folder',
                'newname' => 'new-folder',
                'path'    => '/',
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        Storage::disk('public')->assertMissing('uploads/old-folder');
        Storage::disk('public')->assertExists('uploads/new-folder/file.txt');
    }

    public function test_rename_action_returns_error_when_names_are_missing(): void
    {
        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'rename',
                'path'   => '/',
            ])
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    // ---------------------------------------------------------------
    // Create folder
    // ---------------------------------------------------------------

    public function test_create_action_creates_new_folder(): void
    {
        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'folderCreate',
                'name'   => 'New Folder',
                'path'   => '/',
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        Storage::disk('public')->assertExists('uploads/new-folder');
    }

    public function test_create_action_returns_error_when_name_is_missing(): void
    {
        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'create',
                'path'   => '/',
            ])
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    // ---------------------------------------------------------------
    // Move
    // ---------------------------------------------------------------

    public function test_move_action_moves_file_to_new_path(): void
    {
        Storage::disk('public')->put('uploads/file.jpg', 'content');
        Storage::disk('public')->makeDirectory('uploads/archive');

        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'fileMove',
                'from'   => '/file.jpg',
                'path'   => '/archive/',
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        Storage::disk('public')->assertMissing('uploads/file.jpg');
        Storage::disk('public')->assertExists('uploads/archive/file.jpg');
    }

    public function test_move_action_returns_error_when_file_not_found(): void
    {
        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'fileMove',
                'from'   => '/ghost.jpg',
                'path'   => '/archive/',
            ])
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_image_resize_accepts_jodit_box_and_resizes_image(): void
    {
        $image = UploadedFile::fake()->image('photo.png', 100, 100);
        Storage::disk('public')->put('uploads/photo.png', $image->getContent());

        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'imageResize',
                'name'   => 'photo.png',
                'newname' => 'photo.png',
                'path'   => '/',
                'box'    => [
                    'w' => 40,
                    'h' => 40,
                ],
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        [$width, $height] = getimagesize(
            Storage::disk('public')->path('uploads/photo.png'),
        );

        $this->assertSame(40, $width);
        $this->assertSame(40, $height);
    }

    public function test_image_crop_accepts_jodit_box_and_crops_image(): void
    {
        $image = UploadedFile::fake()->image('photo.png', 100, 100);
        Storage::disk('public')->put('uploads/photo.png', $image->getContent());

        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'imageCrop',
                'name'   => 'photo.png',
                'newname' => 'photo.png',
                'path'   => '/',
                'box'    => [
                    'w' => 30,
                    'h' => 20,
                    'x' => 5,
                    'y' => 5,
                ],
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        [$width, $height] = getimagesize(
            Storage::disk('public')->path('uploads/photo.png'),
        );

        $this->assertSame(30, $width);
        $this->assertSame(20, $height);
    }

    // ---------------------------------------------------------------
    // Path traversal protection
    // ---------------------------------------------------------------

    public function test_path_traversal_is_stripped_from_path_parameter(): void
    {
        // A traversal attempt in the path should be sanitised — no 500 errors.
        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action' => 'files',
                'path'   => '../../../etc',
            ])
            ->assertStatus(200);
    }

    public function test_path_traversal_is_stripped_from_directory_parameter(): void
    {
        $this->actingAs($this->user)
            ->post(route('jodit.connector'), [
                'action'    => 'files',
                'directory' => '../../sensitive',
            ])
            ->assertStatus(200);
    }

    // ---------------------------------------------------------------
    // Unknown action
    // ---------------------------------------------------------------

    public function test_unknown_action_returns_empty_source_response(): void
    {
        $this->actingAs($this->user)
            ->post(route('jodit.connector'), ['action' => 'invalid_action'])
            ->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['sources']]);
    }
}
