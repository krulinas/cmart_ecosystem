import { env } from '../config/env.js';
import { waitForTestId, waitForUrlContains } from './wait.js';

function appHost() {
  try {
    return new URL(env.baseUrl).host;
  } catch {
    return 'localhost:5175';
  }
}

export async function clearBrowserSession(driver) {
  try {
    await driver.manage().deleteAllCookies();
  } catch {
    // Cookie cleanup is best-effort between role switches.
  }

  // Clear storage on the current app page before /login (router redirects authenticated users away from login).
  try {
    const url = await driver.getCurrentUrl();
    if (url.includes(appHost())) {
      await driver.executeScript('localStorage.clear(); sessionStorage.clear();');
    }
  } catch {
    // Best-effort when the browser is between navigations.
  }

  try {
    await driver.get(`${env.baseUrl}/login`);
    await driver.executeScript('localStorage.clear(); sessionStorage.clear();');
    await driver.get(`${env.baseUrl}/login`);
  } catch {
    // Storage clear is best-effort if the frontend is temporarily unreachable.
  }

  try {
    await driver.manage().deleteAllCookies();
  } catch {
    // Second cookie pass after navigating to login.
  }
}

export async function ensureLoginPage(driver, { timeoutMs = 30000 } = {}) {
  await clearBrowserSession(driver);
  await waitForUrlContains(driver, '/login', timeoutMs);
  await waitForTestId(driver, 'login-email', timeoutMs);
}
