<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categories extends Model
{

    protected $table = "cyber_categories";

    public $timestamps = false;

    protected $rememberTokenName = false;

    protected $fillable = [
        'id',
        'name'
    ];

    protected $hidden = [];

    protected $casts = [
        'id' => 'integer',
        'name' => 'string'
    ];
}