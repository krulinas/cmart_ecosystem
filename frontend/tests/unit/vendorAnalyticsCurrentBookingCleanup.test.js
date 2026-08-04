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

  it('keeps a compact booking summary and details entry on the slim dashboard', () => {
    assert.equal(dashboardSource.includes('data-testid="my-bookings-root"'), true);
    assert.equal(dashboardSource.includes('data-testid="booking-view-details"'), true);
    assert.equal(dashboardSource.includes('openBookingDetails'), true);
    assert.equal(dashboardSource.includes('VendorBookingDetailsModal'), true);
    assert.equal(dashboardSource.includes(':bookings="validBookings"'), false);
    assert.equal(dashboardSource.includes('@view-booking="openBookingDetails"'), false);
    assert.equal(dashboardSource.includes('vendor-current-booking-status-card'), false);
    assert.equal(dashboardSource.includes('VendorDashboardFocus'), true);
  });

  it('does not mount secondary workspaces or chip navigation on the slim dashboard', () => {
    assert.equal(dashboardSource.includes('vendor-dashboard-section-nav'), false);
    assert.equal(dashboardSource.includes('VENDOR_DASHBOARD_SECTION_LINKS'), false);
    assert.equal(dashboardSource.includes('VendorItemManager'), false);
    assert.equal(dashboardSource.includes('VendorItemReservationsPanel'), false);
    assert.equal(dashboardSource.includes('MyItemReservationsPanel'), false);
    assert.equal(dashboardSource.includes('VendorEventPassesPanel'), false);
    assert.equal(dashboardSource.includes('VendorBusinessProfileManager'), false);
    assert.equal(dashboardSource.includes('VendorAnalyticsDashboard'), false);
    assert.equal(dashboardSource.includes('VendorHistoryReceipts'), false);
    assert.equal(dashboardSource.includes('fetchVendorInsights'), false);
    assert.equal(dashboardSource.includes('fetchPaymentHistory'), false);
    assert.equal(dashboardSource.includes('/vendor/analytics/me'), false);
    assert.equal(dashboardSource.includes('/vendor/history-receipts'), false);
  });
});
