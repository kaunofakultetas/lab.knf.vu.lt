{{ Form::open(['url' => env('APP_URL').'/control-panel/emails/sending_test/'.$uid]) }}
 <div class="mb-3">
 <label class="form-label" for="f1">Email</label>
{{ Form::email('email', '', ['class' => 'form-control', 'maxlength'=>255, 'required'=>'required', 'id'=>'f1']) }}
</div>
<div class="mb-3">
{{ Form::submit('Send', ['class'=>'btn btn-primary', 'login'=>'login']) }}
</div>     
 
	{{ Form::hidden('uid', $uid ?? 0) }}
       
{{ Form::close() }}