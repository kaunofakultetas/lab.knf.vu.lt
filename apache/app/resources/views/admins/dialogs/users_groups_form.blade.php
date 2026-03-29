@if($type =='new')
{{ Form::open(['url' => env('APP_URL').'/control-panel/dialog/users/vu-groups/store']) }}
@else
{{ Form::open(['url' => env('APP_URL').'/control-panel/dialog/users/vu-groups/update']) }}
@endif
 <div class="mb-3">
 <label class="form-label" for="f1">Group name</label>
{{ Form::text('name', $name ?? '', ['class' => 'form-control', 'maxlength'=>255, 'required'=>'required', 'id'=>'f1']) }}
</div>
<div class="mb-3">
{{ Form::submit('Submit', ['class'=>'btn btn-primary', 'login'=>'login']) }}
</div>     

@if($type =='edit')
	{{ Form::hidden('uid', $uid ?? 0) }}
@endif
             
{{ Form::close() }}