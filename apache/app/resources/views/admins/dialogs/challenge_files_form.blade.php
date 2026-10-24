@if($type =='new')
<form method="POST" action="{{ $url }}" accept-charset="UTF-8">@csrf
@else
<form method="POST" action="{{ $url }}" accept-charset="UTF-8">@csrf
@endif
<div class="mb-3">
<label class="form-label" for="f1">Name</label>
<input class="form-control" maxlength="255" required="required" id="f1" name="name" type="text" value="{{ old('name', $name ?? '') }}">
</div>
<div class="mb-3">
<label class="form-label" for="f2">File URL (with https://)</label>
<input class="form-control" required="required" id="f2" name="file_url" type="text" value="{{ old('file_url', $file_url ?? '') }}">
</div>
<div class="mb-3">
<input class="btn btn-primary" login="login" type="submit" value="Submit">
</div>     

 
	<input name="uid" type="hidden" value="{{ old('uid', $uid ?? 0) }}">
	<input name="type" type="hidden" value="{{ old('type', $type ?? '') }}">
 
             
</form>