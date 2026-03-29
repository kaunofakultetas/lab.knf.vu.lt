<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Models\User;
use App\Mail\UserVerifyMail;
use Lunaweb\RecaptchaV3\Facades\RecaptchaV3;

class RegistrationTest extends TestCase
{
    use WithFaker; 
  
    public function test_registration_form_submission_with_valid_data()
    {   
        
        Mail::fake();
    
        $response = $this->post('/register', [
            'username' => $this->faker->userName,
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'g-recaptcha-response' => RecaptchaV3::shouldReceive('verify')->once()->andReturn(1.0),
        ]);
       
        $response->assertRedirect('/');
        $response->assertSessionHas('success', 'You are registered! Check your email for verification link.');
        Mail::assertSent(UserVerifyMail::class);
    }
 
    public function test_registration_form_fails_validation_with_invalid_username()
    {
        $response = $this->post('/register', [
            'username' => 'abc',
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'g-recaptcha-response' => 'valid_recaptcha_response',
        ]);
        
        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('username');
    }
 
    public function test_registration_form_fails_validation_with_invalid_email()
    {
        $response = $this->post('/register', [
            'username' => $this->faker->userName,
            'email' => 'invalid_email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'g-recaptcha-response' => 'valid_recaptcha_response',
        ]);
        
        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('email');
    }

    public function test_user_verification_with_valid_token()
    { 
        $user = User::create([
            'user_type' => 1,
            'username' => $this->faker->userName,
            'email' => $this->faker->unique()->safeEmail,
            'password' => Hash::make(Str::random(20)),
            'status'=>0,
            'newsletter' => 0,
            'verify_token'=>Str::random(50)
        ]);
        
        $token = $user->verify_token;
         
        $response = $this->get('/verify/' . $token);
         
        $response->assertRedirect(route('login_users'));
        $response->assertSessionHas('success', 'User verified! Now you can login.');
         
        $user = User::find($user->id);
        $this->assertEquals(1, $user->status);
        $this->assertEmpty($user->verify_token);
    }
    
    public function test_user_verification_with_invalid_token()
    { 
        $response = $this->get('/verify/invalid_token');
         
        $response->assertStatus(404);
        $response->assertSessionMissing('success');
    }
     
    public function test_user_verification_with_empty_token()
    { 
        $response = $this->get('/verify/');
         
        $response->assertStatus(404);
        $response->assertSessionMissing('success');
    }


}