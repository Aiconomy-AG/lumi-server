<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'phone_number' => $this->phone_number,
            'language_flag' => $this->language_flag,
            'avatar_url' => $this->avatar_url,
            'is_active' => (bool) $this->is_active,
            'must_change_password' => (bool) $this->must_change_password,
            'is_bot' => strcasecmp((string) $this->email, (string) config('chat_ai.user_email')) === 0,
        ];
    }
}
