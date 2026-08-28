<?php

namespace Tests\Feature;

use App\Livewire\SupportCenter;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupportCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('support'))->assertRedirect(route('login'));
    }

    public function test_a_signed_in_user_can_submit_a_request(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(SupportCenter::class)
            ->set('formType', 'support')
            ->set('formSubject', 'Wochenplan zeigt Ferientage nicht an')
            ->set('formMessage', 'Ich habe pausiert, der Tag sieht aber normal aus.')
            ->call('submit');

        $request = SupportRequest::sole();
        $this->assertSame($user->id, $request->user_id);
        $this->assertSame('support', $request->type);
        $this->assertSame('open', $request->status);
    }

    public function test_subject_and_message_are_required(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(SupportCenter::class)
            ->set('formSubject', '')
            ->set('formMessage', '')
            ->call('submit')
            ->assertHasErrors(['formSubject', 'formMessage']);

        $this->assertSame(0, SupportRequest::count());
    }

    public function test_a_user_only_sees_their_own_requests(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        SupportRequest::create(['user_id' => $user->id, 'subject' => 'Meins', 'message' => 'X']);
        SupportRequest::create(['user_id' => $other->id, 'subject' => 'Fremd', 'message' => 'X']);

        $subjects = Livewire::actingAs($user)->test(SupportCenter::class)->instance()->myRequests()->pluck('subject');

        $this->assertSame(['Meins'], $subjects->all());
    }

    public function test_a_response_from_an_admin_is_visible_to_the_submitter(): void
    {
        $user = User::factory()->create();
        SupportRequest::create([
            'user_id' => $user->id, 'subject' => 'Frage', 'message' => 'X',
            'status' => 'resolved', 'response' => 'Behoben im nächsten Update.',
        ]);

        $request = Livewire::actingAs($user)->test(SupportCenter::class)->instance()->myRequests()->first();

        $this->assertSame('resolved', $request->status);
        $this->assertSame('Behoben im nächsten Update.', $request->response);
    }
}
