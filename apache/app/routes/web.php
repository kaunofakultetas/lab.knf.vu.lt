<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('', [App\Http\Controllers\Users\UserAuthController::class, 'index'])->name('login_users')->middleware(['guest']);

Route::post('login', [App\Http\Controllers\Users\UserAuthController::class, 'login'])->name('login')->middleware(['event.status', 'guest']);

Route::get('register', [App\Http\Controllers\Users\RegisterController::class, 'create'])->name('register_users')->middleware(['guest']);

Route::post('register', [App\Http\Controllers\Users\RegisterController::class, 'store'])->name('create_users')->middleware(['guest', 'reg.status']);

Route::get('verify/{verify_token}', [App\Http\Controllers\Users\RegisterController::class, 'verify'])->middleware(['guest', 'validate.verify']);

Route::get('reset/{reset_token}', [App\Http\Controllers\Users\RegisterController::class, 'reset_pass'])->middleware(['guest', 'validate.reset']);

Route::get('forgot-password', [App\Http\Controllers\Users\RegisterController::class, 'ForgotPasswordForm'])->name('remind_password')->middleware(['guest']);

Route::post('forgot-password', [App\Http\Controllers\Users\RegisterController::class, 'forgotpassword'])->middleware(['guest'])->name('forgot-password');

Route::get('cp', [App\Http\Controllers\Users\UserAuthController::class, 'cp'])->name('cp')->middleware(['saml_auth']);

Route::get('page/{alias}', [App\Http\Controllers\Users\PageController::class, 'show']);

Route::prefix('user')->group(function(){
 
    Route::get('/dashboard', [App\Http\Controllers\Users\ChallengesController::class, 'index'])->middleware(['event.status', 'auth:users'])->name('user_dashboard'); 
    
    Route::get('/challenge/{public_id}/{public_group_id?}', [App\Http\Controllers\Users\ChallengesController::class, 'show'])->middleware(['event.status', 'auth:users', 'validate.ifsolved']); 
    
    Route::post('/challenge/{public_id}/{public_group_id?}', [App\Http\Controllers\Users\ChallengesController::class, 'submit_answer'])->middleware(['event.status', 'auth:users', 'validate.ifsolved']);

    Route::get('/challenge/vote/{type}/{public_id}', [App\Http\Controllers\Users\ChallengesController::class, 'vote'])->middleware(['auth:users']);
     
    Route::get('/logout', [App\Http\Controllers\Users\UserAuthController::class, 'logout'])->middleware(['event.status', 'auth:users'])->name('user_logout');
    
    Route::get('/leaderboard', [App\Http\Controllers\Users\LeaderboardController::class, 'index'])->middleware(['event.status', 'auth:users'])->name('user_leaderboard');
    
    Route::get('/logout_saml', [App\Http\Controllers\Users\UserAuthController::class, 'logout_saml']);
    
    Route::get('/group/{public_id}', [App\Http\Controllers\Users\GroupsController::class, 'index'])->middleware(['event.status', 'auth:users', 'group.exists']); 
    
    Route::get('/group/leaderboard/{public_id}', [App\Http\Controllers\Users\GroupsController::class, 'group_leaderboard'])->middleware(['event.status', 'auth:users', 'group.exists']); 

    Route::get('/dialog/feedback', [App\Http\Controllers\Users\ChallengesController::class, 'dialog_feedback'])->middleware(['auth:users']);
    
    Route::post('/feedback', [App\Http\Controllers\Users\ChallengesController::class, 'feedback_save'])->middleware(['auth:users']);
    
}); 

