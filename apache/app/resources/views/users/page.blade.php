@extends('users.layouts.app')
@section('content')
<div class="login-page">
      <div class="container d-flex align-items-center position-relative py-5">
        <div class="card shadow-sm w-100 rounded overflow-hidden bg-none">
          <div class="card-body p-0">
            <div class="row gx-0 align-items-stretch">
            
              <div class="col-lg-12">
                <div class="">
                  <div class="py-5">
                    <h1 class="display-6 fw-bold text-center"><img src="{{ env('APP_URL') }}/assets/img/logo-light.png" id="loginlogo" class="img-fluid"></h1> 
                  </div>
                </div>
              </div>
            
              <div class="col-lg-12 bg-white">  
              
                <div class=" align-items-center p-5 px-lg-5 h-100" style="text-align:justify!important;line-height:30px!important;"> 
                                
                   <h1 class="h1 pb-4">{{ $data['name'] }}</h1>  
                   
                   {!! $data['content'] !!}
                   
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