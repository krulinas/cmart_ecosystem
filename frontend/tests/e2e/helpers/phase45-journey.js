import { strict as assert } from 'node:assert';
import { By } from 'selenium-webdriver';
import { env } from '../config/env.js';
import {
  loginAsCommunityMember,
  loginAsCommunityVendor,
  loginAsCmartManagement,
  loginAsOrganizer,
  logout,
  managementApiRequest,
} from './auth.js';
import { waitForTestId } from './wait.js';

export function readPhase45EnvFixture() {
  return {
    eventId: Number(process.env.E2E_P45_EVENT_ID),
    eventTitle: process.env.E2E_P45_EVENT_TITLE || process.env.E2E_BOOKING_EVENT_NAME,
    serviceFeeAmount: process.env.E2E_P45_SERVICE_FEE_AMOUNT || '15.00',
    vendorBookingId: Number(process.env.E2E_P45_VENDOR_BOOKING_ID),
    vendorEmail: process.env.E2E_VENDOR_EMAIL,
    vendorPassword: process.env.E2E_VENDOR_PASSWORD,
    unrelatedVendorEmail: process.env.E2E_VENDOR_B_EMAIL,
    unrelatedVendorPassword: process.env.E2E_VENDOR_B_PASSWORD,
    reserverEmail: process.env.E2E_P45_RESERVER_EMAIL,
    reserverPassword: process.env.E2E_P45_RESERVER_PASSWORD,
    competitorEmail: process.env.E2E_P45_COMPETITOR_EMAIL,
    competitorPassword: process.env.E2E_P45_COMPETITOR_PASSWORD,
    organizerEmail: process.env.E2E_ORGANIZER_EMAIL,
    organizerPassword: process.env.E2E_ORGANIZER_PASSWORD,
    managementEmail: process.env.E2E_CMART_MANAGEMENT_EMAIL,
    managementPassword: process.env.E2E_CMART_MANAGEMENT_PASSWORD,
    successItemId: Number(process.env.E2E_P45_SUCCESS_ITEM_ID),
    conflictItemId: Number(process.env.E2E_P45_CONFLICT_ITEM_ID),
    cancelItemId: Number(process.env.E2E_P45_CANCEL_ITEM_ID),
    expiryItemId: Number(process.env.E2E_P45_EXPIRY_ITEM_ID),
    completionItemId: Number(process.env.E2E_P45_COMPLETION_ITEM_ID),
    accessItemId: Number(process.env.E2E_P45_ACCESS_ITEM_ID),
    ownerOnlyItemId: Number(process.env.E2E_P45_OWNER_ONLY_ITEM_ID),
    heldReservationReference: process.env.E2E_P45_HELD_RESERVATION_REFERENCE,
  };
}

export async function apiLogin(email, password) {
  const response = await fetch(`${env.apiBaseUrl}/auth/login`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  });
  const body = await response.json();
  assert.equal(response.status, 200, JSON.stringify(body));
  return body.token;
}

