import api from './api';

export const getEventAnalyticsOverview = (eventId) =>
  api.get(`/organizer/events/${eventId}/analytics/overview`);

export const getEventAnalyticsSection = (eventId, section) =>
  api.get(`/organizer/events/${eventId}/analytics/${section}`);

export const recomputeEventAnalytics = (eventId) =>
  api.post(`/organizer/events/${eventId}/analytics/recompute`);

export const listSurveyImports = (eventId) =>
  api.get(`/organizer/events/${eventId}/survey-imports`);

export const getSurveyImport = (eventId, batchId) =>
  api.get(`/organizer/events/${eventId}/survey-imports/${batchId}`);

export const uploadSurveyImport = (eventId, file, { replaceExisting = false } = {}) => {
  const form = new FormData();
  form.append('file', file);
  if (replaceExisting) {
    form.append('replace_existing', '1');
  }
  return api.post(`/organizer/events/${eventId}/survey-imports`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
};

export const setAnalyticsSourceMode = (eventId, mode) =>
  api.put(`/organizer/events/${eventId}/analytics/source-mode`, { mode });

export const removeCsvFromAnalytics = (eventId) =>
  api.post(`/organizer/events/${eventId}/survey-imports/remove-from-analytics`);

export const activateSurveyImport = (eventId, batchId) =>
  api.post(`/organizer/events/${eventId}/survey-imports/${batchId}/activate`);

export const excludeSurveyImport = (eventId, batchId) =>
  api.post(`/organizer/events/${eventId}/survey-imports/${batchId}/exclude`);

export const archiveSurveyImport = (eventId, batchId) =>
  api.post(`/organizer/events/${eventId}/survey-imports/${batchId}/archive`);

export const restoreSurveyImport = (eventId, batchId) =>
  api.post(`/organizer/events/${eventId}/survey-imports/${batchId}/restore`);

export const undoSurveyImport = (eventId) =>
  api.post(`/organizer/events/${eventId}/survey-imports/undo`);

export const listCarbootEventsForAnalytics = () => api.get('/carboot-events');

/** Event-scoped word cloud via Laravel proxy (never falls back to global). */
export const getEventWordcloud = (source, eventId) =>
  api.get(`/boss/analytics/wordcloud/${source}`, {
    params: { event_id: eventId },
  });
