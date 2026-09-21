/**
 * Explicit audience/mode copy for VisualParkingLayout.
 * Never infer mode from auth role — callers pass mode explicitly.
 */

import { tt } from '../i18n';

export const VISUAL_PARKING_MODES = Object.freeze(['organizer', 'vendor', 'public']);

export function visualParkingCopy(mode, t = tt) {
  if (!VISUAL_PARKING_MODES.includes(mode)) {
    throw new Error(`Unsupported visual parking layout mode: ${mode}`);
  }

  const shared = {
    exit: t('calendar.layout.exit'),
    entrance: t('calendar.layout.entrance'),
    aisle: t('calendar.layout.aisle'),
    available: t('calendar.layout.available'),
    selected: t('calendar.layout.selected'),
    reserved: t('calendar.layout.reserved'),
    confirmed: t('calendar.layout.booked'),
    unavailable: t('calendar.layout.unavailable'),
    disabled: t('calendar.layout.disabled'),
    publicSite: t('calendar.layout.site'),
    rowPrefix: t('calendar.layout.row'),
    focused: t('calendar.layout.focused'),
  };

  if (mode === 'public') {
    return {
      ...shared,
      title: t('calendar.layout.title'),
      legendAria: t('calendar.layout.legendAria'),
      sitesCount: (count) => t('calendar.layout.sitesCount', { n: count }),
      categoryFallback: t('calendar.layout.category'),
      selectSite: t('calendar.layout.site'),
    };
  }

  if (mode === 'vendor') {
    return {
      ...shared,
      title: t('booking.sites.visualMapTitle'),
      legendAria: t('booking.sites.legendAria'),
      available: t('booking.sites.legendAvailable'),
      selected: t('booking.sites.legendSelected'),
      confirmed: t('booking.sites.legendBooked'),
      unavailable: t('booking.sites.legendUnavailable'),
      disabled: t('booking.sites.legendDisabled'),
      rowPrefix: t('booking.sites.row'),
      sitesCount: (count) => t('booking.sites.sitesCountLabel', { count }),
      categoryFallback: t('booking.sites.noCategory'),
      selectSite: t('booking.sites.selectSite'),
    };
  }

  // organizer (shared component; full organizer chrome is Phase 3)
  return {
    ...shared,
    title: t('calendar.layout.organizerTitle'),
    legendAria: t('calendar.layout.legendAria'),
    disabled: t('calendar.layout.notOpen'),
    sitesCount: (count) => t('calendar.layout.sitesCountSimple', { n: count }),
    categoryFallback: t('calendar.layout.noCategory'),
    selectSite: t('calendar.layout.site'),
  };
}

export const VISUAL_PARKING_COPY = Object.freeze({
  get organizer() {
    return visualParkingCopy('organizer');
  },
  get vendor() {
    return visualParkingCopy('vendor');
  },
  get public() {
    return visualParkingCopy('public');
  },
});
