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
              <h2 class="float-left mb-0 p-1">Dashboard</h2>
              
              <div class="float-right col-md-8"> 
              <span><small><b>{{ $solved_percent ?? 0 }}%</b> - <a href="/user/group/leaderboard/{{ $public_id }}">Group Leaderboard</a></small></span>
              <div class="progress"> 
             		<div class="progress-bar bg-info" role="progressbar" style="width: {{ $solved_percent ?? 0 }}%" aria-valuenow="{{ $solved_percent ?? 0 }}" aria-valuemin="0" aria-valuemax="100"></div>
              </div>
              </div>
              
              <div class="clear"></div>
     
            </div>
          </header>
          <!-- Dashboard Counts Section-->
          <section class="pb-0">
            <div class="container-fluid">
              
              <div class="row"> 
              
              @if(count($recordlist) > 0)
							
				  @foreach($recordlist as $value)
				  
              <div class="col-md-4"> 
              	<div class="card item">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    {{ $value['name'] }} <span class="badge bg-secondary">{{ $value['points'] }} points</span> 
                  </div>
                  <div class="card-body">
                    <h5 class="card-title">{{ $value['categories'] }}</h5>
                    <p class="card-text">{{ substr($value['descr'], 0, 100) }}...</p>
                    </div>
                  <div class="card-footer">
                  		@if($value['solved'] == 0)
                  			<a href="/user/challenge/{{ $value['public_id'] }}/{{ $public_id }}" class="btn btn-primary">Solve the challenge</a>
                  		@else 
                  			<a class="btn btn-default pe-none" disabled>Challenge Solved</a>
                  		@endif
                  </div>
                </div>   
              </div>  
              
                 @endforeach
                 
             @elseif(count($recordlist) == 0)  
					  <div class="col-md-12"><center>No challenges</center></div>
		     @endif
              
              
              </div>
              
            </div>
          </section>
         
           @include("users.partials.footer")
           
        </div>
      </div>
    </div> 
@endsection