<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * One feedback row per authenticated user + event + participation type.
 *
 * Duplicate handling (deterministic, non-destructive):
 * - Rows with NULL user_id, NULL carboot_event_id, or NULL participation_type
 *   are left unchanged (MySQL UNIQUE allows multiple NULLs in those columns).
 * - When non-null duplicates exist for (user_id, carboot_event_id, participation_type):
 *   keep the newest row (updated_at DESC, id DESC) scoped to the event;
 *   older siblings have carboot_event_id set to NULL so every response is preserved
 *   and remains excluded from event analytics until manually re-scoped.
 * - No feedback rows are deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('feedbacks') || ! Schema::hasColumn('feedbacks', 'carboot_event_id')) {
            return;
        }

        if ($this->hasIndex('feedbacks', 'feedbacks_user_event_participation_unique')) {
            return;
        }

        $resolved = $this->resolveNonNullDuplicates();

        if ($resolved['groups'] > 0) {
            Log::warning('feedbacks unique migration: unscoped older duplicate rows', $resolved);
        }

        Schema::table('feedbacks', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'carboot_event_id', 'participation_type'],
                'feedbacks_user_event_participation_unique',
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('feedbacks')) {
            return;
        }

        if (! $this->hasIndex('feedbacks', 'feedbacks_user_event_participation_unique')) {
            return;
        }

        Schema::table('feedbacks', function (Blueprint $table) {
            $table->dropUnique('feedbacks_user_event_participation_unique');
        });
    }

    /**
     * @return array{groups: int, unscoped_ids: list<int>, kept_ids: list<int>}
     */
    private function resolveNonNullDuplicates(): array
    {
        $groups = DB::table('feedbacks')
            ->select('user_id', 'carboot_event_id', 'participation_type', DB::raw('COUNT(*) as aggregate'))
            ->whereNotNull('user_id')
            ->whereNotNull('carboot_event_id')
            ->whereNotNull('participation_type')
            ->groupBy('user_id', 'carboot_event_id', 'participation_type')
            ->having('aggregate', '>', 1)
            ->get();

        $unscopedIds = [];
        $keptIds = [];

        foreach ($groups as $group) {
            $rows = DB::table('feedbacks')
                ->where('user_id', $group->user_id)
                ->where('carboot_event_id', $group->carboot_event_id)
                ->where('participation_type', $group->participation_type)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get(['id']);

            $keepId = (int) $rows->first()->id;
            $keptIds[] = $keepId;

            $olderIds = $rows->skip(1)->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($olderIds === []) {
                continue;
            }

            DB::table('feedbacks')
                ->whereIn('id', $olderIds)
                ->update([
                    'carboot_event_id' => null,
                    'updated_at' => now(),
                ]);

            foreach ($olderIds as $id) {
                $unscopedIds[] = $id;
            }
        }

        return [
            'groups' => $groups->count(),
            'unscoped_ids' => $unscopedIds,
            'kept_ids' => $keptIds,
        ];
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $database = Schema::getConnection()->getDatabaseName();
        $row = DB::selectOne(
            'SELECT 1 AS present
             FROM information_schema.statistics
             WHERE table_schema = ?
               AND table_name = ?
               AND index_name = ?
             LIMIT 1',
            [$database, $table, $indexName],
        );

        return $row !== null;
    }
};
