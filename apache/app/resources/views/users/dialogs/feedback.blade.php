{{ Form::open(['url'=>'/user/feedback']) }}
 
     {{ Form::textarea('feedback', '', ['class' => 'form-control', 'required'=>'required']) }} <br>
     {{ Form::submit('Submit', ['class'=>'btn btn-primary', 'login'=>'login']) }} 
     
{{ Form::close() }}