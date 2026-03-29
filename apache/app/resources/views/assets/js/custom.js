$(document).ready(function(){  
  	
		$('.sidebar-menu > li > a[href="'+ window.location.href +'"]').parent('li').addClass('active');
		//$('.sidebar-menu > li > a[href="'+ window.location.href +'"]').parent('li').parent('ul.collapse').parent('li').addClass('active');
		$('.collapse > li > a[href="'+ window.location.href +'"]').parent('li').addClass('active');
		$('.collapse > li > a[href="'+ window.location.href +'"]').parent('li').parent('ul').parent('li').addClass('active');
		
		 $('.item').matchHeight();
		 
		 $('input[name="user_type"]').change(function() {
			  if ($(this).val() == 1) {
			    $('#user-type-label').text('Username');
			  } else if ($(this).val() == 2) {
			    $('#user-type-label').text('Team name');
			  }
		 });
		 
		    
	   //  $('.filtered-grid').isotope({ 
		//  itemSelector: '.filtered-item' 
		//});
		
		var $grid = $('.filtered-grid').isotope({
		    itemSelector: '.filtered-item',
		    layoutMode: 'fitRows' 
		  }); 
		  
		  if(Cookies.get('filter_points_from') != undefined && Cookies.get('filter_points_to') != undefined && Cookies.get('filter_category') != undefined){
			  $('#min').val(Cookies.get('filter_points_from'));
			  $('#max').val(Cookies.get('filter_points_to'))
			  var category = Cookies.get('filter_category');
			  $("[data-filter='"+Cookies.get('filter_category')+"']").addClass('active');
			  
			  $grid.isotope({
				      filter: function() {
				        var price = $(this).data('price');
				        return (!category || $(this).is(category)) && price >= Cookies.get('filter_points_from') && price <= Cookies.get('filter_points_to');
				      } 
				  });
		  } 
		
		  $('#filter-button').on( 'click', function() {
		    var min = parseInt($('#min').val());
		    var max = parseInt($('#max').val());
		    
		    var category = $('.button-group-cats').find('.active').data('filter');
		    Cookies.set('filter_category', category, { secure: true });
		    Cookies.set('filter_points_from', min, { secure: true });
		    Cookies.set('filter_points_to', max, { secure: true });
		
		    $grid.isotope({
		      filter: function() {
		        var price = $(this).data('price');
		        return (!category || $(this).is(category)) && price >= min && price <= max;
		      }
		    });
		  }); 
		  
		  $(".button-group").on( 'click', function() {
			      var single_category = $(this).data('filter');
			      var min = parseInt($('#min').val());
		    	  var max = parseInt($('#max').val());
		    	  
		    	  Cookies.set('filter_category', single_category, { secure: true });
		    	  Cookies.set('filter_points_from', min, { secure: true });
		          Cookies.set('filter_points_to', max, { secure: true });
		    
				  $grid.isotope({
				      filter: function() {
				        var price = $(this).data('price');
				        return (!single_category || $(this).is(single_category)) && price >= min && price <= max;
				      } 
				  });
			      
		   });
		  
		  $('.button-group-cats').on('click', 'button', function() {
		       $(this).addClass('active').siblings().removeClass('active');
		  }); 
		  
		  $('select[multiple]').multiselect({
			  texts: {
        			placeholder: 'Select Categories' 
    		  }
		  });
		  
		  $grid.on( 'layoutComplete', function( event, filteredItems ) {			
			  $('.item').matchHeight();
		  });
		  
		  
		  
		  
		//  $grid.on('layoutComplete', function() {
		   
		//      $('.item').matchHeight();
	 
		//  });
	 
}); 


function thumbs(type, public_id){
	 
	 if(type == 'up'){
		 $('#thumbs-up-count').text(parseInt($('#thumbs-up-count').text())+1);
	 } 
	 
	 if(type == 'down'){
		 $('#thumbs-down-count').text(parseInt($('#thumbs-down-count').text())+1);
	 }
	  
				
	 $('.thumbs-up, .thumbs-down').attr('disabled', 'disabled');	 
	 
	 $.get('/user/challenge/vote/'+type+'/'+public_id); 
	   
}  


function thumbs_list(type, public_id){
	 
	 if(type == 'up'){
		 $('#thumbs-up-count-'+public_id).text(parseInt($('#thumbs-up-count-'+public_id).text())+1);
	 } 
	 
	 if(type == 'down'){
		 $('#thumbs-down-count-'+public_id).text(parseInt($('#thumbs-down-count-'+public_id).text())+1);
	 }
	  
				
	 $('.thumbs-up-'+public_id+', .thumbs-down-'+public_id).attr('disabled', 'disabled');	 
	 
	 $.get('/user/challenge/vote/'+type+'/'+public_id); 
	   
}  



function feedback(){
	
	$('.modal-title').text('Leave a Feedback');
	 
	$('.modal-body').fadeIn(500,function() {
		 
		 $(this).load("/user/dialog/feedback");
		 
	});
	
	$('#mod').modal('show');
	
} 



function Confirm_Dialog(URL){
	
	if (confirm("You sure?")) {
			
		window.location.href = URL;
				
	}	 
	   
}  