import { strict as assert } from 'node:assert';
import { By } from 'selenium-webdriver';
import { env } from '../config/env.js';
import { loginAsCommunityVisitor } from './auth.js';
import {
  fillInputValue,
  prepareVendorBookingPage,
  setSelectValue,
} from './booking.js';
import { selectBookingCategory } from './vendor-categories.js';
import { captureFailureDiagnostics } from './diagnostics.js';
import { ensureGuestSession } from './guest-access.js';
import { clearBrowserSession } from './session.js';
import { waitForTestId, waitForTestIdHidden, waitForUrlContains } from './wait.js';

const EXPECTED_PUBLIC_NAV_LABELS = [
  'Community',
  'Carboot Preview',
  'Events',
  'Become a Vendor',
];

const FORBIDDEN_NAV_LABELS = [
  'Browse Events',
  'Explore CMart',
  'Event Calendar',
];

const BLOCKING_COPY_PATTERNS = [
  /403\s*forbidden/i,
  /approved vendor required/i,
  /vendor access has not been approved/i,
  /you must be an approved vendor/i,
];

const ONBOARDING_COPY_PATTERNS = [
  /start vendor booking/i,
  /choose an event/i,
  /cmart staff will review/i,
  /review your application/i,
  /pending/i,
];

/**
 * Register a fresh community visitor with no vendor activity via the public API.
 */
export async function registerCommunityVisitorAccount() {
  const suffix = Date.now();
  const email = `e2e-visitor-${suffix}@example.com`;
  const password = 'password123';

  const response = await fetch(`${env.apiBaseUrl}/auth/register`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({
      name: `E2E Community Visitor ${suffix}`,
      email,
      password,
      password_confirmation: password,
    }),
  });

  const bodyText = await response.text();
  assert.equal(
    response.status,
    201,
    `Community visitor registration failed (HTTP ${response.status}): ${bodyText.slice(0, 240)}`,
  );

  return { email, password, suffix };
}

export async function loginFreshCommunityVisitor(driver) {
  const account = await registerCommunityVisitorAccount();
  await loginAsCommunityVisitor(driver, account, { roleLabel: 'Fresh community visitor' });
  return account;
}

export async function visitCommunityPortal(driver, { hash = '' } = {}) {
  const path = hash ? `/community${hash.startsWith('#') ? hash : `#${hash}`}` : '/community';
  await driver.get(`${env.baseUrl}${path}`);
  await waitForTestId(driver, 'community-portal-root', 20000);
}

export async function assertCommunityNavLabels(driver) {
  const nav = await driver.findElement(By.css('nav'));
  const navText = await nav.getText();

  for (const label of EXPECTED_PUBLIC_NAV_LABELS) {
    assert.ok(
      navText.includes(label),
      `Community nav must include "${label}". Nav text: ${navText}`,
    );
  }

  for (const label of FORBIDDEN_NAV_LABELS) {
    assert.ok(
      !navText.includes(label),
      `Community nav must not include duplicate legacy label "${label}".`,
    );
  }
}

export async function assertNotOnVendorDashboard(driver) {
  const url = await driver.getCurrentUrl();
  assert.ok(
    !url.includes('/dashboard'),
    `Community visitor must not land on vendor dashboard. URL: ${url}`,
  );

  const dashboardRoots = await driver.findElements(By.css('[data-testid="vendor-dashboard-root"]'));
  for (const root of dashboardRoots) {
    if (await root.isDisplayed()) {
      assert.fail('Vendor dashboard root must not be visible for a plain community visitor.');
    }
  }
}

export async function clickBecomeVendorNav(driver) {
  const navLink = await driver.findElement(By.css('[data-testid="nav-become-vendor"]'));
  await driver.executeScript('arguments[0].scrollIntoView({block: "center"});', navLink);
  await navLink.click();
  await waitForTestId(driver, 'become-vendor-section', 15000);
}

