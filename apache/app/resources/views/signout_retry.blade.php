{{--
    The answer to a sign-out whose CSRF token was refused
    (VerifyCsrfToken::handle): the page it came from had outlived
    its session, and nothing was signed out. It offers the same
    sign-out again, as a form with the session's current token, and
    waits for the press: a page that sent itself would let any site
    sign a visitor out of the VU single sign-on.
--}}
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
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
                  <p class="fs-4">The page had been open too long, so you are not signed out yet.</p>
                  <form method="POST" action="{{ $action }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">Sign out</button>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>
</html>
