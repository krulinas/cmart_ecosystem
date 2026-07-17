import api from './api';

export function getPublicEventLayout(eventId) {
  return api.get(`/events/${eventId}/layout`);
}

export function isPublicLayoutUnavailable(error) {
  return error?.response?.status === 404
    && error?.response?.data?.error === 'PUBLIC_LAYOUT_NOT_AVAILABLE';
}
