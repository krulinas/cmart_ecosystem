/**
 * Phase 4.4 — focused item-reservation API client.
 * Uses the shared Axios instance (Bearer Sanctum token).
 */
import api from './api';

// ── Community / reserving user ──────────────────────────────────────────────

export function createItemReservation(vendorItemId) {
  return api.post('/reservations', { vendor_item_id: vendorItemId });
}

export function getMyItemReservations(params = {}) {
  return api.get('/reservations/me', { params });
}

export function getMyItemReservation(publicReference) {
  return api.get(`/reservations/${publicReference}`);
}

export function cancelMyItemReservation(publicReference, payload = {}) {
  return api.post(`/reservations/${publicReference}/cancel`, payload);
}

// ── Vendor (item owner) ─────────────────────────────────────────────────────

export function getVendorItemReservations(params = {}) {
  return api.get('/vendor/item-reservations', { params });
}

export function getVendorItemReservation(publicReference) {
  return api.get(`/vendor/item-reservations/${publicReference}`);
}

export function cancelVendorItemReservation(publicReference, payload = {}) {
  return api.post(`/vendor/item-reservations/${publicReference}/cancel`, payload);
}

export function completeVendorItemReservation(publicReference) {
  return api.post(`/vendor/item-reservations/${publicReference}/complete`);
}

// ── Organizer ───────────────────────────────────────────────────────────────

export function getOrganizerEventItemReservations(eventId, params = {}) {
  return api.get(`/organizer/events/${eventId}/item-reservations`, { params });
}

export function getOrganizerItemReservation(publicReference) {
  return api.get(`/organizer/item-reservations/${publicReference}`);
}

export function getOrganizerItemReservationAudits(publicReference) {
  return api.get(`/organizer/item-reservations/${publicReference}/audits`);
}

export function confirmOrganizerItemReservationCharge(publicReference, note) {
  return api.post(`/organizer/item-reservations/${publicReference}/confirm-charge`, { note });
}

export function waiveOrganizerItemReservationCharge(publicReference, reason) {
  return api.post(`/organizer/item-reservations/${publicReference}/waive-charge`, { reason });
}

export function cancelOrganizerItemReservation(publicReference, payload = {}) {
  return api.post(`/organizer/item-reservations/${publicReference}/cancel`, payload);
}

export function expireOrganizerItemReservation(publicReference, reason) {
  return api.post(`/organizer/item-reservations/${publicReference}/expire`, { reason });
}

export function completeOrganizerItemReservation(publicReference) {
  return api.post(`/organizer/item-reservations/${publicReference}/complete`);
}
