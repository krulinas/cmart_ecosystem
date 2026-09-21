/**
 * Event-specific item selection API client (authenticated vendor).
 * Uses the shared Axios instance (Bearer Sanctum token).
 */
import api from './api';

export function getEligibleItemEvents() {
  return api.get('/vendor/item-event-listings/eligible-events');
}

export function getEventItemListings(carbootEventId) {
  return api.get(`/vendor/events/${carbootEventId}/item-listings`);
}

export function selectEventItems(carbootEventId, vendorItemIds = []) {
  return api.post(`/vendor/events/${carbootEventId}/item-listings`, {
    vendor_item_ids: vendorItemIds.map((id) => Number(id)),
  });
}

export function selectAllUnsoldEventItems(carbootEventId) {
  return api.post(`/vendor/events/${carbootEventId}/item-listings`, {
    select_all_unsold: true,
    vendor_item_ids: [],
  });
}

export function removeEventItemListing(carbootEventId, vendorItemId) {
  return api.delete(`/vendor/events/${carbootEventId}/item-listings/${vendorItemId}`);
}
