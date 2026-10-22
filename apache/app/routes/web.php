<?php

// -----------------------------------------------------------
//  [*] web — the routes of the lab
//
//  Every page of the lab, loaded under the web group —
//  sessions, cookies and the CSRF check of every POST. The
//  player side sits at the root and under /user: its login,
//  registration and password pages are for guests (guest),
//  the rest sits behind auth:users, which sends a guest to
//  the login page. The control panel sits under
//  /control-panel, behind auth.admin:admins, which sends a
//  guest to the panel's login page; perms keeps parts of it
//  to main admins, and r_exists keeps a lab admin to the
//  groups and challenges they created. The dialog routes
//  return the forms the panel's pages open in a modal.
//  event.status is switched off and lets every request
//  through. No route has a rate limit (LAB-20, LAB-21), and
//  every change the panel makes from a link — a deletion, a
//  removal, a switch, a mail sent to the list — is a plain
//  GET request, which no CSRF token guards: a link an admin
//  clicks on another site makes the change in the admin's
//  name (LAB-19).
//
//  The player side — guests, then anyone, then players:
//
//    GET       /
//    POST      /login
//    GET|POST  /register
//    GET       /verify/{verify_token}
//    GET       /reset/{reset_token}
//    GET|POST  /forgot-password
//    GET       /cp
//    GET       /page/{alias}
//    GET       /user/logout_saml
//    GET       /user/dashboard
//    GET|POST  /user/challenge/{public_id}/{public_group_id?}
//    GET       /user/challenge/vote/{type}/{public_id}
//    GET       /user/logout
//    GET       /user/leaderboard
//    GET       /user/group/{public_id}
//    GET       /user/group/leaderboard/{public_id}
//    GET       /user/dialog/feedback
//    POST      /user/feedback
//
//  The control panel — signing in and out, the dashboard:
//
//    GET       /control-panel
//    GET       /control-panel/login
//    GET       /control-panel/logout
//    GET       /control-panel/dashboard
//
//  The control panel, main admins only — the categories
//  page, the partners, the content pages, the mail
//  templates and the settings:
//
//    GET       /control-panel/categories
//    GET       /control-panel/category/delete/{uid}
//    GET       /control-panel/partners[/delete/{uid}]
//    GET|POST  /control-panel/dialog/partners/…
//    GET|POST  /control-panel/pages[/…]
//    GET|POST  /control-panel/emails[/…]
//    GET       /control-panel/dialog/emails/send_test/{email_id}
//    GET|POST  /control-panel/settings[/…]
//
//  The control panel, every admin — the category dialogs,
//  the accounts, the challenges and the groups; within them
//  perms guards an account's new password, status and
//  deletion and a challenge's status, and r_exists the
//  changes to a group or a challenge:
//
//    GET|POST  /control-panel/dialog/category/…
//    GET|POST  /control-panel/users[/…]
//    GET|POST  /control-panel/dialog/users/…
//    GET|POST  /control-panel/challenges[/…]
//    POST      /control-panel/challenges_file/…
//    GET       /control-panel/dialog/challenges/categories/{uid}
//    GET       /control-panel/dialog/challenge_file/{type}/{uid}
//
//  Used by:
//    - RouteServiceProvider::boot — loads it under the web
//      middleware group
// -----------------------------------------------------------


use Illuminate\Support\Facades\Route;

// The login page — the site's front page — and the login
// form; signed-in visitors go to their dashboard (guest).
//
// Known bug LAB-20 (medium): the login form's route has no
// rate limit, lockout or reCAPTCHA (web.php:97), so a password
// can be guessed without end. Fix: throttle the route, by
// address and e-mail.
Route::get('', [App\Http\Controllers\Users\UserAuthController::class, 'index'])->name('login_users')->middleware(['guest']);

Route::post('login', [App\Http\Controllers\Users\UserAuthController::class, 'login'])->name('login')->middleware(['event.status', 'guest']);

// Registration — the form, and its POST, which the reg.status
// switch closes — and the links the mails carry: the
// verification link and the password-reminder link, whose
// validate.verify and validate.reset answer an unknown token
// with a 404
Route::get('register', [App\Http\Controllers\Users\RegisterController::class, 'create'])->name('register_users')->middleware(['guest']);

