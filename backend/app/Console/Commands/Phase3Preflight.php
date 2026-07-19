<?php

namespace App\Console\Commands;

use App\Services\Phase3PreflightService;
use Illuminate\Console\Command;

class Phase3Preflight extends Command
{
    protected $signature = 'phase3:preflight
                            {--json : Emit machine-readable JSON}';

    protected $description = 'Run the read-only Phase 3 migration and data-integrity preflight';

    public function handle(Phase3PreflightService $preflight): int
    {
        $result = $preflight->inspect();

        if ($this->option('json')) {
            $this->line(json_encode(
                $result,
                JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
            ));
        } else {
            $this->table(
                ['Field', 'Value'],
                collect($result)
                    ->map(fn ($value, $key) => [
                        $key,
                        is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : $value,
                    ])
                    ->values()
                    ->all(),
            );
        }

        return self::SUCCESS;
    }
}
