<?php

// -----------------------------------------------------------
//  [*] EmailsController — the mail templates and lists
//
//  The main admins' mail page: write, change and delete
//  mail templates, send one as a test to any address or to
//  the registered players' Mailjet list, and copy the
//  players' addresses into the two Mailjet contact lists.
//  The templates go out as written, as HTML (SendMail).
//
//    GET  /control-panel/emails                             — the list
//    GET  /control-panel/emails/create                      — the empty form
//    POST /control-panel/emails/store                       — adds
//    GET  /control-panel/emails/edit/{uid}                  — the filled form
//    POST /control-panel/emails/update                      — changes
//    POST /control-panel/emails/delete/{uid}                — deletes
//    GET  /control-panel/dialog/emails/send_test/{email_id} — test dialog
//    POST /control-panel/emails/sending_test/{email_id}     — test mail
//    POST /control-panel/emails/send/{email_id}             — sends
//    POST /control-panel/emails/sync/{type}                 — list sync
//
//  Used by:
//    - routes/web.php — the routes above, all main-only
//      (perms)
// -----------------------------------------------------------


namespace App\Http\Controllers\Admins;

use Mail;
use App\Mail\SendMail;
use App\Http\Controllers\Admins\BaseController;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Emails;
use Mailjet\Client;
use Mailjet\Resources;








// -----------------------------------------------------------
// EmailsController
// -----------------------------------------------------------
//
// A template needs a name, subject and content, new or
// edited, and a test mail an address. The Mailjet API this
// controller calls for the list sync is not among the
// hosts the stack's egress lets through (exit/Caddyfile:
// the SMTP relay and reCAPTCHA only), so the sync cannot
// work in production as it stands.
//
//   __construct     — the base's layout data
//   index           — the templates list
//   create          — the empty form
//   store           — adds a template
//   edit            — the filled form
//   update          — changes a template
//   destroy         — deletes a template
//   send_test       — the test dialog
//   sending_test    — sends a test mail
//   sync_with_lists — copies addresses into a Mailjet list
//   addSubscriber   — one address into one list
//   send            — sends a template to the list
//
// Used by:
//   - routes/web.php
// -----------------------------------------------------------

class EmailsController extends BaseController
{






    // -----------------------------------------------------------
    // __construct
    // -----------------------------------------------------------
    //
    // Only runs the base constructor, which shares the
    // layout's data.
    //
    // Used by:
    //   - Laravel, when it builds the controller for a request
    // -----------------------------------------------------------

    public function __construct()
    {
        parent::__construct();
    }






    // -----------------------------------------------------------
    // index
    // -----------------------------------------------------------
    //
    // GET /control-panel/emails
    //
    // The admins.emails view with the templates, 50 to a page
    // of the list — the page query parameter picks one — or
    // an empty list when there are none.
    //
    // Used by:
    //   - the Emails link of the panel's sidebar, and the
    //     redirects of the template actions
    // -----------------------------------------------------------

    public function index(Request $request)
    {
        if (count(Emails::all()->toArray()) > 0) {
            $arr = Emails::all()->toArray();
        } else {
            $arr = array();
        }

        $curr_page = $this->listPage(count($arr));

        $perPage = 50;

        $currentItems = array_slice($arr, $perPage * ($curr_page - 1), $perPage);

        $paginator = new LengthAwarePaginator($currentItems, count($arr), $perPage, $curr_page, [
            'path' => url('control-panel/emails')
        ]);

        if (count($arr) == 0) {
            $arr = array();
            $paginator = array();
        }
        return view('admins.emails', [
            'recordlist' => $paginator
        ]);
    }






    // -----------------------------------------------------------
    // create
    // -----------------------------------------------------------
    //
    // GET /control-panel/emails/create
    //
    // The admins.emails_form view, empty, posting to store.
    //
    // Used by:
    //   - the add button of the templates list
    // -----------------------------------------------------------

    public function create()
    {
        return view('admins.emails_form', [
            'url' => env('APP_URL') . '/control-panel/emails/store',
            'name' => '',
            'subject' => '',
            'content' => '',
            'uid'=>''
        ]);
    }






