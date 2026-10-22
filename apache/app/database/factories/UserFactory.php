<?php

// -----------------------------------------------------------
//  [*] UserFactory — the skeleton's player factory
//
//  Laravel's skeleton factory for App\Models\User, never
//  adapted to cyber_users: it fills a name column the table
//  does not have, so making a user with it fails. Nothing
//  calls it — the seeder seeds nothing, and the tests build
//  their players themselves (tests/TestCase.php). Dead
//  code, kept as found.
//
//  Used by:
//    - nothing at the moment — User's HasFactory would find
//      it
// -----------------------------------------------------------


namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;








// -----------------------------------------------------------
// UserFactory
// -----------------------------------------------------------
//
//   definition — a verified user
//   unverified — the same, not verified
//
// Used by:
//   - nothing at the moment
// -----------------------------------------------------------

class UserFactory extends Factory
{






    // -----------------------------------------------------------
    // definition
    // -----------------------------------------------------------
    //
    // A fake name and unique e-mail, verified now, with the
    // skeleton's fixed password hash and a random remember
    // token.
    //
    // Used by:
    //   - Laravel's factory, when a user is made — never
    // -----------------------------------------------------------

    public function definition()
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            // The bcrypt hash of the word password
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'remember_token' => Str::random(10),
        ];
    }






    // -----------------------------------------------------------
    // unverified
    // -----------------------------------------------------------
    //
    // The same user with no verification date.
    //
    // Used by:
    //   - nothing at the moment
    // -----------------------------------------------------------

    public function unverified()
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
