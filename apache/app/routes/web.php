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
//  through. By design no route has a rate limit, the login
//  and answer forms included.
//  Every change the panel makes from its lists — a deletion,
//  a removal, a switch, a mail sent to the list, a list
//  sync — is a POST, which the panel's script sends with the
//  page's CSRF token, so a link on another site changes
//  nothing.
//
//  The player side — guests, then anyone, then players:
//
//    GET       /
//    POST      /login
//    GET|POST  /register
//    GET       /verify/{verify_token}
//    GET|POST  /reset/{reset_token}
//    GET|POST  /forgot-password
//    GET       /cp
//    GET       /page/{alias}
//    POST      /user/logout_saml
//    GET       /user/dashboard
//    GET|POST  /user/challenge/{public_id}/{public_group_id?}
//    POST      /user/challenge/vote/{type}/{public_id}
//    POST      /user/logout
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
//    POST      /control-panel/logout
//    GET       /control-panel/dashboard
//
//  The control panel, main admins only — the categories,
//  the partners, the content pages, the mail templates and
//  the settings:
//
//    GET       /control-panel/categories
//    POST      /control-panel/category/delete/{uid}
//    GET|POST  /control-panel/dialog/category/…
//    GET       /control-panel/partners
//    POST      /control-panel/partners/delete/{uid}
//    GET|POST  /control-panel/dialog/partners/…
//    GET|POST  /control-panel/pages[/…]
//    GET|POST  /control-panel/emails[/…]
//    GET       /control-panel/dialog/emails/send_test/{email_id}
//    GET|POST  /control-panel/settings[/…]
//
//  The control panel, every admin — the accounts, the
//  challenges and the groups; within them perms guards an
//  account's new password, status and deletion and a
//  challenge's status, and r_exists the changes to a group
//  or a challenge:
//
//    GET|POST  /control-panel/users[/…]
//    GET|POST  /control-panel/dialog/users/…
//    GET|POST  /control-panel/challenges[/…]
//    POST      /control-panel/challenges_file/…
//    GET       /control-panel/dialog/challenges/categories/{uid}
//    GET       /control-panel/dialog/challenge_file/new/{uid}
//    GET       /control-panel/dialog/challenge_file/edit/{uid}
//
//  Used by:
//    - RouteServiceProvider::boot — loads it under the web
//      middleware group
// -----------------------------------------------------------


use Illuminate\Support\Facades\Route;

// The login page — the site's front page — and the login
// form; signed-in visitors go to their dashboard (guest).
//
// The login form's route has no rate limit, lockout or
// reCAPTCHA, by design.
Route::get('', [App\Http\Controllers\Users\UserAuthController::class, 'index'])->name('login_users')->middleware(['guest']);

Route::post('login', [App\Http\Controllers\Users\UserAuthController::class, 'login'])->name('login')->middleware(['event.status', 'guest']);

// Registration — the form, and its POST, which the reg.status
// switch closes — and the links the mails carry: the
// verification link, and the password-reminder link, which
// opens a page whose button posts the reset. validate.verify
// answers an unknown token with a 404, validate.reset an
// unknown one or one mailed over an hour ago
Route::get('register', [App\Http\Controllers\Users\RegisterController::class, 'create'])->name('register_users')->middleware(['guest']);

Route::post('register', [App\Http\Controllers\Users\RegisterController::class, 'store'])->name('create_users')->middleware(['guest', 'reg.status']);

Route::get('verify/{verify_token}', [App\Http\Controllers\Users\RegisterController::class, 'verify'])->middleware(['guest', 'validate.verify']);

Route::get('reset/{reset_token}', [App\Http\Controllers\Users\RegisterController::class, 'reset_form'])->middleware(['guest', 'validate.reset']);

