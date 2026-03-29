@if($type =='new')
{{ Form::open(['url' => env('APP_URL').'/control-panel/dialog/partners/store', 'files' => true]) }}
@else
{{ Form::open(['url' => env('APP_URL').'/control-panel/dialog/partners/update', 'files' => true]) }}
@endif
 <div class="mb-3">
 <label class="form-label" for="f1">Partners name</label>
{{ Form::text('name', $name ?? '', ['class' => 'form-control', 'maxlength'=>255, 'required'=>'required', 'id'=>'f1']) }}
</div>
{{ Form::label('file_path', 'Picture (JPG, PNG, GIF):') }}
{{ Form::file('picture', ['class' => 'form-control', 'id' => 'file_name', 'required' => 'required', 'accept' => '.jpg,.png,.gif']) }}
</div>
<div class="mb-3 mt-3">
{{ Form::submit('Submit', ['class'=>'btn btn-primary', 'login'=>'login']) }}
</div>     

@if($type =='edit')
	{{ Form::hidden('uid', $uid ?? 0) }}
@endif
             
{{ Form::close() }}