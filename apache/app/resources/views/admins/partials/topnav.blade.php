<!-- Navbar Menu -->
              <ul class="nav-menu list-unstyled d-flex flex-md-row align-items-md-center">
                <!-- Search-->
               
                <!-- Messages -->
                <li class="nav-item dropdown"> <a class="nav-link text-white" id="messages" rel="nofollow" href="#" data-bs-toggle="dropdown" aria-expanded="false">
             
                  <ul class="dropdown-menu dropdown-menu-end mt-3 shadow-sm" aria-labelledby="messages">
                    <li><a class="dropdown-item d-flex py-3" href="#">  
                        <div class="ms-3"> </div></a></li>
                  
                  </ul>
                </li> 
             
                </li>
                <!-- Logout    -->
                <li class="nav-item"><a class="nav-link text-white" href="{{ env('APP_URL') }}/control-panel/logout"> <span class="d-none d-sm-inline">Logout</span>
                    <svg class="svg-icon svg-icon-xs svg-icon-heavy">
                      <use xlink:href="#security-1"> </use>
                    </svg></a></li>
              </ul>