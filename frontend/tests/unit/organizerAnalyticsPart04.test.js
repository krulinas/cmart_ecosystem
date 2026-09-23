import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const display = await import('../../src/utils/itemReservationStatusCore.js');
const panel = readFileSync(
  join(root, 'src/views/dashboards/organizer/OrganizerEventAnalyticsPanel.vue'),
  'utf8',
);
const reservationsPanel = readFileSync(
  join(root, 'src/views/dashboards/organizer/OrganizerItemReservationsPanel.vue'),
  'utf8',
);
const en = readFileSync(join(root, 'src/i18n/locales/en.js'), 'utf8');
const ms = readFileSync(join(root, 'src/i18n/locales/ms.js'), 'utf8');
const lifecycleStrip = readFileSync(
  join(root, 'src/components/reservations/ReservationLifecycleStrip.vue'),
  'utf8',
);

const tEn = (key) => {
  // Minimal stub: evaluate keys from embedded en catalogue via string search is hard;
  // use the real label maps through a tiny resolver that mirrors production keys.
  const map = {
    'status.pending_charge': 'Awaiting charge decision',
    'status.confirmed': 'Confirmed',
    'status.cancelled': 'Cancelled',
    'status.Cancelled': 'Cancelled',
    'status.expired': 'Expired',
    'status.completed': 'Completed',
    'status.required': 'Charge required',
    'status.charge_confirmed': 'Charge confirmed',
    'status.waived': 'Charge waived',
    'status.not_required': 'No charge required',
    'status.charge_cancelled': 'Charge cancelled',
    'common.unknown': 'Unknown',
    'reservation.lifecycle.received': 'Reservation received',
    'reservation.lifecycle.chargeDecision': 'Charge decision',
    'reservation.lifecycle.noChargeRequired': 'No charge required',
    'reservation.lifecycle.chargeConfirmed': 'Charge confirmed',
    'reservation.lifecycle.chargeWaived': 'Charge waived',
    'reservation.lifecycle.confirmed': 'Confirmed',
    'reservation.lifecycle.completed': 'Completed',
    'reservation.lifecycle.cancelled': 'Cancelled',
    'reservation.lifecycle.expired': 'Expired',
    'reservation.lifecycle.nextConfirmOrWaive': 'Next: confirm or waive the service charge',
    'reservation.lifecycle.nextMarkCollected': 'Next: mark item as collected',
  };
  return map[key] || key;
};

const tMs = (key) => {
  const map = {
    'status.pending_charge': 'Menunggu keputusan caj',
    'status.confirmed': 'Disahkan',
    'status.cancelled': 'Dibatalkan',
    'status.expired': 'Tamat tempoh',
    'status.completed': 'Selesai',
    'status.required': 'Caj diperlukan',
    'status.charge_confirmed': 'Caj disahkan',
    'status.waived': 'Caj dikecualikan',
    'status.not_required': 'Tiada caj diperlukan',
    'status.charge_cancelled': 'Caj dibatalkan',
    'common.unknown': 'Tidak diketahui',
  };
  return map[key] || key;
};

describe('analytics hub four-tab navigation', () => {
  it('defines exactly four hub tabs including vendor-insights', () => {
    assert.match(panel, /id: 'overview'/);
    assert.match(panel, /id: 'vendor-insights'/);
    assert.match(panel, /id: 'operations'/);
    assert.match(panel, /id: 'data-sources'/);
    assert.match(panel, /TAB_IDS = new Set\(\['overview', 'vendor-insights', 'operations', 'data-sources'\]\)/);
    assert.equal(panel.includes("id: 'survey-results'"), false);
    assert.equal(panel.includes("id: 'comments'"), false);
  });

  it('maps old tab IDs to vendor-insights', () => {
    assert.match(panel, /'survey-results': 'vendor-insights'/);
    assert.match(panel, /comments: 'vendor-insights'/);
  });

  it('keeps feedback and survey content under Vendor Insights', () => {
    assert.match(panel, /data-testid="analytics-vendor-insights"/);
    assert.match(panel, /data-testid="community-feedback-section"/);
    assert.match(panel, /data-testid="vendor-survey-results-section"/);
    assert.match(panel, /data-testid="vendor-comments-themes-section"/);
    assert.match(panel, /SurveyResultsPanel/);
    assert.match(panel, /EventCommentsWordCloud/);
  });

  it('does not mount data-source controls outside Data Sources', () => {
    assert.equal(panel.includes('AnalyticsDataSourceBadge'), false);
    assert.match(panel, /data-testid="analytics-data-sources-tab"/);
    assert.match(panel, /AnalyticsDataSourceManager/);
    const managerIdx = panel.indexOf('<AnalyticsDataSourceManager');
    const tabIdx = panel.indexOf('data-testid="analytics-data-sources-tab"');
    assert.ok(managerIdx > tabIdx);
  });
});

