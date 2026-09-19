<?php

namespace Tests\Feature;

use App\Livewire\Admin\SupportQueue;
use App\Livewire\Help;
use App\Livewire\SupportCenter;
use App\Models\PushSubscription;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

class SupportPushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function subscribed(User $user): User
    {
        PushSubscription::storeFor($user, 'https://push.example.com/'.$user->id, 'key', 'token', 'UA');

        return $user;
    }

    public function test_every_subscribed_admin_is_notified_of_a_new_request(): void
    {
        $adminA = $this->subscribed(User::factory()->create(['is_admin' => true]));
        $adminB = $this->subscribed(User::factory()->create(['is_admin' => true]));
        User::factory()->create(['is_admin' => true]); // admin without a device: skipped
        $this->subscribed(User::factory()->create()); // regular user: never notified
        $submitter = User::factory()->create(['name' => 'Lena']);

        $notified = [];
        $this->mock(PushNotifier::class, function (MockInterface $mock) use (&$notified) {
            $mock->shouldReceive('notify')->twice()->andReturnUsing(function (User $user, array $payload) use (&$notified) {
                $notified[$user->id] = $payload;
            });
        });

        Livewire::actingAs($submitter)->test(SupportCenter::class)
            ->set('formType', 'support')
            ->set('formSubject', 'Wochenplan kaputt')
            ->set('formMessage', 'Bitte schauen.')
            ->call('submit');

        $this->assertSame([$adminA->id, $adminB->id], collect(array_keys($notified))->sort()->values()->all());
        $payload = $notified[$adminA->id];
        $this->assertSame('Neue Support-Anfrage', $payload['title']);
        $this->assertSame('Lena: Wochenplan kaputt', $payload['body']);
        $this->assertSame(route('admin.support', absolute: false), $payload['url']);
    }

    public function test_feedback_from_the_help_center_also_notifies_admins(): void
    {
        $this->subscribed(User::factory()->create(['is_admin' => true]));

        $this->mock(PushNotifier::class, function (MockInterface $mock) {
            $mock->shouldReceive('notify')->once()->withArgs(fn ($user, $payload) => $payload['title'] === 'Neues Feedback');
        });

        $submitter = User::factory()->create();
        $submitter->supportRequests()->create([
            'type' => 'feedback', 'subject' => 'Feedback zu: X', 'message' => 'Nein', 'status' => 'open',
        ]);
    }

    public function test_an_admin_submitting_their_own_request_is_not_pushed_about_it(): void
    {
        $admin = $this->subscribed(User::factory()->create(['is_admin' => true]));

        $this->mock(PushNotifier::class, fn (MockInterface $mock) => $mock->shouldNotReceive('notify'));

        $admin->supportRequests()->create([
            'type' => 'support', 'subject' => 'Selbst', 'message' => 'x', 'status' => 'open',
        ]);
    }

    public function test_the_submitter_is_notified_when_their_request_is_answered(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $submitter = $this->subscribed(User::factory()->create());
        $request = $submitter->supportRequests()->create([
            'type' => 'support', 'subject' => 'Frage', 'message' => 'x', 'status' => 'open',
        ]);

        $this->mock(PushNotifier::class, function (MockInterface $mock) use ($submitter) {
            $mock->shouldReceive('notify')->once()->withArgs(function (User $user, array $payload) use ($submitter) {
                return $user->is($submitter)
                    && $payload['title'] === 'Antwort auf deine Anfrage'
                    && str_contains($payload['body'], 'Hier die Lösung')
                    && $payload['url'] === route('support', absolute: false);
            });
        });

        Livewire::actingAs($admin)->test(SupportQueue::class)
            ->call('startResponding', $request->id)
            ->set('responseDraft', 'Hier die Lösung')
            ->call('saveResponse');
    }

    public function test_changing_only_the_status_or_clearing_the_answer_sends_no_push(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $submitter = $this->subscribed(User::factory()->create());
        $request = $submitter->supportRequests()->create([
            'type' => 'support', 'subject' => 'Frage', 'message' => 'x', 'status' => 'open', 'response' => 'Alt', 'responded_by' => $admin->id,
        ]);

        $this->mock(PushNotifier::class, fn (MockInterface $mock) => $mock->shouldNotReceive('notify'));

        Livewire::actingAs($admin)->test(SupportQueue::class)
            ->call('setStatus', $request->id, 'resolved')
            ->call('startResponding', $request->id)
            ->set('responseDraft', '')
            ->call('saveResponse');
    }

    public function test_a_failing_push_never_breaks_saving_the_request(): void
    {
        $this->subscribed(User::factory()->create(['is_admin' => true]));

        $this->mock(PushNotifier::class, fn (MockInterface $mock) => $mock->shouldReceive('notify')->andThrow(new \RuntimeException('push down')));

        $submitter = User::factory()->create();

        Livewire::actingAs($submitter)->test(SupportCenter::class)
            ->set('formSubject', 'Betreff')
            ->set('formMessage', 'Text')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame(1, SupportRequest::count());
    }

    public function test_no_push_infrastructure_is_touched_when_nobody_has_a_device(): void
    {
        User::factory()->create(['is_admin' => true]);

        $this->mock(PushNotifier::class, fn (MockInterface $mock) => $mock->shouldNotReceive('notify'));

        User::factory()->create()->supportRequests()->create([
            'type' => 'feedback', 'subject' => 'S', 'message' => 'M', 'status' => 'open',
        ]);

        $this->assertSame(1, SupportRequest::count());
    }
}
