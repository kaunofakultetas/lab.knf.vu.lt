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
              
			<div class="row"> 
            <div class="col-lg-2"> 
              <h2 class="mb-0 p-1">Dashboard</h2>
            </div>  
            <div class="col-lg-10">   
            
             
            
             <table class="float-right">
             	<tr>
             	    <td style="padding-right:20px;"> 
             	      <div class="button-group-cats" data-filter-group="categories">
             	      @if(count($categories)>0) 
             	            <button class="btn btn-light float-left btn-sm m-1 button-group" data-filter="">Any</button>
             	      		@foreach($categories as $k => $val)
                      			<button class="btn btn-light float-left btn-sm m-1 button-group" data-filter=".c{{ $k }}">{{ $val }}</button>
                      		@endforeach
                      @endif
                      </div>
                    </td>
             		<td><label for="min">Points min:&nbsp;</label></td>
             		<td width="100"><input type="number" id="min" name="min" min="0" value="5" class="form-control"></td>
             		<td><label for="max">&nbsp;&nbsp;&nbsp;max:&nbsp;</label></td>
             		<td width="100"><input type="number" id="max" name="max" min="0" value="250" class="form-control"></td>
             		<td>&nbsp;&nbsp;&nbsp;<button id="filter-button" class="btn btn-primary">Filter</button></td>
             	</tr>
             </table>
                
              
              
             </div>
             </div>
             
            </div>
          </header>
          <!-- Dashboard Counts Section-->
          <section class="pb-0">
            <div class="container-fluid">
            
             
              
              <div class="row filtered-grid"> 
              
              @if(count($recordlist) > 0)
							
				  @foreach($recordlist as $value)
				  
              <div class="col-md-4 filtered-item c{{ $value['categories_ids'] }}" data-price="{{ $value['points'] }}"> 
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
                  			<a href="/user/challenge/{{ $value['public_id'] }}" class="btn btn-primary">Solve the challenge</a>
                  		@else 
                  			<a class="btn btn-default pe-none" disabled>Challenge Solved</a>
                  			
                  		   
                  		    <div style="float:right!important;padding-top:5px;">
                  		    
                  		    	<span class="text-center"> <button {{ $value['voted'] == 0 ? '' : 'disabled' }} class="badge border-0 text-white bg-primary thumbs-up-{{ $value['public_id'] }}" onClick="thumbs_list('up', '{{ $value['public_id'] }}')"> <i class="fa-regular fa-thumbs-up"></i> <span id="thumbs-up-count-{{ $value['public_id'] }}">{{ $value['thumbs_up_count'] }}</span></button> </span> &nbsp; 
                    			<span class="text-center"> <button {{ $value['voted'] == 0 ? '' : 'disabled' }} class="badge border-0 bg-white text-primary thumbs-down-{{ $value['public_id'] }} " onClick="thumbs_list('down', '{{ $value['public_id'] }}')"> <i class="fa-regular fa-thumbs-down"></i> <span id="thumbs-down-count-{{ $value['public_id'] }}">{{ $value['thumbs_down_count'] }}</span></button> </span>
                  		    
                  		    </div>
                   		 
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