<?php

// -----------------------------------------------------------
//  [*] RegisterController — local accounts and passwords
//
//  The life of a local account outside the lab: the
//  registration, behind reCAPTCHA v3 and the registration
//  switch; the verification link — good for a day — whose
//  page has the button that activates the account; and the
//  forgotten-password reminder, whose link — good for an
//  hour — opens a page whose button sets and mails a new
//  password. Neither link changes anything when it is only
//  opened, as mail scanners open links before the reader
//  does. VU accounts never come here — the SSO creates
//  them, and the reminder skips them.
//
//    GET  /register              — the registration form
//    POST /register              — registers an account
//    GET  /verify/{verify_token} — the activation button
//    POST /verify/{verify_token} — activates it
//    GET  /forgot-password       — the reminder form
//    POST /forgot-password       — mails a reminder link
//    GET  /reset/{reset_token}   — the new-password button
//    POST /reset/{reset_token}   — mails a new password
//
//  Used by:
//    - routes/web.php — the routes above
// -----------------------------------------------------------


namespace App\Http\Controllers\Users;

use Mail;
use App\Models\User;
use App\Models\Settings;
use App\Mail\UserVerifyMail;
use App\Mail\SendRemindLink;
use App\Mail\SendNewPass;
use App\Http\Controllers\Controller;
use App\Rules\MailableAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use ReCaptcha\ReCaptcha;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;








// -----------------------------------------------------------
// RegisterController
// -----------------------------------------------------------
//
// Extends the bare base controller, not the player one, so
// its pages get none of the player layout's data.
//
//   create             — the registration form
//   store              — registers an account
//   verify_form        — the activation button
//   verify             — activates it
//   send_verification  — mails a verification link
//   ForgotPasswordForm — the reminder form
//   forgotpassword     — mails a reminder link
//   reset_form         — the new-password button
//   reset_pass         — mails a new password
//
// Used by:
//   - routes/web.php
// -----------------------------------------------------------

class RegisterController extends Controller
{






    // -----------------------------------------------------------
    // create
    // -----------------------------------------------------------
    //
    // GET /register
    //
    // The users.register view, told whether registration is
    // open (the reg_open setting).
    //
    // Used by:
    //   - the registration links of the login and reminder
    //     pages
    // -----------------------------------------------------------

    public function create()
    {
        return view('users.register', ['reg_status'=>Settings::find(2)->val1]);
    }






    // -----------------------------------------------------------
    // store
    // -----------------------------------------------------------
    //
    // POST /register
    //
    // Registers a local account: a username of letters,
    // digits, blanks and . , _ - that is still free, an e-mail
    // still free that the mailer takes too (MailableAddress —
    // the RFC allows forms it refuses, and such an account
    // would never get its mail), a confirmed password of 8 or
    // more, and a reCAPTCHA v3 score of at least 0.6 for the
    // register action. The account starts inactive and is
    // mailed its verification link (send_verification); tags
    // are stripped from the username and e-mail first. A
    // refused form comes back with the username and e-mail
    // refilled (refill), so neither password, nor anything
    // else a hand-made request adds, sits in the session. The
    // form fetches its reCAPTCHA token as it is sent
    // (custom.js), as a token is good for two minutes only,
    // and a refused score says so in words
    // (lang/en/validation.php).
    //
    // Two registrations sent at once — a double press — both
    // pass the check that the username is free, and the later
    // insert meets the username_UNIQUE key: it goes back as
    // the taken username it is, not as a server error. The
    // form's script also ignores a second press while it
    // fetches the token.
    //
    // Used by:
    //   - the registration form of users/register.blade.php
    // -----------------------------------------------------------

