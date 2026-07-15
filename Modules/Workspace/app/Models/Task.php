<?php

namespace Modules\Workspace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
// use Modules\Workspace\Database\Factories\TaskFactory;
use App\Models\User;
use Laravel\Scout\Searchable;

class Task extends Model
{
    use HasFactory;
    use Searchable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'title',
        'description',
        'status',
        'due_date',
        'parent_id',
        'project_id',
    ];

    protected $casts = [
        'due_date' => 'datetime',
    ];

    public function parent()
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function subtasks()
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class, 'task_assignees')
            ->withTimestamps();
    }

    public function timeEntries()
    {
        return $this->hasMany(TaskTimeEntry::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function toSearchableArray(): array
    {
        $this->loadMissing('project');

        return [
            'id' => (int) $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'project_id' => $this->project_id !== null ? (int) $this->project_id : null,
            'project_name' => $this->project?->name,
            'updated_at' => $this->updated_at?->timestamp,
        ];
    }
}
