<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Challenges extends Model
{
    protected $table = "cyber_challenges";
    public $timestamps = false;
    protected $rememberTokenName = false;
    
    protected $fillable = [
        'public_id', 'partner_id', 'created_by', 'name', 'descr', 'content', 'answer', 'points' , 'hidden' 
    ];
    
    protected $hidden = [
        'answer'
    ];
    
    protected $casts = [
        'name'=>'string',
        'public_id'=>'string',
        'created_by' => 'integer',
        'partner_id'=>'integer', 
        'descr'=>'string', 
        'content'=>'string', 
        'answer'=>'string',
        'points'=>'integer',
        'hidden'=>'integer'
    ];
    
    public static function getIdByPublicId($publicId)
    {
        return Challenges::select('id')->where('public_id', $publicId)->get()->pluck('id')->first();
    }
    
    public static function getPartnerIdByPublicId($publicId)
    {
        return Challenges::select('partner_id')->where('public_id', $publicId)->get()->pluck('partner_id')->first();
    }
    
    
    
    
    
    
    
    
    
}