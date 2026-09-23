<?php

namespace App\Support;

/**
 * Deterministic, non-speculative descriptive analysis for Post-Event reports.
 * Never invents thresholds (good/poor/successful) or fabricates missing data.
 */
final class PostEventReportAnalysis
{
    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{
     *   participation: list<string>,
     *   finance: list<string>,
     *   vendor_mix: list<string>,
     *   feedback: list<string>
     * }
     */
    public static function fromSnapshot(array $snapshot): array
    {
        $sections = is_array($snapshot['sections'] ?? null) ? $snapshot['sections'] : [];

        return [
            'participation' => self::participation($sections),
            'finance' => self::finance($sections),
            'vendor_mix' => self::vendorMix($sections),
            'feedback' => self::feedback($sections),
        ];
    }

    /**
     * Grammar-correct executive summary from frozen metrics only.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public static function executiveSummary(array $snapshot): string
    {
        $sections = is_array($snapshot['sections'] ?? null) ? $snapshot['sections'] : [];
        $pipeline = self::section($sections, 'booking_pipeline');
        $payments = self::section($sections, 'payments');
        $attendance = self::section($sections, 'attendance');
        $utilisation = self::section($sections, 'site_day_utilisation');
        $survey = self::section($sections, 'vendor_survey');

        $bits = [];
        $apps = self::num($pipeline, 'total_bookings');
        if ($apps !== null) {
            $bits[] = (int) $apps.' application'.((int) $apps === 1 ? '' : 's');
        }
        $approved = self::num($pipeline, 'approved_count');
        if ($approved !== null) {
            $bits[] = (int) $approved.' approved booking'.((int) $approved === 1 ? '' : 's');
        }
        $vendors = self::num($pipeline, 'approved_unique_vendors');
        if ($vendors !== null) {
            $bits[] = (int) $vendors.' approved unique vendor'.((int) $vendors === 1 ? '' : 's');
        }
        if (! empty($attendance['recorded']) && self::num($attendance, 'verified_check_in_count') !== null) {
            $n = (int) $attendance['verified_check_in_count'];
            $bits[] = $n.' verified check-in'.($n === 1 ? '' : 's');
        }
        if ($utilisation && isset($utilisation['utilisation_percent']) && $utilisation['utilisation_percent'] !== null) {
            $bits[] = 'site-day utilisation of '.$utilisation['utilisation_percent'].'%';
        }
        $collected = self::moneyVal($payments, 'collected_booth_fees', 'collected_revenue', 'collected');
        if ($collected !== null) {
            $bits[] = 'collected booth fees of '.PostEventReportPresentation::money($collected);
        }
        if ($survey && ! empty($survey['available']) && self::num($survey, 'respondent_count') !== null) {
            $n = (int) $survey['respondent_count'];
            $bits[] = $n.' survey response'.($n === 1 ? '' : 's');
        }

        if ($bits === []) {
            return 'This report summarises the available snapshot for the selected event. Some operational or survey indicators were not recorded.';
        }

        return 'Based on the frozen event snapshot, this report records '
            .self::joinBits($bits)
            .'. Figures reflect recorded system and survey data only and do not imply an overall success judgement.';
    }

    /**
     * Deterministic conclusion draft from frozen metrics and limitations only.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public static function conclusionDraft(array $snapshot): string
    {
        $sections = is_array($snapshot['sections'] ?? null) ? $snapshot['sections'] : [];
        $pipeline = self::section($sections, 'booking_pipeline');
        $payments = self::section($sections, 'payments');
        $attendance = self::section($sections, 'attendance');
        $feedback = self::section($sections, 'feedback');
        $survey = self::section($sections, 'vendor_survey');
        $utilisation = self::section($sections, 'site_day_utilisation');
        $eventPerf = self::section($sections, 'event_performance');

        $parts = [];
        $apps = self::num($pipeline, 'total_bookings');
        $approved = self::num($pipeline, 'approved_count');
        $vendors = self::num($pipeline, 'approved_unique_vendors');
        if ($apps !== null || $approved !== null || $vendors !== null) {
            $clause = [];
            if ($apps !== null) {
                $clause[] = (int) $apps.' application'.((int) $apps === 1 ? '' : 's');
            }
            if ($approved !== null) {
                $clause[] = (int) $approved.' approved booking'.((int) $approved === 1 ? '' : 's');
            }
            if ($vendors !== null) {
                $clause[] = (int) $vendors.' unique approved vendor'.((int) $vendors === 1 ? '' : 's');
            }
            $parts[] = 'At the data cut-off, the snapshot recorded '.self::joinBits($clause).'.';
        }

        $sold = self::num($eventPerf, 'sites_sold');
        $open = self::num($eventPerf, 'open_booking_sites');
        $util = $eventPerf['site_utilisation_percent'] ?? ($utilisation['utilisation_percent'] ?? null);
        $remaining = self::num($eventPerf, 'available_sites');
        if ($sold !== null && $open !== null && $util !== null) {
            $sentence = (int) $sold.' of '.(int) $open.' open booking sites were occupied, producing '
                .$util.'% utilisation';
            if ($remaining !== null) {
                $sentence .= ' and leaving '.(int) $remaining.' site'.((int) $remaining === 1 ? '' : 's').' available';
            }
            $parts[] = $sentence.'.';
        }

        $invoiced = self::moneyVal($payments, 'expected_booth_fees', 'expected_booking_revenue', 'expected');
        $collected = self::moneyVal($payments, 'collected_booth_fees', 'collected_revenue', 'collected');
        $outstanding = self::moneyVal($payments, 'outstanding_invoice_balance', 'unpaid_approved', 'outstanding');
        if ($invoiced !== null && $collected !== null) {
            $out = $outstanding !== null
                ? $outstanding
                : max(0, (float) $invoiced - (float) $collected);
            $parts[] = 'At the data cut-off, '.PostEventReportPresentation::money($collected)
                .' of '.PostEventReportPresentation::money($invoiced)
                .' invoiced revenue had been collected, leaving '
                .PostEventReportPresentation::money($out).' outstanding.';
        }

        $attendanceOk = ! empty($attendance['recorded']) && self::num($attendance, 'verified_check_in_count') !== null;
        if (! $attendanceOk) {
            $parts[] = 'Verified attendance was not recorded for this event, so participation figures must not be read as footfall.';
        }

        $surveyOk = $survey && ! empty($survey['available']) && (self::num($survey, 'respondent_count') ?? 0) > 0;
        if (! $surveyOk) {
            $parts[] = 'Vendor survey sales evidence was not available in this snapshot.';
        }

        $fbN = self::num($feedback, 'response_count');
        if ($fbN !== null && (int) $fbN > 0) {
            $avg = $feedback['average_overall_rating'] ?? null;
            if ($avg !== null) {
                $parts[] = 'Community feedback comprised '.(int) $fbN.' response'
                    .((int) $fbN === 1 ? '' : 's')
                    .' with an average overall rating of '.$avg.' / 5.'
                    .((int) $fbN < 5
                        ? ' Because n='.(int) $fbN.', these ratings should not be interpreted as representative of all participants.'
                        : '');
            }
        } else {
            $parts[] = 'No community feedback responses were recorded in this snapshot.';
        }

        $parts[] = 'This conclusion summarises recorded snapshot metrics and known data limitations only. It does not judge overall event success.';

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $sections
     * @return list<string>
     */
    private static function participation(array $sections): array
    {
        $out = [];
        $pipeline = self::section($sections, 'booking_pipeline');
        $attendance = self::section($sections, 'attendance');
        $eventPerf = self::section($sections, 'event_performance');
        $utilisation = self::section($sections, 'site_day_utilisation');

        $apps = self::num($pipeline, 'total_bookings');
        $approved = self::num($pipeline, 'approved_count');
        if ($apps !== null && $approved !== null) {
            $out[] = (int) $approved.' of '.(int) $apps.' application'
                .((int) $apps === 1 ? ' was' : 's were').' approved at the data cut-off.';
        }

        $vendors = self::num($pipeline, 'approved_unique_vendors');
        if ($vendors !== null) {
            $out[] = 'The snapshot records '.(int) $vendors.' unique participating vendor'
                .((int) $vendors === 1 ? '' : 's').' among approved bookings.';
        }

        $sold = self::num($eventPerf, 'sites_sold');
        $open = self::num($eventPerf, 'open_booking_sites');
        $util = $eventPerf['site_utilisation_percent'] ?? null;
        $remaining = self::num($eventPerf, 'available_sites');
        if ($sold !== null && $open !== null && $util !== null) {
            $sentence = (int) $sold.' of '.(int) $open.' open booking sites were occupied, producing '
                .$util.'% utilisation';
            if ($remaining !== null) {
                $sentence .= ' and leaving '.(int) $remaining.' site'.((int) $remaining === 1 ? '' : 's').' available';
            }
            $out[] = $sentence.'.';
        } elseif ($utilisation && isset($utilisation['utilisation_percent'])) {
            $out[] = 'Site-day utilisation was '.$utilisation['utilisation_percent']
                .'% (occupied site-days ÷ available active site-days).';
        }

        if (empty($attendance['recorded']) || self::num($attendance, 'verified_check_in_count') === null) {
            $out[] = 'Verified attendance was not recorded; approved bookings are not verified attendance.';
        } else {
            $out[] = (int) $attendance['verified_check_in_count'].' verified check-in'
                .((int) $attendance['verified_check_in_count'] === 1 ? '' : 's')
                .' were recorded.';
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $sections
     * @return list<string>
     */
    private static function finance(array $sections): array
    {
        $out = [];
        $payments = self::section($sections, 'payments');
        if (! $payments) {
            return $out;
        }

        $expected = self::moneyVal($payments, 'expected_booth_fees', 'expected_booking_revenue', 'expected');
        $invoiced = self::moneyVal($payments, 'invoiced_amount') ?? $expected;
        $collected = self::moneyVal($payments, 'collected_booth_fees', 'collected_revenue', 'collected');
        $outstanding = self::moneyVal($payments, 'outstanding_invoice_balance', 'unpaid_approved', 'outstanding');
        $unbilled = self::moneyVal($payments, 'unbilled_booking_value');
        $rate = $payments['collection_rate_percent'] ?? null;
        $withoutInvoice = self::num($payments, 'approved_bookings_without_invoice');

        if ($expected !== null) {
            $out[] = 'Expected booking revenue was '.PostEventReportPresentation::money($expected).'.';
        }
        if ($invoiced !== null && $collected !== null) {
            $outAmt = $outstanding !== null
                ? $outstanding
                : max(0, (float) $invoiced - (float) $collected);
            $out[] = 'At the data cut-off, '.PostEventReportPresentation::money($collected)
                .' of '.PostEventReportPresentation::money($invoiced)
                .' invoiced revenue had been collected, leaving '
                .PostEventReportPresentation::money($outAmt).' outstanding.';
        }
        if ($unbilled !== null) {
            $out[] = 'Unbilled booking value was '.PostEventReportPresentation::money($unbilled).'.';
        }
        if ($rate !== null) {
            $out[] = 'Collection rate was '.$rate.'%.';
        }
        if ($withoutInvoice !== null && (int) $withoutInvoice > 0) {
            $out[] = (int) $withoutInvoice.' approved booking'
                .((int) $withoutInvoice === 1 ? '' : 's')
                .' had no invoice at the data cut-off.';
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $sections
     * @return list<string>
     */
    private static function vendorMix(array $sections): array
    {
        $out = [];
        $categories = self::section($sections, 'vendor_categories');
        $rows = is_array($categories['distribution'] ?? null) ? $categories['distribution'] : [];
        if ($rows === []) {
            return $out;
        }

        $ranked = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $count = $row['unique_vendors'] ?? $row['count'] ?? null;
            if ($count === null || ! is_numeric($count)) {
                continue;
            }
            $ranked[] = [
                'label' => (string) ($row['label'] ?? $row['key'] ?? 'Category'),
                'count' => (float) $count,
            ];
        }
        usort($ranked, fn ($a, $b) => $b['count'] <=> $a['count']);
        if ($ranked === []) {
            return $out;
        }

        $total = array_sum(array_column($ranked, 'count'));
        $top = $ranked[0];
        $share = $total > 0 ? round(($top['count'] / $total) * 100, 1) : 0;
        $out[] = 'The largest category was '.$top['label'].' with '.(int) $top['count']
            .' unique vendor'.((int) $top['count'] === 1 ? '' : 's')
            .' ('.$share.'% of category-labelled unique-vendor counts).';
        $out[] = 'Category totals may exceed unique vendors when a vendor has multiple category-labelled bookings.';

        return $out;
    }

    /**
     * @param  array<string, mixed>  $sections
     * @return list<string>
     */
    private static function feedback(array $sections): array
    {
        $out = [];
        $feedback = self::section($sections, 'feedback');
        if (! $feedback || ! empty($feedback['excluded'])) {
            return ['No community feedback responses were recorded in this snapshot.'];
        }

        $n = self::num($feedback, 'response_count');
        if ($n === null || (int) $n === 0) {
            return ['No community feedback responses were recorded in this snapshot.'];
        }

        $avg = $feedback['average_overall_rating'] ?? null;
        if ($avg !== null) {
            $out[] = 'Average overall rating was '.$avg.' / 5 across '.(int) $n
                .' response'.((int) $n === 1 ? '' : 's').' (n='.(int) $n.').';
        } else {
            $out[] = (int) $n.' feedback response'.((int) $n === 1 ? '' : 's').' were recorded (n='.(int) $n.').';
        }

        $dist = is_array($feedback['rating_distribution'] ?? null) ? $feedback['rating_distribution'] : [];
        $parts = [];
        foreach ($dist as $key => $count) {
            if (! is_numeric($count) || (int) $count <= 0) {
                continue;
            }
            $parts[] = $key.'★: '.(int) $count;
        }
        if ($parts !== []) {
            $out[] = 'Rating distribution — '.implode('; ', $parts).'.';
        }

        $vendorN = self::num($feedback, 'vendor_response_count');
        $nonVendorN = self::num($feedback, 'non_vendor_response_count');
        if ($vendorN !== null || $nonVendorN !== null) {
            $out[] = 'Responses comprised '
                .(int) ($vendorN ?? 0).' vendor and '
                .(int) ($nonVendorN ?? 0).' non-vendor submission'
                .(((int) ($nonVendorN ?? 0) === 1) ? '' : 's').'.';
        }

        if ((int) $n < 5) {
            $out[] = 'Because n='.(int) $n.', this should not be interpreted as representative of all participants.';
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $sections
     * @return array<string, mixed>|null
     */
    private static function section(array $sections, string $key): ?array
    {
        $section = $sections[$key] ?? null;
        if (! is_array($section) || (($section['excluded'] ?? false) === true)) {
            return null;
        }

        return $section;
    }

    /**
     * @param  array<string, mixed>|null  $section
     */
    private static function num(?array $section, string $key): ?float
    {
        if (! $section || ! array_key_exists($key, $section) || $section[$key] === null || $section[$key] === '') {
            return null;
        }
        if (! is_numeric($section[$key])) {
            return null;
        }

        return (float) $section[$key];
    }

    /**
     * @param  array<string, mixed>|null  $section
     */
    private static function moneyVal(?array $section, string ...$keys): ?float
    {
        if (! $section) {
            return null;
        }
        foreach ($keys as $key) {
            if (array_key_exists($key, $section) && $section[$key] !== null && is_numeric($section[$key])) {
                return (float) $section[$key];
            }
        }

        return null;
    }

    /** @param  list<string>  $bits */
    private static function joinBits(array $bits): string
    {
        if (count($bits) === 1) {
            return $bits[0];
        }
        if (count($bits) === 2) {
            return $bits[0].' and '.$bits[1];
        }
        $last = array_pop($bits);

        return implode(', ', $bits).', and '.$last;
    }
}
