<?php

// -----------------------------------------------------------
//  [*] UnauthorizedMiddleware — an unused JWT check
//
//  A leftover from another project: it would demand a valid
//  JSON Web Token (the tymon/jwt-auth package) and answer
//  401 for an expired, invalid or missing one. The package
//  is not installed and no alias names the class, so
//  nothing runs it. Dead code, kept as found.
//
//  Used by:
//    - nothing at the moment
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;








// -----------------------------------------------------------
// UnauthorizedMiddleware
// -----------------------------------------------------------
//
// One method, handle.
//
// Used by:
//   - nothing at the moment
// -----------------------------------------------------------

class UnauthorizedMiddleware
{






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // Would authenticate the request's token and turn each of
    // the package's token errors into a 401 naming it.
    //
    // Used by:
    //   - nothing at the moment
    // -----------------------------------------------------------

    public function handle($request, Closure $next)
    {
        try {
            $user = auth()->parseToken()->authenticate();
        } catch (TokenExpiredException $e) {
            throw new UnauthorizedHttpException('jwt-auth', 'Token has expired');
        } catch (TokenInvalidException $e) {
            throw new UnauthorizedHttpException('jwt-auth', 'Token is invalid');
        } catch (JWTException $e) {
            throw new UnauthorizedHttpException('jwt-auth', 'Token is absent');
        }
        return $next($request);
    }
}