Route::prefix('control-panel')->group(function(){

    Route::get('/', [App\Http\Controllers\Admins\AdminAuthController::class, 'index'])->name('login_admins')->middleware(['guest']);
    
    Route::get('/login', [App\Http\Controllers\Admins\AdminAuthController::class, 'cp_login'])->name('cp_login')->middleware(['saml_auth_admin']);

    //Route::post('/login', [App\Http\Controllers\Admins\AdminAuthController::class, 'login'])->name('login_admin')->middleware(['guest']);
    
    Route::get('/dashboard', [App\Http\Controllers\Admins\LeaderboardController::class, 'dashboard'])->middleware(['auth.admin:admins'])->name('admin_dashboard');
    
    Route::get('/categories', [App\Http\Controllers\Admins\CategoriesController::class, 'index'])->middleware(['auth.admin:admins', 'perms'])->name('admin_categories');
    
    Route::get('/partners', [App\Http\Controllers\Admins\PartnersController::class, 'index'])->middleware(['auth.admin:admins', 'perms'])->name('admin_partners');
    
    Route::get('/settings', [App\Http\Controllers\Admins\SettingsController::class, 'index'])->middleware(['auth.admin:admins', 'perms'])->name('admin_settings');
    
    Route::post('/settings/event_data', [App\Http\Controllers\Admins\SettingsController::class, 'event_data'])->middleware(['auth.admin:admins', 'perms'])->name('settings.event_data');
    
    Route::post('/settings/reg_open', [App\Http\Controllers\Admins\SettingsController::class, 'reg_open'])->middleware(['perms', 'auth.admin:admins'])->name('settings.reg_open');

    Route::get('/logout', [App\Http\Controllers\Admins\AdminAuthController::class, 'logout'])->middleware(['auth.admin:admins'])->name('admin_logout');
    
    Route::prefix('dialog')->group(function(){
        
        Route::get('/category/new', [App\Http\Controllers\Admins\CategoriesController::class, 'create'])->middleware(['auth.admin:admins']);
        
        Route::post('/category/store', [App\Http\Controllers\Admins\CategoriesController::class, 'store'])->middleware(['auth.admin:admins'])->name('admin.category.store');
        
        Route::get('/category/edit/{uid}', [App\Http\Controllers\Admins\CategoriesController::class, 'edit'])->middleware(['auth.admin:admins']);
        
        Route::post('/category/update', [App\Http\Controllers\Admins\CategoriesController::class, 'update'])->middleware(['auth.admin:admins'])->name('admin.category.update');
        
        Route::get('/partners/new', [App\Http\Controllers\Admins\PartnersController::class, 'create'])->middleware(['auth.admin:admins', 'perms']);
        
        Route::post('/partners/store', [App\Http\Controllers\Admins\PartnersController::class, 'store'])->middleware(['auth.admin:admins', 'perms'])->name('admin.partners.store');
        
        Route::get('/partners/edit/{uid}', [App\Http\Controllers\Admins\PartnersController::class, 'edit'])->middleware(['auth.admin:admins', 'perms']);
        
        Route::post('/partners/update', [App\Http\Controllers\Admins\PartnersController::class, 'update'])->middleware(['auth.admin:admins', 'perms'])->name('admin.partners.update');
        
        Route::get('/users/new', [App\Http\Controllers\Admins\UsersController::class, 'create'])->middleware(['auth.admin:admins']);
        
        Route::post('/users/store', [App\Http\Controllers\Admins\UsersController::class, 'store'])->middleware(['auth.admin:admins'])->name('admin.users.store');
        
        Route::get('/users/edit/{uid}', [App\Http\Controllers\Admins\UsersController::class, 'edit'])->middleware(['auth.admin:admins']);
        
        Route::post('/users/update', [App\Http\Controllers\Admins\UsersController::class, 'update'])->middleware(['auth.admin:admins', 'perms'])->name('admin.users.update');
        
        Route::get('/emails/send_test/{email_id}', [App\Http\Controllers\Admins\EmailsController::class, 'send_test'])->middleware(['auth.admin:admins', 'perms']);
        
        Route::get('/challenges/categories/{uid}', [App\Http\Controllers\Admins\ChallengesController::class, 'categories'])->middleware(['auth.admin:admins']);
        
        Route::get('/challenge_file/{type}/{uid}', [App\Http\Controllers\Admins\ChallengesController::class, 'file_form'])->middleware(['auth.admin:admins']);
        
        Route::get('/users/vu-groups/new', [App\Http\Controllers\Admins\UsersGroupsController::class, 'create'])->middleware(['auth.admin:admins']);
        
        Route::post('/users/vu-groups/store', [App\Http\Controllers\Admins\UsersGroupsController::class, 'store'])->middleware(['auth.admin:admins'])->name('admin.users.groups.store');
        
        Route::get('/users/vu-groups/edit/{uid}', [App\Http\Controllers\Admins\UsersGroupsController::class, 'edit'])->middleware(['auth.admin:admins','r_exists:group_owned']);
        
        Route::post('/users/vu-groups/update', [App\Http\Controllers\Admins\UsersGroupsController::class, 'update'])->middleware(['auth.admin:admins','r_exists:group_owned_uid'])->name('admin.users.groups.update');
         
        Route::get('/users/vu-groups/user_add_{type}/{uid}', [App\Http\Controllers\Admins\UsersGroupsController::class, 'togroup_form'])->middleware(['auth.admin:admins','r_exists:group_owned']);
        
        Route::get('/users/vu-groups/challenge_add_{type}/{uid}', [App\Http\Controllers\Admins\UsersGroupsController::class, 'challenges_togroup_form'])->middleware(['auth.admin:admins','r_exists:group_owned']);
        
        Route::post('/users/vu-groups/users_store', [App\Http\Controllers\Admins\UsersGroupsController::class, 'users_store'])->middleware(['auth.admin:admins','r_exists:group_owned_uid'])->name('admin.users.groups.users_store');
        
        Route::post('/users/vu-groups/challenges_store', [App\Http\Controllers\Admins\UsersGroupsController::class, 'challenges_store'])->middleware(['auth.admin:admins','r_exists:group_owned_uid'])->name('admin.users.groups.challenges_store');
    
         
    });
     
   Route::get('/category/delete/{uid}', [App\Http\Controllers\Admins\CategoriesController::class, 'destroy'])->middleware(['auth.admin:admins', 'perms']);
   
   Route::get('/partners/delete/{uid}', [App\Http\Controllers\Admins\PartnersController::class, 'destroy'])->middleware(['auth.admin:admins', 'perms']);
   
   Route::get('/pages', [App\Http\Controllers\Admins\PagesController::class, 'index'])->middleware(['auth.admin:admins', 'perms'])->name('admin.pages');
   
   Route::get('/pages/create', [App\Http\Controllers\Admins\PagesController::class, 'create'])->middleware(['auth.admin:admins', 'perms']);
   
   Route::post('/pages/store', [App\Http\Controllers\Admins\PagesController::class, 'store'])->middleware(['auth.admin:admins', 'perms']);
   
   Route::get('/pages/edit/{uid}', [App\Http\Controllers\Admins\PagesController::class, 'edit'])->middleware(['auth.admin:admins', 'perms']);
   
   Route::get('/pages/delete/{uid}', [App\Http\Controllers\Admins\PagesController::class, 'destroy'])->middleware(['auth.admin:admins', 'perms']);
   
   Route::post('/pages/update', [App\Http\Controllers\Admins\PagesController::class, 'update'])->middleware(['auth.admin:admins', 'perms']);
   
   Route::get('/users', [App\Http\Controllers\Admins\UsersController::class, 'index'])->middleware(['auth.admin:admins'])->name('admin.users');
   
   Route::get('/users/status/{user_id}/{status_id}', [App\Http\Controllers\Admins\UsersController::class, 'change_status'])->middleware(['auth.admin:admins', 'perms']);
   
   Route::get('/users/create', [App\Http\Controllers\Admins\UsersController::class, 'create'])->middleware(['auth.admin:admins']);
   
   Route::post('/users/store', [App\Http\Controllers\Admins\UsersController::class, 'store'])->middleware(['auth.admin:admins']);  
   
   Route::get('/users/delete/{uid}', [App\Http\Controllers\Admins\UsersController::class, 'destroy'])->middleware(['auth.admin:admins', 'perms']); 
   
   Route::get('/emails', [App\Http\Controllers\Admins\EmailsController::class, 'index'])->middleware(['auth.admin:admins', 'perms'])->name('admin.emails');
   
   Route::get('/users/vu-groups', [App\Http\Controllers\Admins\UsersGroupsController::class, 'index'])->middleware(['auth.admin:admins'])->name('admin.users.groups');
   
   Route::get('/users/vu-groups/users_list/{uid}', [App\Http\Controllers\Admins\UsersGroupsController::class, 'users_list'])->middleware(['auth.admin:admins','r_exists:group_owned'])->name('admin.users.groups.users_list');
   
   Route::get('/users/vu-groups/challenges_list/{uid}', [App\Http\Controllers\Admins\UsersGroupsController::class, 'challenges_list'])->middleware(['auth.admin:admins','r_exists:group_owned'])->name('admin.users.groups.challenges_list');
   
   Route::get('/users/vu-groups/leaderboard/{uid}', [App\Http\Controllers\Admins\UsersGroupsController::class, 'group_leaderboard'])->middleware(['auth.admin:admins','r_exists:group_owned'])->name('admin.users.groups.challenges_list');
   
   Route::get('/users/vu-groups/delete/{uid}', [App\Http\Controllers\Admins\UsersGroupsController::class, 'destroy'])->middleware(['auth.admin:admins','r_exists:group_owned']);
   
   Route::get('/users/vu-groups/delete_group/{uid}', [App\Http\Controllers\Admins\UsersGroupsController::class, 'group_destroy'])->middleware(['auth.admin:admins']);
   
   Route::get('/users/vu-groups/delete_challenge/{uid}', [App\Http\Controllers\Admins\UsersGroupsController::class, 'challenge_destroy'])->middleware(['auth.admin:admins']);
   
   Route::get('/emails/create', [App\Http\Controllers\Admins\EmailsController::class, 'create'])->middleware(['auth.admin:admins', 'perms']);
   
   Route::post('/emails/store', [App\Http\Controllers\Admins\EmailsController::class, 'store'])->middleware(['auth.admin:admins', 'perms']);
   
   Route::get('/emails/edit/{uid}', [App\Http\Controllers\Admins\EmailsController::class, 'edit'])->middleware(['auth.admin:admins', 'perms']);
   
   Route::get('/emails/delete/{uid}', [App\Http\Controllers\Admins\EmailsController::class, 'destroy'])->middleware(['auth.admin:admins', 'perms']);
   
   Route::post('/emails/update', [App\Http\Controllers\Admins\EmailsController::class, 'update'])->middleware(['auth.admin:admins', 'perms']);
   
   Route::get('/emails/send/{email_id}', [App\Http\Controllers\Admins\EmailsController::class, 'send'])->middleware(['auth.admin:admins', 'perms']);
   
   Route::post('/emails/sending_test/{email_id}', [App\Http\Controllers\Admins\EmailsController::class, 'sending_test'])->middleware(['auth.admin:admins', 'perms']);
   
   Route::get('/emails/sync/{type}', [App\Http\Controllers\Admins\EmailsController::class, 'sync_with_lists'])->middleware(['auth.admin:admins', 'perms']);
   
   Route::get('/challenges', [App\Http\Controllers\Admins\ChallengesController::class, 'index'])->middleware(['auth.admin:admins'])->name('admin.challenges');
   
   Route::get('/challenges/create', [App\Http\Controllers\Admins\ChallengesController::class, 'create'])->middleware(['auth.admin:admins']);
   
   Route::post('/challenges/store', [App\Http\Controllers\Admins\ChallengesController::class, 'store'])->middleware(['auth.admin:admins']);
   
   Route::get('/challenges/edit/{uid}', [App\Http\Controllers\Admins\ChallengesController::class, 'edit'])->middleware(['auth.admin:admins','r_exists:challenge_owned']);
   
   Route::get('/challenges/delete/{uid}', [App\Http\Controllers\Admins\ChallengesController::class, 'destroy'])->middleware(['auth.admin:admins','r_exists:challenge_owned']);
   
   Route::post('/challenges/update', [App\Http\Controllers\Admins\ChallengesController::class, 'update'])->middleware(['auth.admin:admins','r_exists:challenge_owned_uid']);
   
   Route::post('/challenges/categories/store', [App\Http\Controllers\Admins\ChallengesController::class, 'categories_store'])->middleware(['auth.admin:admins','r_exists:challenge_owned_uid'])->name('admin.challenges.categories.store');

   Route::get('/challenges/files/{uid}', [App\Http\Controllers\Admins\ChallengesController::class, 'file_list'])->middleware(['auth.admin:admins','r_exists:challenge_owned']);
   
   Route::post('/challenges_file/store', [App\Http\Controllers\Admins\ChallengesController::class, 'file_store'])->middleware(['auth.admin:admins','r_exists:challenge_owned_uid']);
   
   Route::post('/challenges_file/update', [App\Http\Controllers\Admins\ChallengesController::class, 'file_update'])->middleware(['auth.admin:admins','r_exists:challenge_owned_uid']);
   
   Route::get('/challenges/files/delete/{uid}', [App\Http\Controllers\Admins\ChallengesController::class, 'file_destroy'])->middleware(['auth.admin:admins','r_exists:challenge_owned']);
   
   Route::get('/challenges/status/{status}/{challenge_id}', [App\Http\Controllers\Admins\ChallengesController::class, 'status'])->middleware(['auth.admin:admins', 'perms'])->name('admin.challenges.status');
   
}); 








