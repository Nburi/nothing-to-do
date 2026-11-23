<?php

namespace Tests\Feature\Api;

use App\Models\Evening;
use App\Services\PushNotifier;

/**
 * A71 (R2): the location Shortcut can fire again when he leaves and comes back or only passes by.
 * Whatever happens, the evening gets one nudge, shared with the time-based nudge of step 3.3.
 */
class ArrivalOncePerEveningTest extends ArrivalNudgeTestCase
{
    public function test_the_first_arrival_of_the_evening_sends_the_nudge_and_marks_the_evening(): void
    {
        $this->at('18:10');

        $this->arrive()->assertOk()->assertJson(['sent' => true, 'delivered' => 1, 'reason' => null]);

        $this->assertSame(1, $this->pushes());
        $this->assertSame('arrival', $this->sent[0]['via']);
        $this->assertSame('/board?reset=1', $this->sent[0]['payload']['url'], 'the push opens the calm reset');
        $this->assertNotNull(Evening::for($this->user, '2026-11-09')->nudge_sent_at);
    }

    public function test_arriving_again_the_same_evening_sends_nothing(): void
    {
        $this->at('18:10');
        $this->arrive()->assertJson(['sent' => true]);

        $this->at('18:50'); // went to the shop and came back
        $this->arrive()->assertOk()->assertJson(['sent' => false, 'delivered' => 0, 'reason' => 'already_sent']);

        $this->at('21:15'); // walked past the house
        $this->arrive()->assertOk()->assertJson(['sent' => false, 'reason' => 'already_sent']);

        $this->assertSame(1, $this->pushes());
    }

    public function test_arrivals_that_come_at_once_send_one_push(): void
    {
        $this->at('18:10');

        $this->arrive();
        $this->arrive();
        $this->arrive();

        $this->assertSame(1, $this->pushes());
    }

    public function test_an_arrival_that_loses_the_race_for_the_evening_sends_nothing(): void
    {
        // Both callers passed the check. Between the check and the claim the other one (here: the
        // scheduler) takes the evening; resolving the push service is the moment in between.
        $push = $this->app->make(PushNotifier::class);
        $this->app->bind(PushNotifier::class, function () use ($push) {
            Evening::forWrite($this->user, '2026-11-09')->update(['nudge_sent_at' => now()]);

            return $push;
        });
        $this->at('18:10');

        $this->arrive()->assertOk()->assertJson(['sent' => false, 'reason' => 'already_sent']);

        $this->assertSame(0, $this->pushes(), 'only the caller that wins the atomic claim sends');
    }

    public function test_the_time_based_nudge_stays_quiet_after_an_arrival(): void
    {
        $this->at('18:00');
        $this->arrive()->assertJson(['sent' => true]);

        $this->at('18:30');
        $this->tick();
        $this->at('19:00');
        $this->tick();

        $this->assertSame(1, $this->pushes());
        $this->assertSame('arrival', $this->sent[0]['via']);
    }

    public function test_an_arrival_stays_quiet_after_the_time_based_nudge(): void
    {
        $this->at('18:30');
        $this->tick();

        $this->at('19:10'); // he was still out at 18:30
        $this->arrive()->assertOk()->assertJson(['sent' => false, 'reason' => 'already_sent']);

        $this->assertSame(1, $this->pushes());
        $this->assertSame('nudge', $this->sent[0]['via']);
    }

    public function test_the_next_evening_gets_its_own_arrival_nudge(): void
    {
        $this->at('18:10');
        $this->arrive()->assertJson(['sent' => true]);

        $this->at('18:10', '2026-11-10');
        $this->arrive()->assertJson(['sent' => true]);

        $this->assertSame(2, $this->pushes());
        $this->assertNotNull(Evening::for($this->user, '2026-11-10')->nudge_sent_at);
    }
}
