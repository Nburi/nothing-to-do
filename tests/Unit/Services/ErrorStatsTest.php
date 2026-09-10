<?php

namespace Tests\Unit\Services;

use App\Models\ErrorOccurrence;
use App\Services\ErrorStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ErrorStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_writes_the_status_code_from_an_http_exception(): void
    {
        $request = Request::create('/broken-link');

        ErrorStats::record(new NotFoundHttpException('no route'), $request);

        $occurrence = ErrorOccurrence::sole();
        $this->assertSame(404, $occurrence->status_code);
        $this->assertSame('/broken-link', $occurrence->path);
    }

    public function test_record_falls_back_to_500_for_a_plain_exception(): void
    {
        $request = Request::create('/anything');

        ErrorStats::record(new \RuntimeException('boom'), $request);

        $this->assertSame(500, ErrorOccurrence::sole()->status_code);
    }

    /**
     * A 503 is (near-)exclusively `php artisan down`'s own maintenance mode in this app —
     * expected, not a bug — so it must never be written, or a maintenance window would flood
     * the admin stats page with rows there's nothing to act on.
     */
    public function test_record_skips_503(): void
    {
        ErrorStats::record(new HttpException(503, 'Service Unavailable'), Request::create('/anything'));

        $this->assertSame(0, ErrorOccurrence::count());
    }

    /**
     * The one case this must never do: turn a logging failure into a second,
     * unhandled exception on top of the one already being rendered — see
     * CLAUDE.md, "Fehler-Statistiken".
     */
    public function test_record_never_throws_even_if_writing_the_occurrence_fails(): void
    {
        Schema::drop('error_occurrences');
        Log::shouldReceive('debug')->once();

        ErrorStats::record(new NotFoundHttpException('no route'), Request::create('/broken-link'));

        $this->assertTrue(true);
    }
}
