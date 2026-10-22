<?php

// -----------------------------------------------------------
//  [*] CreatesApplication — boots the app for one test
//
//  The app an in-process test talks to, built from
//  bootstrap/app.php the way public/index.php builds it and
//  bootstrapped through the console kernel, which loads the
//  configuration and registers every service provider.
//
//  Used by:
//    - TestCase (this folder)
// -----------------------------------------------------------


namespace Tests;

use Illuminate\Contracts\Console\Kernel;








// -----------------------------------------------------------
// CreatesApplication
// -----------------------------------------------------------
//
// Laravel's test case asks for the app through
// createApplication before every test and throws it away
// after, so no test sees another test's bindings, fakes or
// resolved singletons.
//
// Used by:
//   - TestCase (this folder)
// -----------------------------------------------------------

trait CreatesApplication
{






    // -----------------------------------------------------------
    // createApplication
    // -----------------------------------------------------------
    //
    // A freshly built and bootstrapped app.
    //
    // Used by:
    //   - Illuminate\Foundation\Testing\TestCase — before every
    //     test, through refreshApplication
    // -----------------------------------------------------------

    public function createApplication()
    {
        $app = require __DIR__ . '/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }
}
