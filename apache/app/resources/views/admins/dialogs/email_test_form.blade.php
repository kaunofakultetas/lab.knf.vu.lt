<form method="POST" action="{{ env('APP_URL').'/control-panel/emails/sending_test/'.$uid }}" accept-charset="UTF-8">@csrf
 <div class="mb-3">
 <label class="form-label" for="f1">Email</label>
<input class="form-control" maxlength="255" required="required" id="f1" name="email" type="email" value="{{ old('email') }}">
</div>
<div class="mb-3">
<input class="btn btn-primary" login="login" type="submit" value="Send">
</div>     
 
	<input name="uid" type="hidden" value="{{ old('uid', $uid ?? 0) }}">
       
</form>