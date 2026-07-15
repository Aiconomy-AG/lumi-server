<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_user_can_upload_an_avatar(): void
    {
        Storage::fake('wasabi');

        $user = User::factory()->create(['avatar_path' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->post('/api/v1/auth/me/avatar', [
                'avatar' => UploadedFile::fake()->image('me.jpg', 500, 500),
            ], ['Accept' => 'application/json']);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $user->id);

        $user->refresh();

        $this->assertNotNull($user->avatar_path);
        $this->assertStringStartsWith("avatars/{$user->id}/", $user->avatar_path);
        Storage::disk('wasabi')->assertExists($user->avatar_path);

        $this->assertNotNull($response->json('data.avatar_url'));
        $this->assertArrayNotHasKey('avatar_path', $response->json('data'));
    }

    #[Test]
    public function uploading_a_new_avatar_deletes_the_previous_object(): void
    {
        Storage::fake('wasabi');

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->post('/api/v1/auth/me/avatar', [
                'avatar' => UploadedFile::fake()->image('first.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(200);

        $firstPath = $user->fresh()->avatar_path;

        $this->actingAs($user, 'sanctum')
            ->post('/api/v1/auth/me/avatar', [
                'avatar' => UploadedFile::fake()->image('second.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(200);

        $secondPath = $user->fresh()->avatar_path;

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('wasabi')->assertMissing($firstPath);
        Storage::disk('wasabi')->assertExists($secondPath);
        $this->assertCount(1, Storage::disk('wasabi')->allFiles());
    }

    #[Test]
    public function a_user_can_remove_their_avatar(): void
    {
        Storage::fake('wasabi');

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->post('/api/v1/auth/me/avatar', [
                'avatar' => UploadedFile::fake()->image('me.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(200);

        $path = $user->fresh()->avatar_path;

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/auth/me/avatar')
            ->assertStatus(200)
            ->assertJsonPath('data.avatar_url', null);

        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('wasabi')->assertMissing($path);
    }

    #[Test]
    public function a_user_without_an_avatar_exposes_a_null_url(): void
    {
        $user = User::factory()->create(['avatar_path' => null]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.avatar_url', null);
    }

    #[Test]
    public function an_svg_avatar_is_rejected_with_422(): void
    {
        Storage::fake('wasabi');

        $user = User::factory()->create(['avatar_path' => null]);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        $this->actingAs($user, 'sanctum')
            ->post('/api/v1/auth/me/avatar', [
                'avatar' => UploadedFile::fake()->createWithContent('avatar.svg', $svg),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('avatar');

        $this->assertNull($user->fresh()->avatar_path);
        $this->assertEmpty(Storage::disk('wasabi')->allFiles());
    }

    #[Test]
    public function an_oversized_avatar_is_rejected_with_422(): void
    {
        Storage::fake('wasabi');

        $user = User::factory()->create(['avatar_path' => null]);

        $this->actingAs($user, 'sanctum')
            ->post('/api/v1/auth/me/avatar', [
                'avatar' => UploadedFile::fake()->image('huge.jpg')->size(config('media.avatar_max_kb') + 1),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('avatar');

        $this->assertNull($user->fresh()->avatar_path);
    }

    #[Test]
    public function a_guest_cannot_upload_an_avatar(): void
    {
        $this->post('/api/v1/auth/me/avatar', [
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(401);
    }
}
