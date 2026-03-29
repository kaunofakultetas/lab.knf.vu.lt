@if($type =='new')
{{ Form::open(['url' => env('APP_URL').'/control-panel/dialog/users/vu-groups/users_store']) }}
@else
{{ Form::open(['url' => env('APP_URL').'/control-panel/dialog/users/vu-groups/users_update']) }}
@endif
 <div class="mb-3">
 <label class="form-label" for="f1">VU ID</label>
{{ Form::text('vu_id', '', ['class' => 'form-control', 'maxlength'=>255, 'required'=>'required', 'id'=>'f1']) }}
</div>
<div class="mb-3">
{{ Form::submit('Submit', ['class'=>'btn btn-primary', 'login'=>'login']) }}
</div>     
 
{{ Form::hidden('uid', $uid ?? 0) }}
{{ Form::hidden('type', $type) }}
 
             
{{ Form::close() }}