<?php

namespace App\Console\Commands;

use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Services\EventDayGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * E.D.1 — controlled recovery for events that have zero event_days rows.
 *
 * Default is dry-run. Persist changes only with --apply after explicit approval.
 */
class BackfillMissingEventDaysCommand extends Command
{
    protected $signature = 'event-days:backfill-missing
                            {--apply : Persist generated event days (default is dry-run)}
                            {--chunk=100 : Number of events to scan per chunk}';

    protected $description = 'E.D.1: Dry-run (default) or apply missing event_days materialization for zero-day events';

    public function __construct(
        private readonly EventDayGenerator $generator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');
        $apply = (bool) $this->option('apply');
        $chunk = max(1, (int) $this->option('chunk'));

        $this->info('Resolved database: '.$database);
        $this->info($apply ? 'Mode: APPLY' : 'Mode: DRY-RUN (no writes)');

        $scanned = 0;
        $eligible = 0;
        $generated = 0;
        $skipped = 0;
        $failed = 0;
        $eligibleIds = [];
        $skippedRows = [];
        $failedRows = [];
        $generatedIds = [];

        CarbootEvent::query()
            ->orderBy('id')
            ->chunkById($chunk, function ($events) use (
                $apply,
                &$scanned,
                &$eligible,
                &$generated,
                &$skipped,
                &$failed,
                &$eligibleIds,
                &$skippedRows,
                &$failedRows,
                &$generatedIds,
            ) {
                foreach ($events as $event) {
                    $scanned++;
                    $dayCount = EventDay::query()->forEvent($event->id)->count();

                    if ($dayCount > 0) {
                        $skipped++;
                        $skippedRows[] = [
                            'id' => $event->id,
                            'reason' => 'existing_event_days',
                            'day_count' => $dayCount,
                        ];

                        continue;
                    }

                    if (! $event->starts_at || ! $event->ends_at) {
                        $skipped++;
                        $skippedRows[] = [
                            'id' => $event->id,
                            'reason' => 'missing_starts_or_ends',
                        ];

                        continue;
                    }

                    if ($event->ends_at->lte($event->starts_at)) {
                        $skipped++;
                        $skippedRows[] = [
                            'id' => $event->id,
                            'reason' => 'invalid_date_range',
                        ];

                        continue;
                    }

                    try {
                        $plans = $this->generator->planForEvent($event);
                    } catch (InvalidArgumentException $exception) {
                        $skipped++;
                        $skippedRows[] = [
                            'id' => $event->id,
                            'reason' => 'plan_invalid',
                            'detail' => $exception->getMessage(),
                        ];

                        continue;
                    }

                    $eligible++;
                    $eligibleIds[] = $event->id;

                    if (! $apply) {
                        $this->line(sprintf(
                            'DRY-RUN event_id=%d title=%s planned_days=%d range=%s → %s',
                            $event->id,
                            mb_substr((string) $event->title, 0, 40),
                            count($plans),
                            $event->starts_at->toDateTimeString(),
                            $event->ends_at->toDateTimeString(),
                        ));

                        continue;
                    }

                    try {
                        DB::transaction(function () use ($event) {
                            $this->generator->materializeForEvent($event->fresh());
                        });
                        $generated++;
                        $generatedIds[] = $event->id;
                        $this->info(sprintf('APPLIED event_id=%d days=%d', $event->id, count($plans)));
                    } catch (Throwable $exception) {
                        $failed++;
                        $failedRows[] = [
                            'id' => $event->id,
                            'reason' => 'materialize_failed',
                            'detail' => $exception->getMessage(),
                        ];
                        $this->error(sprintf('FAILED event_id=%d: %s', $event->id, $exception->getMessage()));
                    }
                }
            });

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['scanned', $scanned],
                ['eligible', $eligible],
                ['generated', $generated],
                ['skipped', $skipped],
                ['failed', $failed],
            ],
        );

        if ($eligibleIds !== []) {
            $this->line('Eligible event IDs: '.implode(', ', $eligibleIds));
        }
        if ($generatedIds !== []) {
            $this->line('Generated event IDs: '.implode(', ', $generatedIds));
        }
        if ($skippedRows !== []) {
            $this->line('Skipped (id:reason): '.collect($skippedRows)
                ->map(fn (array $row) => $row['id'].':'.$row['reason'])
                ->implode(', '));
        }
        if ($failedRows !== []) {
            $this->line('Failed (id:reason): '.collect($failedRows)
                ->map(fn (array $row) => $row['id'].':'.$row['reason'])
                ->implode(', '));
        }

        if (! $apply) {
            $this->warn('Dry-run complete. Re-run with --apply after explicit approval to persist.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