Route::post('register', [App\Http\Controllers\Users\RegisterController::class, 'store'])->name('create_users')->middleware(['guest', 'reg.status']);

Route::get('verify/{verify_token}', [App\Http\Controllers\Users\RegisterController::class, 'verify'])->middleware(['guest', 'validate.verify']);

Route::get('reset/{reset_token}', [App\Http\Controllers\Users\RegisterController::class, 'reset_pass'])->middleware(['guest', 'validate.reset']);

// The forgotten-password form and its POST
Route::get('forgot-password', [App\Http\Controllers\Users\RegisterController::class, 'ForgotPasswordForm'])->name('remind_password')->middleware(['guest']);

Route::post('forgot-password', [App\Http\Controllers\Users\RegisterController::class, 'forgotpassword'])->middleware(['guest'])->name('forgot-password');

// The end of the players' VU login (saml_auth), and the
// content pages, open to anyone
Route::get('cp', [App\Http\Controllers\Users\UserAuthController::class, 'cp'])->name('cp')->middleware(['saml_auth']);

Route::get('page/{alias}', [App\Http\Controllers\Users\PageController::class, 'show']);

// The player pages
Route::prefix('user')->group(function(){
    // The dashboard, a challenge's page and its answer form;
    // validate.ifsolved sends a solved challenge's pages to the
    // dashboard.
    //
    // Known bug LAB-21 (low): the answer route has no rate limit
    // (web.php:136), so flags can be guessed without end. Fix:
    // throttle it per player.
    Route::get('/dashboard', [App\Http\Controllers\Users\ChallengesController::class, 'index'])->middleware(['event.status', 'auth:users'])->name('user_dashboard');

    Route::get('/challenge/{public_id}/{public_group_id?}', [App\Http\Controllers\Users\ChallengesController::class, 'show'])->middleware(['event.status', 'auth:users', 'validate.ifsolved']);

    Route::post('/challenge/{public_id}/{public_group_id?}', [App\Http\Controllers\Users\ChallengesController::class, 'submit_answer'])->middleware(['event.status', 'auth:users', 'validate.ifsolved']);

    // A vote, which the dashboard and the challenge page send by
    // script
    Route::get('/challenge/vote/{type}/{public_id}', [App\Http\Controllers\Users\ChallengesController::class, 'vote'])->middleware(['auth:users']);

    // Signing out, and the leaderboard
    Route::get('/logout', [App\Http\Controllers\Users\UserAuthController::class, 'logout'])->middleware(['event.status', 'auth:users'])->name('user_logout');

    Route::get('/leaderboard', [App\Http\Controllers\Users\LeaderboardController::class, 'index'])->middleware(['event.status', 'auth:users'])->name('user_leaderboard');

    // Signing out of the SSO too; open to anyone
    Route::get('/logout_saml', [App\Http\Controllers\Users\UserAuthController::class, 'logout_saml']);

    // A group's page and leaderboard, for its members
    // (group.exists)
    Route::get('/group/{public_id}', [App\Http\Controllers\Users\GroupsController::class, 'index'])->middleware(['event.status', 'auth:users', 'group.exists']);

    Route::get('/group/leaderboard/{public_id}', [App\Http\Controllers\Users\GroupsController::class, 'group_leaderboard'])->middleware(['event.status', 'auth:users', 'group.exists']);

    // The feedback dialog and its POST
    Route::get('/dialog/feedback', [App\Http\Controllers\Users\ChallengesController::class, 'dialog_feedback'])->middleware(['auth:users']);

    Route::post('/feedback', [App\Http\Controllers\Users\ChallengesController::class, 'feedback_save'])->middleware(['auth:users']);
});

