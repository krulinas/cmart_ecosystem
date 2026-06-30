import { By } from 'selenium-webdriver';
import {
  env,
  requireManagerCredentials,
  requireStaffCredentials,
  requireVendorBCredentials,
  requireVendorCredentials,
} from '../config/env.js';
import { captureFailureDiagnostics } from './diagnostics.js';
import { clearBrowserSession, ensureManagementLoginPage, ensurePublicLoginPage } from './session.js';
import { waitForTestId, waitForUrlContains } from './wait.js';

async function fillLoginField(driver, testId, value) {
  const input = await waitForTestId(driver, testId);
  await input.clear();
  await input.sendKeys(value);

  const actual = await input.getAttribute('value');
  if (actual !== value) {
    await driver.executeScript(
      `const el = arguments[0];
       const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
       setter.call(el, arguments[1]);
       el.dispatchEvent(new Event('input', { bubbles: true }));`,
      input,
      value,
    );
  }
}

async function submitLoginForm(driver, { management = false } = {}) {
  const emailTestId = management ? 'management-login-email' : 'login-email';
  await driver.executeScript(`
    const form = document.querySelector('[data-testid="${emailTestId}"]')?.closest('form');
    if (!form) throw new Error('Login form not found.');
    form.requestSubmit();
  `);
}

async function waitForAuthToken(driver, timeoutMs = 25000) {
  await driver.wait(async () => {
    return driver.executeScript(() => Boolean(localStorage.getItem('carboot_cmart_token')));
  }, timeoutMs, 'Auth token was not stored in localStorage after login submit.');
}

async function readLoginFailureContext(driver) {
  const currentUrl = await driver.getCurrentUrl();
  const toasts = await driver.findElements(By.css('.Vue-Toastification__toast-body'));
  const toastMessages = [];

  for (const toast of toasts) {
    toastMessages.push((await toast.getText()).trim());
  }

  return {
    currentUrl,
    toastMessages: toastMessages.filter(Boolean),
  };
}

async function loginWithRole(driver, { email, password, successUrlFragment, dashboardTestId, roleLabel, management = false }) {
  const emailTestId = management ? 'management-login-email' : 'login-email';
  const passwordTestId = management ? 'management-login-password' : 'login-password';
  const ensurePage = management ? ensureManagementLoginPage : ensurePublicLoginPage;

  const attempt = async (retryCount) => {
    await ensurePage(driver);
    await fillLoginField(driver, emailTestId, email);
    await fillLoginField(driver, passwordTestId, password);
    await submitLoginForm(driver, { management });
    await waitForAuthToken(driver, 25000);
    await waitForUrlContains(driver, successUrlFragment, 25000);
    await waitForTestId(driver, dashboardTestId, 25000);
  };

  try {
    await attempt(0);
  } catch (firstError) {
    const diagnostics = await captureFailureDiagnostics(driver, `${roleLabel}-login-attempt-1`);
    const context = await readLoginFailureContext(driver);

    try {
      await clearBrowserSession(driver);
      await attempt(1);
      return;
    } catch (retryError) {
      const retryDiagnostics = await captureFailureDiagnostics(driver, `${roleLabel}-login-failed`);
      const retryContext = await readLoginFailureContext(driver);

      throw new Error(
        `${roleLabel} login failed after one retry. ` +
          `First URL: ${context.currentUrl}. Retry URL: ${retryContext.currentUrl}. ` +
          `Toasts: ${retryContext.toastMessages.join(' | ') || 'none'}. ` +
          `Diagnostics: ${diagnostics.json}${retryDiagnostics.json ? `, ${retryDiagnostics.json}` : ''}.`,
        { cause: retryError },
      );
    }
  }
}

export async function logout(driver, { management = false } = {}) {
  await clearBrowserSession(driver, management ? '/management/login' : '/login');
  if (management) {
    await waitForTestId(driver, 'management-login-email', 15000);
    return;
  }

  const emailInputs = await driver.findElements(By.css('[data-testid="login-email"]'));
  if (emailInputs.length === 0) {
    await waitForTestId(driver, 'auth-continue-email', 15000);
  }
  await waitForTestId(driver, 'login-email', 15000);
}

export async function loginAsVendor(driver) {
  const { email, password } = requireVendorCredentials();
  await loginAsCommunityVendor(driver, { email, password }, { roleLabel: 'Vendor' });
}

export async function loginAsVendorB(driver) {
  const { email, password } = requireVendorBCredentials();
  await loginAsCommunityVendor(driver, { email, password }, { roleLabel: 'Vendor B' });
}

export async function loginAsCommunityVendor(driver, { email, password }, { roleLabel = 'Vendor' } = {}) {
  await loginWithRole(driver, {
    email,
    password,
    successUrlFragment: '/dashboard',
    dashboardTestId: 'vendor-dashboard-root',
    roleLabel,
  });
}

export async function loginAsStaff(driver) {
  const { email, password } = requireStaffCredentials();
  await loginWithRole(driver, {
    email,
    password,
    successUrlFragment: '/admin',
    dashboardTestId: 'staff-dashboard-root',
    roleLabel: 'Staff',
    management: true,
  });
}

export async function loginAsManager(driver) {
  const { email, password } = requireManagerCredentials();
  await loginWithRole(driver, {
    email,
    password,
    successUrlFragment: '/admin',
    dashboardTestId: 'staff-dashboard-root',
    roleLabel: 'Manager',
    management: true,
  });
}