describe('system data vs survey csv separation', () => {
  it('labels system vendors and survey respondents separately', () => {
    assert.match(panel, /participatingVendorsSystem/);
    assert.match(panel, /surveyRespondentsCsv/);
    assert.match(panel, /vendorCategorySystemTitle/);
    assert.match(panel, /communityRatingLabel/);
    assert.match(en, /experienceRatingSourceSub/);
  });

  it('does not add system vendor counts to survey respondent counts', () => {
    assert.equal(/respondentCount\s*\+\s*approvedCount|approvedCount\s*\+\s*respondentCount/.test(panel), false);
    assert.equal(/average_rating\s*\+\s*|experience_rating.*average_rating/.test(panel), false);
  });
});

describe('reservation and charge status labels', () => {
  it('produces readable English and Malay labels for every known reservation status', () => {
    for (const value of display.RESERVATION_STATUS_VALUES) {
      const enLabel = display.reservationStatusLabel(value, tEn);
      const msLabel = display.reservationStatusLabel(value, tMs);
      assert.equal(enLabel.includes('status.'), false, enLabel);
      assert.equal(msLabel.includes('status.'), false, msLabel);
      assert.ok(enLabel.length > 2);
      assert.ok(msLabel.length > 2);
    }
  });

  it('produces readable English and Malay labels for every known charge status', () => {
    for (const value of display.CHARGE_STATUS_VALUES) {
      const enLabel = display.chargeStatusLabel(value, tEn);
      const msLabel = display.chargeStatusLabel(value, tMs);
      assert.equal(enLabel.includes('status.'), false, enLabel);
      assert.equal(msLabel.includes('status.'), false, msLabel);
    }
  });

  it('humanizes unknown values instead of leaking status.* keys', () => {
    assert.equal(display.reservationStatusLabel('status.weird_thing', tEn), 'Weird Thing');
    assert.equal(display.humanizeStatusKey('status.foo_bar'), 'Foo Bar');
  });

  it('normalizes Cancelled casing at the display boundary', () => {
    assert.equal(display.reservationStatusLabel('Cancelled', tEn), 'Cancelled');
    assert.equal(display.normalizeStatusKey('Cancelled'), 'cancelled');
  });

  it('uses translated filter options in the organizer reservations panel', () => {
    assert.match(reservationsPanel, /reservationStatusFilterOptions/);
    assert.match(reservationsPanel, /chargeStatusFilterOptions/);
    assert.equal(reservationsPanel.includes('v-for="(label, value) in RESERVATION_STATUS_LABELS"'), false);
    assert.equal(reservationsPanel.includes('v-for="(label, value) in CHARGE_STATUS_LABELS"'), false);
  });

  it('keeps suggested English wording in the locale catalogue', () => {
    assert.match(en, /Awaiting charge decision/);
    assert.match(en, /Charge waived/);
    assert.match(ms, /Menunggu keputusan caj/);
    assert.match(ms, /Caj dikecualikan/);
  });
});

describe('reservation lifecycle', () => {
  it('models required-charge, no-charge, waived, confirmed, completed, cancelled and expired paths', () => {
    const required = display.buildReservationLifecycle({
      reservation_status: 'pending_charge',
      charge_status: 'required',
      service_fee_amount: 5,
    }, tEn);
    assert.equal(required.stages.find((s) => s.id === 'charge_decision').state, 'current');
    assert.match(required.nextAction, /confirm or waive/i);

    const noCharge = display.buildReservationLifecycle({
      reservation_status: 'confirmed',
      charge_status: 'not_required',
      service_fee_amount: 0,
    }, tEn);
    assert.equal(noCharge.stages.find((s) => s.id === 'charge_decision').label, 'No charge required');
    assert.equal(noCharge.terminal, null);

    const waived = display.buildReservationLifecycle({
      reservation_status: 'confirmed',
      charge_status: 'waived',
      service_fee_amount: 5,
    }, tEn);
    assert.equal(waived.stages.find((s) => s.id === 'charge_decision').label, 'Charge waived');
    assert.equal(waived.stages.find((s) => s.id === 'confirmed').state, 'current');

    const completed = display.buildReservationLifecycle({
      reservation_status: 'completed',
      charge_status: 'confirmed',
    }, tEn);
    assert.equal(completed.stages.find((s) => s.id === 'completed').state, 'complete');

    const cancelled = display.buildReservationLifecycle({
      reservation_status: 'cancelled',
      charge_status: 'cancelled',
    }, tEn);
    assert.equal(cancelled.terminal?.id, 'cancelled');

    const expired = display.buildReservationLifecycle({
      reservation_status: 'expired',
      charge_status: 'confirmed',
    }, tEn);
    assert.equal(expired.terminal?.id, 'expired');
  });

  it('renders an accessible lifecycle strip in the reservation detail modal', () => {
    assert.match(reservationsPanel, /ReservationLifecycleStrip/);
    assert.match(lifecycleStrip, /data-testid="reservation-lifecycle"/);
    assert.match(lifecycleStrip, /aria-label/);
    assert.match(lifecycleStrip, /reservation-lifecycle-fallback/);
    assert.match(lifecycleStrip, /sm:flex-row/);
  });
});
