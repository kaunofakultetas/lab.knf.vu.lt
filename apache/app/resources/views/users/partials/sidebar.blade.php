 <!-- Side Navbar -->
        <nav class="side-navbar z-index-40">
          <!-- Sidebar Header-->
          <div class="sidebar-header d-flex align-items-center py-4 px-3"> 
            <div class="ms-3 title">
              <h1 class="h4 mb-2">{{ $username ?? '' }}</h1>
              
              @isset($my_position)
              <p class="text-sm text-gray-500 fw-light mb-0 lh-1">  
              <span style="font-weight:bold!important;">
              @if($my_position == '1st')<i class="fas fa-trophy first-place2"></i>&nbsp; @endif
               @if($my_position == '2nd')<i class="fas fa-award second-place2"></i>&nbsp; @endif
               @if($my_position == '3rd')<i class="fas fa-shield-alt third-place2"></i>&nbsp; @endif
               @if(in_array($my_position, ['4th','5th','6th','7th','8th','9th','10th']))<i class="fas fa-user-secret ft-place2"></i>&nbsp; @endif
               {{ $my_position }}</span> place |  
              <span style="font-weight:bold!important;">{{ $points ?? ''}}</span> points 
              </p>
              @endisset
            </div>
          </div>
       
          <ul class="list-unstyled py-4 sidebar-menu">
            <li class="sidebar-item"><a class="sidebar-link" href="{{ env('APP_URL') }}/user/dashboard"> 
                <svg class="svg-icon svg-icon-sm svg-icon-heavy me-xl-2">
                  <use xlink:href="#real-estate-1"> </use>
                </svg>Home </a>
            </li>
            @if(count($group_list)>0)
                 @foreach($group_list as $k => $val)
                     <li class="sidebar-item"><a class="sidebar-link" href="{{ env('APP_URL') }}/user/group/{{ $val['public_id'] }}"> 
                        <svg class="svg-icon svg-icon-sm svg-icon-heavy me-xl-2">
                          <use xlink:href="#list-details-1"> </use>
                        </svg>{{ $val['name'] }}</a>
                     </li>
                 @endforeach
            @endif
          </ul>
          
          @if(count($history_list)>0)
          <span class="text-uppercase text-gray-400 text-xs letter-spacing-0 mx-3 px-2 heading">History</span>
          <ul class="list-unstyled py-4">
            @foreach($history_list as $k => $val)
            <li> <span class="sidebar-link2 text-dark"><strong>{{ $val['username'] }}</strong> solved {{ $val['name'] }} challenge  <span class="timestamp text-muted">{{ $val['elapsed'] }}</span></span> </li> 
            @endforeach
          </ul>
          @endif
          
        </nav>