// The control panel
Route::prefix('control-panel')->group(function(){
    // The login page, for guests, and the end of the SSO login
    // (saml_auth_admin). The password login's POST is switched
    // off: admins sign in through the SSO only.
    Route::get('/', [App\Http\Controllers\Admins\AdminAuthController::class, 'index'])->name('login_admins')->middleware(['guest']);

    Route::get('/login', [App\Http\Controllers\Admins\AdminAuthController::class, 'cp_login'])->name('cp_login')->middleware(['saml_auth_admin']);

    // The dashboard, every admin's
    Route::get('/dashboard', [App\Http\Controllers\Admins\LeaderboardController::class, 'dashboard'])->middleware(['auth.admin:admins'])->name('admin_dashboard');

    // The categories, partners and settings pages, and the
    // settings' two forms — main-only
    Route::get('/categories', [App\Http\Controllers\Admins\CategoriesController::class, 'index'])->middleware(['auth.admin:admins', 'perms'])->name('admin_categories');

    Route::get('/partners', [App\Http\Controllers\Admins\PartnersController::class, 'index'])->middleware(['auth.admin:admins', 'perms'])->name('admin_partners');

    Route::get('/settings', [App\Http\Controllers\Admins\SettingsController::class, 'index'])->middleware(['auth.admin:admins', 'perms'])->name('admin_settings');

    Route::post('/settings/event_data', [App\Http\Controllers\Admins\SettingsController::class, 'event_data'])->middleware(['auth.admin:admins', 'perms'])->name('settings.event_data');

    Route::post('/settings/reg_open', [App\Http\Controllers\Admins\SettingsController::class, 'reg_open'])->middleware(['perms', 'auth.admin:admins'])->name('settings.reg_open');

    // Signing out
    Route::get('/logout', [App\Http\Controllers\Admins\AdminAuthController::class, 'logout'])->middleware(['auth.admin:admins'])->name('admin_logout');

    // The forms the panel's pages open in a modal, and the POSTs
    // they send
    Route::prefix('dialog')->group(function(){
        // The category dialog.
        //
        // Known bug LAB-12 (medium): categories are main-only in the
        // list and on deletion, but these four routes lack perms
        // (web.php:198), so a lab admin can add categories and rename
        // the lab-wide ones. Fix: add perms to the four routes.
        Route::get('/category/new', [App\Http\Controllers\Admins\CategoriesController::class, 'create'])->middleware(['auth.admin:admins']);

        Route::post('/category/store', [App\Http\Controllers\Admins\CategoriesController::class, 'store'])->middleware(['auth.admin:admins'])->name('admin.category.store');

        Route::get('/category/edit/{uid}', [App\Http\Controllers\Admins\CategoriesController::class, 'edit'])->middleware(['auth.admin:admins']);

        Route::post('/category/update', [App\Http\Controllers\Admins\CategoriesController::class, 'update'])->middleware(['auth.admin:admins'])->name('admin.category.update');

        // The partner dialog — main-only
        Route::get('/partners/new', [App\Http\Controllers\Admins\PartnersController::class, 'create'])->middleware(['auth.admin:admins', 'perms']);

        Route::post('/partners/store', [App\Http\Controllers\Admins\PartnersController::class, 'store'])->middleware(['auth.admin:admins', 'perms'])->name('admin.partners.store');

        Route::get('/partners/edit/{uid}', [App\Http\Controllers\Admins\PartnersController::class, 'edit'])->middleware(['auth.admin:admins', 'perms']);

        Route::post('/partners/update', [App\Http\Controllers\Admins\PartnersController::class, 'update'])->middleware(['auth.admin:admins', 'perms'])->name('admin.partners.update');

        // The account dialog; only a new password is main-only
        Route::get('/users/new', [App\Http\Controllers\Admins\UsersController::class, 'create'])->middleware(['auth.admin:admins']);

        Route::post('/users/store', [App\Http\Controllers\Admins\UsersController::class, 'store'])->middleware(['auth.admin:admins'])->name('admin.users.store');

        Route::get('/users/edit/{uid}', [App\Http\Controllers\Admins\UsersController::class, 'edit'])->middleware(['auth.admin:admins']);

        Route::post('/users/update', [App\Http\Controllers\Admins\UsersController::class, 'update'])->middleware(['auth.admin:admins', 'perms'])->name('admin.users.update');

        // The test-mail dialog — main-only
        Route::get('/emails/send_test/{email_id}', [App\Http\Controllers\Admins\EmailsController::class, 'send_test'])->middleware(['auth.admin:admins', 'perms']);

        // A challenge's categories dialog and file link dialog;
        // neither checks whose challenge it is — saving them does
        Route::get('/challenges/categories/{uid}', [App\Http\Controllers\Admins\ChallengesController::class, 'categories'])->middleware(['auth.admin:admins']);

        Route::get('/challenge_file/{type}/{uid}', [App\Http\Controllers\Admins\ChallengesController::class, 'file_form'])->middleware(['auth.admin:admins']);

        // The group dialogs: add and rename a group, add a member or
        // a challenge; but for adding a group, r_exists checks the
        // group is the admin's
        Route::get('/users/vu-groups/new', [App\Http\Controllers\Admins\UsersGroupsController::class, 'create'])->middleware(['auth.admin:admins']);

        Route::post('/users/vu-groups/store', [App\Http\Controllers\Admins\UsersGroupsController::class, 'store'])->middleware(['auth.admin:admins'])->name('admin.users.groups.store');

        Route::get('/users/vu-groups/edit/{uid}', [App\Http\Controllers\Admins\UsersGroupsController::class, 'edit'])->middleware(['auth.admin:admins','r_exists:group_owned']);

        Route::post('/users/vu-groups/update', [App\Http\Controllers\Admins\UsersGroupsController::class, 'update'])->middleware(['auth.admin:admins','r_exists:group_owned_uid'])->name('admin.users.groups.update');

        Route::get('/users/vu-groups/user_add_{type}/{uid}', [App\Http\Controllers\Admins\UsersGroupsController::class, 'togroup_form'])->middleware(['auth.admin:admins','r_exists:group_owned']);

        Route::get('/users/vu-groups/challenge_add_{type}/{uid}', [App\Http\Controllers\Admins\UsersGroupsController::class, 'challenges_togroup_form'])->middleware(['auth.admin:admins','r_exists:group_owned']);

        Route::post('/users/vu-groups/users_store', [App\Http\Controllers\Admins\UsersGroupsController::class, 'users_store'])->middleware(['auth.admin:admins','r_exists:group_owned_uid'])->name('admin.users.groups.users_store');

        Route::post('/users/vu-groups/challenges_store', [App\Http\Controllers\Admins\UsersGroupsController::class, 'challenges_store'])->middleware(['auth.admin:admins','r_exists:group_owned_uid'])->name('admin.users.groups.challenges_store');
    });

    // Deleting a category or a partner — main-only
    Route::get('/category/delete/{uid}', [App\Http\Controllers\Admins\CategoriesController::class, 'destroy'])->middleware(['auth.admin:admins', 'perms']);

    Route::get('/partners/delete/{uid}', [App\Http\Controllers\Admins\PartnersController::class, 'destroy'])->middleware(['auth.admin:admins', 'perms']);

    // The content pages — main-only
    Route::get('/pages', [App\Http\Controllers\Admins\PagesController::class, 'index'])->middleware(['auth.admin:admins', 'perms'])->name('admin.pages');

    Route::get('/pages/create', [App\Http\Controllers\Admins\PagesController::class, 'create'])->middleware(['auth.admin:admins', 'perms']);

    Route::post('/pages/store', [App\Http\Controllers\Admins\PagesController::class, 'store'])->middleware(['auth.admin:admins', 'perms']);

    Route::get('/pages/edit/{uid}', [App\Http\Controllers\Admins\PagesController::class, 'edit'])->middleware(['auth.admin:admins', 'perms']);

    Route::get('/pages/delete/{uid}', [App\Http\Controllers\Admins\PagesController::class, 'destroy'])->middleware(['auth.admin:admins', 'perms']);

    Route::post('/pages/update', [App\Http\Controllers\Admins\PagesController::class, 'update'])->middleware(['auth.admin:admins', 'perms']);

    // The accounts: every admin sees the list and may create
    // one, the status switch and deletion are main-only. The
    // create and store routes duplicate the dialog ones; no view
    // uses them.
    Route::get('/users', [App\Http\Controllers\Admins\UsersController::class, 'index'])->middleware(['auth.admin:admins'])->name('admin.users');

    Route::get('/users/status/{user_id}/{status_id}', [App\Http\Controllers\Admins\UsersController::class, 'change_status'])->middleware(['auth.admin:admins', 'perms']);

    Route::get('/users/create', [App\Http\Controllers\Admins\UsersController::class, 'create'])->middleware(['auth.admin:admins']);

    Route::post('/users/store', [App\Http\Controllers\Admins\UsersController::class, 'store'])->middleware(['auth.admin:admins']);

    Route::get('/users/delete/{uid}', [App\Http\Controllers\Admins\UsersController::class, 'destroy'])->middleware(['auth.admin:admins', 'perms']);

    // The mail templates list — main-only
    Route::get('/emails', [App\Http\Controllers\Admins\EmailsController::class, 'index'])->middleware(['auth.admin:admins', 'perms'])->name('admin.emails');

    // The groups: the list, a group's members, challenges and
    // leaderboard, and its deletion; r_exists keeps a lab admin
    // to their own. The leaderboard route reuses the name of the
    // challenges list route, which nothing looks up by name.
    Route::get('/users/vu-groups', [App\Http\Controllers\Admins\UsersGroupsController::class, 'index'])->middleware(['auth.admin:admins'])->name('admin.users.groups');

    Route::get('/users/vu-groups/users_list/{uid}', [App\Http\Controllers\Admins\UsersGroupsController::class, 'users_list'])->middleware(['auth.admin:admins','r_exists:group_owned'])->name('admin.users.groups.users_list');

    Route::get('/users/vu-groups/challenges_list/{uid}', [App\Http\Controllers\Admins\UsersGroupsController::class, 'challenges_list'])->middleware(['auth.admin:admins','r_exists:group_owned'])->name('admin.users.groups.challenges_list');

    Route::get('/users/vu-groups/leaderboard/{uid}', [App\Http\Controllers\Admins\UsersGroupsController::class, 'group_leaderboard'])->middleware(['auth.admin:admins','r_exists:group_owned'])->name('admin.users.groups.challenges_list');

    Route::get('/users/vu-groups/delete/{uid}', [App\Http\Controllers\Admins\UsersGroupsController::class, 'destroy'])->middleware(['auth.admin:admins','r_exists:group_owned']);

    // Removing a member or a challenge from a group.
    //
    // Known bug LAB-13 (medium): these two routes (web.php:310 and
    // web.php:312) run no ownership check, unlike every other group
    // route, and take bare row ids, so any admin can remove
    // members and challenges from any group. Fix: check that the
    // row's group belongs to the admin, as r_exists does for the
    // group itself.
    Route::get('/users/vu-groups/delete_group/{uid}', [App\Http\Controllers\Admins\UsersGroupsController::class, 'group_destroy'])->middleware(['auth.admin:admins']);

    Route::get('/users/vu-groups/delete_challenge/{uid}', [App\Http\Controllers\Admins\UsersGroupsController::class, 'challenge_destroy'])->middleware(['auth.admin:admins']);

    // The mail templates' forms, the sending and the list sync —
    // main-only
    Route::get('/emails/create', [App\Http\Controllers\Admins\EmailsController::class, 'create'])->middleware(['auth.admin:admins', 'perms']);

    Route::post('/emails/store', [App\Http\Controllers\Admins\EmailsController::class, 'store'])->middleware(['auth.admin:admins', 'perms']);

    Route::get('/emails/edit/{uid}', [App\Http\Controllers\Admins\EmailsController::class, 'edit'])->middleware(['auth.admin:admins', 'perms']);

    Route::get('/emails/delete/{uid}', [App\Http\Controllers\Admins\EmailsController::class, 'destroy'])->middleware(['auth.admin:admins', 'perms']);

    Route::post('/emails/update', [App\Http\Controllers\Admins\EmailsController::class, 'update'])->middleware(['auth.admin:admins', 'perms']);

    Route::get('/emails/send/{email_id}', [App\Http\Controllers\Admins\EmailsController::class, 'send'])->middleware(['auth.admin:admins', 'perms']);

    Route::post('/emails/sending_test/{email_id}', [App\Http\Controllers\Admins\EmailsController::class, 'sending_test'])->middleware(['auth.admin:admins', 'perms']);

    Route::get('/emails/sync/{type}', [App\Http\Controllers\Admins\EmailsController::class, 'sync_with_lists'])->middleware(['auth.admin:admins', 'perms']);

    // The challenges: every admin sees the list and may add one;
    // the changes are for the challenge's creator or a main
    // admin (r_exists), the status switch for main admins.
    //
    // Known bug LAB-06 (medium): a file link can be neither
    // changed nor deleted. The challenges_file/update and
    // challenges/files/delete routes (web.php:362 and web.php:364)
    // run the r_exists challenge check, which reads the link's id
    // as a challenge id: unless a challenge happens to share it,
    // the answer is "No challenge!", and a lab admin is checked
    // against the wrong challenge. Fix: check the challenge the
    // link belongs to.
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
