<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>VU KNF Labs</title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="robots" content="noindex,nofollow">  
    
    <link href="/assets/img/favicon.ico" rel="shortcut icon" type="image/vnd.microsoft.icon" />
    <link rel="stylesheet" href="/static/fonts/poppins.css"> 
    <link rel="stylesheet" href="/static/fontawesome/6/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/app.min.css">
  
    <!-- Tweaks for older IEs--><!--[if lt IE 9]>
        <script src="/static/js/html5shiv-3.7.3.min.js"></script>
        <script src="/static/js/respond-1.4.2.min.js"></script><![endif]-->
  </head>
  <body>
  
  @yield('content')
  
     
     
    <script src="/assets/js/app.min.js"></script> 
    <script src="/static/js/bootstrap-5.0.2.min.js"></script>
   
   
   @if(session('success'))
       <script>
       $(document).ready(function(){ 
       
           $('.selectcategories').selectpicker();
                             	
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
   
   @if(auth()->guard('users')->check())
 
 	 
	
   @else
   
   
   @if(request()->routeIs('login_users'))
        @if(time() <= $event_start_time)
       
         
              
        @endif
     @endif
   @endif
  

</body>
</html> 