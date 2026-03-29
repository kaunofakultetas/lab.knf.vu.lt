<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class UnauthorizedMiddleware
{
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