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
                  <div class="brand-text d-none d-lg-inline-block"><img src="{{ env('APP_URL') }}/assets/img/logo-light.png" class="logo"></div>
            	  <div class="brand-text d-none d-sm-inline-block d-lg-none"><img src="{{ env('APP_URL') }}/assets/img/logo-icon.png" class="logo_icon"></div></a>	
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
              <h2 class="mb-0 p-1">{{ $group_name }} Leaderboard</h2>
            </div>
          </header>
          <!-- Dashboard Counts Section-->
          <section class="pb-0">
            <div class="container-fluid">
              
              <div class="row"> 
               
              
                <div class="col-lg-12">
                  <div class="card mb-0">
                    <div class="card-header position-relative">
                     
                      <h2 class="h3 mb-0 d-flex align-items-center">{{ $group_name }} Top players</h2>
                      
                      @if(count($group_list)>0)
                      	  <div style="display:inline-block;padding-top:5px;"> <small> 
                      	  <a href="{{ env('APP_URL') }}/user/leaderboard"> Leaderboard</a> &nbsp;&nbsp; 
                         @foreach($group_list as $k => $val)
                            @if($val['name'] !== $group_name)
                            <a href="{{ env('APP_URL') }}/user/group/leaderboard/{{ $val['public_id'] }}"> {{ $val['name'] }} Leaderboard</a> &nbsp;&nbsp; 
                            @endif
                         @endforeach
                         </small></div>
                    @endif
            
                    </div>
                    <div class="card-body p-0">
                    
                      @if(count($ranking_table)>0)
                      
                          @foreach($ranking_table as $player)
                          
                          <div class="p-3 d-flex align-items-center {{ $loop->index % 2 == 1 ? 'bg-light' : '' }}"> 
                          <div class="rounded-square p-1 border border-faintBlue flex-shrink-0 text-center" style="width:100px!important;font-size:12px;">
                          @if($loop->iteration == 1)<i class="fas fa-trophy first-place"></i><br>@endif
                          @if($loop->iteration == 2)<i class="fas fa-award second-place"></i><br>@endif
                          @if($loop->iteration == 3)<i class="fas fa-shield-alt third-place"></i><br>@endif
                          @if($loop->iteration >= 4 && $loop->iteration <= 10)<i class="fas fa-user-secret ft-place"></i><br>@endif
                          {{ $player['position'] }} place
                          </div>
                           
                            <div class="ms-3 flex-fill w-25">
                            	 <h3 class="h2 fw-normal text-dark mb-0">{{ $player['username'] }}</h3> 
                            	<small class="text-gray-500"> <b>{{ $player['points'] }}</b> points </small>
                            </div>
                            
                            <div class="ms-5 flex-fill" style="text-align:left!important;"> 
                            	 <h3 class="h6 fw-normal text-dark">
                            	 @if($player['user_type'] == 1)
                            	 	<i class="fas fa-user"></i>
                            	 @elseif($player['user_type'] == 2)
                            	 	<i class="fas fa-users"></i>
                            	 @endif
                            	 </h3>  
                            </div>
                            
                            
                            <div class="ms-5 flex-fill" style="text-align:right!important;">
                            	<small class="text-gray-500 mb-0"> Last challenge solved </small>
                            	 <h3 class="h6 fw-normal text-dark">{{ $player['last_challenge'] }}</h3> 
                            	 
                            </div>
                            
                            
                          </div>  
                          
                          @endforeach
                          
                      @else 
                      
                      <div class="ms-3 flex-fill text-center p-5 m-5"> 
                           <small class="text-center h4"> Nobody yet! </small>
                       </div>
                      
                      @endif
                      
                 
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