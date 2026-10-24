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
                  <form method="POST" action="{{ env('APP_URL').'/reset/'.$reset_token }}" accept-charset="UTF-8" class="login-form py-5 w-100">@csrf
                    <p class="mb-4">Press the button and we will email you a new password.</p>
                    <input class="btn btn-primary mb-3" type="submit" value="Send new password">
                    <br><small class="text-gray-500">Remembered your password? </small><a class="text-sm text-paleBlue" href="{{ env('APP_URL') }}/">Login</a>
                   </form>
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