    // -----------------------------------------------------------
    // store
    // -----------------------------------------------------------
    //
    // POST /control-panel/emails/store
    //
    // Adds a template, not sent yet, and goes to the list with
    // "Created!"; a form missing the name, subject or content,
    // or with a name or subject longer than the 255
    // characters their columns hold, goes back with the
    // errors and what was typed.
    //
    // Used by:
    //   - the template form, for a new template
    // -----------------------------------------------------------

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'content' => 'required',
            'subject' => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        Emails::create([
            'name' => $request->input('name'),
            'content' => $request->input('content'),
            'subject' => $request->input('subject'),
            'sent' => 0
        ]);

        session()->flash('success', 'Created!');

        return redirect(route('admin.emails'));
    }






    // -----------------------------------------------------------
    // edit
    // -----------------------------------------------------------
    //
    // GET /control-panel/emails/edit/{uid}
    //
    // The template form filled with the template, posting to
    // update; an unknown template is a 404.
    //
    // Used by:
    //   - the edit links of the templates list
    // -----------------------------------------------------------

    public function edit(Request $request, $uid)
    {
        return view('admins.emails_form', [
            'url' => env('APP_URL') . '/control-panel/emails/update',
            'name' => Emails::findOrFail($uid)->name,
            'content' => Emails::findOrFail($uid)->content,
            'subject' => Emails::findOrFail($uid)->subject,
            'uid' => $uid
        ]);
    }






    // -----------------------------------------------------------
    // update
    // -----------------------------------------------------------
    //
    // POST /control-panel/emails/update
    //
    // Changes the template with the posted uid to the name,
    // content and subject — all three required and held to
    // the lengths of store, a refused form going back with
    // the errors and what was typed — then goes to the list
    // with "Updated!". A template another admin deleted in
    // the meantime goes to the list with "No email!" instead.
    //
    // Used by:
    //   - the template form, for an existing template
    // -----------------------------------------------------------

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'content' => 'required',
            'subject' => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        if (Emails::whereKey($request->input('uid'))->doesntExist()) {
            return redirect(route('admin.emails'))->withErrors(['No email!']);
        }

        Emails::where(['id' => $request->input('uid')])->update([
            'name' => $request->input('name'),
            'content' => $request->input('content'),
            'subject' => $request->input('subject')
        ]);

        session()->flash('success', 'Updated!');

        return redirect(route('admin.emails'));
    }






    // -----------------------------------------------------------
    // destroy
    // -----------------------------------------------------------
    //
    // POST /control-panel/emails/delete/{uid}
    //
    // Deletes the template and goes back with "Deleted!".
    //
    // Used by:
    //   - Confirm_Dialog() of custom_admin.js — the delete
    //     links of the templates list
    // -----------------------------------------------------------

    public function destroy($uid)
    {
        Emails::destroy($uid);

        session()->flash('success', 'Deleted!');

        return back();
    }






    // -----------------------------------------------------------
    // send_test
    // -----------------------------------------------------------
    //
    // GET /control-panel/dialog/emails/send_test/{email_id}
    //
    // The admins.dialogs.email_test_form view, which asks for
    // the address to test with; an unknown template is a 404.
    //
    // Used by:
    //   - email_test() of dialogs.js — the Test links of the
    //     templates list
    // -----------------------------------------------------------

    public function send_test(Request $request, $email_id)
    {
        Emails::findOrFail($email_id);

        return view('admins.dialogs.email_test_form', [
            'uid' => $email_id
        ]);
    }






    // -----------------------------------------------------------
    // sending_test
    // -----------------------------------------------------------
    //
    // POST /control-panel/emails/sending_test/{email_id}
    //
    // Mails the template to the address typed in the dialog —
    // which must be an e-mail address, or the form goes back
    // with the error — then goes to the list with "Test email
    // sent!". The template stays marked as not sent; an
    // unknown template is a 404.
    //
    // Used by:
    //   - the test dialog's form
    // -----------------------------------------------------------

    public function sending_test(Request $request, $email_id)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        Mail::to($request->input('email'))->send(new SendMail(Emails::findOrFail($email_id)->subject, Emails::findOrFail($email_id)->content));

        session()->flash('success', 'Test email sent!');

        return redirect(route('admin.emails'));
    }






    // -----------------------------------------------------------
    // sync_with_lists
    // -----------------------------------------------------------
    //
    // POST /control-panel/emails/sync/{type}
    //
    // Copies addresses into a Mailjet contact list, one
    // addSubscriber call each: for registered, every active
    // account into list 10226591; for newsletters, every
    // account that asked for the newsletter into list
    // 10226593. Any other type is a 404. Then back with the list
    // named as synced.
    //
    // Used by:
    //   - Post_Link() of custom_admin.js — the registered and
    //     newsletters buttons of the templates list
    // -----------------------------------------------------------

    public function sync_with_lists($type)
    {
        if($type =="registered"){
            $listId = 10226591;

            $emails = User::select('email')->where(['status'=>1])->get()->pluck('email')->toArray();
        } elseif($type =="newsletters"){
            $listId = 10226593;

            $emails = User::select('email')->where(['newsletter'=>1])->get()->pluck('email')->toArray();
        } else {
            abort(404);
        }

        if(is_array($emails) && count($emails)>0){
            foreach($emails as $k => $email){
                $this->addSubscriber($email, $listId);
            }
        }

        return back()->withSuccess($type .' email list synced!');
    }






    // -----------------------------------------------------------
    // addSubscriber
    // -----------------------------------------------------------
    //
    // Puts one address on one Mailjet list (API v3, with
    // MAILJET_APIKEY and MAILJET_APISECRET): skips an address
    // that is not an e-mail, finds the contact by address or
    // creates it, then subscribes it to the list. Failures of
    // the subscription are swallowed; a failure of the lookup
    // or of the contact's creation is not, and ends the sync
    // in a server error.
    //
    // Used by:
    //   - sync_with_lists (above) — every address
    // -----------------------------------------------------------

    public static function addSubscriber($email, $listId)
    {
        $validator = Validator::make(['email' => $email], ['email' => 'required|email']);
        if ($validator->fails()) {
            return;
        }

        $mj = new Client(env('MAILJET_APIKEY'), env('MAILJET_APISECRET'),true,['version' => 'v3']);

        $exists = $mj->get(Resources::$Contact, [ 'filters' => [ 'Email' => $email ] ]);

        try {
            $contactId = $exists->getData()[0]['ID'];
        } catch (\Exception $e) {
        }

        if (isset($exists->getData()[0]['ID'])){
            try {
                $body2 = [
                    'IsUnsubscribed' => "false",
                    'ContactID' => $contactId,
                    'ListID' => $listId,
                ];
                $response2 = $mj->post(Resources::$Listrecipient, ['body' => $body2]);
            } catch (\Exception $e) {
            }
        } else {
            $body = [
                'IsExcludedFromCampaigns' => "false",
                'Email' => $email,
                'IsOptInPending'=>"false"
            ];
            $response = $mj->post(Resources::$Contact, ['body' => $body]);
            try {
                $body2 = [
                    'IsUnsubscribed' => "false",
                    'ContactID' => $response->getData()[0]['ID'],
                    'ListID' => $listId,
                ];
                $response2 = $mj->post(Resources::$Listrecipient, ['body' => $body2]);
            } catch (\Exception $e) {
            }
        }
    }






    // -----------------------------------------------------------
    // send
    // -----------------------------------------------------------
    //
    // POST /control-panel/emails/send/{email_id}
    //
    // Mails the template to the address of the registered
    // players' Mailjet list, MAILJET_REGISTERED_LIST, marks it
    // sent — its Send link then disappears — and goes to the
    // list with "Email sent!". A template another admin
    // deleted in the meantime is a 404. The stack sets no
    // MAILJET_REGISTERED_LIST, so in production the mail has
    // no address to go to.
    //
    // Used by:
    //   - Confirm_Dialog() of custom_admin.js — the Send links
    //     of the templates list
    // -----------------------------------------------------------

    public function send(Request $request, $email_id)
    {
        $template = Emails::findOrFail($email_id);

        Mail::to(env('MAILJET_REGISTERED_LIST'))->send(new SendMail($template->subject, $template->content));

        Emails::where(['id' => $email_id])->update(['sent' => 1]);

        session()->flash('success', 'Email sent!');

        return redirect(route('admin.emails'));
    }
}
