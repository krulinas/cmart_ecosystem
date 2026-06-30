import { By } from 'selenium-webdriver';
import { env } from '../config/env.js';
import { waitForTestId, waitForUrlContains } from './wait.js';

function appHost() {
  try {
    return new URL(env.baseUrl).host;
  } catch {
    return 'localhost:5175';
  }
}

export async function clearBrowserSession(driver, loginPath = '/login') {
  try {
    await driver.manage().deleteAllCookies();
  } catch {
    // Cookie cleanup is best-effort between role switches.
  }

  try {
    const url = await driver.getCurrentUrl();
    if (url.includes(appHost())) {
      await driver.executeScript('localStorage.clear(); sessionStorage.clear();');
    }
  } catch {
    // Best-effort when the browser is between navigations.
  }

  try {
    await driver.get(`${env.baseUrl}${loginPath}`);
    await driver.executeScript('localStorage.clear(); sessionStorage.clear();');
    await driver.get(`${env.baseUrl}${loginPath}`);
  } catch {
    // Storage clear is best-effort if the frontend is temporarily unreachable.
  }

  try {
    await driver.manage().deleteAllCookies();
  } catch {
    // Second cookie pass after navigating to login.
  }
}

async function openPublicEmailLoginForm(driver) {
  const emailInputs = await driver.findElements(By.css('[data-testid="login-email"]'));
  if (emailInputs.length > 0) {
    return;
  }

  const continueEmail = await waitForTestId(driver, 'auth-continue-email', 15000);
  await continueEmail.click();
  await waitForTestId(driver, 'login-email', 15000);
}

export async function ensurePublicLoginPage(driver, { timeoutMs = 30000 } = {}) {
  await clearBrowserSession(driver, '/login');
  await waitForUrlContains(driver, '/login', timeoutMs);
  await openPublicEmailLoginForm(driver);
  await waitForTestId(driver, 'login-email', timeoutMs);
}

export async function ensureManagementLoginPage(driver, { timeoutMs = 30000 } = {}) {
  await clearBrowserSession(driver, '/management/login');
  await waitForUrlContains(driver, '/management/login', timeoutMs);
  await waitForTestId(driver, 'management-login-email', timeoutMs);
}

/** @deprecated Use ensurePublicLoginPage or ensureManagementLoginPage */
export async function ensureLoginPage(driver, options = {}) {
  await ensurePublicLoginPage(driver, options);
}