export async function assertBecomeVendorSection(driver) {
  const section = await waitForTestId(driver, 'become-vendor-section');
  assert.equal(await section.isDisplayed(), true, 'Become a vendor section must be visible.');

  const cta = await waitForTestId(driver, 'start-vendor-booking-cta');
  const href = await cta.getAttribute('href');
  assert.ok(
    href && (href.includes('/vendor-booking') || href.includes('/login') || href.includes('/register')),
    `Start Vendor Booking CTA must target vendor booking or auth. href=${href}`,
  );

  const ctaText = (await cta.getText()).trim();
  assert.match(
    ctaText,
    /start vendor booking|make a booking|book a space/i,
    `Unexpected primary CTA label: "${ctaText}"`,
  );
}

export async function clickStartVendorBookingCta(driver) {
  const cta = await waitForTestId(driver, 'start-vendor-booking-cta');
  await driver.executeScript('arguments[0].scrollIntoView({block: "center"});', cta);
  await cta.click();
}

export async function assertVendorBookingPageReady(driver) {
  await waitForUrlContains(driver, '/vendor-booking', 20000);
  await waitForTestId(driver, 'booking-page-root');
  await waitForTestIdHidden(driver, 'booking-events-loading', 20000);
  await waitForTestId(driver, 'booking-form');

  const bodyText = (await driver.findElement(By.css('body')).getText()).toLowerCase();
  for (const pattern of BLOCKING_COPY_PATTERNS) {
    assert.ok(!pattern.test(bodyText), `Vendor booking page must not show blocking copy: ${pattern}`);
  }

  const heading = await waitForTestId(driver, 'booking-onboarding-heading');
  const headingText = (await heading.getText()).trim();
  assert.match(headingText, /start vendor booking/i, 'Booking page heading must describe vendor onboarding.');

  let matchedOnboarding = false;
  for (const pattern of ONBOARDING_COPY_PATTERNS) {
    if (pattern.test(bodyText)) {
      matchedOnboarding = true;
      break;
    }
  }

  assert.ok(
    matchedOnboarding,
    'Vendor booking page must show onboarding guidance (event selection / staff review wording).',
  );
}

export async function assertGuestAuthRedirectPreservesVendorBooking(driver) {
  await driver.wait(
    async () => {
      const url = await driver.getCurrentUrl();
      return url.includes('/login') || url.includes('/register');
    },
    15000,
    'Guest Start Vendor Booking must redirect to login or register.',
  );

  const url = await driver.getCurrentUrl();
  const decoded = decodeURIComponent(url);
  assert.ok(
    decoded.includes('/vendor-booking'),
    `Auth redirect must preserve /vendor-booking intent. URL: ${url}`,
  );
}

export async function submitApplicantBooking(driver, marker) {
  await prepareVendorBookingPage(driver);
  await openFirstBookableEvent(driver);
  await fillInputValue(driver, 'booking-business-name', env.bookingBusinessName);
  await selectBookingCategory(driver, 'Food & Beverages');
  await fillInputValue(driver, 'booking-details', marker);

  await driver.wait(
    async () => {
      const submitButton = await driver.findElement(By.css('[data-testid="booking-submit"]'));
      return submitButton.isEnabled();
    },
    10000,
    'Booking submit stayed disabled for applicant booking.',
  );

  const toastsBefore = await readToastTexts(driver);
  await driver.executeScript(`
    const form = document.querySelector('[data-testid="booking-form"]');
    if (!form) throw new Error('Booking form not found.');
    form.requestSubmit();
  `);

  await driver.wait(
    async () => {
      const currentUrl = await driver.getCurrentUrl();
      if (currentUrl.includes('/dashboard')) return true;

      const toasts = (await readToastTexts(driver)).filter((text) => !toastsBefore.includes(text));
      return toasts.some((text) => /booking submitted|201 created|awaiting tier/i.test(text));
    },
    25000,
    'Applicant booking submit did not reach success state.',
  );

  if (!(await driver.getCurrentUrl()).includes('/dashboard')) {
    await driver.get(`${env.baseUrl}/dashboard`);
  }

  await waitForTestId(driver, 'vendor-dashboard-root', 20000);
}

