import { strict as assert } from 'node:assert';
import process from 'node:process';
import { after, before, describe, it } from 'mocha';
import { env } from '../config/env.js';
import { createDriver, quitDriver } from '../helpers/driver.js';
import { phase39FixtureStatus } from '../helpers/phase39-fixtures.js';
import { waitForTestId } from '../helpers/wait.js';
import { setActiveDriver } from '../setup.js';

describe('Phase 3.11 cross-role closure', function () {
  let driver;
  let vendorToken;
  let organizerToken;
  let managementToken;
  let bookingId;
  let invoiceAmount;
  let bookingQuantity;
  let publicLayoutBeforeWithdrawal;

  const fixture = {
    eventId: Number(process.env.E2E_P39_EVENT_ID),
    foodCategoryId: Number(process.env.E2E_P39_FOOD_CATEGORY_ID),
    siteIds: JSON.parse(process.env.E2E_P39_SITE_IDS || '{}'),
    vendorEmail: process.env.E2E_VENDOR_EMAIL,
    vendorPassword: process.env.E2E_VENDOR_PASSWORD,
    organizerEmail: process.env.E2E_ORGANIZER_EMAIL,
    organizerPassword: process.env.E2E_ORGANIZER_PASSWORD,
    managementEmail: process.env.E2E_CMART_MANAGEMENT_EMAIL,
    managementPassword: process.env.E2E_CMART_MANAGEMENT_PASSWORD,
  };

  before(async function () {
    this.timeout(60000);
    driver = await createDriver();
    await setActiveDriver(driver);
    [vendorToken, organizerToken, managementToken] = await Promise.all([
      apiLogin(fixture.vendorEmail, fixture.vendorPassword),
      apiLogin(fixture.organizerEmail, fixture.organizerPassword),
      apiLogin(fixture.managementEmail, fixture.managementPassword),
    ]);
  });

  after(async function () {
    await quitDriver(driver);
  });

  it('Organizer verifies public readiness and publishes the category layout', async function () {
    const layout = await apiRequest(organizerToken, 'GET', `/organizer/events/${fixture.eventId}/layout`);
    assert.equal(layout.status, 200);
    assert.equal(layout.body.readiness.public_ready, true);
    assert.equal(layout.body.rows.length, 2);

    const publish = await apiRequest(
      organizerToken,
      'POST',
      `/organizer/events/${fixture.eventId}/layout/publish`,
      { entrance_note: 'Phase 3.11 visitor entrance.' },
    );
    assert.equal(publish.status, 200);
    assert.equal(publish.body.publication.published, true);

    const persisted = await apiRequest(organizerToken, 'GET', `/organizer/events/${fixture.eventId}/layout`);
    assert.equal(persisted.body.event.public_layout_published, true);
  });

  it('Community vendor creates a canonical category booking with full-event allocations', async function () {
    const created = await apiRequest(vendorToken, 'POST', '/bookings', {
      event_id: fixture.eventId,
      vendor_category_id: fixture.foodCategoryId,
      event_site_ids: [fixture.siteIds.B01, fixture.siteIds.B02],
      product_details: 'E2E-P311 complete category booking journey',
    });
    assert.equal(created.status, 201, JSON.stringify(created.body));

    bookingId = Number(created.body.booking.id);
    invoiceAmount = String(created.body.invoice.amount);
    bookingQuantity = Number(created.body.booking.site_selection.site_count);
    assert.equal(created.body.booking.vendor_category_id, fixture.foodCategoryId);
    assert.equal(created.body.booking.category_label_snapshot, 'Food & Beverages');
    assert.equal(created.body.booking.site_selection.active_day_count, 2);
    assert.equal(created.body.booking.site_selection.allocation_count, 4);

    const status = phase39FixtureStatus();
    assert.equal(status.latest_vendor_booking_id, bookingId);
    assert.equal(status.latest_vendor_category_id, fixture.foodCategoryId);
    assert.equal(status.latest_vendor_category_snapshot, 'Food & Beverages');
    assert.equal(status.vendor_allocations, 4);
  });

  it('Organizer reassigns compatible sites without changing category, quantity, or invoice', async function () {
    const approval = await apiRequest(organizerToken, 'PATCH', `/bookings/${bookingId}`, {
      approval_status: 'Approved',
    });
    assert.equal(approval.status, 200, JSON.stringify(approval.body));

    const options = await apiRequest(
      organizerToken,
      'GET',
      `/organizer/bookings/${bookingId}/site-reassignment-options`,
    );
    assert.equal(options.status, 200, JSON.stringify(options.body));
    const reassigned = await apiRequest(
      organizerToken,
      'PATCH',
      `/organizer/bookings/${bookingId}/site-assignment`,
      {
        event_site_ids: [fixture.siteIds.B02, fixture.siteIds.B03],
        assignment_fingerprint: options.body.requirements.assignment_fingerprint,
      },
    );
    assert.equal(reassigned.status, 200, JSON.stringify(reassigned.body));
    assert.equal(reassigned.body.booking.vendor_category_id, fixture.foodCategoryId);
    assert.equal(reassigned.body.booking.site_selection.site_count, bookingQuantity);
    assert.equal(String(reassigned.body.booking.invoice.amount), invoiceAmount);
    assert.equal(reassigned.body.category_placement.current_assignment.compatible, true);
  });

  it('Organizer mismatch reassignment requires acknowledgement and records immutable override history', async function () {
    const options = await apiRequest(
      organizerToken,
      'GET',
      `/organizer/bookings/${bookingId}/site-reassignment-options`,
    );
    const payload = {
      event_site_ids: [fixture.siteIds.A01, fixture.siteIds.A02],
      assignment_fingerprint: options.body.requirements.assignment_fingerprint,
    };

    const withoutAcknowledgement = await apiRequest(
      organizerToken,
      'PATCH',
      `/organizer/bookings/${bookingId}/site-assignment`,
      payload,
    );
    assert.equal(withoutAcknowledgement.status, 422);
    assert.equal(withoutAcknowledgement.body.error, 'CATEGORY_OVERRIDE_ACKNOWLEDGEMENT_REQUIRED');

    const overridden = await apiRequest(
      organizerToken,
      'PATCH',
      `/organizer/bookings/${bookingId}/site-assignment`,
      {
        ...payload,
        acknowledge_category_override: true,
        override_reason: 'Phase 3.11 controlled mismatch placement near the visitor entrance.',
      },
    );
    assert.equal(overridden.status, 200, JSON.stringify(overridden.body));
    assert.equal(overridden.body.booking.vendor_category_id, fixture.foodCategoryId);
    assert.equal(overridden.body.category_placement.current_assignment.compatible, false);
    assert.equal(phase39FixtureStatus().overrides, 1);
  });

  it('Guest sees the published physical map without occupancy, PII, payment, or override data', async function () {
    const response = await fetch(`${env.apiBaseUrl}/events/${fixture.eventId}/layout`);
    assert.equal(response.status, 200);
    publicLayoutBeforeWithdrawal = await response.json();
    const json = JSON.stringify(publicLayoutBeforeWithdrawal).toLowerCase();

    assert.match(json, /"label":"a01"/);
    assert.match(json, /"label":"b03"/);
    assert.doesNotMatch(
      json,
      /"(?:booking|allocation|occupancy|reserved|confirmed|invoice|payment|override|email|phone|active_lock)[^"]*":/,
    );
    assert.doesNotMatch(json, new RegExp(escapeRegex(fixture.vendorEmail), 'i'));

    await driver.manage().window().setRect({ width: 1280, height: 900 });
    await driver.get(env.baseUrl);
    await waitForTestId(driver, 'public-events-root', 30000);
    const browserProjection = await driver.executeAsyncScript(
      async (base, eventId, done) => {
        const response = await fetch(`${base}/events/${eventId}/layout`);
        done({
          status: response.status,
          body: await response.json(),
          token: localStorage.getItem('carboot_cmart_token'),
        });
      },
      env.apiBaseUrl,
      fixture.eventId,
    );
    assert.equal(browserProjection.status, 200);
    assert.equal(browserProjection.token, null);
    assert.equal(browserProjection.body.rows.length, 2);
  });

  it('Vendor withdrawal releases allocations while preserving the public physical map', async function () {
    const withdrawn = await apiRequest(vendorToken, 'PATCH', `/bookings/${bookingId}/withdraw`, {
      withdrawal_reason: 'Phase 3.11 closure withdrawal compatibility check.',
    });
    assert.equal(withdrawn.status, 200, JSON.stringify(withdrawn.body));
    assert.equal(withdrawn.body.booking.approval_status, 'Withdrawn');

    const status = phase39FixtureStatus();
    assert.equal(status.latest_vendor_status, 'Withdrawn');
    const afterResponse = await fetch(`${env.apiBaseUrl}/events/${fixture.eventId}/layout`);
    assert.equal(afterResponse.status, 200);
    const publicLayoutAfter = await afterResponse.json();
    assert.deepEqual(publicLayoutAfter.rows, publicLayoutBeforeWithdrawal.rows);
  });

  it('Guest, community, and CMart Management remain outside Organizer authority boundaries', async function () {
    const organizerPath = `/organizer/events/${fixture.eventId}/layout`;
    assert.equal((await apiRequest(null, 'GET', organizerPath)).status, 401);
    assert.equal((await apiRequest(vendorToken, 'GET', organizerPath)).status, 403);
    assert.equal((await apiRequest(managementToken, 'GET', organizerPath)).status, 403);

    const bookingPayload = {
      event_id: fixture.eventId,
      vendor_category_id: fixture.foodCategoryId,
      event_site_ids: [fixture.siteIds.B01],
      product_details: 'E2E-P311 forbidden organizer booking attempt',
    };
    assert.equal((await apiRequest(organizerToken, 'POST', '/bookings', bookingPayload)).status, 403);
    assert.equal((await apiRequest(null, 'POST', '/bookings', bookingPayload)).status, 401);
  });
});

async function apiLogin(email, password) {
  const response = await fetch(`${env.apiBaseUrl}/auth/login`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  });
  const body = await response.json();
  assert.equal(response.status, 200, JSON.stringify(body));
  return body.token;
}

async function apiRequest(token, method, path, body) {
  const headers = { Accept: 'application/json' };
  if (token) headers.Authorization = `Bearer ${token}`;
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  const options = { method, headers };
  if (body !== undefined) options.body = JSON.stringify(body);
  const response = await fetch(`${env.apiBaseUrl}${path}`, options);
  const text = await response.text();
  return {
    status: response.status,
    body: text ? JSON.parse(text) : null,
  };
}

function escapeRegex(value = '') {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
