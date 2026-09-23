<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProjectUrgencyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function projectDueIn(int $days): Project
    {
        Carbon::setTestNow('2026-09-23 12:00:00');
        $user = User::factory()->create(['timezone_offset' => 0, 'timezone_auto_dst' => false]);
        $this->actingAs($user);

        return Project::factory()->for($user)->create(['deadline' => Carbon::parse('2026-09-23')->addDays($days)->toDateString()]);
    }

    public function test_a_deadline_within_four_days_is_urgent(): void
    {
        foreach ([0, 1, 4] as $days) {
            $this->assertTrue($this->projectDueIn($days)->isUrgent(), "due in {$days} days");
        }
    }

    public function test_a_deadline_further_away_is_not_urgent(): void
    {
        foreach ([5, 30, 200] as $days) {
            $this->assertFalse($this->projectDueIn($days)->isUrgent(), "due in {$days} days");
        }
    }

    public function test_an_overdue_project_is_not_reported_as_urgent(): void
    {
        $this->assertFalse($this->projectDueIn(-2)->isUrgent());
    }

    public function test_a_project_without_a_deadline_is_not_urgent(): void
    {
        $this->projectDueIn(1);
        $project = Project::factory()->for(auth()->user())->create(['deadline' => null]);

        $this->assertFalse($project->isUrgent());
    }
}
