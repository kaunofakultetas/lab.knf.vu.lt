<!DOCTYPE html>
<html lang="en-US">
  <head>
    <meta charset="utf-8" />
  </head>
  <body>
Hello,
<br><br>
Login email: {{ $email }}
<br><br>
Your password:
<br>
    <b>{{ $new_pass }}</b>


<br><br>
Login URL: <a href="{{ env('APP_URL') }}" target="_blank" disable-tracking=true>{{ env('APP_URL') }}</a>
<br><br><br>
--<br>
VU KNF Labs


  </body>
</html>