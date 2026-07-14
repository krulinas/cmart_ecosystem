<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

/**
 * Phase 2A.3 — guarded local dummy booking cleanup.
 *
 * Safety rules (ADR-016):
 * - local environment only
 * - never wipe catalogue tables (users, events, spaces, news)
 * - delete only targeted dummy bookings + related invoices/audit/payment proofs
 */
class CleanupLocalDummyBookings extends Command
{
    protected $signature = 'cmart:cleanup-local-dummy-bookings
                            {--dry-run : Inspect and snapshot without deleting}
                            {--force : Required to execute deletion}
                            {--booking-ids= : Optional comma-separated booking ID allowlist}';

    protected $description = 'Phase 2A.3: Remove local/development dummy bookings and related invoices/audit/proof files';

    private const SNAPSHOT_DIR = 'phase-2a3-cleanup';

    private const STATUS_MIGRATION_AUDIT = 'booking_status_migration_audit_202607';

    public function handle(): int
    {
        $env = config('app.env');
        if ($env !== 'local') {
            $this->error("Refusing to run: app environment is [{$env}], expected [local].");

            return self::FAILURE;
        }

        $bookingIds = $this->resolveTargetBookingIds();
        if ($bookingIds === []) {
            $this->warn('No dummy booking targets identified. Nothing to do.');

            return self::SUCCESS;
        }

        $before = $this->collectBaselineCounts();
        $snapshot = $this->buildSnapshot($bookingIds, $before);
        $snapshotPath = $this->writeSnapshot($snapshot);
        $this->info("Safety snapshot written: {$snapshotPath}");

        $this->table(
            ['Metric', 'Count'],
            collect($before)->map(fn ($v, $k) => [$k, $v])->values()->all()
        );

        $this->info('Target booking IDs: ' . implode(', ', $bookingIds));
        $this->line('Related invoices: ' . count($snapshot['invoices']));
        $this->line('Related audit logs: ' . count($snapshot['audit_logs']));
        $this->line('Payment proof paths: ' . count($snapshot['payment_proof_paths']));

        if ($this->option('dry-run') || ! $this->option('force')) {
            $this->warn('Dry-run / no --force: no records deleted. Re-run with --force to execute.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Delete the listed local dummy bookings and related records?', true)) {
            $this->warn('Aborted by operator.');

            return self::SUCCESS;
        }

        $result = $this->executeCleanup($bookingIds, $snapshot);
        $after = $this->collectBaselineCounts();

        $reportPath = $this->writeMarkdownReport($snapshot, $result, $before, $after, $snapshotPath);
        $this->info("Cleanup report written: {$reportPath}");

        $this->table(
            ['Metric', 'Before', 'After'],
            collect($before)->keys()->map(fn ($k) => [$k, $before[$k], $after[$k] ?? 'n/a'])->all()
        );

        $this->info('Phase 2A.3 cleanup completed.');

        return self::SUCCESS;
    }

