<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partners extends Model
{

    protected $table = "cyber_partners";

    public $timestamps = false;

    protected $rememberTokenName = false;

    protected $fillable = [
        'id',
        'name',
        'picture'
    ];

    protected $hidden = [];

    protected $casts = [
        'id' => 'integer',
        'name' => 'string',
        'picture' => 'string'
    ];
}