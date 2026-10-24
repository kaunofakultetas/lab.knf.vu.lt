@if($type =='new')
<form method="POST" action="{{ env('APP_URL').'/control-panel/dialog/users/vu-groups/challenges_store' }}" accept-charset="UTF-8">@csrf
@else
 
@endif
 <div class="mb-3">
 <label class="form-label" for="f1">Challenge</label><br>
 <select class="form-control" required="required" name="challenge_id">@foreach ($challenge_list as $option_value => $option_text)<option value="{{ $option_value }}"@if ((string) $option_value === (string) old("challenge_id", $challenge_id ?? 0)) selected="selected"@endif>{{ $option_text }}</option>@endforeach</select>
</div>
<div class="mb-3">
<input class="btn btn-primary" login="login" type="submit" value="Submit">
</div>     
 
<input name="uid" type="hidden" value="{{ old('uid', $uid ?? 0) }}">
<input name="type" type="hidden" value="{{ old('type', $type) }}">
 
             
</form>
 
