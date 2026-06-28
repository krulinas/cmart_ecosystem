import { strict as assert } from 'node:assert';
import { env, requireManagerCredentials, requireStaffCredentials } from '../config/env.js';
import {
  assertManagerOnlyBookingActionsAbsent,
  assertStaffCannotDeleteBooking,
  managerApiRequest,
  staffApiRequest,
} from './access-guards.js';
import { loginAsManager, loginAsStaff, loginAsVendor, loginAsVendorB, logout } from './auth.js';
import { ensureE2EBookingExists } from './booking.js';
import { captureFailureDiagnostics } from './diagnostics.js';
import { runE2EApprovalPipeline } from './approval-pipeline.js';
import { createRejectedE2EBooking } from './payment-guards.js';
import { verifyPaymentAsPaid } from './payment-verification.js';
import {
  assertGuestApiDenied,
  assertBookingStillExistsForVendor,
  ensureGuestSession,
  guestApiRequest,
} from './guest-access.js';
import {
  openStaffBookings,
  searchStaffBookings,
  forwardE2EBookingToManager,
  approveE2EBookingAsManager,
} from './staff-bookings.js';
import { createWithdrawnE2EBooking } from './vendor-bookings.js';
import {
  attemptVendorPaymentSubmitForBookingId,
  assertOwnershipDenied,
  resolveVendorBookingIdByMarker,
  vendorApiRequest,
} from './vendor-ownership.js';
import {
  goToVendorPaymentRecords,
  openVendorPaymentAction,
  submitVendorPayment,
  uploadVendorPaymentProof,
} from './vendor-payment-records.js';

export const DENIED_MUTATION_STATUSES = new Set([401, 403, 404, 409, 422]);

export function assertMutationDenied(response, { endpoint, method = 'UNKNOWN', label }) {
  assert.equal(
    response.ok,
    false,
    `${label} (${method} ${endpoint}) must be denied. Response: ${String(response.body).slice(0, 240)}`,
  );
  assert.ok(
    DENIED_MUTATION_STATUSES.has(response.status),
    `${label} (${method} ${endpoint}) must return a safe denial (401/403/404/409/422); got HTTP ${response.status}. ` +
      `Body: ${String(response.body).slice(0, 240)}`,
  );
  assert.notEqual(
    response.status,
    200,
    `${label} (${method} ${endpoint}) must not return HTTP 200.`,
  );
  assert.notEqual(
    response.status,
    204,
    `${label} (${method} ${endpoint}) must not return HTTP 204.`,
  );
}

export function parseBookingRecord(body) {
  const payload = JSON.parse(body);
  return payload.booking ?? payload;
}

export function extractBookingSnapshot(booking) {
  const invoice = booking.invoice ?? {};
  return {
    bookingId: Number(booking.id ?? booking.booking_id),
    approvalStatus: booking.approval_status,
    paymentStatus: invoice.payment_status ?? booking.payment_status ?? null,
    userId: booking.user_id ?? null,
    withdrawnAt: booking.withdrawn_at ?? null,
    withdrawalReason: booking.withdrawal_reason ?? null,
    productDetails: booking.product_details ?? null,
  };
}

export async function fetchVendorBookingSnapshot(driver, bookingId) {
  const response = await vendorApiRequest(driver, 'GET', `/vendor/bookings/${bookingId}`);
  assert.equal(
    response.status,
    200,
    `GET /vendor/bookings/${bookingId} failed: ${response.body?.slice(0, 240)}`,
  );
  return extractBookingSnapshot(parseBookingRecord(response.body));
}

export async function fetchStaffBookingSnapshot(driver, bookingId) {
  const response = await staffApiRequest(driver, 'GET', `/bookings/${bookingId}`);
  assert.equal(
    response.status,
    200,
    `GET /bookings/${bookingId} failed: ${response.body?.slice(0, 240)}`,
  );
  return extractBookingSnapshot(parseBookingRecord(response.body));
}

