<?php

namespace Modules\Workspace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Workspace\Database\Factories\ProjectFactory;
use Laravel\Scout\Searchable;

class Project extends Model
{
    use HasFactory;
    use Searchable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'deadline',
        'description',
        'status',
    ];

    protected $casts = [
        'deadline' => 'datetime',
    ];

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'updated_at' => $this->updated_at?->timestamp,
        ];
    }
}