Route::post('reset/{reset_token}', [App\Http\Controllers\Users\RegisterController::class, 'reset_pass'])->middleware(['guest', 'validate.reset']);

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
    // The answer route has no rate limit, by design.
    Route::get('/dashboard', [App\Http\Controllers\Users\ChallengesController::class, 'index'])->middleware(['event.status', 'auth:users'])->name('user_dashboard');

    Route::get('/challenge/{public_id}/{public_group_id?}', [App\Http\Controllers\Users\ChallengesController::class, 'show'])->middleware(['event.status', 'auth:users', 'validate.ifsolved']);

    Route::post('/challenge/{public_id}/{public_group_id?}', [App\Http\Controllers\Users\ChallengesController::class, 'submit_answer'])->middleware(['event.status', 'auth:users', 'validate.ifsolved']);

    // A vote, which the dashboard and the challenge page post by
    // script with the page's CSRF token
    Route::post('/challenge/vote/{type}/{public_id}', [App\Http\Controllers\Users\ChallengesController::class, 'vote'])->middleware(['auth:users']);

    // Signing out, and the leaderboard
    Route::post('/logout', [App\Http\Controllers\Users\UserAuthController::class, 'logout'])->middleware(['event.status', 'auth:users'])->name('user_logout');

    Route::get('/leaderboard', [App\Http\Controllers\Users\LeaderboardController::class, 'index'])->middleware(['event.status', 'auth:users'])->name('user_leaderboard');

    // Signing out of the SSO too; open to anyone
    Route::post('/logout_saml', [App\Http\Controllers\Users\UserAuthController::class, 'logout_saml']);

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
    Route::post('/logout', [App\Http\Controllers\Admins\AdminAuthController::class, 'logout'])->middleware(['auth.admin:admins'])->name('admin_logout');

    // The forms the panel's pages open in a modal, and the POSTs
    // they send
    Route::prefix('dialog')->group(function(){
        // The category dialog — main-only, like the categories
        // page
        Route::get('/category/new', [App\Http\Controllers\Admins\CategoriesController::class, 'create'])->middleware(['auth.admin:admins', 'perms']);

        Route::post('/category/store', [App\Http\Controllers\Admins\CategoriesController::class, 'store'])->middleware(['auth.admin:admins', 'perms'])->name('admin.category.store');

        Route::get('/category/edit/{uid}', [App\Http\Controllers\Admins\CategoriesController::class, 'edit'])->middleware(['auth.admin:admins', 'perms']);

        Route::post('/category/update', [App\Http\Controllers\Admins\CategoriesController::class, 'update'])->middleware(['auth.admin:admins', 'perms'])->name('admin.category.update');

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

        // A challenge's categories dialog and its new-link and
        // edit-link dialogs, checked as saving them is: the
        // challenge — an edited link's challenge — must be the
        // admin's (r_exists)
        Route::get('/challenges/categories/{uid}', [App\Http\Controllers\Admins\ChallengesController::class, 'categories'])->middleware(['auth.admin:admins','r_exists:challenge_owned']);

        Route::get('/challenge_file/new/{uid}', [App\Http\Controllers\Admins\ChallengesController::class, 'file_form_new'])->middleware(['auth.admin:admins','r_exists:challenge_owned']);

        Route::get('/challenge_file/edit/{uid}', [App\Http\Controllers\Admins\ChallengesController::class, 'file_form_edit'])->middleware(['auth.admin:admins','r_exists:file_owned']);

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
    Route::post('/category/delete/{uid}', [App\Http\Controllers\Admins\CategoriesController::class, 'destroy'])->middleware(['auth.admin:admins', 'perms']);

    Route::post('/partners/delete/{uid}', [App\Http\Controllers\Admins\PartnersController::class, 'destroy'])->middleware(['auth.admin:admins', 'perms']);

    // The content pages — main-only
    Route::get('/pages', [App\Http\Controllers\Admins\PagesController::class, 'index'])->middleware(['auth.admin:admins', 'perms'])->name('admin.pages');

    Route::get('/pages/create', [App\Http\Controllers\Admins\PagesController::class, 'create'])->middleware(['auth.admin:admins', 'perms']);

    Route::post('/pages/store', [App\Http\Controllers\Admins\PagesController::class, 'store'])->middleware(['auth.admin:admins', 'perms']);

    Route::get('/pages/edit/{uid}', [App\Http\Controllers\Admins\PagesController::class, 'edit'])->middleware(['auth.admin:admins', 'perms']);

    Route::post('/pages/delete/{uid}', [App\Http\Controllers\Admins\PagesController::class, 'destroy'])->middleware(['auth.admin:admins', 'perms']);

    Route::post('/pages/update', [App\Http\Controllers\Admins\PagesController::class, 'update'])->middleware(['auth.admin:admins', 'perms']);

    // The accounts: every admin sees the list and creates
    // accounts in the dialog (above); the status switch and
    // deletion are main-only.
    Route::get('/users', [App\Http\Controllers\Admins\UsersController::class, 'index'])->middleware(['auth.admin:admins'])->name('admin.users');

    Route::post('/users/status/{user_id}/{status_id}', [App\Http\Controllers\Admins\UsersController::class, 'change_status'])->middleware(['auth.admin:admins', 'perms']);

    Route::post('/users/delete/{uid}', [App\Http\Controllers\Admins\UsersController::class, 'destroy'])->middleware(['auth.admin:admins', 'perms']);

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

    Route::post('/users/vu-groups/delete/{uid}', [App\Http\Controllers\Admins\UsersGroupsController::class, 'destroy'])->middleware(['auth.admin:admins','r_exists:group_owned']);

    // Removing a member or a challenge from a group; the uid
    // is the membership's or the group challenge's row, and
    // r_exists checks the group it belongs to
    Route::post('/users/vu-groups/delete_group/{uid}', [App\Http\Controllers\Admins\UsersGroupsController::class, 'group_destroy'])->middleware(['auth.admin:admins','r_exists:membership_owned']);

    Route::post('/users/vu-groups/delete_challenge/{uid}', [App\Http\Controllers\Admins\UsersGroupsController::class, 'challenge_destroy'])->middleware(['auth.admin:admins','r_exists:group_challenge_owned']);

    // The mail templates' forms, the sending and the list sync —
    // main-only
    Route::get('/emails/create', [App\Http\Controllers\Admins\EmailsController::class, 'create'])->middleware(['auth.admin:admins', 'perms']);

    Route::post('/emails/store', [App\Http\Controllers\Admins\EmailsController::class, 'store'])->middleware(['auth.admin:admins', 'perms']);

    Route::get('/emails/edit/{uid}', [App\Http\Controllers\Admins\EmailsController::class, 'edit'])->middleware(['auth.admin:admins', 'perms']);

    Route::post('/emails/delete/{uid}', [App\Http\Controllers\Admins\EmailsController::class, 'destroy'])->middleware(['auth.admin:admins', 'perms']);

    Route::post('/emails/update', [App\Http\Controllers\Admins\EmailsController::class, 'update'])->middleware(['auth.admin:admins', 'perms']);

    Route::post('/emails/send/{email_id}', [App\Http\Controllers\Admins\EmailsController::class, 'send'])->middleware(['auth.admin:admins', 'perms']);

    Route::post('/emails/sending_test/{email_id}', [App\Http\Controllers\Admins\EmailsController::class, 'sending_test'])->middleware(['auth.admin:admins', 'perms']);

    Route::post('/emails/sync/{type}', [App\Http\Controllers\Admins\EmailsController::class, 'sync_with_lists'])->middleware(['auth.admin:admins', 'perms']);

    // The challenges: every admin sees the list and may add one;
    // the changes are for the challenge's creator or a main
    // admin (r_exists) — for a file link, the creator of the
    // challenge the link belongs to — the status switch for
    // main admins.
    Route::get('/challenges', [App\Http\Controllers\Admins\ChallengesController::class, 'index'])->middleware(['auth.admin:admins'])->name('admin.challenges');

    Route::get('/challenges/create', [App\Http\Controllers\Admins\ChallengesController::class, 'create'])->middleware(['auth.admin:admins']);

    Route::post('/challenges/store', [App\Http\Controllers\Admins\ChallengesController::class, 'store'])->middleware(['auth.admin:admins']);

    Route::get('/challenges/edit/{uid}', [App\Http\Controllers\Admins\ChallengesController::class, 'edit'])->middleware(['auth.admin:admins','r_exists:challenge_owned']);

    Route::post('/challenges/delete/{uid}', [App\Http\Controllers\Admins\ChallengesController::class, 'destroy'])->middleware(['auth.admin:admins','r_exists:challenge_owned']);

    Route::post('/challenges/update', [App\Http\Controllers\Admins\ChallengesController::class, 'update'])->middleware(['auth.admin:admins','r_exists:challenge_owned_uid']);

    Route::post('/challenges/categories/store', [App\Http\Controllers\Admins\ChallengesController::class, 'categories_store'])->middleware(['auth.admin:admins','r_exists:challenge_owned_uid'])->name('admin.challenges.categories.store');

    Route::get('/challenges/files/{uid}', [App\Http\Controllers\Admins\ChallengesController::class, 'file_list'])->middleware(['auth.admin:admins','r_exists:challenge_owned']);

    Route::post('/challenges_file/store', [App\Http\Controllers\Admins\ChallengesController::class, 'file_store'])->middleware(['auth.admin:admins','r_exists:challenge_owned_uid']);

    Route::post('/challenges_file/update', [App\Http\Controllers\Admins\ChallengesController::class, 'file_update'])->middleware(['auth.admin:admins','r_exists:file_owned_uid']);

    Route::post('/challenges/files/delete/{uid}', [App\Http\Controllers\Admins\ChallengesController::class, 'file_destroy'])->middleware(['auth.admin:admins','r_exists:file_owned']);

    Route::post('/challenges/status/{status}/{challenge_id}', [App\Http\Controllers\Admins\ChallengesController::class, 'status'])->middleware(['auth.admin:admins', 'perms'])->name('admin.challenges.status');
});
