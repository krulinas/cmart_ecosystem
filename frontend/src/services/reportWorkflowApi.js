import api from './api';

export const REPORT_TYPE_POST_EVENT = 'post_event_summary';

/** @param {Record<string, unknown>} [params] */
export function listCmartReportEvents(params = {}) {
  return api.get('/cmart/report-events', { params });
}

export function listCmartReportRequests(params = {}) {
  return api.get('/cmart/report-requests', { params });
}

export function getCmartReportRequest(id) {
  return api.get(`/cmart/report-requests/${id}`);
}

export function createCmartReportRequest(payload) {
  return api.post('/cmart/report-requests', payload);
}

export function cancelCmartReportRequest(id) {
  return api.post(`/cmart/report-requests/${id}/cancel`);
}

export function listCmartGeneratedReports(params = {}) {
  return api.get('/cmart/generated-reports', { params });
}

export function getCmartGeneratedReport(id) {
  return api.get(`/cmart/generated-reports/${id}`);
}

export function markCmartGeneratedReportViewed(id) {
  return api.post(`/cmart/generated-reports/${id}/mark-viewed`);
}

export function cmartGeneratedReportPdfUrl(id) {
  const base = api.defaults.baseURL || '';
  return `${base}/cmart/generated-reports/${id}/pdf`;
}

export function listOrganizerReportRequests(params = {}) {
  return api.get('/organizer/report-requests', { params });
}

export function getOrganizerReportRequest(id) {
  return api.get(`/organizer/report-requests/${id}`);
}

export function acknowledgeReportRequest(id, payload = {}) {
  return api.post(`/organizer/report-requests/${id}/acknowledge`, payload);
}

export function startReportRequestPreparation(id, payload = {}) {
  return api.post(`/organizer/report-requests/${id}/start-preparation`, payload);
}

export function declineReportRequest(id, payload) {
  return api.post(`/organizer/report-requests/${id}/decline`, payload);
}

export function listOrganizerGeneratedReports(params = {}) {
  return api.get('/organizer/generated-reports', { params });
}

export function createOrganizerGeneratedReport(payload) {
  return api.post('/organizer/generated-reports', payload);
}

export function getOrganizerGeneratedReport(id) {
  return api.get(`/organizer/generated-reports/${id}`);
}

export function updateOrganizerReportNarratives(id, payload) {
  return api.patch(`/organizer/generated-reports/${id}/narratives`, payload);
}

export function regenerateOrganizerReport(id) {
  return api.post(`/organizer/generated-reports/${id}/regenerate`);
}

export function publishOrganizerReport(id) {
  return api.post(`/organizer/generated-reports/${id}/publish`);
}

export function deleteOrganizerDraftReport(id) {
  return api.delete(`/organizer/generated-reports/${id}`);
}

export function reviseOrganizerReport(id, payload) {
  return api.post(`/organizer/generated-reports/${id}/revise`, payload);
}

export function organizerGeneratedReportPdfUrl(id) {
  const base = api.defaults.baseURL || '';
  return `${base}/organizer/generated-reports/${id}/pdf`;
}

export function listManagementNotifications() {
  return api.get('/management/notifications');
}

export function getManagementUnreadNotificationCount() {
  return api.get('/management/notifications/unread-count');
}

export function markManagementNotificationRead(id) {
  return api.post(`/management/notifications/${id}/read`);
}

export function markAllManagementNotificationsRead() {
  return api.post('/management/notifications/mark-all-read');
}

export async function openAuthorizedPdf(url) {
  const token = localStorage.getItem('carboot_cmart_token');
  const response = await fetch(url, {
    headers: {
      Accept: 'application/pdf',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });
  if (!response.ok) {
    throw new Error('Unable to download PDF.');
  }
  const blob = await response.blob();
  const objectUrl = URL.createObjectURL(blob);
  window.open(objectUrl, '_blank', 'noopener');
  setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000);
}
