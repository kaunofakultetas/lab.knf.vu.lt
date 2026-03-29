function category_form(type, uid=null){
	
	$('.modal-title').text('Category form');
	 
	$('.modal-body').fadeIn(500,function() {
		 
		 if(type == 'new'){ 
		 
	     	$(this).load("/control-panel/dialog/category/"+type);
	     
	     } else if(type == 'edit'){ 
	     
	     	$(this).load("/control-panel/dialog/category/"+type+"/"+uid);
	     
	     }
		 
	});
	
	$('#mod').modal('show');
	
} 
 
function partners_form(type, uid=null){
	
	$('.modal-title').text('Partners form');
	 
	$('.modal-body').fadeIn(500,function() {
		 
		 if(type == 'new'){ 
		 
	     	$(this).load("/control-panel/dialog/partners/"+type);
	     
	     } else if(type == 'edit'){ 
	     
	     	$(this).load("/control-panel/dialog/partners/"+type+"/"+uid);
	     
	     }
		 
	});
	
	$('#mod').modal('show');
	
} 

function users_form(type, uid=null){
	
	$('.modal-title').text('Users form');
	 
	$('.modal-body').fadeIn(500,function() {
		 
		 if(type == 'new'){ 
		 
	     	$(this).load("/control-panel/dialog/users/"+type);
	     
	     } else if(type == 'edit'){ 
	     
	     	$(this).load("/control-panel/dialog/users/"+type+"/"+uid);
	     
	     }
		 
	});
	
	$('#mod').modal('show');
	
} 
 
function email_test(uid=null){
	
	$('.modal-title').text('Send email test');
	 
	$('.modal-body').fadeIn(500,function() {
		 
		 $(this).load("/control-panel/dialog/emails/send_test/"+uid);
		 
	});
	
	$('#mod').modal('show');
	
} 
 
function challenge_categories(uid){
	
	$('.modal-title').text('Challenge Categories');
	 
	$('.modal-body').fadeIn(500,function() {
		 
		 $(this).load("/control-panel/dialog/challenges/categories/"+uid);
		 
	});
	
	$('#mod').modal('show');
	
} 

function challenge_files_form(type, uid){
	
	$('.modal-title').text('Challenge files form');
	 
	$('.modal-body').fadeIn(500,function() {
		 
		 if(type == 'new'){ // uid - challenge id
		 
	     	$(this).load("/control-panel/dialog/challenge_file/"+type+"/"+uid);
	     
	     } else if(type == 'edit'){ // uid - unique record id
	     
	     	$(this).load("/control-panel/dialog/challenge_file/"+type+"/"+uid);
	     
	     }
		 
	});
	
	$('#mod').modal('show');
	
} 

function users_groups_form(type, uid=null){
	
	$('.modal-title').text('Users - VU Group form');
	 
	$('.modal-body').fadeIn(500,function() {
		 
		 if(type == 'new'){ 
		 
	     	$(this).load("/control-panel/dialog/users/vu-groups/"+type);
	     
	     } else if(type == 'edit'){ 
	     
	     	$(this).load("/control-panel/dialog/users/vu-groups/"+type+"/"+uid);
	     
	     }
		 
	});
	
	$('#mod').modal('show');
	
} 

function users_togroups_form(type, uid){
	
	$('.modal-title').text('Add users to group');
	 
	$('.modal-body').fadeIn(500,function() {
		 
		 if(type == 'new'){ 
		 
	     	$(this).load("/control-panel/dialog/users/vu-groups/user_add_"+type+"/"+uid);
	     
	     } 
		 
	});
	
	$('#mod').modal('show');
	
} 

function users_groupschallenges_form(type, uid){
	
	$('.modal-title').text('Add challenges to group');
	 
	$('.modal-body').fadeIn(500,function() {
		 
		 if(type == 'new'){ 
		 
	     	$(this).load("/control-panel/dialog/users/vu-groups/challenge_add_"+type+"/"+uid);
	     
	     } 
		 
	});
	
	$('#mod').modal('show');
	
} 