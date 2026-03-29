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
            
            <div class="row"> 
            <div class="col-lg-7"> 
              <h2 class="mb-0 p-1">Users</h2>
            </div>  
            <div class="col-lg-5">   
             {{ Form::open(['method' => 'GET', 'class' => 'd-flex align-items-center']) }}
                  <div class="form-group flex-grow-1">  
                    {{ Form::text('q', request()->query('q'), ['class' => 'form-control form-control-xs float-right', 'placeholder' => 'Search by username or email']) }}
                  </div>
                  {{ Form::button('Search', ['class' => 'btn btn-primary ml-2', 'type' => 'submit']) }}
                {{ Form::close() }}
             </div>
             </div>
                  @if(request()->query('q') !='')
                    <a href="{{ env('APP_URL') }}/control-panel/users" class="float-right"><small><b>&#xD7; Clear all</b></small></a>
                    <div class="clear"></div>
                  @endif
            </div>
          </header>
          <!-- Dashboard Counts Section-->
          <section class="pb-0">
            <div class="container-fluid">
             
             <div class="row"> 
             <div class="col-lg-12"> 
             
              <div class="card mb-0">
             <div class="card-header">
                      <div class="card-close">
                        <div class="dropdown">
                          <a class="dropdown-toggle text-md xxsd" href="#" onClick="users_form('new');">
                          	<i class="fas fa-plus"></i>
                          </a>
                   
                        </div>
                      </div>
                      <h3 class="h4 mb-0">Users</h3>
                    </div>
                   
             <div class="card-body">
                      <div class="table-responsive">
                        <table class="table mb-0 table">
                          <thead>
                            <tr> 
                              <th>Username</th>
                              <th width="80"> </th>
                              <th>Email</th>
                              <th>Last Login</th>
                              <th align="center">Status</th> 
                              
                              @if($main == 1)
                              <th width="80"> </th>
                              <th width="80"> </th>
                              @endif
                            </tr>
                          </thead>
                          <tbody>
                          
                          @if(count($userlist) > 0)

                                @foreach ($userlist as $value)
                                
                                <tr> 
                                  <td>{{ $value['username'] }}</td> 
                                  <td class="text-center">
                                  	@if($value['user_type'] == 1)
                            	 	  <i class="fas fa-user"></i>
                                	 @elseif($value['user_type'] == 2)
                                	  <i class="fas fa-users"></i>
                                	 @endif
                                  </td>
                                  <td>{{ $value['email'] }}</td> 
                                  <td>{{ $value['last_login'] }}</td>
                                  <td><a href="/control-panel/users/status/{{ $value['id'] }}/{{ $value['status'] }}">@if($value['status'] == 1) 
                                  	 <span class="badge bg-success">Active</span>  
                                  	  @else <span class="badge bg-danger">Not active</span>  
                                  	  @endif </a></td> 
                                  	  
                                  @if($main == 1)
                                  <td class="text-center"> <a href="#" onClick="users_form('edit', {{ $value['id'] }});"><i class="fas fa-edit"></i></a> </td>
                                  <td class="text-center"> <a href="#" onClick="Confirm_Dialog('/control-panel/users/delete/{{ $value['id'] }}');"><i class="fas fa-trash"></i></a> </td>
                                  @endif
                                </tr>
                                <tr>  
                                @endforeach
                                
                               
                        			<tr><td colspan="7">{!! $userlist->links()->render() !!}</td></tr>
                        	 
 
                            @elseif(count($userlist) == 0)
                            	<tr>
                            		<td colspan="7"><center>No Users</center></td>
                            	</tr>
                            @endif
                            
                            
                    
                             
                          
                          </tbody>
                        </table>
                      </div>
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