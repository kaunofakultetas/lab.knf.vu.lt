<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    
    public $timestamps = false;
    protected $table = 'cyber_users';
 
    protected $fillable = [ 
        'username',
        'user_type',
        'vu_id',
        'display_name',
        'email',
        'password',
        'points',
        'status',
        'verify_token',
        'last_login',
        'remember_token',
        'newsletter',
        'last_ip',
    ];
 
    protected $hidden = [
        'password',
        'remember_token',
        'verify_token'
    ];
 
    protected $casts = [
        'points' => 'integer',
        'user_type' => 'integer',
        'status' => 'integer',
        'newsletter' => 'integer',
        'last_login' => 'string',
        'last_ip' => 'string',
    ];
    
    function getUsername()
    {
        return $this->username;
    }
    
    public static function scopeByRating($query)
    {
        $data = json_decode($query->selectRaw("@row_number:=@row_number+1 AS position, cyber_users.*")
            ->from(DB::raw("cyber_users, (SELECT @row_number:=0) AS t WHERE points > 0"))
            ->orderBy('points', 'desc')
            ->get(), 1);
        
        foreach ($data as $k => $v) {
            if ($v['id'] == Auth::guard('mem')->id()) {
                
                return $v['position'];
            }
        }
    }
    
    public static function ratingList($query)
    {
        $data = json_decode($query->selectRaw("@row_number:=@row_number+1 AS position, cyber_users.*")
            ->from(DB::raw("cyber_users, (SELECT @row_number:=0) AS t WHERE points > 0"))
            ->orderBy('points', 'desc')
            ->get(), 1);
         
    }
    
    public static function ratingList10($query)
    {
        $data = json_decode($query->selectRaw("@row_number:=@row_number+1 AS position, cyber_users.*")
            ->from(DB::raw("cyber_users, (SELECT @row_number:=0) AS t WHERE points > 0"))
            ->orderBy('points', 'desc')
            ->take(10)
            ->get(), 1);
        
        return $data;
    }

     

}
