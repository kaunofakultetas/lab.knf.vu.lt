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
              <h2 class="mb-0 p-1">Dashboard</h2>
            </div>
          </header>
          <!-- Dashboard Counts Section-->
          <section class="pb-0">
            <div class="container-fluid">
            
            
               <div class="col-lg-12">
               <div class="row"> 
               <div class="card mb-4">
                <div class="card-body">
                  <div class="row gx-5 bg-white">
                    <!-- Item -->
                    <div class="col-xl-3 col-sm-6 py-4 border-lg-end border-gray-200">
                      <div class="d-flex align-items-center">
                        <div class="icon flex-shrink-0 bg-blue">
                          <svg class="svg-icon svg-icon-sm svg-icon-heavy">
                            <use xlink:href="#user-1"> </use>
                          </svg>
                        </div>
                        <div class="mx-4">
                          <h6 class="h4 fw-light text-gray-600 mb-3">Registered<br>Users</h6>
                     
                        </div>
                        <div class="number"><strong class="text-lg">{{ $registered_users }}</strong></div>
                      </div>
                    </div> 
                    <!-- Item -->
                    <div class="col-xl-3 col-sm-6 py-4 border-lg-end border-gray-200">
                      <div class="d-flex align-items-center">
                      <div class="icon flex-shrink-0 bg-blue">
                          <svg class="svg-icon svg-icon-sm svg-icon-heavy">
                            <use xlink:href="#user-1"> </use>
                          </svg>
                        </div>
                         
                        <div class="mx-4">
                          <h6 class="h4 fw-light text-gray-600 ">Logged in<br>Users</h6>
                    
                        </div>
                        <div class="number"><strong class="text-lg">{{ $loggedin_users }}</strong></div>
                      </div>
                    </div>
                    <!-- Item -->
                    <div class="col-xl-3 col-sm-6 py-4 border-lg-end border-gray-200">
                      <div class="d-flex align-items-center">
                          <div class="icon flex-shrink-0 bg-blue">
                          <svg class="svg-icon svg-icon-sm svg-icon-heavy">
                            <use xlink:href="#list-details-1"> </use>
                          </svg>
                        </div>
                        <div class="mx-4">
                          <h6 class="h4 fw-light text-gray-600 ">Solved at least<br>one challenge</h6>
                  
                        </div>
                        <div class="number"><strong class="text-lg">{{ $solvedone_users }}</strong></div>
                      </div>
                    </div> 
                    
                    <div class="col-xl-3 col-sm-6 py-4 border-gray-200">
                      <div class="d-flex align-items-center">
                          <div class="icon flex-shrink-0 bg-blue">
                          <svg class="svg-icon svg-icon-sm svg-icon-heavy">
                            <use xlink:href="#user-1"> </use>
                          </svg>
                        </div>
                        <div class="mx-4">
                          <h6 class="h4 fw-light text-gray-600 ">Total<br>groups</h6>
                  
                        </div>
                        <div class="number"><strong class="text-lg">{{ $total_groups }}</strong></div>
                      </div>
                    </div> 
                    
                    
                  </div>
                </div>
              </div>
              </div>
              </div>
              
               
            
            	<div class="row"> 
            	
            	<div class="col-lg-6">
                  
                  @if(count($latest_solved)>0)
                  <div class="card mb-0">
                    <div class="card-header"> 
                      <h3 class="h4 mb-0">Latest solved challenges</h3>
                    </div>
                    <div class="card-body">
                      <div class="table-responsive">
                        <table class="table mb-0">
                          <thead>
                            <tr> 
                              <th>Challenge</th>
                              <th>Points</th>
                              <th>Username</th>
                              <th>Solved</th>
                            </tr>
                          </thead>
                          <tbody>
                          @foreach($latest_solved as $log)
                            <tr> 
                              <td>{{ $log['name'] }}</td>
                              <td>{{ $log['points'] }}</td>
                              <td>{{ $log['username'] }}</td>
                              <td><span class="text-muted">{{ $log['created'] }}</span></td>
                            </tr> 
                           @endforeach
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div> 
                 @endif
                 
                 </div>
               
              
                <div class="col-lg-6">
                  <div class="card mb-4">
                    <div class="card-header position-relative">
                     
                      <h2 class="h3 mb-0 d-flex align-items-center">Top 10 players</h2>
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
                          {{ $player['user_rank'] }} place
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
                      
                      @endif
                      
                 
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