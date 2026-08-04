import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const analyticsSource = readFileSync(
  join(root, 'src/components/VendorAnalyticsDashboard.vue'),
  'utf8',
);
const dashboardSource = readFileSync(
  join(root, 'src/views/dashboards/VendorDashboard.vue'),
  'utf8',
);

describe('vendor analytics current-booking card cleanup', () => {
  it('does not render the duplicated Current Booking card in Analytics', () => {
    assert.equal(analyticsSource.includes('vendor-current-booking-status-card'), false);
    assert.equal(analyticsSource.includes('vendor-current-booking-view-button'), false);
    assert.equal(analyticsSource.includes('Current Booking'), false);
    assert.equal(analyticsSource.includes('actionableBookingCard'), false);
    assert.equal(analyticsSource.includes('resolveActionableCurrentBooking'), false);
    assert.equal(analyticsSource.includes('view-booking'), false);
    assert.equal(analyticsSource.includes('compact current-booking card'), false);
  });

  it('does not accept bookings prop on VendorAnalyticsDashboard', () => {
    assert.equal(/bookings:\s*\{\s*type:\s*Array/.test(analyticsSource), false);
    assert.equal(analyticsSource.includes(":bookings="), false);
  });

  it('keeps My Bookings as the primary booking details entry point', () => {
    assert.equal(dashboardSource.includes('data-testid="my-bookings-root"'), true);
    assert.equal(dashboardSource.includes('data-testid="booking-view-details"'), true);
    assert.equal(dashboardSource.includes('openBookingDetails'), true);
    assert.equal(dashboardSource.includes('VendorBookingDetailsModal'), true);
    assert.equal(dashboardSource.includes(':bookings="validBookings"'), false);
    assert.equal(dashboardSource.includes('@view-booking="openBookingDetails"'), false);
    assert.equal(dashboardSource.includes('vendor-current-booking-status-card'), false);
    assert.equal(dashboardSource.includes('View insights'), true);
  });

  it('keeps payment history behind progressive disclosure', () => {
    assert.equal(dashboardSource.includes('hasPaymentHistoryEntry'), true);
    assert.equal(dashboardSource.includes('showReceipts'), true);
    assert.equal(dashboardSource.includes('Payment history'), true);
    assert.equal(dashboardSource.includes('v-if="hasPaymentHistoryEntry"'), true);
    assert.equal(
      /VendorHistoryReceipts[\s\S]*v-else/.test(dashboardSource)
        || dashboardSource.includes('v-else\n          :records="paymentRecords"')
        || dashboardSource.includes('<VendorHistoryReceipts\n          v-else'),
      true,
    );
  });
});