export function assertSnapshotUnchanged(before, after, { label, bookingId }) {
  assert.equal(
    after.approvalStatus,
    before.approvalStatus,
    `${label}: approval_status for booking #${bookingId} must remain "${before.approvalStatus}" after denied mutation.`,
  );
  assert.equal(
    after.paymentStatus,
    before.paymentStatus,
    `${label}: payment_status for booking #${bookingId} must remain "${before.paymentStatus}" after denied mutation.`,
  );
  assert.equal(
    after.userId,
    before.userId,
    `${label}: owner user_id for booking #${bookingId} must remain unchanged after denied mutation.`,
  );
  assert.equal(
    after.withdrawnAt,
    before.withdrawnAt,
    `${label}: withdrawn_at for booking #${bookingId} must remain unchanged after denied mutation.`,
  );
  assert.equal(
    after.withdrawalReason,
    before.withdrawalReason,
    `${label}: withdrawal_reason for booking #${bookingId} must remain unchanged after denied mutation.`,
  );
}

export async function attemptVendorWithdrawApi(driver, bookingId, reason = 'E2E destructive guard withdraw attempt') {
  return vendorApiRequest(driver, 'PATCH', `/bookings/${bookingId}/withdraw`, {
    body: { withdrawal_reason: reason },
  });
}

export async function attemptVendorUpdateApi(driver, bookingId, payload) {
  return vendorApiRequest(driver, 'PATCH', `/vendor/bookings/${bookingId}`, { body: payload });
}

export async function attemptManagerDuplicateApprove(driver, bookingId) {
  return managerApiRequest(driver, 'PATCH', `/bookings/${bookingId}`, {
    body: { approval_status: 'Approved' },
  });
}

export async function assertStaffDeleteGuard(driver, marker, { bookingId } = {}) {
  const ensured = await ensureE2EBookingExists(driver, marker, { allowReuse: false });
  marker = ensured.marker;
  await logout(driver);

  await loginAsStaff(driver);
  await openStaffBookings(driver, env.baseUrl);
  await searchStaffBookings(driver, marker);
  const resolvedId = bookingId ?? (await resolveStaffBookingIdByMarker(driver, marker));
  await assertManagerOnlyBookingActionsAbsent(driver, marker, { bookingId: resolvedId });
  const result = await assertStaffCannotDeleteBooking(driver, resolvedId, marker);
  await logout(driver);

  return { marker, bookingId: resolvedId, deleteStatus: result.deleteStatus };
}

async function resolveStaffBookingIdByMarker(driver, marker) {
  const response = await staffApiRequest(driver, 'GET', '/staff/bookings');
  assert.equal(response.status, 200, `GET /staff/bookings failed: ${response.body?.slice(0, 240)}`);

  const payload = JSON.parse(response.body);
  const bookings = payload.data ?? payload.bookings ?? payload;

  for (const booking of bookings) {
    const details = String(booking.product_details || '').toLowerCase();
    if (details.includes(marker.toLowerCase())) {
      return Number(booking.id ?? booking.booking_id);
    }
  }

  throw new Error(`No staff booking found with marker "${marker}".`);
}

export async function assertGuestDeleteGuard(driver, marker, { bookingId } = {}) {
  await ensureE2EBookingExists(driver, marker, { allowReuse: true });
  const resolvedId = bookingId ?? (await resolveVendorBookingIdByMarker(driver, marker));
  const before = await fetchVendorBookingSnapshot(driver, resolvedId);
  assert.ok(
    String(before.productDetails || '').toLowerCase().includes(marker.toLowerCase()),
    `Booking #${resolvedId} must contain marker "${marker}" before guest delete attempt.`,
  );
  await logout(driver);
  await ensureGuestSession(driver);

  const deleteResponse = await guestApiRequest(driver, 'DELETE', `/bookings/${resolvedId}`);
  assertGuestApiDenied(deleteResponse, {
    endpoint: `/bookings/${resolvedId}`,
    label: 'Guest booking delete',
  });

  await assertBookingStillExistsForVendor(driver, resolvedId, marker);
  await loginAsVendor(driver);
  const after = await fetchVendorBookingSnapshot(driver, resolvedId);
  assertSnapshotUnchanged(before, after, {
    label: 'Guest DELETE guard',
    bookingId: resolvedId,
  });
  await logout(driver);

  return { marker, bookingId: resolvedId, deleteStatus: deleteResponse.status };
}

