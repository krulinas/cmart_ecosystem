import { strict as assert } from 'node:assert';
import { e2eT8BOpenVendorMarker } from '../helpers/actions.js';
import { createDriver } from '../helpers/driver.js';
import {
  assertApplicantBlockedFromAdmin,
  assertApplicantDashboardGuided,
  assertApplicantProfileAccessible,
  assertBecomeVendorSection,
  assertCommunityNavLabels,
  assertGuestAuthRedirectPreservesVendorBooking,
  assertNotOnVendorDashboard,
  assertOperationalGatesRemainLocked,
  assertVendorBookingPageReady,
  captureOnboardingFailureDiagnostics,
  clickBecomeVendorNav,
  clickStartVendorBookingCta,
  registerCommunityVisitorAccount,
  resetGuestForOnboardingFlow,
  submitApplicantBooking,
  visitCommunityPortal,
} from '../helpers/vendor-onboarding.js';
import { loginAsCommunityMember, loginAsCommunityVisitor } from '../helpers/auth.js';
import { setActiveDriver } from '../setup.js';

describe('Test 8B: Open Vendor Onboarding Flow', function () {
  this.timeout(300000);

  let driver;
  let visitorAccount;
  let bookingMarker;
  let hasSubmittedBooking = false;

  async function ensureVisitorSession() {
    if (!visitorAccount) {
      visitorAccount = await registerCommunityVisitorAccount();
    }

    if (hasSubmittedBooking) {
      await loginAsCommunityMember(driver, visitorAccount, { roleLabel: 'Community applicant' });
    } else {
      await loginAsCommunityVisitor(driver, visitorAccount, { roleLabel: 'Community visitor' });
    }

    return visitorAccount;
  }

  before(async function () {
    driver = await createDriver();
    await setActiveDriver(driver);
    bookingMarker = e2eT8BOpenVendorMarker();
  });

  it('8B-1 Normal community user is not auto-forced to vendor dashboard', async function () {
    try {
      await ensureVisitorSession();
      await assertNotOnVendorDashboard(driver);
      await assertCommunityNavLabels(driver);
    } catch (error) {
      const diagnostics = await captureOnboardingFailureDiagnostics(driver, 't8b-1-not-forced-dashboard', {
        flow: '8B-1',
      });
      error.message = `${error.message} Diagnostics: ${JSON.stringify(diagnostics)}.`;
      throw error;
    }
  });

  it('8B-2 Become a Vendor navbar orients the user to the vendor section', async function () {
    try {
      await ensureVisitorSession();
      await visitCommunityPortal(driver);
      await clickBecomeVendorNav(driver);

      const url = await driver.getCurrentUrl();
      assert.ok(url.includes('/community'), `Become a Vendor nav must stay on /community. URL: ${url}`);
      assert.ok(url.includes('#become-vendor'), `Become a Vendor nav must include #become-vendor hash. URL: ${url}`);

      await assertBecomeVendorSection(driver);
    } catch (error) {
      const diagnostics = await captureOnboardingFailureDiagnostics(driver, 't8b-2-become-vendor-nav', {
        flow: '8B-2',
      });
      error.message = `${error.message} Diagnostics: ${JSON.stringify(diagnostics)}.`;
      throw error;
    }
  });

  it('8B-3 Start Vendor Booking opens /vendor-booking for non-approved community user', async function () {
    try {
      await ensureVisitorSession();
      await visitCommunityPortal(driver, { hash: '#become-vendor' });
      await assertBecomeVendorSection(driver);
      await clickStartVendorBookingCta(driver);
      await assertVendorBookingPageReady(driver);
    } catch (error) {
      const diagnostics = await captureOnboardingFailureDiagnostics(driver, 't8b-3-start-vendor-booking', {
        flow: '8B-3',
      });
      error.message = `${error.message} Diagnostics: ${JSON.stringify(diagnostics)}.`;
      throw error;
    }
  });

  it('8B-4 Guest Start Vendor Booking redirects to auth with /vendor-booking return path', async function () {
    try {
      await resetGuestForOnboardingFlow(driver);
      await visitCommunityPortal(driver, { hash: '#become-vendor' });
      await clickStartVendorBookingCta(driver);
      await assertGuestAuthRedirectPreservesVendorBooking(driver);
    } catch (error) {
      const diagnostics = await captureOnboardingFailureDiagnostics(driver, 't8b-4-guest-auth-redirect', {
        flow: '8B-4',
      });
      error.message = `${error.message} Diagnostics: ${JSON.stringify(diagnostics)}.`;
      throw error;
    }
  });

  it('8B-5 Community user can submit vendor booking without prior approval', async function () {
    try {
      await ensureVisitorSession();
      await submitApplicantBooking(driver, bookingMarker);
      hasSubmittedBooking = true;
    } catch (error) {
      const diagnostics = await captureOnboardingFailureDiagnostics(driver, 't8b-5-submit-booking', {
        flow: '8B-5',
        marker: bookingMarker,
      });
      error.message = `${error.message} Diagnostics: ${JSON.stringify(diagnostics)}.`;
      throw error;
    }
  });

  it('8B-6 Pending applicant can access vendor workspace with guided onboarding state', async function () {
    try {
      await ensureVisitorSession();
      await assertApplicantDashboardGuided(driver, { marker: bookingMarker });
      await assertApplicantProfileAccessible(driver);
    } catch (error) {
      const diagnostics = await captureOnboardingFailureDiagnostics(driver, 't8b-6-applicant-workspace', {
        flow: '8B-6',
        marker: bookingMarker,
      });
      error.message = `${error.message} Diagnostics: ${JSON.stringify(diagnostics)}.`;
      throw error;
    }
  });

  it('8B-7 Operational gates remain locked for pending applicant', async function () {
    try {
      await ensureVisitorSession();
      await assertOperationalGatesRemainLocked(driver);
    } catch (error) {
      const diagnostics = await captureOnboardingFailureDiagnostics(driver, 't8b-7-operational-gates', {
        flow: '8B-7',
        marker: bookingMarker,
      });
      error.message = `${error.message} Diagnostics: ${JSON.stringify(diagnostics)}.`;
      throw error;
    }
  });

  it('8B-8 Staff/manager protection remains unchanged for community applicant', async function () {
    this.timeout(45000);

    try {
      await ensureVisitorSession();
      await assertApplicantBlockedFromAdmin(driver);
    } catch (error) {
      const diagnostics = await captureOnboardingFailureDiagnostics(driver, 't8b-8-admin-protection', {
        flow: '8B-8',
      });
      error.message = `${error.message} Diagnostics: ${JSON.stringify(diagnostics)}.`;
      throw error;
    }
  });

  it.skip('8B-9 Approval alignment across staff/manager workflow (follow-up)', async function () {
    // Follow-up: reuse approval-pipeline helpers to forward/approve the E2E marker booking,
    // then assert whether unlocked features depend on booking.approval_status vs user.vendor_status.
    // Covered partially by manager.booking-approval.spec.js and vendor.receipt-pass-after-paid.spec.js.
  });
});
