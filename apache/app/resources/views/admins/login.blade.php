@extends('admins.layouts.app')
@section('content')
<div class="login-page">
      <div class="container d-flex align-items-center position-relative py-5">
        <div class="card shadow-sm w-100 rounded overflow-hidden bg-none">
          <div class="card-body p-0">
            <div class="row gx-0 align-items-stretch">
           
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
                
                <a href="{{ env('APP_URL') }}/control-panel/login" class="btn btn-primary d-block w-100">Login with VU SSO</a>
                
                  {{-- <form method="POST" action="{{ route('login_admin') }}" class="login-form py-5 w-100">
                	@csrf	
                    <div class="input-material-group mb-3"> 
                      <input type="email" id="login-username" class="input-material" name="email" autocomplete="off" required autofocus>
                      <label class="label-material" for="login-username">Email</label>
                      @if ($errors->has('email'))
                             <span class="text-danger">{{ $errors->first('email') }}</span>
                      @endif
                    </div>
                    <div class="input-material-group mb-4">
                      <input class="input-material" id="login-password" type="password" name="password" required>
                      <label class="label-material" for="login-password">Password</label>
                       @if ($errors->has('password'))
                       		<span class="text-danger">{{ $errors->first('password') }}</span>
                       @endif
                    </div>
                    <button class="btn btn-primary mb-3" id="login" type="submit">Login</button><br> 
                  
                  </form> --}}
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