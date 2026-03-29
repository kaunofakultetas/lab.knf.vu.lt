@if($type =='new')
{{ Form::open(['url' => $url]) }}
@else
{{ Form::open(['url' => $url]) }}
@endif
<div class="mb-3">
<label class="form-label" for="f1">Name</label>
{{ Form::text('name', $name ?? '', ['class' => 'form-control', 'maxlength'=>255, 'required'=>'required', 'id'=>'f1']) }}
</div>
<div class="mb-3">
<label class="form-label" for="f2">File URL (with https://)</label>
{{ Form::text('file_url', $file_url ?? '', ['class' => 'form-control', 'required'=>'required', 'id'=>'f2']) }}
</div>
<div class="mb-3">
{{ Form::submit('Submit', ['class'=>'btn btn-primary', 'login'=>'login']) }}
</div>     

 
	{{ Form::hidden('uid', $uid ?? 0) }}
	{{ Form::hidden('type', $type ?? '') }}
 
             
{{ Form::close() }}