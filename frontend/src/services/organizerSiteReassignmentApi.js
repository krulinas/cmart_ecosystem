import api from './api.js';
export { reassignmentErrorMessage } from './organizerSiteReassignmentMessages.js';

export async function fetchCategoryPlacement(bookingId) {
  const { data } = await api.get(`/organizer/bookings/${bookingId}/category-placement`);
  return data;
}

export async function fetchReassignmentOptions(bookingId) {
  const { data } = await api.get(`/organizer/bookings/${bookingId}/site-reassignment-options`);
  return data;
}

export async function submitSiteReassignment(bookingId, payload) {
  const { data } = await api.patch(`/organizer/bookings/${bookingId}/site-assignment`, payload);
  return data;
}
