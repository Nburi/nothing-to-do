<?php

namespace Tests\Feature;

use App\Livewire\Admin\SupportQueue;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSupportQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_admin_cannot_open_the_queue(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route('admin.support'))->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.support'))->assertRedirect(route('login'));
    }

    public function test_an_admin_sees_requests_from_every_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        SupportRequest::create(['user_id' => $userA->id, 'subject' => 'A', 'message' => 'X']);
        SupportRequest::create(['user_id' => $userB->id, 'subject' => 'B', 'message' => 'X']);

        $subjects = Livewire::actingAs($admin)->test(SupportQueue::class)->instance()->requests()->pluck('subject');

        $this->assertCount(2, $subjects);
    }

    public function test_status_filter_narrows_the_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        SupportRequest::create(['user_id' => $user->id, 'subject' => 'Offen', 'message' => 'X', 'status' => 'open']);
        SupportRequest::create(['user_id' => $user->id, 'subject' => 'Erledigt', 'message' => 'X', 'status' => 'resolved']);

        $subjects = Livewire::actingAs($admin)->test(SupportQueue::class)
            ->call('setStatusFilter', 'resolved')
            ->instance()->requests()->pluck('subject');

        $this->assertSame(['Erledigt'], $subjects->all());
    }

    public function test_an_admin_can_change_the_status(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $request = SupportRequest::create(['user_id' => $user->id, 'subject' => 'X', 'message' => 'X']);

        Livewire::actingAs($admin)->test(SupportQueue::class)->call('setStatus', $request->id, 'in_progress');

        $this->assertSame('in_progress', $request->fresh()->status);
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $request = SupportRequest::create(['user_id' => $user->id, 'subject' => 'X', 'message' => 'X']);

        Livewire::actingAs($admin)->test(SupportQueue::class)->call('setStatus', $request->id, 'not-a-real-status');

        $this->assertSame('open', $request->fresh()->status);
    }

    public function test_an_admin_can_save_a_response(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $request = SupportRequest::create(['user_id' => $user->id, 'subject' => 'X', 'message' => 'X']);

        Livewire::actingAs($admin)->test(SupportQueue::class)
            ->call('startResponding', $request->id)
            ->set('responseDraft', 'Kümmern uns darum.')
            ->call('saveResponse');

        $fresh = $request->fresh();
        $this->assertSame('Kümmern uns darum.', $fresh->response);
        $this->assertSame($admin->id, $fresh->responded_by);
    }

    public function test_status_counts_reflect_the_full_queue_regardless_of_filter(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        SupportRequest::create(['user_id' => $user->id, 'subject' => 'A', 'message' => 'X', 'status' => 'open']);
        SupportRequest::create(['user_id' => $user->id, 'subject' => 'B', 'message' => 'X', 'status' => 'resolved']);

        $counts = Livewire::actingAs($admin)->test(SupportQueue::class)->instance()->statusCounts();

        $this->assertSame(2, $counts['all']);
        $this->assertSame(1, $counts['open']);
        $this->assertSame(1, $counts['resolved']);
        $this->assertSame(0, $counts['closed']);
    }
}
