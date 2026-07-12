import { strict as assert } from 'node:assert';
import { By, until } from 'selenium-webdriver';
import { env } from '../config/env.js';
import { assertTestIdAbsent } from './access-guards.js';
import {
  assertGuestRedirectedFromProtectedRoute,
  assertGuestSessionClean,
  ensureGuestSession,
  guestApiRequest,
  PROTECTED_FRONTEND_ROUTES,
} from './guest-access.js';
import { captureFailureDiagnostics } from './diagnostics.js';
import { waitForTestId } from './wait.js';

export const PROTECTED_DASHBOARD_TEST_IDS = [
  'vendor-dashboard-root',
  'management-dashboard-root',
  'organizer-bookings-root',
  'booking-form',
];

export const PUBLIC_API_ENDPOINTS = [
  { endpoint: '/events', label: 'Public events list' },
  { endpoint: '/news', label: 'Public news list' },
  { endpoint: '/marketplace/items', label: 'Public marketplace items' },
];

export const PUBLIC_ROUTE_CONTROL_CHECKS = PROTECTED_FRONTEND_ROUTES.filter((route) =>
  ['/dashboard', '/admin'].includes(route.path),
);

export async function visitPublicRoute(driver, path) {
  await driver.get(`${env.baseUrl}${path}`);
}

export async function assertNotRedirectedToLogin(driver, { label } = {}) {
  await driver.wait(
    async () => {
      const url = await driver.getCurrentUrl();
      if (url.includes('/login')) {
        return false;
      }

      const loginInputs = await driver.findElements(By.css('[data-testid="login-email"]'));
      for (const input of loginInputs) {
        if (await input.isDisplayed()) {
          return false;
        }
      }

      return true;
    },
    20000,
    `Guest must not be redirected to login on ${label || 'public page'}.`,
  );

  const currentUrl = await driver.getCurrentUrl();
  assert.ok(
    !currentUrl.includes('/login'),
    `URL must not be /login after visiting ${label || 'public page'}. Got: ${currentUrl}`,
  );
}

export async function assertNoProtectedDashboardVisible(driver) {
  for (const testId of PROTECTED_DASHBOARD_TEST_IDS) {
    await assertTestIdAbsent(driver, testId);
  }
}

export async function assertPublicPageVisible(driver, { rootTestId, requiredText = [] } = {}) {
  if (rootTestId) {
    const element = await waitForTestId(driver, rootTestId, 20000);
    await driver.wait(until.elementIsVisible(element), 10000, `[data-testid="${rootTestId}"] must be visible.`);
  }

  if (requiredText.length) {
    const bodyText = await driver.findElement(By.css('body')).getText();
    for (const snippet of requiredText) {
      assert.ok(
        bodyText.includes(snippet),
        `Public page should include visible text "${snippet}".`,
      );
    }
  }
}

export async function assertPublicRouteAccessible(driver, path, expectations = {}) {
  const { rootTestId, label = path, requiredText = [] } = expectations;

  await visitPublicRoute(driver, path);
  await assertNotRedirectedToLogin(driver, { label });
  await assertPublicPageVisible(driver, { rootTestId, requiredText });
  await assertNoProtectedDashboardVisible(driver);
}

export async function assertPublicApiReturnsOk(driver, endpoint, { label } = {}) {
  const response = await guestApiRequest(driver, 'GET', endpoint);

  assert.equal(
    response.status,
    200,
    `${label || endpoint} must be reachable without authentication (HTTP 200); got HTTP ${response.status}. ` +
      `Body: ${String(response.body).slice(0, 240)}`,
  );
}

export async function clickFirstVisibleTestId(driver, testId) {
  const elements = await driver.findElements(By.css(`[data-testid="${testId}"]`));

  for (const element of elements) {
    try {
      if (!(await element.isDisplayed())) {
        continue;
      }

      await driver.executeScript('arguments[0].scrollIntoView({block: "center"});', element);
      await driver.sleep(200);
      await element.click();
      return true;
    } catch (error) {
      if (error.name === 'StaleElementReferenceError') {
        continue;
      }

      await driver.executeScript('arguments[0].click();', element);
      return true;
    }
  }

  return false;
}

export async function assertPublicDetailModalOpen(driver) {
  const modal = await waitForTestId(driver, 'public-detail-modal', 15000);
  await driver.wait(until.elementIsVisible(modal), 10000, '[data-testid="public-detail-modal"] must be visible.');
  await assertNoProtectedDashboardVisible(driver);
}

export async function closePublicDetailModal(driver) {
  const closeButtons = await driver.findElements(
    By.css('[data-testid="public-detail-modal"] button[aria-label^="Close"]'),
  );

  for (const button of closeButtons) {
    if (await button.isDisplayed()) {
      await driver.executeScript('arguments[0].click();', button);
      return;
    }
  }

  await driver.actions({ bridge: true }).sendKeys('\uE00C').perform();
}

export async function waitForPublicContentLoaded(driver, { loadingGoneText } = {}) {
  if (!loadingGoneText) {
    return;
  }

  await driver.wait(
    async () => {
      const bodyText = await driver.findElement(By.css('body')).getText();
      return !bodyText.includes(loadingGoneText);
    },
    20000,
    `Timed out waiting for "${loadingGoneText}" to disappear.`,
  );
}

export async function waitForOptionalPublicCards(driver, { cardTestId, emptyStateText }) {
  await driver.wait(
    async () => {
      const cards = await driver.findElements(By.css(`[data-testid="${cardTestId}"]`));
      if (cards.length > 0) {
        return true;
      }

      const bodyText = await driver.findElement(By.css('body')).getText();
      return bodyText.includes(emptyStateText);
    },
    20000,
    `Timed out waiting for ${cardTestId} cards or empty state "${emptyStateText}".`,
  );
}

export async function assertProtectedRoutesStillBlocked(driver) {
  for (const route of PUBLIC_ROUTE_CONTROL_CHECKS) {
    await assertGuestRedirectedFromProtectedRoute(driver, route.path, {
      label: route.label,
      forbiddenTestIds: route.forbiddenTestIds,
    });
  }
}

export async function capturePublicRouteFailureDiagnostics(driver, label, context = {}) {
  const meta = await captureFailureDiagnostics(driver, label);
  meta.publicRouteContext = context;

  try {
    meta.currentUrl = await driver.getCurrentUrl();
  } catch (error) {
    meta.currentUrlError = error.message;
  }

  return meta;
}

export { ensureGuestSession, assertGuestSessionClean };
