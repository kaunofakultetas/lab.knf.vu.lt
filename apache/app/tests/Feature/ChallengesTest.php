<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Auth;
use App\Models\Challenges;
use App\Models\ChallengesSubmitted;
use App\Models\User;

class ChallengesTest extends TestCase
{
    public $email = 'test@test.com';
    public $pass = 'test'; 
    public $uid = 13;
     
    public function test_user_view_challenges()
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
        
        $publicIds = Challenges::where(['hidden'=>0])->pluck('public_id')->toArray();
        
        foreach ($publicIds as $publicId) {
            $response = $this->get("/user/challenge/{$publicId}");
            $response->assertStatus(200);
            $response->assertSee('Submit your answer');
        }
    }
    
    public function test_solve_challenge_with_correct_answer(){
        
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
          
        $authenticated = Auth::guard('users')->attempt([
            'email' => $this->email,
            'password' => $this->pass,
        ]); 
        
        $user = Auth::guard('users')->user();
        
        if ($authenticated && $user) {
            $this->actingAs($user);
        }  
        
        $submittedChallenges = ChallengesSubmitted::select("challenge_id")->where(['user_id'=>$user->id])->get()->pluck("challenge_id")->toArray(); 
        $unsolvedChallenge = Challenges::whereNotIn("id", $submittedChallenges)->get()->first();
        
        $answer = $unsolvedChallenge['answer'];
        $public_id = $unsolvedChallenge['public_id'];
        
        $challenge = [
            'answer' => $answer, 
            '_token' => csrf_token()
        ];
        
        $response = $this->post('/user/challenge/'.$public_id, $challenge); 
        $response->assertRedirect('/user/dashboard');
        $response->assertSessionHas('success');
        
        
    }
    
    public function test_solve_challenge_with_wrong_answer(){
        
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        
        $user = new User([
            'email' => $this->email,
            'password' => bcrypt($this->pass)
        ]);
        
        $authenticated = Auth::guard('users')->attempt([
            'email' => $this->email,
            'password' => $this->pass,
        ]);
        
        if($authenticated) {
            $this->actingAs($user);
        }
        
        $submittedChallenges = ChallengesSubmitted::select("challenge_id")->where(['user_id'=>$this->uid])->get()->pluck("challenge_id")->toArray(); 
        $unsolvedChallenge = Challenges::whereNotIn("id", $submittedChallenges)->get()->first();
        
        $public_id = $unsolvedChallenge['public_id'];
        
        $challenge = [
            'answer' => 'random_answer',
            '_token' => csrf_token()
        ];
        
        $response = $this->post('/user/challenge/'.$public_id, $challenge); 
        
        $response->assertRedirect();
        $response->assertSessionMissing('success');
        
    }
    
     
    
    
    
    
    
    
    
}






















