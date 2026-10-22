<?php

// -----------------------------------------------------------
//  [*] UpdateRankingTable — refreshes the cached leaderboard
//
//  Before every request, copies the cyber_users_ranks view —
//  every player with a solve, ranked by points — into Redis
//  as ranking_table, keyed by user id. The player sidebar
//  and both leaderboards read that copy, so it is never
//  older than the request reading it; the price is one
//  ranking query on every request Laravel serves, a guest's
//  included.
//
//  Used by:
//    - app/Http/Kernel.php — the global stack
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Closure;
use App\Http\Controllers\Users\SettingsController;








// -----------------------------------------------------------
// UpdateRankingTable
// -----------------------------------------------------------
//
// One method, handle.
//
// Used by:
//   - app/Http/Kernel.php — the global stack
// -----------------------------------------------------------

class UpdateRankingTable
{






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // Refreshes the copy through
    // Users\SettingsController::update_rank_table, then passes
    // the request on.
    //
    // Used by:
    //   - the global stack — every request
    // -----------------------------------------------------------

    public function handle($request, Closure $next)
    {
        SettingsController::update_rank_table();

        return $next($request);
    }
}
