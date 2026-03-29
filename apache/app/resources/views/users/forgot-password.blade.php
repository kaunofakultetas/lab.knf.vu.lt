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
                  {{ Form::open(['url' => route('forgot-password'), 'class'=>'login-form py-5 w-100']) }}
                   
                    <div class="input-material-group mb-3"> 
                      {{ Form::email('email', '', ['class' => 'input-material', 'id'=>'login-username', 'required'=>'required', 'autofocus'=>'autofocus', 'maxlength'=>255]) }}
                      
                      <label class="label-material" for="login-username">Email</label>
                      @if ($errors->has('email'))
                          <span class="text-danger">{{ $errors->first('email') }}</span>
                      @endif
                    </div>
                    <div class="input-material-group mb-2"> 
                    
                      {{-- <div class="g-recaptcha" data-sitekey="{{ env('NOCAPTCHA_SITEKEY') }}"></div> --}}
                      
                       {!! RecaptchaV3::field('forgotpass') !!} 
                        @if($errors->has('g-recaptcha-response')) 
                            <span class="help-block"> 
                                 <strong>{{ $errors->first('g-recaptcha-response') }}</strong> 
                            </span> 
                        @endif

                    </div>
                    {{ Form::submit('Change', ['class'=>'btn btn-primary mb-3', 'login'=>'login']) }}
                    <br><small class="text-gray-500">Already have an account? </small><a class="text-sm text-paleBlue" href="{{ env('APP_URL') }}/">Login</a><br>
                    <small class="text-gray-500">Do not have an account? </small><a class="text-sm text-paleBlue" href="{{ env('APP_URL') }}/register">Signup</a>
                   {{ Form::close() }}
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