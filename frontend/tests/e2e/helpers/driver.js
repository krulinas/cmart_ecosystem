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

  return new Builder().forBrowser('chrome').setChromeOptions(options).build();
}

export async function quitDriver(driver) {
  if (!driver) return;

  try {
    await driver.quit();
  } catch {
    // Driver may already be closed if the browser was closed manually.
  }
}
