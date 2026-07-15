<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AvatarController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'avatar' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.config('media.avatar_max_kb'),
                'dimensions:max_width=8000,max_height=8000',
            ],
        ]);

        $user = $request->user();
        $file = $request->file('avatar');
        $disk = Storage::disk(config('media.disk'));

        $path = $disk->putFileAs(
            'avatars/'.$user->id,
            $file,
            Str::ulid().'.'.$file->extension(),
        );

        if ($path === false) {
            return response()->json([
                'code' => 'UPLOAD_FAILED',
                'message' => 'Could not store the avatar. Please try again.',
            ], 502);
        }

        $previousPath = $user->avatar_path;

        $user->avatar_path = $path;
        $user->save();

        $this->deleteIfPresent($previousPath);

        return new UserResource($user);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        $path = $user->avatar_path;

        $user->avatar_path = null;
        $user->save();

        $this->deleteIfPresent($path);

        return new UserResource($user);
    }

    private function deleteIfPresent(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        try {
            Storage::disk(config('media.disk'))->delete($path);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
