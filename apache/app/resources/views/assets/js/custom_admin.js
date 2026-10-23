$(document).ready(function(){  
  	
		$('.sidebar-menu > li > a[href="'+ window.location.href +'"]').parent('li').addClass('active');
		//$('.sidebar-menu > li > a[href="'+ window.location.href +'"]').parent('li').parent('ul.collapse').parent('li').addClass('active');
		$('.collapse > li > a[href="'+ window.location.href +'"]').parent('li').addClass('active');
		$('.collapse > li > a[href="'+ window.location.href +'"]').parent('li').parent('ul').parent('li').addClass('active');
		
		 
	 
});

$(function() {
      $('#eventstart').daterangepicker({
        timePicker24Hour: true,
        timePicker: true, 
        locale: {
          format: 'YYYY-MM-DD HH:mm:ss'
        }
      });
      
      $('#summernote').summernote({
			'height': 600
	   });
});

function Confirm_Dialog(URL){
	
	if (confirm("You sure?")) {
			
		Post_Link(URL);
				
	}	 
	   
}

// Sends a POST to the address, with the CSRF token of the
// page's csrf-token meta tag: the control panel makes its
// changes by POST only, which a link on another site cannot
// send, and Laravel refuses a POST without the token
function Post_Link(URL){
	var form = document.createElement('form');
	form.method = 'POST';
	form.action = URL;

	var token = document.createElement('input');
	token.type = 'hidden';
	token.name = '_token';
	token.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
	form.appendChild(token);

	document.body.appendChild(form);
	form.submit();
}

function pgen(length) {
    var result           = '';
    var characters       = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    var charactersLength = characters.length;
    for ( var i = 0; i < length; i++ ) {
        result += characters.charAt(Math.floor(Math.random() * charactersLength));
    }
    $('#f3').val(result);
}