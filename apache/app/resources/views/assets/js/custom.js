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
		  
		  // The filter's cookies from before its boxes started
		  // empty held the prices of their day rather than bounds
		  // the player typed, and would hide dearer challenges
		  // added since: they are dropped unread, and the filter
		  // keeps its bounds under names of its own
		  Cookies.remove('filter_category');
		  Cookies.remove('filter_points_from');
		  Cookies.remove('filter_points_to');
		  
		  if(Cookies.get('dashboard_filter_from') != undefined && Cookies.get('dashboard_filter_to') != undefined && Cookies.get('dashboard_filter_category') != undefined){
			  $('#min').val(Cookies.get('dashboard_filter_from'));
			  $('#max').val(Cookies.get('dashboard_filter_to'))
			  // Only a category one of the page's own buttons carries
			  // comes back from the cookie: other hosts of the domain
			  // can set the cookie too, and .is() builds HTML out of
			  // a string that looks like it; anything else, an
			  // "undefined" saved by a Filter click without a
			  // category among them, means no category
			  var category = Cookies.get('dashboard_filter_category');
			  var $button = $('.button-group-cats .button-group').filter(function() {
				  return $(this).attr('data-filter') === category;
			  });
			  if ($button.length === 0) {
				  category = '';
			  }
			  $button.addClass('active');
			  
			  $grid.isotope({
				      filter: function() {
				        var price = $(this).data('price');
				        return (!category || $(this).is(category)) && price >= pointsBound(Cookies.get('dashboard_filter_from'), -Infinity) && price <= pointsBound(Cookies.get('dashboard_filter_to'), Infinity);
				      } 
				  });
		  } 
		
		  $('#filter-button').on( 'click', function() {
		    var min = pointsBound($('#min').val(), -Infinity);
		    var max = pointsBound($('#max').val(), Infinity);
		    
		    var category = $('.button-group-cats').find('.active').data('filter');
		    Cookies.set('dashboard_filter_category', category, { secure: true });
		    Cookies.set('dashboard_filter_from', $('#min').val(), { secure: true });
		    Cookies.set('dashboard_filter_to', $('#max').val(), { secure: true });
		
		    $grid.isotope({
		      filter: function() {
		        var price = $(this).data('price');
		        return (!category || $(this).is(category)) && price >= min && price <= max;
		      }
		    });
		  }); 
		  
		  $(".button-group").on( 'click', function() {
			      var single_category = $(this).data('filter');
			      var min = pointsBound($('#min').val(), -Infinity);
		    	  var max = pointsBound($('#max').val(), Infinity);
		    	  
		    	  Cookies.set('dashboard_filter_category', single_category, { secure: true });
		    	  Cookies.set('dashboard_filter_from', $('#min').val(), { secure: true });
		          Cookies.set('dashboard_filter_to', $('#max').val(), { secure: true });
		    
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


// A bound of the points filter, read from its box or from the
// cookie that keeps the box's text: anything but a number —
// an emptied box, say — is no bound, given back as none
function pointsBound(value, none){
	var bound = parseInt(value, 10);
	return isNaN(bound) ? none : bound;
}


function thumbs(type, public_id){
	 
	 if(type == 'up'){
		 $('#thumbs-up-count').text(parseInt($('#thumbs-up-count').text())+1);
	 } 
	 
	 if(type == 'down'){
		 $('#thumbs-down-count').text(parseInt($('#thumbs-down-count').text())+1);
	 }
	  
				
	 $('.thumbs-up, .thumbs-down').attr('disabled', 'disabled');	 
	 
	 $.post('/user/challenge/vote/'+type+'/'+public_id, {_token: $('meta[name="csrf-token"]').attr('content')}); 
	   
}  


function thumbs_list(type, public_id){
	 
	 if(type == 'up'){
		 $('#thumbs-up-count-'+public_id).text(parseInt($('#thumbs-up-count-'+public_id).text())+1);
	 } 
	 
	 if(type == 'down'){
		 $('#thumbs-down-count-'+public_id).text(parseInt($('#thumbs-down-count-'+public_id).text())+1);
	 }
	  
				
	 $('.thumbs-up-'+public_id+', .thumbs-down-'+public_id).attr('disabled', 'disabled');	 
	 
	 $.post('/user/challenge/vote/'+type+'/'+public_id, {_token: $('meta[name="csrf-token"]').attr('content')}); 
	   
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

// Sends a POST to the address, with the CSRF token of the
// page's csrf-token meta tag: signing out is a POST only,
// which a link on another site cannot send, and Laravel
// refuses a POST without the token
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

// A form behind reCAPTCHA v3 — the registration and reminder
// forms, which carry data-recaptcha-action — fetches its token
// as it is sent rather than as the page loads: a token is good
// for two minutes only, so a form filled in more slowly would
// be refused. A second press while the token is on its way
// sends nothing more, as a double press would register twice
// or mail two reminder links — for fifteen seconds, after
// which a press sends again: a send cancelled in the browser,
// or a token that never comes, would otherwise leave the form
// dead until a reload. Should the reCAPTCHA script be missing
// or fail, the form goes without a token, and the server
// refuses it with the reCAPTCHA message
$(document).on('submit', 'form[data-recaptcha-action]', function(event){
	var form = this;
	if (typeof grecaptcha === 'undefined') {
		return;
	}
	event.preventDefault();

	if (form.getAttribute('data-sending') === '1') {
		return;
	}
	form.setAttribute('data-sending', '1');
	setTimeout(function() {
		form.removeAttribute('data-sending');
	}, 15000);

	// The form's own submit, which no submit handler sees again
	var send = function() {
		HTMLFormElement.prototype.submit.call(form);
	};

	grecaptcha.ready(function() {
		grecaptcha.execute(form.getAttribute('data-recaptcha-key'), {action: form.getAttribute('data-recaptcha-action')}).then(function(token) {
			form.querySelector('input[name="g-recaptcha-response"]').value = token;
			send();
		}, send);
	});
});

// A page the browser brings back from its history keeps the
// mark of a form already sent, which would ignore every later
// press of it
$(window).on('pageshow', function(){
	$('form[data-recaptcha-action]').removeAttr('data-sending');
});
