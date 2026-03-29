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
			
		window.location.href = URL;
				
	}	 
	   
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