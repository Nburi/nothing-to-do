<?php

namespace App\Services;

use App\Models\ErrorOccurrence;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Stateless, like PomodoroCycle/TaskSuggestor. Backs the admin
 * "Fehler-Statistiken" page (App\Livewire\Admin\ErrorLog). See
 * CLAUDE.md, "Fehler-Statistiken", for why this exists and what it
 * deliberately does not do (no rate-limiting/dedup, no alerting).
 */
class ErrorStats
{
    /**
     * Called from bootstrap/app.php's exception render() hook, for every
     * HTML-rendered error page (never for a Livewire action or the JSON
     * API — those are filtered out by the caller before this is reached).
     *
     * Wrapped end-to-end: a failure to log an error must never become a
     * second, unhandled error on top of the one already being rendered —
     * especially since a database outage could be the very reason the
     * original request failed in the first place.
     */
    public static function record(Throwable $e, Request $request): void
    {
        try {
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            // 503 in this app is (near-)exclusively `php artisan down`'s own maintenance mode,
            // not a genuine failure — every request during a maintenance window would otherwise
            // flood this table with expected, non-actionable rows. It's also often exactly the
            // moment a deploy/migration makes the database itself least reliable to write to.
            if ($status < 400 || $status === 503) {
                return;
            }

            ErrorOccurrence::create([
                'status_code' => $status,
                'exception_class' => get_class($e),
                'message' => Str::limit((string) $e->getMessage(), 500, ''),
                'path' => Str::limit(Str::start($request->path(), '/'), 2048, ''),
                'method' => $request->method(),
                'user_id' => $request->user()?->id,
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            ]);
        } catch (Throwable $loggingFailure) {
            Log::debug('ErrorStats::record() could not write an occurrence.', [
                'exception' => $loggingFailure->getMessage(),
            ]);
        }
    }

    /** @return array<int, int> status code => count, every code that has ever occurred. */
    public static function countsByStatus(): array
    {
        return ErrorOccurrence::query()
            ->selectRaw('status_code, count(*) as total')
            ->groupBy('status_code')
            ->orderByDesc('total')
            ->pluck('total', 'status_code')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /** Local calendar day => count, for the last $days days (today included). */
    public static function countsByDay(int $days = 14): array
    {
        $since = now()->subDays($days - 1)->startOfDay();

        return ErrorOccurrence::query()
            ->where('created_at', '>=', $since)
            ->get(['created_at'])
            ->countBy(fn (ErrorOccurrence $row) => $row->created_at->toDateString())
            ->all();
    }

    /** @return Collection<int, object{path: string, total: int}> */
    public static function topPaths(int $limit = 10): Collection
    {
        return ErrorOccurrence::query()
            ->selectRaw('path, count(*) as total')
            ->groupBy('path')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, ErrorOccurrence> */
    public static function recent(int $limit = 50): Collection
    {
        return ErrorOccurrence::query()
            ->with('user')
            ->newestFirst()
            ->limit($limit)
            ->get();
    }
}
