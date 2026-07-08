<?php

namespace Modules\Workspace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Workspace\Database\Factories\ProjectFactory;

class Project extends Model
{
    use HasFactory;

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
}
