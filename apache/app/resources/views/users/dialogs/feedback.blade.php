<form method="POST" action="{{ url('/user/feedback') }}" accept-charset="UTF-8">@csrf
 
     <textarea class="form-control" required="required" name="feedback" cols="50" rows="10">{{ old('feedback') }}</textarea> <br>
     <input class="btn btn-primary" login="login" type="submit" value="Submit"> 
     
</form>