    public function store(Request $request)
    {
        // The reCAPTCHA v2 check (NOCAPTCHA_SECRET) is switched
        // off; the recaptchav3 rule below replaced it
        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'min:4', 'max:255', 'regex:/^[\pL\pN\.\,\s_-]+$/u', 'unique:cyber_users'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:cyber_users', new MailableAddress],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'g-recaptcha-response' => ['required', 'recaptchav3:register,0.6']
        ]);

        if ($validator->fails()) {
            return redirect()->route('register_users')->withErrors($validator)->withInput($this->refill($request, ['username', 'email']));
        }

        // MySQL's duplicate-entry error: the same registration
        // made a moment ago by the other half of a double press
        try {
            $user = User::create([
                'user_type' => 1,
                'username' => trim(strip_tags($request->input('username'))),
                'email' => trim(strip_tags($request->input('email'))),
                'password' => Hash::make($request->input('password')),
                'status'=>0,
                'newsletter' => 0
            ]);
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }

            return redirect()->route('register_users')->withErrors(['username' => __('validation.unique', ['attribute' => 'username'])])->withInput($this->refill($request, ['username', 'email']));
        }

        self::send_verification($user);

        session()->flash('success', 'You are registered! Check your email for verification link.');

        return redirect()->back();
    }






    // -----------------------------------------------------------
    // verify_form
    // -----------------------------------------------------------
    //
    // GET /verify/{verify_token}
    //
    // The users.verify view: one button, which posts the
    // activation to the same address. Opening the link changes
    // nothing — mail scanners, such as Microsoft 365's Safe
    // Links, open the links of incoming mail before the reader
    // does, and would use it up, leaving the player's own
    // click a 404, and activate an account no one confirmed.
    // The validate.verify middleware has refused unknown and
    // expired tokens already.
    //
    // Used by:
    //   - the link of the verification mail (UserVerifyMail)
    // -----------------------------------------------------------

    public function verify_form($verify_token)
    {
        return view('users.verify', ['verify_token' => $verify_token]);
    }






    // -----------------------------------------------------------
    // verify
    // -----------------------------------------------------------
    //
    // POST /verify/{verify_token}
    //
    // Activates the account holding the token's hash and
    // empties it, so the link works once, then goes to the
    // login page with "User verified! Now you can login.",
    // whatever page an earlier visit left asked for. The
    // validate.verify middleware has refused unknown and
    // expired tokens already; anything that still gets through
    // ends on the front page.
    //
    // Used by:
    //   - the button of users/verify.blade.php
    // -----------------------------------------------------------

    public function verify(Request $request, $verify_token)
    {
        if($verify_token <> ''){
            if(User::where(array('verify_token' => hash('sha256', $verify_token)))->count() == 0) {
            } else {
                User::where(['verify_token' => hash('sha256', $verify_token)])->update(['status'=>1,'verify_token'=>'']);

                session()->flash('success', 'User verified! Now you can login.');

                return redirect(route('login_users'));
            }
        } else {
            return redirect('/');
        }

        // The "already verified" message is switched off
        return redirect('/');
    }






    // -----------------------------------------------------------
    // send_verification
    // -----------------------------------------------------------
    //
    // Mails the account a verification link and stores the
    // link's token as its SHA-256 hash in verify_token — never
    // the token, so the users table alone activates nothing.
    // The token is 50 random characters followed, after a
    // dash, by the Unix time it is mailed, which
    // ValidateVerify reads back to refuse a link older than a
    // day; as the hash covers the time too, it cannot be moved
    // on. A newer link replaces the one before. An account
    // gets one link every 15 minutes at most — the cache
    // remembers the last one — as the login mails a fresh link
    // to an account never verified, and signing in again and
    // again would otherwise mail any number of them to its
    // address. A send that fails — the mail server down, say —
    // ends the request as before but keeps no 15 minutes, so
    // the next login tries again at once. Returns whether it
    // mailed one.
    //
    // Used by:
    //   - store (above) — a new registration
    //   - UserAuthController::login — an account never
    //     verified signing in, whose link may have expired
    // -----------------------------------------------------------

    public static function send_verification(User $user): bool
    {
        $mailed = "verification_mailed.{$user->id}";

        if (! Cache::add($mailed, true, now()->addMinutes(15))) {
            return false;
        }

        $uid_token = Str::random(50) . '-' . now()->getTimestamp();

        User::where(['id' => $user->id])->update(['verify_token' => hash('sha256', $uid_token)]);

        // A mail that failed holds back no next one
        try {
            Mail::to($user->email)->send(new UserVerifyMail(['uid' => $uid_token]));
        } catch (\Throwable $failure) {
            Cache::forget($mailed);
            throw $failure;
        }

        return true;
    }






    // -----------------------------------------------------------
    // ForgotPasswordForm
    // -----------------------------------------------------------
    //
    // GET /forgot-password
    //
    // The users.forgot-password view.
    //
    // Used by:
    //   - the reminder links of the login and registration
    //     pages
    // -----------------------------------------------------------

    public function ForgotPasswordForm()
    {
        return view('users.forgot-password');
    }






    // -----------------------------------------------------------
    // forgotpassword
    // -----------------------------------------------------------
    //
    // POST /forgot-password
    //
    // Behind a reCAPTCHA v3 score of at least 0.6 for the
    // forgotpass action: when exactly one local account has
    // the e-mail, mails it a reminder link whose token is a
    // fresh 50-character random string followed, after a
    // dash, by the Unix time it is mailed, and stores the
    // token's SHA-256 hash in remember_token — never the
    // token, so the users table alone resets no password.
    // The hash goes to that local account alone: a VU account
    // created later with the same e-mail is left out, as the
    // link must name exactly one account. The link goes to
    // the address stored with the account, not the one typed:
    // the column's collation matches the two ignoring case
    // and accents, so a look-alike domain would match too.
    // ValidateReset reads the time back to refuse a link
    // older than an hour; as the hash covers the time too, it
    // cannot be moved on. The answer is the same whether or
    // not the account exists, so the form tells no one which
    // addresses are registered. As in store, the form fetches
    // its reCAPTCHA token as it is sent, an address the mailer
    // would refuse is refused (MailableAddress), and a refused
    // form comes back with the e-mail alone refilled (refill).
    //
    // Used by:
    //   - the form of users/forgot-password.blade.php
    // -----------------------------------------------------------

    public function forgotpassword(Request $request)
    {
        // The reCAPTCHA v2 check (NOCAPTCHA_SECRET) is switched
        // off; the recaptchav3 rule below replaced it
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', new MailableAddress],
            'g-recaptcha-response' => ['required', 'recaptchav3:forgotpass,0.6']
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($this->refill($request, ['email']));
        }

        $local = User::where(['email' => $request->input('email')])->where(function ($query) { $query->where('vu_id', '')->orWhereNull('vu_id'); });

        if ($local->count() == 1) {
            $uid = Str::random(50) . '-' . now()->getTimestamp();

            Mail::to($local->value('email'))->send(new SendRemindLink(['uid'=>$uid]));

            $local->update(['remember_token'=>hash('sha256', $uid)]);
        }

        session()->flash('success', 'If email exists in our database, you will receive reset link.');

        return back();
    }






    // -----------------------------------------------------------
    // reset_form
    // -----------------------------------------------------------
    //
    // GET /reset/{reset_token}
    //
    // The users.reset-password view: one button, which posts
    // the reset to the same address. Opening the link changes
    // nothing, as mail filters and link scanners open the
    // links of incoming mail before the player does. The
    // validate.reset middleware has refused unknown and
    // expired tokens already.
    //
    // Used by:
    //   - the link of the reminder mail (SendRemindLink)
    // -----------------------------------------------------------

    public function reset_form($reset_token)
    {
        return view('users.reset-password', ['reset_token' => $reset_token]);
    }






    // -----------------------------------------------------------
    // reset_pass
    // -----------------------------------------------------------
    //
    // POST /reset/{reset_token}
    //
    // Gives the account holding the token's hash a new random
    // 12-character password, mails it, stores its hash and
    // empties the token, then goes to the login page. The
    // validate.reset middleware has refused unknown and
    // expired tokens already. The account is found by the
    // hash without a lock, then claimed by its id: the token
    // is emptied and the password set in one statement before
    // the mail goes out, so of two presses at once only the
    // one that empties it mails a password, the one stored;
    // the transaction around them puts both back should the
    // mail fail. Claimed by id, the account's row is the only
    // one locked while the mail is sent — remember_token has
    // no index, and a statement searching it would lock every
    // row of cyber_users until the mail server answered.
    //
    // Used by:
    //   - the button of users/reset-password.blade.php
    // -----------------------------------------------------------

    public function reset_pass($reset_token)
    {
        $new_pass = Str::random(12);

        $user = User::select('id', 'email')->where('remember_token', '=', hash('sha256', $reset_token))->first();

        if ($user !== null) {
            DB::transaction(function () use ($reset_token, $user, $new_pass) {
                $claimed = User::whereKey($user->id)->where('remember_token', '=', hash('sha256', $reset_token))->update(['password'=>Hash::make($new_pass), 'remember_token'=>'']);

                if ($claimed === 1) {
                    Mail::to($user->email)->send(new SendNewPass(['email'=>$user->email, 'new_pass'=>$new_pass]));
                }
            });
        }

        session()->flash('success', 'We just emailed you new password');

        return redirect(route('login_users'));
    }
}
