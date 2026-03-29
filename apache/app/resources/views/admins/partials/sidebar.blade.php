<!-- Side Navbar -->
<nav class="side-navbar z-index-40">
  <!-- Sidebar Header-->
  <div class="sidebar-header d-flex align-items-center py-4 px-3">
    <div class="ms-3 title">
      <h1 class="h4 mb-2">{{ $vu_id }}</h1>
      <p class="text-sm text-gray-500 fw-light mb-0 lh-1">Logged in</p>
    </div>
  </div>
  <!-- Sidebar Navidation Menus-->
  <span class="text-uppercase text-gray-400 text-xs letter-spacing-0 mx-3 px-2 heading">Main</span>
  <ul class="list-unstyled py-4 sidebar-menu">
    <li class="sidebar-item">
      <a class="sidebar-link" href="{{ env('APP_URL') }}/control-panel/dashboard">
        <svg class="svg-icon svg-icon-sm svg-icon-heavy me-xl-2">
          <use xlink:href="#real-estate-1"></use>
        </svg>Home </a>
    </li> 
    
    <li class="sidebar-item">
      <a class="sidebar-link" href="#example1dropdownDropdown" data-bs-toggle="collapse">
        <svg class="svg-icon svg-icon-sm svg-icon-heavy me-xl-2">
          <use xlink:href="#user-1"></use>
        </svg>Users </a>
      <ul class="collapse list-unstyled " id="example1dropdownDropdown">
        <li>
          <a class="sidebar-link" href="{{ env('APP_URL') }}/control-panel/users">All users</a>
        </li>
        <li>
          <a class="sidebar-link" href="{{ env('APP_URL') }}/control-panel/users/vu-groups">VU Groups</a>
        </li>  
      </ul>
    </li>
     
    <li class="sidebar-item">
      <a class="sidebar-link" href="#exampledropdownDropdown" data-bs-toggle="collapse">
        <svg class="svg-icon svg-icon-sm svg-icon-heavy me-xl-2">
          <use xlink:href="#list-details-1"></use>
        </svg>Challenges </a>
      <ul class="collapse list-unstyled " id="exampledropdownDropdown">
        <li>
          <a class="sidebar-link" href="{{ env('APP_URL') }}/control-panel/challenges">All challenges</a>
        </li>
        @if($main == 1)
        <li>
          <a class="sidebar-link" href="{{ env('APP_URL') }}/control-panel/categories">Categories</a>
        </li>  
        @endif
      </ul>
    </li>
    
    @if($main == 1)
    
    <li class="sidebar-item">
      <a class="sidebar-link" href="{{ env('APP_URL') }}/control-panel/pages">
        <svg class="svg-icon svg-icon-sm svg-icon-heavy me-xl-2">
          <use xlink:href="#stack-1"></use>
        </svg>Pages </a>
    </li>
    
    <li class="sidebar-item">
      <a class="sidebar-link" href="{{ env('APP_URL') }}/control-panel/emails">
        <svg class="svg-icon svg-icon-sm svg-icon-heavy me-xl-2">
          <use xlink:href="#paper-plane-1"></use>
        </svg>Emails </a>
    </li>
    
    <li class="sidebar-item">
      <a class="sidebar-link" href="{{ env('APP_URL') }}/control-panel/settings">
        <svg class="svg-icon svg-icon-sm svg-icon-heavy me-xl-2">
          <use xlink:href="#settings-1"></use>
        </svg>Settings </a>
    </li>
    
    @endif
     
     
  </ul>
 
</nav>