<?php

namespace Tests\Feature;

use App\Models\ErrorOccurrence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneErrorOccurrencesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_occurrences_older_than_60_days_and_keeps_the_rest(): void
    {
        $old = ErrorOccurrence::create(['status_code' => 404, 'path' => '/old', 'method' => 'GET']);
        $old->forceFill(['created_at' => now()->subDays(61)])->save();

        $recent = ErrorOccurrence::create(['status_code' => 404, 'path' => '/recent', 'method' => 'GET']);
        $recent->forceFill(['created_at' => now()->subDays(59)])->save();

        $this->artisan('app:prune-error-occurrences')->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }
}
