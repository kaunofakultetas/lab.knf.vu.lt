<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{ 
    
    protected $table = "cyber_settings";
    
    public $timestamps = false;
    
    protected $rememberTokenName = false;
    
    protected $fillable = [
        'name',
        'val1',
        'val2'
    ];
    
    protected $hidden = [];
    
    protected $casts = [
        'name' => 'string',
        'val1' => 'string',
        'val2' => 'string'
    ];
}
 