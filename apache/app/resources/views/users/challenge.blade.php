@extends('users.layouts.app')
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
                  <div class="brand-text d-none d-lg-inline-block"><img src="{{ env('APP_URL') }}/assets/img/logo-light-vu.png?" class="logo"></div>
            	  <div class="brand-text d-none d-sm-inline-block d-lg-none"><img src="{{ env('APP_URL') }}/assets/img/logo-icon-vu.png?" class="logo_icon"></div></a>	
            	  <a class="menu-btn active" id="toggle-btn" href="#"><span></span><span></span><span></span></a>
               
              </div>
              
              
               @include("users.partials.topnav")
              
              
            </div>
          </div>
        </nav>
      </header>
      <div class="page-content d-flex align-items-stretch"> 
      
      @include("users.partials.sidebar")
        
        <div class="content-inner w-100">
          <!-- Page Header-->
          <header class="bg-white shadow-sm px-4 py-3 z-index-20">
            <div class="container-fluid px-0">
              <h2 class="mb-0 p-1">{{ $data['name'] }}</h2>
            </div>
          </header>
          <!-- Dashboard Counts Section-->
          <section class="pb-0">
            <div class="container-fluid">
              
              <div class="row"> 
              
               	<div class="col-lg-3"> 
               	
               	<div class="card item">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    Submit your answer
                  </div>
                  <div class="card-body"> 
                    <p class="card-text">
                    
                    {{ Form::open() }}
                    
                     <div class="input-group"> 
                          {{ Form::text('answer', '', ['class' => 'form-control', 'required'=>'required', 'placeholder'=>'VU{...}', 'maxlength'=>100]) }} 
                          {{ Form::submit('Submit', ['class'=>'btn btn-primary', 'login'=>'login']) }} 
                     </div>
                     
                     {{ Form::close() }}
                    
                    </p>
                    </div>
               
                </div> 
                 
                </div>
              
                <div class="col-lg-9 item"> 
                
                <div class="card">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    {{ $data['name'] }}
                     
                    
                    <div style="float:right!important;" class="text-center">
                    <span class="badge bg-secondary w-100">{{ $data['points'] }} points </span>   <br>
                    	<span class="text-center"> <button {{ $voted == 0 ? '' : 'disabled' }} class="badge border-0 text-white bg-primary thumbs-up" onClick="thumbs('up', '{{ $data['public_id'] }}')"> <i class="fa-regular fa-thumbs-up"></i> <span id="thumbs-up-count">{{ $thumbs_up_count }}</span></button> </span> &nbsp; 
                    	<span class="text-center"> <button {{ $voted == 0 ? '' : 'disabled' }} class="badge border-0 bg-white text-primary thumbs-down" onClick="thumbs('down', '{{ $data['public_id'] }}')"> <i class="fa-regular fa-thumbs-down"></i> <span id="thumbs-down-count">{{ $thumbs_down_count }}</span></button> </span>
                    </div>
                    
                  </div> 
                  <div class="card-body"> 
                    <p class="card-text"> {!! $data['content'] !!} </p>
                    </div>
               
                </div> 
                
                @if(count($files) > 0)
                    <div class="card">
                      <div class="card-header d-flex justify-content-between align-items-center">
                        Related files (Make sure you haven't downloaded these files previously)
                      </div>
                      <div class="card-body"> 
                      
                      <ul> 
                        @foreach($files as $v)
                        	<li><a href="{{ $v['file_url'] }}" class="text-info" target="_blank"><u>{{ $v['name'] }}</u></a></li>
                        @endforeach
                      </ul>
                        
                        </div> 
                    </div> 
                @endif
                
                <div class="card">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    Challenge created by {{ $partner['name'] }}
                  </div>
                  <div class="card-body"> 
                    <p class="card-text"> <img style="max-width:300px!important;" src="{{ env('APP_URL') }}/upload/{{ $partner['picture'] }}"> </p>
                    </div>
               
                </div> 
                
                
                </div>
              
              </div>
              
            </div>
          </section>
         
           @include("users.partials.footer")
           
        </div>
      </div>
    </div> 
@endsection