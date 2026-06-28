import { Builder } from 'selenium-webdriver';
import chrome from 'selenium-webdriver/chrome.js';
import { env } from '../config/env.js';

export async function createDriver() {
  const options = new chrome.Options();

  if (env.headless) {
    options.addArguments('--headless=new');
  }

  options.addArguments('--window-size=1280,900');
  options.addArguments('--disable-gpu');
  options.addArguments('--no-sandbox');
  // Do not wait for every asset; full-suite runs otherwise hit the ~300s page-load timeout.
  options.setPageLoadStrategy('eager');

  const driver = await new Builder().forBrowser('chrome').setChromeOptions(options).build();

  await driver.manage().setTimeouts({
    pageLoad: 60000,
    script: 30000,
    implicit: 0,
  });

  try {
    await driver.sendDevToolsCommand('Page.addScriptToEvaluateOnNewDocument', {
      source: `
        window.prompt = function() {
          return 'E2E automated revision request - safe to ignore';
        };
      `,
    });
  } catch {
    // DevTools command is best-effort for local E2E runs.
  }

  return driver;
}

export async function quitDriver(driver) {
  if (!driver) return;

  try {
    await driver.quit();
  } catch {
    // Driver may already be closed if the browser was closed manually.
  }
}
