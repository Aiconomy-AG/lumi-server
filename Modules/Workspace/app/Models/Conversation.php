<?php

namespace Modules\Workspace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class Conversation extends Model{

    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'created_by',
    ];

    public function participants(){
        return $this->belongsToMany(User::class, 'conversation_participants')->withTimestamps();
    }


}
