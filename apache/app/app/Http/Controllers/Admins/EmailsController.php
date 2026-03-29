<?php

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

class EmailsController extends BaseController
{
    
    public function __construct()
    {
        parent::__construct();
    }
 
    public function index(Request $request)
    { 
        if (count(Emails::all()->toArray()) > 0) {
            
            $arr = Emails::all()->toArray();
        } else {
            
            $arr = array();
        }
        
        if (empty($request->get('page')) || ! is_numeric($request->get('page'))) {
            
            $curr_page = 1;
        } else {
            
            $curr_page = $request->get('page');
        }
        
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
 
    public function create()
    {
        //
        return view('admins.emails_form', [
            'url' => env('APP_URL') . '/control-panel/emails/store',
            'name' => '',
            'subject' => '',
            'content' => '',
            'uid'=>''
        ]);
    }
 
    public function store(Request $request)
    {
       
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'content' => 'required',
            'subject' => 'required'
        ]);
        
        Emails::create([
            'name' => $request->get('name'),
            'content' => $request->get('content'),
            'subject' => $request->get('subject'),
            'sent' => 0
        ]);
        
        session()->flash('success', 'Created!');
        
        return redirect(route('admin.emails'));
        
    } 
 
    public function edit(Request $request, $uid)
    {
        //
        
        return view('admins.emails_form', [
            'url' => env('APP_URL') . '/control-panel/emails/update',
            'name' => Emails::find($uid)->name,
            'content' => Emails::find($uid)->content,
            'subject' => Emails::find($uid)->subject,
            'uid' => $uid
        ]);
    }
 
    public function update(Request $request)
    {
        //
        Emails::where(['id' => $request->get('uid')])->update([
            'name' => $request->get('name'),
            'content' => $request->get('content'),
            'subject' => $request->get('subject')
        ]);
        
        session()->flash('success', 'Updated!');
        
        return redirect(route('admin.emails'));
    }
 
    public function destroy($uid)
    {
        
        Emails::destroy($uid);
        
        session()->flash('success', 'Deleted!');
        
        return back();
    }

    public function send_test(Request $request, $email_id)
    {
        return view('admins.dialogs.email_test_form', [
            'uid' => $email_id
        ]);
    }
    
    public function sending_test(Request $request, $email_id)
    { 
        
        Mail::to($request->get('email'))->send(new SendMail(Emails::find($email_id)->subject, Emails::find($email_id)->content));
        
        session()->flash('success', 'Test email sent!');
        
        return redirect()->intended(route('admin.emails'));
    }

    public function sync_with_lists($type){
        
        if($type =="registered"){
            
            $listId = 10226591;
            
            $emails = User::select('email')->where(['status'=>1])->get()->pluck('email')->toArray();
            
        } elseif($type =="newsletters"){
            
            $listId = 10226593;
            
            $emails = User::select('email')->where(['newsletter'=>1])->get()->pluck('email')->toArray();
            
        }
     
        if(is_array($emails) && count($emails)>0){
            foreach($emails as $k => $email){
                $this->addSubscriber($email, $listId);
            }
        }
        
        return back()->withSuccess($type .' email list synced!');
        
    }
     
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

    public function send(Request $request, $email_id)
    {
        
        Mail::to(env('MAILJET_REGISTERED_LIST'))->send(new SendMail(Emails::find($email_id)->subject, Emails::find($email_id)->content));
        
        Emails::where(['id' => $email_id])->update(['sent' => 1]);
        
        session()->flash('success', 'Email sent!');
        
        return redirect()->intended(route('admin.emails'));
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    

}