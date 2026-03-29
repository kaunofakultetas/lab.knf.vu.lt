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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,700"> 
    <link rel="stylesheet" href="/assets/css/app_admin.min.css">
  
    <!-- Tweaks for older IEs--><!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script><![endif]-->
  </head>
  <body>
  
  @yield('content') 
   
	<script src="/assets/js/app_admin.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.min.js" integrity="sha384-cVKIPhGWiC2Al4u+LWgxfKTRIcfu0JTxR+EQDz/bgldoEyl4H0zUF0QKbrJ0EcQF" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">
     
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