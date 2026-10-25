<?php

// -----------------------------------------------------------
//  [*] FeedbackTest — the feedback button on player pages
//
//  The feedback link in the corner of every player page opens
//  a dialog loaded from /user/dialog/feedback; its form posts
//  the text to /user/feedback, which stores it with its
//  author and time. Nobody reads it in the app itself — the
//  rows are read through dbgate.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Play;

use App\Models\Feedback;
use Tests\TestCase;








// -----------------------------------------------------------
// FeedbackTest
// -----------------------------------------------------------
//
// Each test signs in a local player first.
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

final class FeedbackTest extends TestCase
{






    // -----------------------------------------------------------
    // test_the_dialog_is_a_form_posting_the_feedback
    // -----------------------------------------------------------
    //
    // The dialog is a bare form — no layout around it, as the
    // page script drops it into a modal — with a text area of
    // up to 5000 characters, the most the route takes, posting
    // to the feedback route.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_the_dialog_is_a_form_posting_the_feedback(): void
    {
        $response = $this->signIn($this->makePlayer())->get('/user/dialog/feedback');

        $response->assertOk();
        $response->assertSee('action="https://lab.knf.vu.lt/user/feedback"', false);
        $response->assertSee('<textarea class="form-control" required="required" maxlength="5000" name="feedback"', false);
        $response->assertDontSee('<html', false);
    }






    // -----------------------------------------------------------
    // test_feedback_is_stored_with_its_author_and_time
    // -----------------------------------------------------------
    //
    // The text is stored with the player's id and the current
    // time, and the player returns to the page the dialog was
    // opened on with a thank-you. The test runs in UTC
    // (inUtc), as a local time the app stored, read back the
    // night the clocks go back, may be an hour off.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_feedback_is_stored_with_its_author_and_time(): void
    {
        $this->inUtc();
        $player = $this->makePlayer();

        $response = $this->signIn($player)
            ->from('/user/leaderboard')
            ->post('/user/feedback', ['feedback' => 'The steganography task was great.']);

        $response->assertRedirect('/user/leaderboard');
        $response->assertSessionHas('success', 'Thank you for your feedback!');
        $feedback = Feedback::where('user_id', $player->id)->firstOrFail();
        $this->assertSame('The steganography task was great.', $feedback->feedback);
        $this->assertEqualsWithDelta(time(), strtotime($feedback->created), 60);
    }






    // -----------------------------------------------------------
    // test_each_submission_is_kept
    // -----------------------------------------------------------
    //
    // Feedback is never merged or overwritten: every post is a
    // row of its own.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_each_submission_is_kept(): void
    {
        $player = $this->makePlayer();
        $this->signIn($player);

        $this->post('/user/feedback', ['feedback' => 'First thought.']);
        $this->post('/user/feedback', ['feedback' => 'Second thought.']);

        $this->assertSame(['First thought.', 'Second thought.'], Feedback::where('user_id', $player->id)->orderBy('id')->pluck('feedback')->all());
    }






    // -----------------------------------------------------------
    // test_feedback_sent_as_a_list_is_refused
    // -----------------------------------------------------------
    //
    // Feedback posted as a list, which only a hand-made request
    // sends, goes back with the field's error rather than a
    // server error, and nothing is stored.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_feedback_sent_as_a_list_is_refused(): void
    {
        $player = $this->makePlayer();

        $response = $this->signIn($player)
            ->from('/user/leaderboard')
            ->post('/user/feedback', ['feedback' => ['first', 'second']]);

        $response->assertRedirect('/user/leaderboard');
        $response->assertSessionHasErrors('feedback');
        $this->assertSame(0, Feedback::where('user_id', $player->id)->count());
    }






    // -----------------------------------------------------------
    // test_feedback_in_the_address_never_replaces_the_forms
    // -----------------------------------------------------------
    //
    // Feedback put into the form's address as a list is passed
    // over: the text stored is the form's, the one the rule
    // checked, and the player goes back as after any feedback.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_feedback_in_the_address_never_replaces_the_forms(): void
    {
        $player = $this->makePlayer();

        $this->signIn($player)
            ->from('/user/leaderboard')
            ->post('/user/feedback?feedback[]=x', ['feedback' => 'Great lab'])
            ->assertRedirect('/user/leaderboard');

        $this->assertSame('Great lab', Feedback::where('user_id', $player->id)->value('feedback'));
    }






    // -----------------------------------------------------------
    // test_feedback_has_a_length_limit
    // -----------------------------------------------------------
    //
    // Feedback of 5000 characters is stored; one character more
    // goes back with the error and stores nothing, so no
    // player can fill the database with megabyte messages.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_feedback_has_a_length_limit(): void
    {
        $player = $this->makePlayer();
        $this->signIn($player)->from('/user/dashboard');

        $this->post('/user/feedback', ['feedback' => str_repeat('a', 5000)])->assertSessionHasNoErrors();
        $this->post('/user/feedback', ['feedback' => str_repeat('a', 5001)])->assertSessionHasErrors('feedback');
        $this->post('/user/feedback', ['feedback' => str_repeat('Long feedback. ', 70000)])->assertSessionHasErrors('feedback');

        $this->assertSame(1, Feedback::where('user_id', $player->id)->count());
    }






    // -----------------------------------------------------------
    // test_a_line_break_counts_once_as_the_box_counts_it
    // -----------------------------------------------------------
    //
    // The box counts a line break as one character and lets
    // 5000 through, but the form sends each break as two: 5000
    // characters with 40 line breaks, which the form sends as
    // 5040, are stored with their breaks as one, rather than
    // refused and lost; a break more is too long again.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function test_a_line_break_counts_once_as_the_box_counts_it(): void
    {
        $player = $this->makePlayer();
        $this->signIn($player)->from('/user/dashboard');
        $text = str_repeat(str_repeat('a', 99) . "\n", 40) . str_repeat('a', 1000);

        $this->post('/user/feedback', ['feedback' => str_replace("\n", "\r\n", $text)])->assertSessionHasNoErrors();
        $this->post('/user/feedback', ['feedback' => str_replace("\n", "\r\n", $text) . "\r\nb"])->assertSessionHasErrors('feedback');

        $this->assertSame(5000, mb_strlen($text));
        $this->assertSame([$text], Feedback::where('user_id', $player->id)->pluck('feedback')->all());
    }
}
