import { after } from 'mocha';
import { quitDriver } from './helpers/driver.js';

let activeDriver = null;

export function setActiveDriver(driver) {
  if (activeDriver && activeDriver !== driver) {
    void quitDriver(activeDriver);
  }
  activeDriver = driver;
}

after(async function () {
  await quitDriver(activeDriver);
  activeDriver = null;
});
