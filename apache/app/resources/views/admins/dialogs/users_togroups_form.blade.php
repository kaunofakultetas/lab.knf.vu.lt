@if($type =='new')
<form method="POST" action="{{ env('APP_URL').'/control-panel/dialog/users/vu-groups/users_store' }}" accept-charset="UTF-8">@csrf
@else
<form method="POST" action="{{ env('APP_URL').'/control-panel/dialog/users/vu-groups/users_update' }}" accept-charset="UTF-8">@csrf
@endif
 <div class="mb-3">
 <label class="form-label" for="f1">VU ID</label>
<input class="form-control" maxlength="255" required="required" id="f1" name="vu_id" type="text" value="{{ old('vu_id') }}">
</div>
<div class="mb-3">
<input class="btn btn-primary" login="login" type="submit" value="Submit">
</div>     
 
<input name="uid" type="hidden" value="{{ old('uid', $uid ?? 0) }}">
<input name="type" type="hidden" value="{{ old('type', $type) }}">
 
             
</form>