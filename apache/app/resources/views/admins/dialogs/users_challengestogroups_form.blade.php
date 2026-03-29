@if($type =='new')
{{ Form::open(['url' => env('APP_URL').'/control-panel/dialog/users/vu-groups/challenges_store']) }}
@else
 
@endif
 <div class="mb-3">
 <label class="form-label" for="f1">Challenge</label><br>
 {{ Form::select("challenge_id", $challenge_list, $challenge_id ?? 0, ['class'=>'form-control', 'required'=>'required']) }}
</div>
<div class="mb-3">
{{ Form::submit('Submit', ['class'=>'btn btn-primary', 'login'=>'login']) }}
</div>     
 
{{ Form::hidden('uid', $uid ?? 0) }}
{{ Form::hidden('type', $type) }}
 
             
{{ Form::close() }}
 
