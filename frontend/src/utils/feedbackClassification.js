/**
 * Feedback participation / community-background vocabulary.
 * Distinct from authenticated authorization roles.
 */

export const PARTICIPATION_TYPE_OPTIONS = [
  { value: 'visitor_shopper', label: 'Visitor / Shopper' },
  { value: 'vendor', label: 'Vendor' },
  { value: 'organizer_event_crew', label: 'Organizer / Event Crew' },
  { value: 'other', label: 'Other' },
];

export const COMMUNITY_BACKGROUND_OPTIONS = [
  { value: 'uum_student', label: 'UUM Student' },
  { value: 'uum_staff', label: 'UUM Staff' },
  { value: 'other_institution', label: 'Student or Staff from Another Institution' },
  { value: 'changlun_resident', label: 'Changlun Resident' },
  { value: 'outside_changlun', label: 'Visitor from Outside Changlun' },
  { value: 'prefer_not_to_say', label: 'Prefer not to say' },
];

export const PREFER_NOT_TO_SAY = 'prefer_not_to_say';

const PARTICIPATION_LABELS = Object.fromEntries(
  PARTICIPATION_TYPE_OPTIONS.map((option) => [option.value, option.label]),
);

/**
 * Enforce prefer_not_to_say exclusivity.
 * @param {string[]} selected
 * @param {string} [changedValue] Last toggled value, when known.
 * @returns {string[]}
 */
export function normalizeCommunityBackgrounds(selected = [], changedValue = null) {
  const unique = [...new Set((selected || []).filter(Boolean))];

  if (!unique.includes(PREFER_NOT_TO_SAY)) {
    return unique;
  }

  if (changedValue === PREFER_NOT_TO_SAY) {
    return [PREFER_NOT_TO_SAY];
  }

  if (changedValue && changedValue !== PREFER_NOT_TO_SAY) {
    return unique.filter((value) => value !== PREFER_NOT_TO_SAY);
  }

  return [PREFER_NOT_TO_SAY];
}

export function participationTypeLabel(value) {
  if (!value) return null;
  return PARTICIPATION_LABELS[value] || value;
}
