<?php

namespace App\Support;

/**
 * Builds a privacy-safe Post-Event Summary snapshot for CMart consumers.
 *
 * Strips free-text survey content and other PII-like keys from historical snapshots
 * without mutating stored JSON.
 */
final class CmartReportSnapshotFilter
{
    /**
     * Keys that must never appear in CMart-facing snapshot trees.
     *
     * @var list<string>
     */
    public const DENIED_KEYS = [
        'qualitative_comments',
        'comments_and_suggestions',
        'difficulty_details',
        'improvement_areas_other_text',
        'product_categories_other_text',
        'event_info_sources_other_text',
        'supporting_activity_impacts_other_text',
        'import_review_notes',
        'import_auto_review_flags',
        'email',
        'phone',
        'phone_number',
        'user_id',
        'vendor_user_id',
        'respondent_id',
        'booking_ids',
        'invoice_ids',
        'payment_proof_path',
        'payment_reference',
        'name',
        'vendor_name',
        'business_name',
        'address',
    ];

    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return array<string, mixed>
     */
    public static function forCmart(?array $snapshot): array
    {
        if (! is_array($snapshot)) {
            return [];
        }

        $filtered = self::stripDenied($snapshot);

        // Hard-remove survey free-text even if nested under unexpected shapes.
        if (isset($filtered['sections']['vendor_survey']) && is_array($filtered['sections']['vendor_survey'])) {
            unset($filtered['sections']['vendor_survey']['qualitative_comments']);
            $filtered['sections']['vendor_survey']['privacy'] = [
                'free_text_excluded' => true,
                'note' => 'Raw survey comments are organizer-internal and excluded from the CMart report.',
            ];
        }

        // Cover must not rely on registration-capacity status.
        if (isset($filtered['event']) && is_array($filtered['event'])) {
            unset($filtered['event']['status']);
            unset($filtered['event']['max_slots']);
        }

        return $filtered;
    }

    /**
     * @param  mixed  $node
     * @return mixed
     */
    private static function stripDenied(mixed $node): mixed
    {
        if (! is_array($node)) {
            return $node;
        }

        $out = [];
        foreach ($node as $key => $value) {
            if (is_string($key) && in_array($key, self::DENIED_KEYS, true)) {
                continue;
            }
            $out[$key] = self::stripDenied($value);
        }

        return $out;
    }
}
