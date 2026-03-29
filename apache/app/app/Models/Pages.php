<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pages extends Model
{

    protected $table = "cyber_pages";

    public $timestamps = false;

    protected $rememberTokenName = false;

    protected $fillable = [
        'id',
        'name',
        'content',
        'alias'
    ];

    protected $hidden = ['alias'];

    protected $casts = [
        'id' => 'integer',
        'name' => 'string',
        'content' => 'string',
        'alias' => 'string'
    ];
 
}