/**
 * Phase 3.6 — Organizer event layout API client.
 * Uses the shared Axios instance (Bearer Sanctum token).
 */
import api from './api';

export function getOrganizerVendorCategories() {
  return api.get('/organizer/vendor-categories');
}

export function getOrganizerEventLayout(eventId) {
  return api.get(`/organizer/events/${eventId}/layout`);
}

export function getOrganizerEventLayoutReadiness(eventId) {
  return api.get(`/organizer/events/${eventId}/layout/readiness`);
}

export function createLayoutRow(eventId, payload) {
  return api.post(`/organizer/events/${eventId}/layout/rows`, payload);
}

export function updateLayoutRow(eventId, rowId, payload) {
  return api.patch(`/organizer/events/${eventId}/layout/rows/${rowId}`, payload);
}

export function reorderLayoutRows(eventId, payload) {
  return api.patch(`/organizer/events/${eventId}/layout/rows/reorder`, payload);
}

export function deleteLayoutRow(eventId, rowId) {
  return api.delete(`/organizer/events/${eventId}/layout/rows/${rowId}`);
}

export function archiveLayoutRow(eventId, rowId) {
  return api.patch(`/organizer/events/${eventId}/layout/rows/${rowId}/archive`);
}

export function unarchiveLayoutRow(eventId, rowId) {
  return api.patch(`/organizer/events/${eventId}/layout/rows/${rowId}/unarchive`);
}

export function createLayoutSite(eventId, rowId, payload) {
  return api.post(`/organizer/events/${eventId}/layout/rows/${rowId}/sites`, payload);
}

export function generateLayoutSites(eventId, rowId, payload) {
  return api.post(`/organizer/events/${eventId}/layout/rows/${rowId}/sites/generate`, payload);
}

export function updateLayoutSite(eventId, siteId, payload) {
  return api.patch(`/organizer/events/${eventId}/layout/sites/${siteId}`, payload);
}

export function reorderLayoutSites(eventId, rowId, payload) {
  return api.patch(`/organizer/events/${eventId}/layout/rows/${rowId}/sites/reorder`, payload);
}

export function deleteLayoutSite(eventId, siteId) {
  return api.delete(`/organizer/events/${eventId}/layout/sites/${siteId}`);
}

export function getSpaceCatalogue() {
  return api.get('/spaces');
}

export function getCarbootEvents() {
  return api.get('/carboot-events');
}
