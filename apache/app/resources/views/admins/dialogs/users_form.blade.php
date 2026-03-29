@if($type =='new')
{{ Form::open(['url' => env('APP_URL').'/control-panel/dialog/users/store']) }}
@else
{{ Form::open(['url' => env('APP_URL').'/control-panel/dialog/users/update']) }}
@endif
<div class="mb-3">
{{ Form::label('f1', 'Username:') }}
@if($type =='new')
	{{ Form::text('username', $username ?? '', ['class' => 'form-control', 'maxlength'=>255, 'required'=>'required', 'id'=>'f1']) }}
@else
	<br>{{ $username ?? '' }}
@endif
</div>
<div class="mb-3">
{{ Form::label('f2', 'Email:') }}
@if($type =='new')
	{{ Form::email('email', $email ?? '', ['class' => 'form-control', 'maxlength'=>255, 'required'=>'required', 'id'=>'f2']) }}
@else
	<br>{{ $email ?? '' }}
@endif
</div>
<div class="mb-3">
 
{{ Form::label('f3', 'Password:') }}
<div class="input-group">
{{ Form::text('password', '', ['class' => 'form-control', 'maxlength'=>255, 'required'=>'required', 'id'=>'f3']) }}
<button class="btn btn-secondary" id="gens" type="button" onClick="pgen(15);">Generate</button>
</div>
</div>

<div class="mb-3">
    <div class="form-check">
           <input class="form-check-input" id="defaultCheck0" type="checkbox" name="notif" value="1">
           <label class="form-check-label" for="defaultCheck0">Email login information</label>
    </div>
</div>                        
                            
<div class="mb-3">
{{ Form::submit('Submit', ['class'=>'btn btn-primary', 'login'=>'login']) }}
</div>     

@if($type =='edit')
	{{ Form::hidden('uid', $uid ?? 0) }}
@endif
             
{{ Form::close() }}