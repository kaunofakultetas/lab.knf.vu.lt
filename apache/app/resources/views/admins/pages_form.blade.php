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
              <h2 class="mb-0 p-1">Page form</h2>
            </div>
          </header>
          <!-- Dashboard Counts Section-->
          <section class="pb-0">
            <div class="container-fluid">
               
               <div class="row">
                <!-- Basic Form-->
                <div class="col-lg-12">
                  <div class="card">
                    <div class="card-header">
                 
                      <h3 class="h4 mb-0">Page Form</h3>
                    </div>
                    <div class="card-body"> 
                      {{ Form::open(['url' => $url]) }}
                        <div class="mb-3">
                          <label class="form-label">Name</label>
                          {{ Form::text('name', $name, ['class' => 'form-control', 'required'=>'required']) }} 
                        </div>
                        
                        <div class="mb-3"> 
							{{ Form::textarea('content', $content, ['id'=>'summernote','required'=>'required']) }}  
                        </div>
                        
                        
                        <div class="mb-3">
                          <label class="form-label">Alias</label>
                          {{ Form::text('alias', $alias, ['class' => 'form-control', 'required'=>'required']) }} 
                        </div>
                        
                        <div class="mb-3 mt-3">
                        {{ Form::submit('Submit', ['class'=>'btn btn-primary']) }}
                         </div>
                         
                         {{ Form::hidden('uid', $uid ?? 0) }}
                        
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