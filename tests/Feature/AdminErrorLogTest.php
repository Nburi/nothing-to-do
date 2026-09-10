<?php

namespace Tests\Feature;

use App\Livewire\Admin\ErrorLog;
use App\Models\ErrorOccurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminErrorLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_admin_cannot_open_the_page(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route('admin.errors'))->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.errors'))->assertRedirect(route('login'));
    }

    public function test_an_admin_sees_status_counts_across_every_occurrence(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        ErrorOccurrence::create(['status_code' => 404, 'path' => '/a', 'method' => 'GET']);
        ErrorOccurrence::create(['status_code' => 404, 'path' => '/b', 'method' => 'GET']);
        ErrorOccurrence::create(['status_code' => 500, 'path' => '/c', 'method' => 'GET']);

        $counts = Livewire::actingAs($admin)->test(ErrorLog::class)->instance()->statusCounts();

        $this->assertSame(2, $counts[404]);
        $this->assertSame(1, $counts[500]);
    }

    public function test_status_filter_narrows_the_recent_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        ErrorOccurrence::create(['status_code' => 404, 'path' => '/a', 'method' => 'GET']);
        ErrorOccurrence::create(['status_code' => 500, 'path' => '/b', 'method' => 'GET']);

        $paths = Livewire::actingAs($admin)->test(ErrorLog::class)
            ->call('setStatusFilter', 500)
            ->instance()->occurrences()->pluck('path');

        $this->assertSame(['/b'], $paths->all());
    }

    public function test_top_paths_are_ordered_by_frequency(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        ErrorOccurrence::create(['status_code' => 404, 'path' => '/rare', 'method' => 'GET']);
        ErrorOccurrence::create(['status_code' => 404, 'path' => '/common', 'method' => 'GET']);
        ErrorOccurrence::create(['status_code' => 404, 'path' => '/common', 'method' => 'GET']);

        $topPath = Livewire::actingAs($admin)->test(ErrorLog::class)->instance()->topPaths()->first();

        $this->assertSame('/common', $topPath->path);
        $this->assertSame(2, $topPath->total);
    }
}