export async function apiRequest(token, method, path, body) {
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

export async function snapshotFixtureBooking(token, bookingId) {
  const response = await apiRequest(token, 'GET', `/bookings/${bookingId}`);
  assert.equal(response.status, 200, JSON.stringify(response.body));
  const booking = response.body.booking || response.body;
  const invoice = booking.invoice || null;
  const siteSelection = booking.site_selection || null;
  return {
    id: booking.id,
    approval_status: booking.approval_status,
    withdrawn_at: booking.withdrawn_at ?? null,
    product_details: booking.product_details ?? null,
    invoice: invoice
      ? {
          id: invoice.id,
          amount: invoice.amount != null ? String(invoice.amount) : null,
          payment_status: invoice.payment_status ?? invoice.status ?? null,
          payment_proof_path: invoice.payment_proof_path ?? null,
        }
      : null,
    site_selection: siteSelection
      ? {
          site_count: siteSelection.site_count ?? null,
          active_day_count: siteSelection.active_day_count ?? null,
          site_ids: [...(siteSelection.site_ids || siteSelection.event_site_ids || [])].sort(),
        }
      : null,
    audit_count: Array.isArray(booking.audit_timeline)
      ? booking.audit_timeline.length
      : Array.isArray(booking.audit_logs)
        ? booking.audit_logs.length
        : 0,
  };
}

export async function assertBookingUnchanged(token, bookingId, before) {
  const after = await snapshotFixtureBooking(token, bookingId);
  assert.deepEqual(after, before, 'Fixture booking financial/approval state must remain unchanged.');
  return after;
}

export async function loginReserver(driver, fixture) {
  await loginAsCommunityMember(
    driver,
    { email: fixture.reserverEmail, password: fixture.reserverPassword },
    { roleLabel: 'Phase 4.5 reserver', requireVisitorHome: true },
  );
}

export async function loginCompetitor(driver, fixture) {
  await loginAsCommunityMember(
    driver,
    { email: fixture.competitorEmail, password: fixture.competitorPassword },
    { roleLabel: 'Phase 4.5 competitor', requireVisitorHome: true },
  );
}

export async function loginOwnerVendor(driver, fixture) {
  await loginAsCommunityVendor(
    driver,
    { email: fixture.vendorEmail, password: fixture.vendorPassword },
    { roleLabel: 'Phase 4.5 owner vendor' },
  );
}

export async function loginUnrelatedVendor(driver, fixture) {
  await loginAsCommunityVendor(
    driver,
    { email: fixture.unrelatedVendorEmail, password: fixture.unrelatedVendorPassword },
    { roleLabel: 'Phase 4.5 unrelated vendor' },
  );
}

export async function openMarketplaceItem(driver, itemId) {
  await driver.get(`${env.baseUrl}/marketplace?item=${encodeURIComponent(itemId)}`);
  await waitForTestId(driver, 'marketplace-preview-root', 20000);
  await waitForTestId(driver, 'public-detail-modal', 20000);
}

export async function reserveOpenMarketplaceItem(driver) {
  const cta = await waitForTestId(driver, 'marketplace-reserve-cta', 15000);
  await cta.click();
  await waitForTestId(driver, 'item-reservation-confirm-modal', 10000);
  const submit = await waitForTestId(driver, 'item-reservation-confirm-submit', 10000);
  await submit.click();
  const success = await waitForTestId(driver, 'item-reservation-success', 20000);
  assert.ok(success);
  const referenceEl = await waitForTestId(driver, 'reservation-success-reference', 10000);
  return (await referenceEl.getText()).trim();
}

export async function attemptReserveExpectConflict(driver) {
  const cta = await waitForTestId(driver, 'marketplace-reserve-cta', 15000);
  await cta.click();
  await waitForTestId(driver, 'item-reservation-confirm-modal', 10000);
  const submit = await waitForTestId(driver, 'item-reservation-confirm-submit', 10000);
  await submit.click();

  await driver.wait(async () => {
    const toasts = await driver.findElements(By.css('.Vue-Toastification__toast-body'));
    for (const toast of toasts) {
      const text = (await toast.getText()).toLowerCase();
      if (text.includes('already reserved') || text.includes('no longer available')) {
        return true;
      }
    }
    const closed = await driver.findElements(By.css('[data-testid="item-reservation-confirm-modal"]'));
    return closed.length === 0;
  }, 20000, 'Expected item_already_reserved conflict feedback.');
}

export async function openMyReservations(driver) {
  await driver.get(`${env.baseUrl}/community#my-item-reservations`);
  await waitForTestId(driver, 'my-item-reservations-root', 20000);
}

export async function findMyReservationRow(driver, publicReference) {
  await driver.wait(async () => {
    const rows = await driver.findElements(
      By.css(`[data-testid="my-reservation-row"][data-public-reference="${publicReference}"]`),
    );
    return rows.length > 0;
  }, 20000, `My reservation row ${publicReference} not found.`);
  return driver.findElement(
    By.css(`[data-testid="my-reservation-row"][data-public-reference="${publicReference}"]`),
  );
}

export async function openOrganizerReservationQueue(driver, eventId) {
  await driver.get(`${env.baseUrl}/admin?eventId=${encodeURIComponent(eventId)}#item-reservations`);
  await waitForTestId(driver, 'management-dashboard-root', 20000);
  await waitForTestId(driver, 'organizer-item-reservations-panel', 20000);
  await waitForTestId(driver, 'organizer-reservation-event-select', 15000);

  await driver.wait(async () => {
    const select = await driver.findElement(By.css('[data-testid="organizer-reservation-event-select"]'));
    const selected = await select.getAttribute('value');
    if (String(selected) === String(eventId)) {
      return true;
    }
    await driver.executeScript(
      (el, value) => {
        el.value = String(value);
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
      },
      select,
      eventId,
    );
    return false;
  }, 20000, `Organizer event select did not settle on event ${eventId}.`);

  await waitForTestId(driver, 'organizer-reservations-queue', 20000);
}

export async function openOrganizerReservationDetail(driver, publicReference) {
  const rowSelector = `[data-testid="organizer-reservation-row"][data-public-reference="${publicReference}"]`;
  await driver.wait(async () => {
    const rows = await driver.findElements(By.css(rowSelector));
    for (const row of rows) {
      try {
        if (await row.isDisplayed()) return true;
      } catch (error) {
        if (error.name !== 'StaleElementReferenceError') throw error;
      }
    }
    return false;
  }, 20000, `Organizer reservation row ${publicReference} not visible.`);

  const rows = await driver.findElements(By.css(rowSelector));
  let openButton = null;
  for (const row of rows) {
    if (!(await row.isDisplayed())) continue;
    const buttons = await row.findElements(By.css('[data-testid="organizer-reservation-open"]'));
    for (const button of buttons) {
      if (await button.isDisplayed()) {
        openButton = button;
        break;
      }
    }
    if (openButton) break;
  }
  assert.ok(openButton, `Organizer open button missing for ${publicReference}`);

  await driver.executeScript(
    'arguments[0].scrollIntoView({block:"center", inline:"nearest"});',
    openButton,
  );
  try {
    await openButton.click();
  } catch {
    await driver.executeScript('arguments[0].click();', openButton);
  }
  await waitForTestId(driver, 'organizer-reservation-detail-modal', 15000);
}

export async function submitOrganizerAction(driver, mode, note, { acknowledgeNoRefund = false } = {}) {
  const openTestId = {
    confirm: 'organizer-confirm-charge-open',
    waive: 'organizer-waive-charge-open',
    cancel: 'organizer-cancel-open',
    expire: 'organizer-expire-open',
    complete: 'organizer-complete-open',
  }[mode];
  assert.ok(openTestId, `Unknown organizer action mode: ${mode}`);

  await waitForTestId(driver, 'organizer-reservation-detail-modal', 15000);
  await driver.wait(async () => {
    const loadingTexts = await driver.findElements(By.css('[data-testid="organizer-reservation-detail-modal"]'));
    if (!loadingTexts.length) return false;
    const text = (await loadingTexts[0].getText()).toLowerCase();
    return !text.includes('loading detail');
  }, 15000, 'Organizer reservation detail still loading.');

  await waitForTestId(driver, 'organizer-reservation-actions', 15000);
  const open = await waitForTestId(driver, openTestId, 15000);
  await driver.executeScript(
    (el) => {
      el.scrollIntoView({ block: 'center', inline: 'nearest' });
      el.focus();
      el.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
    },
    open,
  );

  await driver.wait(async () => {
    const forms = await driver.findElements(By.css('[data-testid="organizer-reservation-action-form"]'));
    for (const candidate of forms) {
      try {
        if (await candidate.isDisplayed()) return true;
      } catch (error) {
        if (error.name !== 'StaleElementReferenceError') throw error;
      }
    }
    return false;
  }, 15000, `Organizer action form did not open for mode ${mode}.`);
  const form = await waitForTestId(driver, 'organizer-reservation-action-form', 5000);
  const actionMode = await form.getAttribute('data-action-mode');
  assert.equal(actionMode, mode);

  if (note != null) {
    const noteInput = await waitForTestId(driver, 'organizer-action-note', 10000);
    await driver.executeScript(
      (el, value) => {
        el.scrollIntoView({ block: 'center', inline: 'nearest' });
        el.focus();
        const setter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value').set;
        setter.call(el, value);
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
      },
      noteInput,
      note,
    );
  }

  if (acknowledgeNoRefund) {
    const checkbox = await waitForTestId(driver, 'organizer-no-refund-acknowledgement', 10000);
    await driver.executeScript(
      (el) => {
        el.scrollIntoView({ block: 'center', inline: 'nearest' });
        if (!el.checked) {
          el.click();
        }
        el.checked = true;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
      },
      checkbox,
    );
  }

  const submit = await waitForTestId(driver, 'organizer-action-submit', 10000);
  await driver.wait(async () => {
    const disabled = await submit.getAttribute('disabled');
    return disabled === null || disabled === 'false';
  }, 10000, `Organizer ${mode} submit stayed disabled.`);
  try {
    await submit.click();
  } catch {
    await driver.executeScript('arguments[0].click();', submit);
  }
  await driver.wait(async () => {
    try {
      const forms = await driver.findElements(By.css('[data-testid="organizer-reservation-action-form"]'));
      for (const candidate of forms) {
        try {
          if (await candidate.isDisplayed()) return false;
        } catch (error) {
          if (error.name !== 'StaleElementReferenceError') throw error;
        }
      }
      return true;
    } catch (error) {
      if (error.name === 'StaleElementReferenceError') return false;
      throw error;
    }
  }, 20000, `Organizer ${mode} action form did not close.`);
}

export async function assertAuditActionPresent(driver, action) {
  await driver.wait(async () => {
    try {
      const items = await driver.findElements(
        By.css(`[data-testid="organizer-reservation-audit-item"][data-audit-action="${action}"]`),
      );
      for (const item of items) {
        try {
          if (await item.isDisplayed()) return true;
        } catch (error) {
          if (error.name !== 'StaleElementReferenceError') throw error;
        }
      }
      return false;
    } catch (error) {
      if (error.name === 'StaleElementReferenceError') return false;
      throw error;
    }
  }, 15000, `Expected audit action ${action}.`);
}

export async function openVendorReservations(driver) {
  await driver.get(`${env.baseUrl}/dashboard#vendor-item-reservations`);
  await waitForTestId(driver, 'vendor-item-reservations-root', 20000);
}

export async function findVendorReservationRow(driver, publicReference) {
  await driver.wait(async () => {
    const rows = await driver.findElements(
      By.css(`[data-testid="vendor-reservation-row"][data-public-reference="${publicReference}"]`),
    );
    return rows.length > 0;
  }, 20000, `Vendor reservation row ${publicReference} not found.`);
  return driver.findElement(
    By.css(`[data-testid="vendor-reservation-row"][data-public-reference="${publicReference}"]`),
  );
}

export {
  loginAsOrganizer,
  loginAsCmartManagement,
  logout,
  managementApiRequest,
};