async function openFirstBookableEvent(driver) {
  const noEvents = await driver.findElements(By.css('[data-testid="booking-no-events"]'));
  if (noEvents.length && (await noEvents[0].isDisplayed())) {
    throw new Error(
      'No upcoming bookable events for applicant booking test. Run backend seeder or create an event.',
    );
  }

  const selected = await driver.findElements(By.css('[data-testid="booking-selected-event"]'));
  if (selected.length && (await selected[0].isDisplayed())) {
    return;
  }

  const selectElement = await waitForTestId(driver, 'booking-event-select');
  const options = await selectElement.findElements(By.css('option'));
  let eventId = null;

  for (const option of options) {
    const value = await option.getAttribute('value');
    if (value) {
      eventId = value;
      break;
    }
  }

  assert.ok(eventId, 'Applicant booking test requires at least one bookable event option.');
  await setSelectValue(driver, 'booking-event-select', eventId);
  await driver.wait(
    async () => {
      const eventCard = await driver.findElements(By.css('[data-testid="booking-selected-event"]'));
      return eventCard.length > 0 && (await eventCard[0].isDisplayed());
    },
    10000,
    'Selected event card did not appear after choosing an event.',
  );
}

async function readToastTexts(driver) {
  const toasts = await driver.findElements(By.css('.Vue-Toastification__toast-body'));
  const texts = [];
  for (const toast of toasts) {
    texts.push((await toast.getText()).trim());
  }
  return texts.filter(Boolean);
}

export async function assertApplicantDashboardGuided(driver, { marker } = {}) {
  await driver.get(`${env.baseUrl}/dashboard`);
  await waitForTestId(driver, 'vendor-dashboard-root', 20000);

  const bodyText = await driver.findElement(By.css('body')).getText();
  const lower = bodyText.toLowerCase();

  for (const pattern of [/403/, /500 internal server error/, /\bundefined\b/, /\bnull\b/]) {
    assert.ok(!pattern.test(lower), `Dashboard must not show broken state (${pattern}).`);
  }

  const banner = await waitForTestId(driver, 'vendor-onboarding-banner', 15000);
  assert.equal(await banner.isDisplayed(), true, 'Vendor onboarding banner must be visible for applicant.');

  const bannerText = (await banner.getText()).toLowerCase();
  assert.ok(
    /welcome to your vendor workspace|vendor booking is under review|action needed|booking not approved/i.test(
      bannerText,
    ),
    `Onboarding banner must use friendly guided copy. Got: ${bannerText}`,
  );

  if (marker) {
    await fillInputValue(driver, 'booking-search', marker);
    await driver.wait(
      async () => {
        const rows = await driver.findElements(By.css('[data-testid="booking-list-item"]'));
        for (const row of rows) {
          if (!(await row.isDisplayed())) continue;
          const text = (await row.getText()).toLowerCase();
          if (text.includes(marker.toLowerCase())) return true;
        }
        return false;
      },
      20000,
      `Submitted booking marker "${marker}" must appear in My Bookings.`,
    );
  }
}

export async function assertApplicantProfileAccessible(driver) {
  await driver.get(`${env.baseUrl}/profile`);
  await waitForTestId(driver, 'vendor-profile-root', 20000);

  const bodyText = (await driver.findElement(By.css('body')).getText()).toLowerCase();
  assert.ok(!/403 forbidden/.test(bodyText), 'Vendor profile must not show 403 for applicant.');
}

