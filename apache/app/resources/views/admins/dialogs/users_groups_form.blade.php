@if($type =='new')
<form method="POST" action="{{ env('APP_URL').'/control-panel/dialog/users/vu-groups/store' }}" accept-charset="UTF-8">@csrf
@else
<form method="POST" action="{{ env('APP_URL').'/control-panel/dialog/users/vu-groups/update' }}" accept-charset="UTF-8">@csrf
@endif
 <div class="mb-3">
 <label class="form-label" for="f1">Group name</label>
<input class="form-control" maxlength="255" required="required" id="f1" name="name" type="text" value="{{ old('name', $name ?? '') }}">
</div>
<div class="mb-3">
<input class="btn btn-primary" login="login" type="submit" value="Submit">
</div>     

@if($type =='edit')
	<input name="uid" type="hidden" value="{{ old('uid', $uid ?? 0) }}">
@endif
             
</form>