    /**
     * All remaining local bookings are treated as dummy development records
     * when no explicit allowlist is provided (owner-authorized Phase 2A.3 scope).
     *
     * @return list<int>
     */
    private function resolveTargetBookingIds(): array
    {
        if ($this->option('booking-ids')) {
            return collect(explode(',', (string) $this->option('booking-ids')))
                ->map(fn ($id) => (int) trim($id))
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        return Booking::query()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function collectBaselineCounts(): array
    {
        return [
            'bookings' => Booking::count(),
            'invoices' => Invoice::count(),
            'booking_audit_logs' => BookingAuditLog::count(),
            'users' => DB::table('users')->count(),
            'carboot_events' => DB::table('carboot_events')->count(),
            'spaces' => DB::table('spaces')->count(),
            'news_posts' => Schema::hasTable('news_posts') ? DB::table('news_posts')->count() : 0,
            'feedbacks' => Schema::hasTable('feedbacks') ? DB::table('feedbacks')->count() : 0,
            'event_user' => Schema::hasTable('event_user') ? DB::table('event_user')->count() : 0,
            'user_booking_preferences' => Schema::hasTable('user_booking_preferences')
                ? DB::table('user_booking_preferences')->count()
                : 0,
            'status_migration_audit' => Schema::hasTable(self::STATUS_MIGRATION_AUDIT)
                ? DB::table(self::STATUS_MIGRATION_AUDIT)->count()
                : 0,
        ];
    }

    private function buildSnapshot(array $bookingIds, array $before): array
    {
        $bookings = Booking::with(['user:id,name,email,role', 'invoice', 'space:id,space_size', 'carbootEvent:id,title'])
            ->whereIn('id', $bookingIds)
            ->orderBy('id')
            ->get();

        $foundIds = $bookings->pluck('id')->map(fn ($id) => (int) $id)->all();
        $invoices = Invoice::whereIn('booking_id', $foundIds)->orderBy('id')->get();
        $auditLogs = BookingAuditLog::whereIn('booking_id', $foundIds)->orderBy('id')->get();

        $migrationAudit = Schema::hasTable(self::STATUS_MIGRATION_AUDIT)
            ? DB::table(self::STATUS_MIGRATION_AUDIT)->whereIn('booking_id', $foundIds)->get()
            : collect();

        $proofPaths = $invoices
            ->pluck('payment_proof_path')
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->unique()
            ->values()
            ->all();

        return [
            'generated_at' => Carbon::now()->toIso8601String(),
            'environment' => config('app.env'),
            'database' => config('database.connections.mysql.database'),
            'before_counts' => $before,
            'target_booking_ids' => $foundIds,
            'identification_rule' => $this->option('booking-ids')
                ? 'explicit --booking-ids allowlist'
                : 'all remaining local bookings (owner-authorized Phase 2A.3 dummy baseline cleanup)',
            'bookings' => $bookings->map(fn (Booking $b) => [
                'id' => $b->id,
                'user_id' => $b->user_id,
                'email' => $b->user?->email,
                'space_id' => $b->space_id,
                'carboot_event_id' => $b->carboot_event_id,
                'event_title' => $b->carbootEvent?->title,
                'booking_date' => optional($b->booking_date)->toDateString(),
                'approval_status' => $b->approval_status,
                'product_category' => $b->product_category,
                'product_details' => $b->product_details,
                'checked_in_at' => optional($b->checked_in_at)->toDateTimeString(),
                'created_at' => optional($b->created_at)->toDateTimeString(),
            ])->all(),
            'invoices' => $invoices->map(fn (Invoice $i) => [
                'id' => $i->id,
                'booking_id' => $i->booking_id,
                'amount' => $i->amount,
                'payment_status' => $i->payment_status,
                'payment_proof_path' => $i->payment_proof_path,
                'payment_submitted_at' => optional($i->payment_submitted_at)->toDateTimeString(),
            ])->all(),
            'audit_logs' => $auditLogs->map(fn (BookingAuditLog $log) => [
                'id' => $log->id,
                'booking_id' => $log->booking_id,
                'actor_user_id' => $log->actor_user_id,
                'action' => $log->action,
                'from_status' => $log->from_status,
                'to_status' => $log->to_status,
                'created_at' => optional($log->created_at)->toDateTimeString(),
            ])->all(),
            'status_migration_audit_rows' => $migrationAudit->map(fn ($row) => (array) $row)->all(),
            'payment_proof_paths' => $proofPaths,
        ];
    }

    private function writeSnapshot(array $snapshot): string
    {
        $relative = self::SNAPSHOT_DIR . '/snapshot-' . Carbon::now()->format('Ymd-His') . '.json';
        Storage::disk('local')->put($relative, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return storage_path('app/' . $relative);
    }

    private function executeCleanup(array $bookingIds, array $snapshot): array
    {
        $deletedProofFiles = [];
        $skippedProofFiles = [];

        foreach ($snapshot['payment_proof_paths'] as $path) {
            if ($this->isSafeDummyProofFile($path) && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                $deletedProofFiles[] = $path;
            } else {
                $skippedProofFiles[] = [
                    'path' => $path,
                    'reason' => $this->proofSkipReason($path),
                ];
            }
        }

        $counts = [
            'audit_logs' => 0,
            'status_migration_audit' => 0,
            'invoices' => 0,
            'bookings' => 0,
            'payment_proof_files_deleted' => count($deletedProofFiles),
            'payment_proof_paths_skipped' => $skippedProofFiles,
        ];

        DB::transaction(function () use ($bookingIds, &$counts) {
            $counts['audit_logs'] = BookingAuditLog::whereIn('booking_id', $bookingIds)->delete();

            if (Schema::hasTable(self::STATUS_MIGRATION_AUDIT)) {
                $counts['status_migration_audit'] = DB::table(self::STATUS_MIGRATION_AUDIT)
                    ->whereIn('booking_id', $bookingIds)
                    ->delete();
            }

            $counts['invoices'] = Invoice::whereIn('booking_id', $bookingIds)->delete();
            $counts['bookings'] = Booking::whereIn('id', $bookingIds)->delete();
        });

        return $counts;
    }

    private function isSafeDummyProofFile(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        if (str_starts_with($normalized, 'demo-gateway/')) {
            return false;
        }

        if (! str_starts_with($normalized, 'payment-proofs/')) {
            return false;
        }

        return ! str_contains($normalized, '..');
    }

    private function proofSkipReason(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        if (str_starts_with($normalized, 'demo-gateway/')) {
            return 'demo-gateway marker path (not a stored file)';
        }

        if (! Storage::disk('public')->exists($normalized)) {
            return 'file does not exist on public disk';
        }

        return 'path outside payment-proofs safety allowlist';
    }

    private function writeMarkdownReport(
        array $snapshot,
        array $result,
        array $before,
        array $after,
        string $snapshotPath,
    ): string {
        $repoReport = base_path('../docs/phase-2/phase-2a3-local-dummy-booking-cleanup-report.md');
        $lines = [];
        $lines[] = '# Phase 2A.3 — Local Dummy Booking Data Cleanup Report';
        $lines[] = '';
        $lines[] = '**Executed at:** ' . Carbon::now()->toIso8601String();
        $lines[] = '**Environment:** `' . config('app.env') . '`';
        $lines[] = '**Database:** `' . config('database.connections.mysql.database') . '`';
        $lines[] = '**Snapshot file:** `' . $snapshotPath . '`';
        $lines[] = '';
        $lines[] = '## Scope';
        $lines[] = '';
        $lines[] = '- Identification rule: ' . $snapshot['identification_rule'];
        $lines[] = '- Target booking IDs: `' . implode(', ', $snapshot['target_booking_ids']) . '`';
        $lines[] = '- Unrelated tables preserved: users, carboot_events, spaces, news_posts, feedbacks, event_user, user_booking_preferences';
        $lines[] = '';
        $lines[] = '## Before / After Counts';
        $lines[] = '';
        $lines[] = '| Metric | Before | After | Delta |';
        $lines[] = '| ------ | -----: | ----: | ----: |';
        foreach ($before as $key => $value) {
            $afterValue = $after[$key] ?? 0;
            $lines[] = sprintf('| %s | %d | %d | %d |', $key, $value, $afterValue, $afterValue - $value);
        }
        $lines[] = '';
        $lines[] = '## Deleted Record Counts';
        $lines[] = '';
        $lines[] = '- Booking audit logs: **' . $result['audit_logs'] . '**';
        $lines[] = '- Status migration audit rows: **' . $result['status_migration_audit'] . '**';
        $lines[] = '- Invoices: **' . $result['invoices'] . '**';
        $lines[] = '- Bookings: **' . $result['bookings'] . '**';
        $lines[] = '- Payment-proof files deleted: **' . $result['payment_proof_files_deleted'] . '**';
        $lines[] = '';
        $lines[] = '## Payment Proof Handling';
        $lines[] = '';
        if ($result['payment_proof_paths_skipped'] === []) {
            $lines[] = 'No payment-proof paths required special handling.';
        } else {
            $lines[] = '| Path | Reason skipped |';
            $lines[] = '| ---- | -------------- |';
            foreach ($result['payment_proof_paths_skipped'] as $row) {
                $lines[] = '| `' . $row['path'] . '` | ' . $row['reason'] . ' |';
            }
        }
        $lines[] = '';
        $lines[] = '## Target Bookings Snapshot (summary)';
        $lines[] = '';
        $lines[] = '| ID | Email | Event | Status | Payment | Details |';
        $lines[] = '| --: | ----- | ----- | ------ | ------- | ------- |';
        $invoiceByBooking = collect($snapshot['invoices'])->keyBy('booking_id');
        foreach ($snapshot['bookings'] as $booking) {
            $invoice = $invoiceByBooking->get($booking['id']);
            $lines[] = sprintf(
                '| %d | %s | %s | %s | %s | %s |',
                $booking['id'],
                $booking['email'] ?? '—',
                $booking['event_title'] ?? '—',
                $booking['approval_status'],
                $invoice['payment_status'] ?? '—',
                str_replace('|', '/', mb_substr((string) ($booking['product_details'] ?? ''), 0, 60))
            );
        }
        $lines[] = '';
        $lines[] = '## Integrity Checks';
        $lines[] = '';
        $lines[] = '- Remaining bookings: **' . ($after['bookings'] ?? 0) . '** (expected 0 for clean baseline)';
        $lines[] = '- Remaining invoices: **' . ($after['invoices'] ?? 0) . '**';
        $lines[] = '- Users unchanged: **' . (($before['users'] === ($after['users'] ?? null)) ? 'yes' : 'NO') . '**';
        $lines[] = '- Events unchanged: **' . (($before['carboot_events'] === ($after['carboot_events'] ?? null)) ? 'yes' : 'NO') . '**';
        $lines[] = '- Spaces unchanged: **' . (($before['spaces'] === ($after['spaces'] ?? null)) ? 'yes' : 'NO') . '**';
        $lines[] = '- News unchanged: **' . (($before['news_posts'] === ($after['news_posts'] ?? null)) ? 'yes' : 'NO') . '**';
        $lines[] = '- Feedback unchanged: **' . (($before['feedbacks'] === ($after['feedbacks'] ?? null)) ? 'yes' : 'NO') . '**';
        $lines[] = '';
        $lines[] = '## Commands';
        $lines[] = '';
        $lines[] = '```bash';
        $lines[] = 'php artisan cmart:cleanup-local-dummy-bookings --force';
        $lines[] = '```';
        $lines[] = '';
        $lines[] = '## Notes';
        $lines[] = '';
        $lines[] = '- No `migrate:fresh`, `db:wipe`, or table truncation was used.';
        $lines[] = '- Demo gateway proof markers (`demo-gateway/...`) are not filesystem files and were skipped.';
        $lines[] = '- Phase 2A.4+ may proceed against this clean booking baseline.';
        $lines[] = '';

        $content = implode(PHP_EOL, $lines);
        file_put_contents($repoReport, $content);

        $relative = self::SNAPSHOT_DIR . '/cleanup-report-' . Carbon::now()->format('Ymd-His') . '.md';
        Storage::disk('local')->put($relative, $content);

        return $repoReport;
    }
}
