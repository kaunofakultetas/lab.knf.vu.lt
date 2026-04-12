<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>VU KNF Labs - Admin</title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="robots" content="noindex,nofollow">
    <!-- Google fonts - Poppins --> 
    <link href="/assets/img/favicon.ico" rel="shortcut icon" type="image/vnd.microsoft.icon" />
    <link rel="stylesheet" href="/static/fonts/poppins.css"> 
    <link rel="stylesheet" href="/static/fontawesome/6/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/app_admin.min.css">
  
    <!-- Tweaks for older IEs--><!--[if lt IE 9]>
        <script src="/static/js/html5shiv-3.7.3.min.js"></script>
        <script src="/static/js/respond-1.4.2.min.js"></script><![endif]-->
  </head>
  <body>
  
  @yield('content') 
   
	<script src="/assets/js/app_admin.min.js"></script>
    
    <script src="/static/js/bootstrap-5.0.2.min.js"></script>
     
 @if(session('success'))
       <script>
       $(document).ready(function(){
                             	
           $.toast({
           
            heading: 'Success',
            text: '{{ session('success') }}',
            showHideTransition: 'slide',
            icon: 'success',
            hideAfter: 10000,
            position: 'top-right'
             
           });
                                   
        }); 
       </script>
   @endif 
                    
   @if($errors->any())
       <script>
	     $(document).ready(function(){
                             	
           $.toast({
            	heading: 'Error',
            	text:[ 
                   @foreach ($errors->all() as $error)
                      '{{ $error }}',
                   @endforeach
                   ],
                 showHideTransition: 'fade',
            	icon: 'error',
            	hideAfter: 10000,
            	position: 'top-right'
		   });
                                   
        }); 
	</script>
   @endif
   
   			@if(!request()->routeIs('login_admins'))
   					<div class="modal fade text-start" id="mod" tabindex="-1" aria-labelledby="myModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                          <div class="modal-content">
                            <div class="modal-header">
                              <h5 class="modal-title" id="myModalLabel">&nbsp;</h5>
                              <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                              &nbsp;
                            </div>
                            <div class="modal-footer">
                              <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button> 
                            </div>
                          </div>
                        </div>
          			</div>              
             @endif           
  </body>
</html> 