export async function assertWrongVendorMutationGuard(driver, vendorBMarker, vendorBCredentials) {
  await ensureE2EBookingExists(driver, vendorBMarker, {
    allowReuse: false,
    vendorCredentials: vendorBCredentials,
  });

  const vendorBBookingId = await resolveVendorBookingIdByMarker(driver, vendorBMarker);
  const before = await fetchVendorBookingSnapshot(driver, vendorBBookingId);
  await logout(driver);

  await loginAsVendor(driver);
  const mutationAttempts = [
    {
      label: 'Vendor A withdraw on Vendor B booking',
      method: 'PATCH',
      endpoint: `/bookings/${vendorBBookingId}/withdraw`,
      run: () => attemptVendorWithdrawApi(driver, vendorBBookingId),
    },
    {
      label: 'Vendor A payment submit on Vendor B booking',
      method: 'POST',
      endpoint: `/vendor/bookings/${vendorBBookingId}/submit-payment`,
      run: () => attemptVendorPaymentSubmitForBookingId(driver, vendorBBookingId),
    },
    {
      label: 'Vendor A update on Vendor B booking',
      method: 'PATCH',
      endpoint: `/vendor/bookings/${vendorBBookingId}`,
      run: () =>
        attemptVendorUpdateApi(driver, vendorBBookingId, {
          product_details: 'E2E wrong-vendor mutation attempt',
        }),
    },
  ];

  const observed = [];

  for (const attempt of mutationAttempts) {
    const response = await attempt.run();
    assertOwnershipDenied(response, { endpoint: attempt.endpoint, label: attempt.label });
    observed.push({ ...attempt, status: response.status });
  }

  await logout(driver);
  await loginAsVendorB(driver);
  const after = await fetchVendorBookingSnapshot(driver, vendorBBookingId);
  assertSnapshotUnchanged(before, after, {
    label: 'Wrong-vendor mutation guard',
    bookingId: vendorBBookingId,
  });
  await logout(driver);

  return { vendorBMarker, vendorBBookingId, observed };
}

export async function assertTerminalWithdrawDenied(driver, marker, prepareBooking) {
  const prepared = await prepareBooking(driver, marker);
  marker = prepared.marker;
  const bookingId = prepared.bookingId;

  await loginAsVendor(driver);
  const before = await fetchVendorBookingSnapshot(driver, bookingId);
  const response = await attemptVendorWithdrawApi(driver, bookingId);
  assertMutationDenied(response, {
    endpoint: `/bookings/${bookingId}/withdraw`,
    method: 'PATCH',
    label: `Terminal withdraw guard (${before.approvalStatus})`,
  });

  const after = await fetchVendorBookingSnapshot(driver, bookingId);
  assertSnapshotUnchanged(before, after, {
    label: `Terminal withdraw guard (${before.approvalStatus})`,
    bookingId,
  });
  await logout(driver);

  return {
    marker,
    bookingId,
    approvalStatus: before.approvalStatus,
    paymentStatus: before.paymentStatus,
    withdrawStatus: response.status,
  };
}

export async function prepareApprovedBooking(driver, marker) {
  const pipeline = await runE2EApprovalPipeline(driver, marker);
  return { marker: pipeline.marker, bookingId: pipeline.bookingId };
}

export async function prepareRejectedBooking(driver, marker) {
  return createRejectedE2EBooking(driver, marker);
}

export async function prepareWithdrawnBooking(driver, marker) {
  return createWithdrawnE2EBooking(driver, marker);
}

