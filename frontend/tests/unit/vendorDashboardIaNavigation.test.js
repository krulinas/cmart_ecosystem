import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const navigationSource = readFileSync(join(root, 'src/config/navigation.js'), 'utf8');
const routerSource = readFileSync(join(root, 'src/router/router.js'), 'utf8');
const navbarSource = readFileSync(join(root, 'src/components/navigation/AppNavbar.vue'), 'utf8');
const legacySource = readFileSync(join(root, 'src/utils/vendorDashboardLegacy.js'), 'utf8');
const reservationPathSource = readFileSync(join(root, 'src/utils/itemReservationDisplay.js'), 'utf8');
const checkoutSource = readFileSync(join(root, 'src/views/vendor/VendorCheckoutPage.vue'), 'utf8');

describe('vendor dashboard IA navigation', () => {
  it('defines Manage, Explore (with My Reservations), and Account destinations', () => {
    assert.equal(navigationSource.includes("label: 'Manage'"), true);
    assert.equal(navigationSource.includes("to: '/vendor/manage/bookings'"), true);
    assert.equal(navigationSource.includes("to: '/vendor/manage/event-passes'"), true);
    assert.equal(navigationSource.includes("to: '/vendor/manage/items'"), true);
    assert.equal(navigationSource.includes("to: '/vendor/manage/customer-reservations'"), true);
    assert.equal(navigationSource.includes("label: 'My Items'"), true);
    assert.equal(navigationSource.includes("label: 'Customer Reservations'"), true);
    assert.equal(navigationSource.includes("to: '/my-reservations'"), true);
    assert.equal(navigationSource.includes("to: '/vendor/payment-history'"), true);
    assert.equal(navigationSource.includes("to: '/vendor/insights'"), true);
    assert.equal(navigationSource.includes('export const VENDOR_DASHBOARD_SECTION_LINKS = [];'), true);
  });

  it('registers discrete vendor routes and legacy hash redirects in the router', () => {
    for (const path of [
      '/vendor/manage/bookings',
      '/vendor/manage/event-passes',
      '/vendor/manage/items',
      '/vendor/manage/customer-reservations',
      '/my-reservations',
      '/vendor/insights',
      '/vendor/payment-history',
    ]) {
      assert.equal(routerSource.includes(`path: '${path}'`), true, `missing route ${path}`);
    }
    assert.equal(routerSource.includes('resolveVendorDashboardLegacyHash'), true);
    assert.equal(legacySource.includes("'vendor-my-bookings': '/vendor/manage/bookings'"), false);
    assert.equal(navigationSource.includes("'vendor-my-bookings': '/vendor/manage/bookings'"), true);
    assert.equal(navigationSource.includes("'vendor-reuse-listings': '/vendor/manage/items'"), true);
    assert.equal(navigationSource.includes("'my-item-reservations': '/my-reservations'"), true);
    assert.equal(navigationSource.includes("'vendor-analytics': '/vendor/insights'"), true);
    assert.equal(navigationSource.includes("'vendor-history-receipts': '/vendor/payment-history'"), true);
  });

  it('wires Manage into the vendor navbar menu set', () => {
    assert.equal(navbarSource.includes('VENDOR_MANAGE_MENU'), true);
    assert.equal(
      navbarSource.includes('[VENDOR_MANAGE_MENU, VENDOR_EXPLORE_MENU, VENDOR_ACCOUNT_MENU]'),
      true,
    );
  });

  it('routes vendor buyer reservations and checkout returns to discrete paths', () => {
    assert.equal(reservationPathSource.includes("return '/my-reservations'"), true);
    assert.equal(reservationPathSource.includes("/dashboard#my-item-reservations"), false);
    assert.equal(checkoutSource.includes('/vendor/manage/bookings'), true);
    assert.equal(checkoutSource.includes('/dashboard#vendor-my-bookings'), false);
  });
});
