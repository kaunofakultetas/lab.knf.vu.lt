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
              <h2 class="mb-0 p-1">Challenges form</h2>
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
                 
                      <h3 class="h4 mb-0">Challenges form</h3>
                    </div>
                    <div class="card-body"> 
                      <form method="POST" action="{{ $url }}" accept-charset="UTF-8">@csrf
                        <div class="mb-3">
                          <label class="form-label">Name</label>
                          <input class="form-control" required="required" maxlength="255" name="name" type="text" value="{{ old('name', $name) }}"> 
                        </div>
                        
                        <div class="mb-3">
                          <label class="form-label">Select partner</label>
                          <select class="form-control" id="partner" required="required" name="partner_id">@foreach ($partner_list as $option_value => $option_text)<option value="{{ $option_value }}"@if ((string) $option_value === (string) old("partner_id", $partner_id ?? 0)) selected="selected"@endif>{{ $option_text }}</option>@endforeach</select>
                        </div>
                        
                        <div class="mb-3"> 
                        	<label class="form-label">Description</label>
							<textarea class="form-control" required="required" name="descr" cols="50" rows="10">{{ old('descr', $descr) }}</textarea>  
                        </div>
                        
                        <div class="mb-3"> 
                        	<label class="form-label">Content</label>
							<textarea id="summernote" required="required" name="content" cols="50" rows="10">{{ old('content', $content) }}</textarea>  
                        </div>
                         
                        <div class="mb-3">
                          <label class="form-label">Answer</label>
                          <input class="form-control" required="required" maxlength="255" name="answer" type="text" value="{{ old('answer', $answer) }}"> 
                        </div>
                        
                        <div class="mb-3">
                          <label class="form-label">Points</label>
                          <input class="form-control" required="required" name="points" type="number" value="{{ old('points', $points) }}"> 
                        </div>
                        
                        <div class="mb-3 mt-3">
                        <input class="btn btn-primary" type="submit" value="Submit">
                         </div>
                         
                         <input name="uid" type="hidden" value="{{ old('uid', $uid ?? 0) }}">
                        
                      </form>
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