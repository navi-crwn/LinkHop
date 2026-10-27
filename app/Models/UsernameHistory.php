<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsernameHistory extends Model
{
    protected $table = 'username_history';

    protected $fillable = ['user_id', 'username', 'released_at'];

    protected function casts(): array
    {
        return ['released_at' => 'datetime'];
    }
}
