<?php

namespace App\Support;

/**
 * Canonical feedback participation / community-background vocabulary.
 * Distinct from authenticated authorization roles (community, organizer, etc.).
 */
final class FeedbackClassification
{
    public const PARTICIPATION_TYPES = [
        'visitor_shopper',
        'vendor',
        'organizer_event_crew',
        'other',
    ];

    public const COMMUNITY_BACKGROUNDS = [
        'uum_student',
        'uum_staff',
        'other_institution',
        'changlun_resident',
        'outside_changlun',
        'prefer_not_to_say',
    ];

    public const PREFER_NOT_TO_SAY = 'prefer_not_to_say';

    /** @return array<string, string> */
    public static function participationLabels(): array
    {
        return [
            'visitor_shopper' => 'Visitor / Shopper',
            'vendor' => 'Vendor',
            'organizer_event_crew' => 'Organizer / Event Crew',
            'other' => 'Other',
        ];
    }

    /** @return array<string, string> */
    public static function communityBackgroundLabels(): array
    {
        return [
            'uum_student' => 'UUM Student',
            'uum_staff' => 'UUM Staff',
            'other_institution' => 'Student or Staff from Another Institution',
            'changlun_resident' => 'Changlun Resident',
            'outside_changlun' => 'Visitor from Outside Changlun',
            'prefer_not_to_say' => 'Prefer not to say',
        ];
    }

    public static function participationLabel(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::participationLabels()[$value] ?? $value;
    }

    /**
     * @param  list<string>|null  $values
     * @return list<string>
     */
    public static function communityBackgroundLabelsFor(?array $values): array
    {
        if (! is_array($values) || $values === []) {
            return [];
        }

        $labels = self::communityBackgroundLabels();

        return array_values(array_filter(array_map(
            fn (string $value) => $labels[$value] ?? null,
            $values,
        )));
    }

    /**
     * Enforce prefer_not_to_say exclusivity and de-duplicate.
     *
     * @param  list<string>|null  $values
     * @return list<string>
     */
    public static function normalizeCommunityBackgrounds(?array $values): array
    {
        if (! is_array($values) || $values === []) {
            return [];
        }

        $allowed = array_flip(self::COMMUNITY_BACKGROUNDS);
        $normalized = [];

        foreach ($values as $value) {
            if (! is_string($value) || $value === '' || ! isset($allowed[$value])) {
                continue;
            }
            $normalized[$value] = true;
        }

        $keys = array_keys($normalized);

        if (isset($normalized[self::PREFER_NOT_TO_SAY])) {
            return [self::PREFER_NOT_TO_SAY];
        }

        return $keys;
    }

    /**
     * Map legacy reviewer_role display strings to participation_type where possible.
     */
    public static function legacyReviewerRoleToParticipation(?string $role): ?string
    {
        return match ($role) {
            'Shopper' => 'visitor_shopper',
            'Vendor' => 'vendor',
            default => null,
        };
    }

    /**
     * Legacy reviewer_role values that correspond to a participation filter value.
     *
     * @return list<string>
     */
    public static function legacyReviewerRolesForParticipation(string $participationType): array
    {
        return match ($participationType) {
            'visitor_shopper' => ['Shopper'],
            'vendor' => ['Vendor'],
            default => [],
        };
    }
}
