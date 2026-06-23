import { By, until } from 'selenium-webdriver';

export async function waitForTestId(driver, testId, timeoutMs = 15000) {
  const locator = By.css(`[data-testid="${testId}"]`);
  return driver.wait(until.elementLocated(locator), timeoutMs, `Timed out waiting for [data-testid="${testId}"]`);
}

export async function waitForUrlContains(driver, fragment, timeoutMs = 15000) {
  return driver.wait(
    async () => {
      const currentUrl = await driver.getCurrentUrl();
      return currentUrl.includes(fragment);
    },
    timeoutMs,
    `Timed out waiting for URL to contain "${fragment}"`,
  );
}
