<?php

namespace Tests\Feature\Api;

use App\Models\Evening;
use App\Services\Planning\EveningNudge;
use App\Services\Planning\NudgeHold;
use App\Services\PushNotifier;
use Illuminate\Support\Facades\Log;
use Mockery;

/**
 * A73 (R2): the Shortcut's answer says what happened, so a silent arrival is never a mystery
 * (the Shortcut shows it with "Ergebnis anzeigen"), and a server without push keys answers
 * calmly instead of failing.
 */
class ArrivalAnswerTest extends ArrivalNudgeTestCase
{
    public function test_a_held_back_arrival_says_why_in_plain_german_and_is_logged(): void
    {
        Log::spy();
        $this->at('18:10', '2026-11-14'); // Saturday: no nudge time

        $this->arrive()->assertOk()
            ->assertJsonPath('reason', 'no_time')
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'keine Zeit zum Abschalten'));

        Log::shouldHaveReceived('info')->with('Arrival ping held back', Mockery::on(fn (array $c) => $c['reason'] === 'no_time'))->once();
    }

    public function test_every_reason_has_its_own_sentence(): void
    {
        $messages = array_map(fn (NudgeHold $hold) => $hold->message(), NudgeHold::cases());

        $this->assertCount(count(NudgeHold::cases()), array_unique($messages));
        $this->assertStringContainsString('2 Stunden', NudgeHold::TooEarly->message(), 'derived from EveningNudge::ARRIVAL_EARLY_MINUTES');
        $this->assertSame(120, EveningNudge::ARRIVAL_EARLY_MINUTES);
    }

    public function test_a_server_without_push_keys_answers_calmly_and_keeps_the_evening_open(): void
    {
        $this->app->bind(PushNotifier::class, fn () => throw new \ErrorException('[VAPID] Public key should be 65 bytes long when decoded.'));
        $this->at('18:10');

        $this->arrive()->assertStatus(503)
            ->assertJson(['sent' => false, 'delivered' => 0, 'reason' => 'push_unavailable'])
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'nicht eingerichtet') && ! str_contains($m, 'VAPID'));
        $this->assertNull(Evening::for($this->user, '2026-11-09')?->nudge_sent_at);

        // The operator adds the keys; arriving again the same evening works.
        $this->fakePush();
        $this->arrive()->assertOk()->assertJson(['sent' => true]);
    }

    public function test_a_push_service_that_fails_is_answered_calmly_and_not_retried_all_evening(): void
    {
        $this->app->instance(PushNotifier::class, Mockery::mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('sendDebug')->once()->andThrow(new \RuntimeException('push service down'));
        }));
        $this->at('18:10');

        $this->arrive()->assertOk()
            ->assertJson(['sent' => true, 'delivered' => 0])
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'nicht zugestellt'));

        $this->at('18:40');
        $this->arrive()->assertJson(['sent' => false, 'reason' => 'already_sent']);
    }
}
