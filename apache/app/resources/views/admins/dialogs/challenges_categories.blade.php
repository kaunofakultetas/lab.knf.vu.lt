<form method="POST" action="{{ env('APP_URL').'/control-panel/challenges/categories/store' }}" accept-charset="UTF-8">@csrf

 <div class="mb-3">
 	<b>{{ $challenge_name }}</b>
 </div>

	@foreach($categories_list as $k => $item)
        
        <div class="mb-3">
            <div class="form-check">
                   <input class="form-check-input" id="defaultCheck{{ $k }}" type="checkbox" name="item[{{ $k }}]" value="{{ $k }}"
                   @if(in_array($k, $selected_categories)) checked @endif >
                   <label class="form-check-label" for="defaultCheck{{ $k }}">{{ $item }}</label>
            </div>
        </div>  
        
    @endforeach
    
    
<div class="mb-3">
	<input class="btn btn-primary" login="login" type="submit" value="Submit">
</div>  

<input name="uid" type="hidden" value="{{ old('uid', $uid ?? 0) }}">
</form>