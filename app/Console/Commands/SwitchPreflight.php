<?php

namespace App\Console\Commands;

use App\Support\Release\SwitchPreflight as Preflight;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Refactor step 5.4: the data checks to run before (and after) the switch. Read-only: it never
 * writes, repairs or migrates anything. Exits 1 when something blocks the switch; WARN lines are
 * decisions or things to look at (docs/RELEASE_RUNBOOK.md), NOTE lines say what the switch will do.
 */
class SwitchPreflight extends Command
{
    protected $signature = 'app:switch-preflight
        {--important-limit=10 : warn about users with more open "Wichtig" marks than this}
        {--no-counts : leave out the row counts per table}';

    protected $description = 'Read-only data checks before the switch to the new app: row counts, orphans, open decisions (exit 1 = blocked)';

    public function handle(Preflight $preflight): int
    {
        $limit = $this->option('important-limit');
        if (! ctype_digit((string) $limit)) {
            $this->error('--important-limit takes a number.');

            return self::INVALID;
        }

        $this->line('Database: <comment>'.DB::connection()->getDatabaseName().'</comment> ('.DB::connection()->getDriverName().') · read-only');

        $report = $preflight->run((int) $limit);

        if (! $this->option('no-counts')) {
            $this->newLine();
            $this->table(['Table', 'Rows'], collect($report['counts'])->map(fn ($n, $t) => [$t, $n])->values());
        }

        $this->newLine();
        $clean = 0;
        foreach ($report['checks'] as $check) {
            if ($check['count'] === 0 && ! $this->output->isVerbose()) {
                $clean++;

                continue;
            }
            $this->line($this->format($check));
        }
        if ($clean > 0) {
            $this->line(sprintf('  <info>ok</info>     %d more checks found nothing (-v lists them)', $clean));
        }

        $blocked = Preflight::blocks($report);
        $this->newLine();
        $blocked
            ? $this->error('BLOCKED: fix the BLOCK lines above before the switch.')
            : $this->info('Nothing blocks the switch. Read the WARN and NOTE lines.');

        return $blocked ? self::FAILURE : self::SUCCESS;
    }

    /** @param array{key: string, level: string, title: string, count: int, detail: string} $check */
    private function format(array $check): string
    {
        if ($check['count'] === 0) {
            return sprintf('  <info>ok</info>     %s', $check['title']);
        }

        $detail = $check['detail'] === '' ? '' : " ({$check['detail']})";

        return match ($check['level']) {
            Preflight::BLOCK => sprintf('  <error> BLOCK </error> %d × %s%s', $check['count'], $check['title'], $detail),
            Preflight::WARN => sprintf('  <comment>WARN</comment>   %d × %s%s', $check['count'], $check['title'], $detail),
            default => sprintf('  <fg=cyan>NOTE</>   %d × %s%s', $check['count'], $check['title'], $detail),
        };
    }
}
