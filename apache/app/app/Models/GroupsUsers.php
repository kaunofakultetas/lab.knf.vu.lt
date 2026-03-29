<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupsUsers extends Model
{

    protected $table = "cyber_users_groups";

    public $timestamps = false;

    protected $rememberTokenName = false;

    protected $fillable = [
        'id',
        'group_id',
        'vu_id'
    ];

    protected $hidden = [];

    protected $casts = [
        'id' => 'integer',
        'group_id' => 'integer',
        'vu_id' => 'string' 
    ];
}