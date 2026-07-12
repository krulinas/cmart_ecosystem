import { strict as assert } from 'node:assert';
import { requireCmartManagementCredentials } from '../config/env.js';
import { loginAsCmartManagement, logout, managementApiRequest } from '../helpers/auth.js';
import { createDriver } from '../helpers/driver.js';
import { setActiveDriver } from '../setup.js';

describe('CMart management access boundary', function () {
  this.timeout(120000);

  let driver;

  before(async function () {
    requireCmartManagementCredentials();
    driver = await createDriver();
    await setActiveDriver(driver);
  });

  after(async function () {
    await logout(driver, { management: true });
  });

  it('cmart_management cannot access bookings or analytics but can access operational reports', async function () {
    await loginAsCmartManagement(driver);

    const bookingsList = await managementApiRequest(driver, 'GET', '/bookings');
    assert.equal(bookingsList.ok, false);
    assert.equal(bookingsList.status, 403, `GET /bookings must return 403; got ${bookingsList.status}.`);

    const analyticsEndpoints = [
      '/boss/analytics/revenue',
      '/boss/audit-logs',
    ];

    for (const endpoint of analyticsEndpoints) {
      const response = await managementApiRequest(driver, 'GET', endpoint);
      assert.equal(response.ok, false, `GET ${endpoint} must be denied for cmart_management.`);
      assert.equal(
        response.status,
        403,
        `GET ${endpoint} must return 403 for cmart_management; got ${response.status}.`,
      );
    }

    const reports = await managementApiRequest(driver, 'GET', '/management/reports/operational-overview');
    assert.equal(
      reports.ok,
      true,
      `GET /management/reports/operational-overview must succeed for cmart_management. Response: ${reports.body?.slice(0, 240)}`,
    );
    assert.equal(reports.status, 200);
  });
});
