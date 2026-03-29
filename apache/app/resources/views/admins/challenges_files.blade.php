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
              <h2 class="mb-0 p-1">{{ $challenge_name }} files</h2>
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
                          <button class="dropdown-toggle text-md" type="button" onClick="challenge_files_form('new', {{ $uid }});">
                          	<i class="fas fa-plus"></i>
                          </button>
                   
                        </div>
                      </div>
                      <h3 class="h4 mb-0">{{ $challenge_name }} files</h3>
                    </div>
                   
             <div class="card-body">
                      <div class="table-responsive">
                        <table class="table mb-0 table">
                          <thead>
                            <tr> 
                              <th>Name</th>
                              <th width="80"> </th>
                              <th width="80"> </th>
                            </tr>
                          </thead>
                          <tbody>
                          
                          @if(count($recordlist) > 0)

                                @foreach ($recordlist as $value)
                                
                                <tr> 
                                  <td>{{ $value['name'] }}</td>
                                  <td class="text-center"> <a href="#" onClick="challenge_files_form('edit', {{ $value['id'] }});"><i class="fas fa-edit"></i></a> </td>
                                  <td class="text-center"> <a href="#" onClick="Confirm_Dialog('/control-panel/challenges/files/delete/{{ $value['id'] }}');"><i class="fas fa-trash"></i></a> </td>
                                </tr>
                                <tr>  
                                @endforeach
 
                            @elseif(count($recordlist) == 0)
                            	<tr>
                            		<td colspan="4"><center>No challenge files</center></td>
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