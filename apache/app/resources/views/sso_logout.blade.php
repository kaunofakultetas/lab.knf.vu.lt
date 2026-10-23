{{--
    The page between the lab's sign-out and the VU identity provider's
    logout. It moves on by itself, through the meta refresh, and offers
    the same address as a link. A redirect cannot do this job: the
    sign-out is a form, and the site's CSP (form-action 'self') holds
    every redirect of a form's navigation to the site itself, so the
    browser would stop the redirect to sso.vu.lt and leave the VU
    session open.
--}}
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <meta http-equiv="refresh" content="0;url={{ $logout_url }}">
    <title>VU KNF Labs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="robots" content="noindex,nofollow">

    <link href="/assets/img/favicon.ico" rel="shortcut icon" type="image/vnd.microsoft.icon" />
    <link rel="stylesheet" href="/static/fonts/poppins.css">
    <link rel="stylesheet" href="/assets/css/app.min.css">
  </head>
  <body>
    <div class="login-page">
      <div class="container d-flex align-items-center position-relative py-5">
        <div class="card shadow-sm w-100 rounded overflow-hidden bg-none">
          <div class="card-body p-0">
            <div class="row gx-0 align-items-stretch">
              <div class="col-lg-12 bg-white">
                <div class="align-items-center p-5 px-lg-5 h-100 text-center">
                  <h1 class="h1 pb-4">Signing out</h1>
                  <p class="fs-4">Signing you out of VU SSO&hellip; <a href="{{ $logout_url }}">Continue</a></p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>
</html>
