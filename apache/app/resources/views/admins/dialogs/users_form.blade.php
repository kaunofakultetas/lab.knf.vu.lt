@if($type =='new')
<form method="POST" action="{{ env('APP_URL').'/control-panel/dialog/users/store' }}" accept-charset="UTF-8">@csrf
@else
<form method="POST" action="{{ env('APP_URL').'/control-panel/dialog/users/update' }}" accept-charset="UTF-8">@csrf
@endif
<div class="mb-3">
<label for="f1">Username:</label>
@if($type =='new')
	<input class="form-control" maxlength="255" required="required" id="f1" name="username" type="text" value="{{ old('username', $username ?? '') }}">
@else
	<br>{{ $username ?? '' }}
@endif
</div>
<div class="mb-3">
<label for="f2">Email:</label>
@if($type =='new')
	<input class="form-control" maxlength="255" required="required" id="f2" name="email" type="email" value="{{ old('email', $email ?? '') }}">
@else
	<br>{{ $email ?? '' }}
@endif
</div>
<div class="mb-3">
 
<label for="f3">Password:</label>
<div class="input-group">
<input class="form-control" maxlength="255" required="required" id="f3" name="password" type="text" value="{{ old('password') }}">
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
<input class="btn btn-primary" login="login" type="submit" value="Submit">
</div>     

@if($type =='edit')
	<input name="uid" type="hidden" value="{{ old('uid', $uid ?? 0) }}">
@endif
             
</form>