export async function assertOperationalGatesRemainLocked(driver) {
  await driver.get(`${env.baseUrl}/dashboard`);
  await waitForTestId(driver, 'vendor-dashboard-root', 20000);
  await waitForTestId(driver, 'vendor-event-passes-root', 15000);

  await driver.wait(
    async () => {
      const roots = await driver.findElements(By.css('[data-testid="vendor-event-passes-root"]'));
      if (!roots.length) return false;
      const text = (await roots[0].getText()).toLowerCase();
      return !text.includes('refreshing') && text.length > 60;
    },
    20000,
    'Event pass panel did not finish loading.',
  );

  const passButtons = await driver.findElements(By.css('[data-testid="vendor-pass-button"]'));
  let visiblePassAction = false;
  for (const button of passButtons) {
    if (await button.isDisplayed()) {
      visiblePassAction = true;
      break;
    }
  }

  assert.equal(
    visiblePassAction,
    false,
    'Pending applicant must not see an unlocked event pass download action yet.',
  );

  const passesRoot = await driver.findElement(By.css('[data-testid="vendor-event-passes-root"]'));
  const passesText = (await passesRoot.getText()).toLowerCase();
  assert.ok(
    /no active event pass|book a space|approval|payment|pending|booth will be assigned|verification qr|qr unavailable|pass status|awaiting|select an event pass|event-specific check-in|approved bookings/i.test(
      passesText,
    ),
    `Event pass panel must show pending/empty guidance for applicant. Got: ${passesText.slice(0, 200)}`,
  );

  await waitForTestId(driver, 'vendor-history-receipts-root', 15000);
  const receiptsRoot = await driver.findElement(By.css('[data-testid="vendor-history-receipts-root"]'));

  const viewReceiptButtons = await receiptsRoot.findElements(By.css('[data-testid="view-receipt-button"]'));
  for (const button of viewReceiptButtons) {
    assert.equal(
      await button.isDisplayed(),
      false,
      'Paid receipt download must remain locked for pending applicant.',
    );
  }

  const receiptsText = (await receiptsRoot.getText()).toLowerCase();
  assert.ok(
    /no payment records|unpaid|payment records|submit payment|no receipt/i.test(receiptsText),
    'Receipts section must show unpaid/pending payment state for applicant.',
  );
}

const SAFE_COMMUNITY_ROOTS = ['community-portal-root', 'vendor-dashboard-root'];
const ADMIN_ROUTE_RECOVERY_TIMEOUT_MS = 8000;

async function readBodySnippet(driver, limit = 240) {
  try {
    const text = await driver.executeScript((max) => {
      const app = document.getElementById('app');
      const source = app?.innerText || document.body?.innerText || '';
      return source.trim().slice(0, max);
    }, limit);
    return text || '(empty body)';
  } catch {
    return '(body unreadable)';
  }
}

async function isSpaBlank(driver) {
  return driver.executeScript(() => {
    const app = document.getElementById('app');
    if (!app) return true;
    return app.innerText.trim().length === 0 && app.children.length === 0;
  });
}

async function findVisibleSafeRoot(driver) {
  for (const testId of SAFE_COMMUNITY_ROOTS) {
    const elements = await driver.findElements(By.css(`[data-testid="${testId}"]`));
    for (const element of elements) {
      try {
        if (await element.isDisplayed()) {
          return testId;
        }
      } catch (error) {
        if (error.name !== 'StaleElementReferenceError') {
          throw error;
        }
      }
    }
  }
  return null;
}

async function isStaffDashboardVisible(driver) {
  const staffRoots = await driver.findElements(By.css('[data-testid="management-dashboard-root"]'));
  for (const root of staffRoots) {
    try {
      if (await root.isDisplayed()) {
        return true;
      }
    } catch (error) {
      if (error.name !== 'StaleElementReferenceError') {
        throw error;
      }
    }
  }
  return false;
}

