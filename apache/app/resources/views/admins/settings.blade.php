@extends('admins.layouts.app')
@section('content')
<div class="page">
      <!-- Main Navbar-->
      <header class="header z-index-50">
        <nav class="nav navbar py-3 px-0 shadow-sm text-white position-relative cyber-navbar">
         
          <div class="container-fluid w-100">
            <div class="navbar-holder d-flex align-items-center justify-content-between w-100">
              <!-- Navbar Header-->
              <div class="navbar-header">
                <!-- Navbar Brand --><a class="navbar-brand d-none d-sm-inline-block" href="/">
                  <div class="brand-text d-none d-lg-inline-block"><img src="{{ env('APP_URL') }}/assets/img/logo-light.png" class="logo"></div>
            	  <div class="brand-text d-none d-sm-inline-block d-lg-none"><img src="{{ env('APP_URL') }}/assets/img/logo-icon.png" class="logo_icon"></div></a>	
            	  <a class="menu-btn active" id="toggle-btn" href="#"><span></span><span></span><span></span></a>
               
              </div>
              
				@include("admins.partials.topnav")
				
				
            </div>
          </div>
        </nav>
      </header>
      <div class="page-content d-flex align-items-stretch"> 
        

		@include("admins.partials.sidebar")
		
		
        <div class="content-inner w-100">
          <!-- Page Header-->
          <header class="bg-white shadow-sm px-4 py-3 z-index-20">
            <div class="container-fluid px-0">
              <h2 class="mb-0 p-1">Settings</h2>
            </div>
          </header>
          <!-- Dashboard Counts Section-->
          <section class="pb-0">
            <div class="container-fluid">
           
           <div class="row">
				<div class="col-lg-6">
                  <div class="card">
                    <div class="card-header">
                    
                      <h3 class="h4 mb-0">Event date</h3>
                    </div>
                    <div class="card-body">
                     
                      {{ Form::open(['url' => route('settings.event_data')]) }}
                        <div class="mb-3">
                          <label class="form-label" for="eventstart">Event time range</label>
                          {{ Form::text('event_timerange', $event_timerange, ['class' => 'form-control', 'id'=>'eventstart', 'required'=>'required']) }} 
                        </div>
                        
                        {{ Form::submit('Submit', ['class'=>'btn btn-primary', 'login'=>'login']) }}
                           
                      {{ Form::close() }}
                      
                      
                    </div>
                  </div>
                </div>
                
                
                <div class="col-lg-6">
                  <div class="card">
                    <div class="card-header">
                    
                      <h3 class="h4 mb-0">Registration</h3>
                    </div>
                    <div class="card-body">
                     
                      {{ Form::open(['url' => route('settings.reg_open')]) }}
                        <div class="mb-3">
                          <label class="form-label" for="eventstart">Registration</label>
                           
                           <div class="form-check" style="padding-bottom:15px;">
                              <input class="form-check-input" id="defaultCheck0" type="checkbox" name="reg_open" value="1" {{ $reg_open ? 'checked' : '' }}>
                              <label class="form-check-label" for="defaultCheck0">Registration open</label>
                            </div>
                             
                             
                        </div>
                        
                        {{ Form::submit('Submit', ['class'=>'btn btn-primary', 'login'=>'login']) }}
                           
                      {{ Form::close() }}
                      
                      
                    </div>
                  </div>
                </div>
                
                
            </div>    
                
                
            </div>
          </section>
       
       
       
          @include("admins.partials.footer")
          
          
        </div>
      </div>
    </div> 
@endsection