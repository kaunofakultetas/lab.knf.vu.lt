@if($type =='new')
<form method="POST" action="{{ env('APP_URL').'/control-panel/dialog/partners/store' }}" accept-charset="UTF-8" enctype="multipart/form-data">@csrf
@else
<form method="POST" action="{{ env('APP_URL').'/control-panel/dialog/partners/update' }}" accept-charset="UTF-8" enctype="multipart/form-data">@csrf
@endif
 <div class="mb-3">
 <label class="form-label" for="f1">Partners name</label>
<input class="form-control" maxlength="255" required="required" id="f1" name="name" type="text" value="{{ old('name', $name ?? '') }}">
</div>
<label for="file_path">Picture (JPG, PNG, GIF):</label>
<input class="form-control" id="file_name" required="required" accept=".jpg,.png,.gif" name="picture" type="file">
</div>
<div class="mb-3 mt-3">
<input class="btn btn-primary" login="login" type="submit" value="Submit">
</div>     

@if($type =='edit')
	<input name="uid" type="hidden" value="{{ old('uid', $uid ?? 0) }}">
@endif
             
</form>