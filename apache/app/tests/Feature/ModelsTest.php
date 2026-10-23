<?php

// -----------------------------------------------------------
//  [*] ModelsTest — the models against the real schema
//
//  Every Eloquent model must map onto a table mysql/init.sql
//  creates, the secrets on the models must stay out of their
//  serialised form — answers, password hashes and tokens end
//  up in the arrays the views and Redis receive — and the
//  small lookups the controllers lean on must keep their
//  answers: public id to id, challenge files, group
//  existence, and a player's solved share of a group.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Categories;
use App\Models\Challenges;
use App\Models\ChallengesCategories;
use App\Models\ChallengesFiles;
use App\Models\ChallengesHistory;
use App\Models\ChallengesSubmitted;
use App\Models\Emails;
use App\Models\Feedback;
use App\Models\Groups;
use App\Models\GroupsChallenges;
use App\Models\GroupsUsers;
use App\Models\Pages;
use App\Models\Partners;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;








// -----------------------------------------------------------
// ModelsTest
// -----------------------------------------------------------
//
// The database is the throwaway MySQL with the live schema,
// so a model that drifts from it fails here.
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class ModelsTest extends TestCase
{






    // -----------------------------------------------------------
    // test_every_model_maps_onto_an_existing_table
    // -----------------------------------------------------------
    //
    // A query through the model reaches its table, and the
    // tables init.sql seeds hold their seeds.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('models')]
    public function test_every_model_maps_onto_an_existing_table(string $model, int $seeded): void
    {
        $this->assertSame($seeded, $model::query()->count());
    }






    // -----------------------------------------------------------
    // models
    // -----------------------------------------------------------
    //
    // Every model, with the number of rows init.sql seeds into
    // its table.
    //
    // Used by:
    //   - test_every_model_maps_onto_an_existing_table (above)
    // -----------------------------------------------------------

    public static function models(): array
    {
        return [
            'Admin'                => [Admin::class, 1],
            'Categories'           => [Categories::class, 7],
            'Challenges'           => [Challenges::class, 0],
            'ChallengesCategories' => [ChallengesCategories::class, 0],
            'ChallengesFiles'      => [ChallengesFiles::class, 0],
            'ChallengesHistory'    => [ChallengesHistory::class, 0],
            'ChallengesSubmitted'  => [ChallengesSubmitted::class, 0],
            'Emails'               => [Emails::class, 0],
            'Feedback'             => [Feedback::class, 0],
            'Groups'               => [Groups::class, 0],
            'GroupsChallenges'     => [GroupsChallenges::class, 0],
            'GroupsUsers'          => [GroupsUsers::class, 0],
            'Pages'                => [Pages::class, 0],
            'Partners'             => [Partners::class, 0],
            'Settings'             => [Settings::class, 2],
            'User'                 => [User::class, 0],
        ];
    }






    // -----------------------------------------------------------
    // test_secrets_stay_out_of_serialised_models
    // -----------------------------------------------------------
    //
    // Turned into arrays or JSON, a player loses its password
    // hash and tokens, an admin its password hash and token, a
    // challenge its answer, and a page its alias.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_secrets_stay_out_of_serialised_models(): void
    {
        $player = $this->makePlayer(['remember_token' => 'remember-me', 'verify_token' => 'verify-me']);
        $admin = $this->makeAdmin();
        $challenge = $this->makeChallenge(['answer' => 'the_secret_flag_42']);
        $page = Pages::create(['name' => 'Rules', 'content' => '<p>x</p>', 'alias' => 'rules']);

        $this->assertEmpty(array_intersect(['password', 'remember_token', 'verify_token'], array_keys($player->fresh()->toArray())));
        $this->assertEmpty(array_intersect(['password', 'remember_token'], array_keys($admin->fresh()->toArray())));
        $this->assertArrayNotHasKey('answer', $challenge->fresh()->toArray());
        $this->assertStringNotContainsString('the_secret_flag_42', Challenges::all()->toJson());
        $this->assertArrayNotHasKey('alias', $page->fresh()->toArray());
    }






    // -----------------------------------------------------------
    // test_a_public_id_leads_to_the_challenge_and_its_partner
    // -----------------------------------------------------------
    //
    // The public-id lookups return the challenge's id and its
    // partner's id, and nothing for an unknown public id.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_public_id_leads_to_the_challenge_and_its_partner(): void
    {
        $challenge = $this->makeChallenge();

        $this->assertSame($challenge->id, Challenges::getIdByPublicId($challenge->public_id));
        $this->assertSame($challenge->partner_id, Challenges::getPartnerIdByPublicId($challenge->public_id));
        $this->assertNull(Challenges::getIdByPublicId((string) Str::uuid()));
        $this->assertNull(Challenges::getPartnerIdByPublicId((string) Str::uuid()));
    }






    // -----------------------------------------------------------
    // test_the_files_of_a_challenge_are_its_own
    // -----------------------------------------------------------
    //
    // getFilesById returns the challenge's links as arrays,
    // and none of another challenge's.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_files_of_a_challenge_are_its_own(): void
    {
        $challenge = $this->makeChallenge();
        ChallengesFiles::create(['challenge_id' => $challenge->id, 'name' => 'a.pcap', 'file_url' => 'https://files.test/a']);
        ChallengesFiles::create(['challenge_id' => $this->makeChallenge()->id, 'name' => 'b.pcap', 'file_url' => 'https://files.test/b']);

        $files = ChallengesFiles::getFilesById($challenge->id);

        $this->assertSame(['a.pcap'], array_column($files, 'name'));
        $this->assertSame('https://files.test/a', $files[0]['file_url']);
    }






    // -----------------------------------------------------------
    // test_group_existence_is_checked_by_public_id
    // -----------------------------------------------------------
    //
    // groupExists answers by public id, never by row id.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_group_existence_is_checked_by_public_id(): void
    {
        $group = $this->makeGroup();

        $this->assertTrue(Groups::groupExists($group->public_id));
        $this->assertFalse(Groups::groupExists((string) Str::uuid()));
        $this->assertFalse(Groups::groupExists((string) $group->id));
    }






    // -----------------------------------------------------------
    // test_the_solved_share_of_a_group_is_a_rounded_percentage
    // -----------------------------------------------------------
    //
    // The share is the points the signed-in player earned on
    // the group's challenges over the points they are worth,
    // as a whole percentage: 0 with nothing solved or nothing
    // to solve, rounded in between, 100 when all is solved.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_solved_share_of_a_group_is_a_rounded_percentage(): void
    {
        $player = $this->makeSsoPlayer();
        $this->signIn($player);
        $empty = $this->makeGroup();
        $group = $this->makeGroup();
        $challenges = [$this->makeChallenge(['points' => 10]), $this->makeChallenge(['points' => 10]), $this->makeChallenge(['points' => 10])];
        foreach ($challenges as $challenge) {
            $this->assignToGroup($group, $challenge);
        }

        $this->assertEquals(0, Groups::groupPointsPercent($empty->id));
        $this->assertEquals(0, Groups::groupPointsPercent($group->id));
        $this->recordSolve($player, $challenges[0]);
        $this->assertEquals(33, Groups::groupPointsPercent($group->id));
        $this->recordSolve($player, $challenges[1]);
        $this->assertEquals(67, Groups::groupPointsPercent($group->id));
        $this->recordSolve($player, $challenges[2]);
        $this->assertEquals(100, Groups::groupPointsPercent($group->id));
    }






    // -----------------------------------------------------------
    // test_a_player_is_named_by_username
    // -----------------------------------------------------------
    //
    // getUsername returns the username — its one caller,
    // submit_answer, builds the history line that
    // ChallengesHistory then drops.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_player_is_named_by_username(): void
    {
        $this->assertSame('Jonas', $this->makePlayer(['username' => 'Jonas'])->getUsername());
    }
}
