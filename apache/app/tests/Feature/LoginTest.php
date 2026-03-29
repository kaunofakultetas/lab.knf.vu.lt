<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Mail\UserVerifyMail;
use App\Mail\SendRemindLink;
use App\Mail\SendNewPass;
use Lunaweb\RecaptchaV3\Facades\RecaptchaV3;
use Tests\TestCase;

class LoginTest extends TestCase
{ 
    use WithFaker;
    
    public $email = 'test@test.com';
    public $pass = 'test';
    
    public function test_user_can_view_a_login_form()
    {
        $response = $this->get('/');
        
        $response->assertSuccessful();
        $response->assertViewIs('users.login');
    }
    
    public function test_user_can_login_with_correct_credentials_basic()
    {
        
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
         
        $user = [
            'email' => $this->email,
            'password' => $this->pass,
            '_token' => csrf_token()
        ];
        
        $response = $this->post('/login', $user);
        
        $response->assertRedirect('/user/dashboard');
        $this->assertAuthenticated();
    } 
    
    public function test_user_cannot_login_with_incorrect_password_basic()
    {
        
        $user = [
            'email' => $this->email,
            'password' => 'testincorrect',
        ];
        
        $response = $this->post('/login', $user);
        
        $response->assertRedirect('/');  
        $this->assertGuest();
    }
     
    public function test_user_can_view_forgot_password_page()
    {
        $response = $this->get('/forgot-password');
        
        $response->assertSuccessful();
        $response->assertViewIs('users.forgot-password');
    }
     
    public function test_user_can_reset_password_with_valid_email()
    {
        Mail::fake();
        
        $user = new User([
            'email' => $this->email,
            'password' => bcrypt($this->pass)
        ]);
        
        $response = $this->post(route('forgot-password'), [
            'email' => $user->email,
            'g-recaptcha-response' => RecaptchaV3::shouldReceive('verify')->once()->andReturn(1.0),
        ]);
        
        $response->assertRedirect();
        $response->assertSessionHas('success');
        
        Mail::assertSent(SendRemindLink::class); 
    }
     
    public function test_user_cannot_reset_password_with_invalid_email()
    {
        Mail::fake();
        
        $response = $this->post(route('forgot-password'), [
            'email' => $this->faker->safeEmail,
            'g-recaptcha-response' => 'valid-token',
        ]);
        
        $response->assertRedirect();
        $response->assertSessionMissing('success');
        
        Mail::assertNothingQueued();
    }
     
    public function test_user_cannot_reset_password_with_empty_email()
    {
        $response = $this->post(route('forgot-password'), [
            'email' => '',
            'g-recaptcha-response' => RecaptchaV3::shouldReceive('verify')->once()->andReturn(1.0),
        ]);
        
        $response->assertSessionHasErrors('email');
    }
     
    public function test_user_cannot_reset_password_with_invalid_email_format()
    {
        $response = $this->post(route('forgot-password'), [
            'email' => 'invalid-email-format',
            'g-recaptcha-response' => RecaptchaV3::shouldReceive('verify')->once()->andReturn(1.0),
        ]);
        
        $response->assertSessionHasErrors('email');
    }
     
    public function test_user_cannot_reset_password_without_recaptcha_token()
    {
        $response = $this->post(route('forgot-password'), [
            'email' => $this->faker->safeEmail,
            'g-recaptcha-response' => '',
        ]);
        
        $response->assertSessionMissing('success');
    }
     
    public function test_user_cannot_reset_password_with_invalid_recaptcha_token()
    {
        $response = $this->post(route('forgot-password'), [
            'email' => $this->faker->safeEmail,
            'g-recaptcha-response' => 'invalid-token',
        ]);
        
        $response->assertSessionMissing('success');
    }
    
    public function test_user_password_reminder_new_pass_sent(){
        
        Mail::fake();
        
        $user = User::create([
            'user_type' => 1,
            'username' => $this->faker->userName,
            'email' => $this->faker->unique()->safeEmail,
            'password' => Hash::make(Str::random(20)),
            'status'=>1,
            'newsletter' => 0,
            'verify_token'=>'',
            'remember_token'=>Str::random(50)
        ]);
        
        $token = $user->remember_token;
        
        $response = $this->get('/reset/' . $token);
        
        $response->assertSessionHas('success', 'We just emailed you new password');
        Mail::assertSent(SendNewPass::class);
        
    }
    
    public function test_user_password_reminder_new_pass_not_sent(){
        
        $token = 'asd';
        
        $response = $this->get('/reset/' . $token);
        
        $response->assertSessionMissing('success');
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
}