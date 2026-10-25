@extends('users.layouts.app')
@section('content')
<div class="login-page">
      <div class="container d-flex align-items-center position-relative py-5">
        <div class="card shadow-sm w-100 rounded overflow-hidden bg-none">
          <div class="card-body p-0">
            <div class="row gx-0 align-items-stretch">
              <!-- Logo & Information Panel-->
              <div class="col-lg-6">
                <div class="info d-flex justify-content-center flex-column p-4 h-100 logbox">
                  <div class="py-5">
                    <h1 class="display-6 fw-bold text-center"><img src="{{ env('APP_URL') }}/assets/img/logo-light.svg" id="loginlogo" class="img-fluid"></h1>  
                  </div>
                </div>
              </div>
              <!-- Form Panel    -->
              <div class="col-lg-6 bg-white">
                
                <div class="d-flex align-items-center px-4 px-lg-5 h-100"> 
                
                	@if($reg_status == 1)
                 
                  <form method="POST" action="{{ route('create_users') }}" accept-charset="UTF-8" class="login-form py-5 w-100" data-recaptcha-action="register" data-recaptcha-key="{{ RecaptchaV3::sitekey() }}">@csrf
                  
                 
                  	<div class="input-material-group mb-3"> 
                      <input class="input-material" id="reg-username" required autofocus="autofocus" maxlength="25" minlength="4" name="username" type="text" value="{{ old('username') }}">
                      
                      <label class="label-material" for="reg-username" id="user-type-label">Username</label>
             
                    </div>
                  	
                  	
                    <div class="input-material-group mb-3"> 
                      <input class="input-material" id="reg-email" required="required" maxlength="255" name="email" type="email" value="{{ old('email') }}">
                      
                      <label class="label-material" for="reg-email">Email</label>
                   
                    </div>
                    
                    <div class="input-material-group mb-4"> 
                    
                      <input class="input-material" required="required" id="login-password" maxlength="255" name="password" type="password" value="">
                      
                      <label class="label-material" for="login-password">Password</label>
                 
                    </div>
                    
                    <div class="input-material-group mb-2"> 
                    
                      <input class="input-material" required="required" id="password_confirmation" maxlength="255" name="password_confirmation" type="password" value="">
                      
                      <label class="label-material" for="password_confirmation">Confirm Password</label>
                  
                    </div>
                     
                    <div class="input-material-group mb-2"> 
                    
                    {{-- <div class="g-recaptcha" data-sitekey="{{ env('NOCAPTCHA_SITEKEY') }}"></div>--}}
                    
                    {{-- Filled as the form is sent (custom.js) --}}<input type="hidden" name="g-recaptcha-response"> 
                    @if($errors->has('g-recaptcha-response')) 
                        <span class="alert-danger"> 
                             <strong>{{ $errors->first('g-recaptcha-response') }}</strong> 
                        </span> 
                    @endif
                    

                    </div> 
                    
                    
                    <input class="btn btn-primary mb-3" login="login" type="submit" value="Register">
                    <br><small class="text-gray-500">Already have an account? </small> &nbsp; <a class="text-sm text-paleBlue" href="{{ env('APP_URL') }}/">Login</a> 
                    &nbsp;<small class="text-gray-500"> | </small>&nbsp;
                    <a class="text-sm text-paleBlue" href="{{ env('APP_URL') }}/forgot-password">Forgot password?</a>
                   </form>
                   
                   @else 
                   
                   		<h1>Registration is closed!</h1>
                   
                   @endif
                   
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="text-center position-absolute bottom-0 start-0 w-100 z-index-20">
     
      </div>
    </div>
@endsection 