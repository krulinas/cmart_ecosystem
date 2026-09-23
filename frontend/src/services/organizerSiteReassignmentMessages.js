import { tt } from '../i18n';

export function reassignmentErrorMessage(errorCode, fallback) {
  if (errorCode) {
    const key = `organizer.reassignment.errors.${errorCode}`;
    const translated = tt(key);
    if (translated && translated !== key) return translated;
  }
  if (fallback) return fallback;
  return tt('organizer.reassignment.errors.fallback');
}