/** Part A: staff/manager API must deny community applicants. */
export async function assertApplicantStaffApiBlocked(driver) {
  const apiResult = await driver.executeScript(async () => {
    const token = localStorage.getItem('carboot_cmart_token');
    if (!token) {
      return { status: 401 };
    }

    const response = await fetch('http://127.0.0.1:8000/api/staff/bookings', {
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
    });

    return { status: response.status };
  });

  assert.notEqual(
    apiResult.status,
    200,
    `Community applicant must not receive staff bookings API data (HTTP ${apiResult.status}).`,
  );

  return apiResult;
}

/** Recover SPA to a known safe page so afterEach logout does not hang. */
export async function recoverToSafeCommunityPage(driver) {
  const visibleRoot = await findVisibleSafeRoot(driver);
  if (visibleRoot) {
    return visibleRoot;
  }

  await driver.get(`${env.baseUrl}/community`);

  try {
    await waitForTestId(driver, 'community-portal-root', 8000);
    return 'community-portal-root';
  } catch {
    await clearBrowserSession(driver);
    return null;
  }
}

/**
 * Part A + B: API denial and short UI redirect/recovery check for /admin.
 * Bounded wait — fast-fail, no 300s hangs.
 */
export async function assertApplicantBlockedFromAdmin(driver) {
  await assertApplicantStaffApiBlocked(driver);

  await driver.get(`${env.baseUrl}/admin`);

  const outcome = {
    url: await driver.getCurrentUrl(),
    bodySnippet: await readBodySnippet(driver),
    staffVisible: false,
    redirected: false,
    safeRoot: null,
    blankBody: false,
  };

  try {
    await driver.wait(
      async () => {
        outcome.url = await driver.getCurrentUrl();
        outcome.bodySnippet = await readBodySnippet(driver);
        outcome.blankBody = await isSpaBlank(driver);
        outcome.staffVisible = await isStaffDashboardVisible(driver);

        if (outcome.staffVisible) {
          return false;
        }

        outcome.safeRoot = await findVisibleSafeRoot(driver);
        outcome.redirected = !outcome.url.includes('/admin');

        if (outcome.blankBody) {
          return false;
        }

        if (outcome.redirected && outcome.safeRoot) {
          return true;
        }

        return false;
      },
      ADMIN_ROUTE_RECOVERY_TIMEOUT_MS,
      `Community applicant /admin visit did not redirect to a safe page within ${ADMIN_ROUTE_RECOVERY_TIMEOUT_MS}ms.`,
    );
  } catch {
    outcome.url = await driver.getCurrentUrl();
    outcome.bodySnippet = await readBodySnippet(driver);
    outcome.blankBody = await isSpaBlank(driver);
    outcome.staffVisible = await isStaffDashboardVisible(driver);
    outcome.safeRoot = await findVisibleSafeRoot(driver);
    outcome.redirected = !outcome.url.includes('/admin');
  }

  if (outcome.staffVisible) {
    await recoverToSafeCommunityPage(driver);
    assert.fail(
      `Staff dashboard must not be visible to community applicants. URL: ${outcome.url}. Body: ${outcome.bodySnippet}`,
    );
  }

  if (outcome.blankBody) {
    await recoverToSafeCommunityPage(driver);
    assert.fail(
      `SPA body was blank after visiting /admin. URL: ${outcome.url}. Body: ${outcome.bodySnippet}`,
    );
  }

  assert.ok(
    outcome.redirected && outcome.safeRoot,
    `Community applicant must leave /admin and reach a safe root. URL: ${outcome.url}, safeRoot: ${outcome.safeRoot}, body: ${outcome.bodySnippet}`,
  );

  await recoverToSafeCommunityPage(driver);
}

export async function captureOnboardingFailureDiagnostics(driver, label, context = {}) {
  const diagnostics = await captureFailureDiagnostics(driver, label);
  return { ...diagnostics, onboardingContext: context };
}

export async function resetGuestForOnboardingFlow(driver) {
  await ensureGuestSession(driver);
}

export async function resetApplicantSession(driver) {
  await clearBrowserSession(driver);
}
