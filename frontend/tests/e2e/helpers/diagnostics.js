import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
export const E2E_ARTIFACTS_DIR = join(__dirname, '../artifacts');

export async function captureFailureDiagnostics(driver, label = 'failure') {
  mkdirSync(E2E_ARTIFACTS_DIR, { recursive: true });

  const stamp = `${Date.now()}-${String(label).replace(/\W+/g, '-').slice(0, 80)}`;
  const meta = {
    label,
    capturedAt: new Date().toISOString(),
  };

  try {
    meta.url = await driver.getCurrentUrl();
  } catch (error) {
    meta.urlError = error.message;
  }

  try {
    meta.title = await driver.getTitle();
  } catch (error) {
    meta.titleError = error.message;
  }

  try {
    meta.storageKeys = await driver.executeScript(() => ({
      localStorage: Object.keys(localStorage),
      sessionStorage: Object.keys(sessionStorage),
      tokenPresent: Boolean(localStorage.getItem('carboot_cmart_token')),
    }));
  } catch (error) {
    meta.storageError = error.message;
  }

  try {
    meta.bodySnippet = await driver.executeScript(
      () => (document.body?.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 500),
    );
  } catch (error) {
    meta.bodySnippetError = error.message;
  }

  const jsonPath = join(E2E_ARTIFACTS_DIR, `${stamp}.json`);
  writeFileSync(jsonPath, JSON.stringify(meta, null, 2));

  try {
    const screenshot = await driver.takeScreenshot();
    writeFileSync(join(E2E_ARTIFACTS_DIR, `${stamp}.png`), screenshot, 'base64');
    meta.screenshot = `${stamp}.png`;
  } catch (error) {
    meta.screenshotError = error.message;
  }

  meta.json = `${stamp}.json`;
  return meta;
}
