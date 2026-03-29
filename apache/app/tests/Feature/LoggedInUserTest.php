<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class LoggedInUserTest extends TestCase
{
    public $email = 'test@test.com';
    public $pass = 'test';
    
    public function test_user_sees_dashboard()
    {
        $user = new User([
            'email' => $this->email,
            'password' => bcrypt($this->pass)
        ]);
        
        $authenticated = Auth::guard()->attempt([
            'email' => $this->email,
            'password' => $this->pass,
        ]);
        
        if ($authenticated) {
            $this->actingAs($user);
        }
        
        $response = $this->get('/user/dashboard');
        
        $response->assertStatus(200); 
        $response->assertSee('Dashboard');
    }
    
    public function test_user_sees_leaderboard()
    {
        $user = new User([
            'email' => $this->email,
            'password' => bcrypt($this->pass)
        ]);
        
        $authenticated = Auth::guard()->attempt([
            'email' => $this->email,
            'password' => $this->pass,
        ]);
        
        if ($authenticated) {
            $this->actingAs($user);
        }
        
        $response = $this->get('/user/leaderboard');
        
        $response->assertStatus(200);
        $response->assertSee('Top players');
    } 
    
    public function test_user_logs_out(){
        
        $user = new User([
            'email' => $this->email,
            'password' => bcrypt($this->pass)
        ]);
        
        $authenticated = Auth::guard()->attempt([
            'email' => $this->email,
            'password' => $this->pass,
        ]);
        
        if ($authenticated) {
            $this->actingAs($user);
        }
        
        $response = $this->get('/user/logout'); 
        $response->assertRedirect('/');
        $this->assertGuest();
        
        
    }
    
}