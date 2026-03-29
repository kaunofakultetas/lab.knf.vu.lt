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
                  {{ Form::open(['url' => route('login'), 'class'=>'login-form py-5 w-100']) }}
                      @if(session('info'))
                          <div class="alert alert-info" role="alert">
                              {{ session('info') }}
                          </div>
                      @endif
                  <p class="text-center ddtr">Event is starting in: <span style="font-weight:bold" id="countdown"></span></p>
                   
                    <div class="input-material-group mb-3"> 
                      {{ Form::email('email', '', ['class' => 'input-material', 'id'=>'login-username', 'required'=>'required', 'autofocus'=>'autofocus', 'maxlength'=>255]) }}
                      
                      <label class="label-material" for="login-username">Email</label>
                      @if ($errors->has('email'))
                          <span class="text-danger">{{ $errors->first('email') }}</span>
                      @endif
                    </div>
                    <div class="input-material-group mb-4"> 
                    
                      {{ Form::password('password', ['class' => 'input-material', 'required'=>'required', 'id'=>'login-password', 'maxlength'=>255]) }}
                      
                      <label class="label-material" for="login-password">Password</label>
                      @if ($errors->has('password'))
                          <span class="text-danger">{{ $errors->first('password') }}</span>
                      @endif
                    </div>
                    {{ Form::submit('Login', ['class'=>'btn btn-primary mb-3', 'login'=>'login']) }}<br><a class="text-sm text-paleBlue" href="{{ env('APP_URL') }}/forgot-password">Forgot Password?</a><br><small class="text-gray-500">Do not have an account? </small><a class="text-sm text-paleBlue" href="{{ env('APP_URL') }}/register">Signup</a>
                   
                   
                   <br><br>
                   
                   <a href="/cp" class="btn btn-primary d-block">Login with VU SSO</a>
                   
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