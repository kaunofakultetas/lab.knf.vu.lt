<!-- Navbar Menu -->
              <ul class="nav-menu list-unstyled d-flex flex-md-row align-items-md-center">
                <!-- Search-->
               
                <!-- Messages -->
                <li class="nav-item dropdown"> <a class="nav-link text-white" id="messages" rel="nofollow" href="#" data-bs-toggle="dropdown" aria-expanded="false">
             
                  <ul class="dropdown-menu dropdown-menu-end mt-3 shadow-sm" aria-labelledby="messages">
                    <li><a class="dropdown-item d-flex py-3" href="#"> <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=" class="img-fluid rounded-circle" width="45">   
                        <div class="ms-3"> </div></a></li>
                  
                  </ul>
                </li>
             
                  <li class="nav-item"><a class="nav-link text-white d-flex align-items-center" href="{{ env('APP_URL') }}/user/leaderboard"><span class="d-sm-inline-block">Leaderboard</span></a>
            	  
                </li>
                <li class="nav-item">
                
                @if($vu_id == NULL)
                	<a class="nav-link text-white" href="{{ env('APP_URL') }}/user/logout">
                @elseif($vu_id != NULL)
                	<a class="nav-link text-white" href="{{ env('APP_URL') }}/user/logout_saml">
                @endif
                
                <span class="d-none d-sm-inline">Logout</span>
                    <svg class="svg-icon svg-icon-xs svg-icon-heavy">
                      <use xlink:href="#security-1"> </use>
                    </svg></a></li>
              </ul>