export async function preparePaidVendorBooking(driver, marker) {
  requireStaffCredentials();
  requireManagerCredentials();

  const pipeline = await runE2EApprovalPipeline(driver, marker);
  marker = pipeline.marker;
  let bookingId = pipeline.bookingId;

  await loginAsVendor(driver);
  await goToVendorPaymentRecords(driver, env.baseUrl);
  await openVendorPaymentAction(driver, bookingId);
  await uploadVendorPaymentProof(driver);
  await submitVendorPayment(driver, { bookingId });
  await logout(driver);

  await loginAsStaff(driver);
  await verifyPaymentAsPaid(driver, marker, { bookingId, baseUrl: env.baseUrl });
  await logout(driver);

  return { marker, bookingId };
}

export async function assertDuplicatePaymentSubmitDenied(driver, marker) {
  const prepared = await preparePaidVendorBooking(driver, marker);
  marker = prepared.marker;
  const bookingId = prepared.bookingId;

  await loginAsVendor(driver);
  const before = await fetchVendorBookingSnapshot(driver, bookingId);
  assert.equal(
    before.paymentStatus,
    'Paid',
    `Booking #${bookingId} must be Paid before duplicate payment guard test.`,
  );

  const response = await attemptVendorPaymentSubmitForBookingId(driver, bookingId);
  assertMutationDenied(response, {
    endpoint: `/vendor/bookings/${bookingId}/submit-payment`,
    method: 'POST',
    label: 'Duplicate payment proof after Paid',
  });
  assert.equal(
    response.status,
    422,
    `Expected HTTP 422 for duplicate payment submit on booking #${bookingId}, got ${response.status}.`,
  );

  const after = await fetchVendorBookingSnapshot(driver, bookingId);
  assertSnapshotUnchanged(before, after, {
    label: 'Duplicate payment submit guard',
    bookingId,
  });
  await logout(driver);

  return { marker, bookingId, paymentStatus: after.paymentStatus, submitStatus: response.status };
}

export async function assertDuplicateApproveDenied(driver, marker) {
  requireManagerCredentials();

  const ensured = await ensureE2EBookingExists(driver, marker, { allowReuse: false });
  marker = ensured.marker;
  await logout(driver);

  await loginAsStaff(driver);
  await openStaffBookings(driver, env.baseUrl);
  const forwarded = await forwardE2EBookingToManager(driver, marker, env.baseUrl);
  await logout(driver);

  await loginAsManager(driver);
  await openStaffBookings(driver, env.baseUrl);
  const approved = await approveE2EBookingAsManager(driver, marker, env.baseUrl, {
    bookingId: forwarded.bookingId,
  });
  const bookingId = approved.bookingId;

  const before = await fetchStaffBookingSnapshot(driver, bookingId);
  assert.equal(before.approvalStatus, 'Approved');

  const response = await attemptManagerDuplicateApprove(driver, bookingId);
  assertMutationDenied(response, {
    endpoint: `/bookings/${bookingId}`,
    method: 'PATCH',
    label: 'Duplicate manager approve on Approved booking',
  });
  assert.equal(
    response.status,
    422,
    `Expected HTTP 422 for duplicate approve on booking #${bookingId}, got ${response.status}.`,
  );

  const after = await fetchStaffBookingSnapshot(driver, bookingId);
  assertSnapshotUnchanged(before, after, {
    label: 'Duplicate manager approve guard',
    bookingId,
  });
  await logout(driver);

  return { marker, bookingId, approveStatus: response.status };
}

export async function captureDestructiveFailureDiagnostics(driver, label, context = {}) {
  const meta = await captureFailureDiagnostics(driver, label);
  meta.destructiveContext = context;

  try {
    meta.currentUser = await driver.executeScript(() => {
      const raw = localStorage.getItem('carboot_cmart_user');
      if (!raw) return null;
      try {
        const user = JSON.parse(raw);
        return { email: user.email, role: user.role };
      } catch {
        return { raw: raw.slice(0, 120) };
      }
    });
  } catch (error) {
    meta.currentUserError = error.message;
  }

  return meta;
}
