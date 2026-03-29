<?php

namespace App\Http\Middleware;

use Closure;
use App\Http\Controllers\Users\SettingsController;

class UpdateRankingTable
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        SettingsController::update_rank_table();

        return $next($request);
    }
}