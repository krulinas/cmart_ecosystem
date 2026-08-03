import { ref } from 'vue';

/** Sections stay fresh for 60s before an automatic revisit refetches. */
export const SECTION_STALE_MS = 60_000;

const ALL_SECTIONS = [
  'bookings',
  'feedback',
  'events',
  'layout',
  'item-reservations',
  'news',
  'tools',
  'event-analytics',
  'audit',
  'report-centre',
  'reports',
];

function createSectionState() {
  return {
    loaded: false,
    loading: false,
    error: null,
    lastLoadedAt: null,
  };
}

/**
 * Per-section load cache for the management dashboard.
 * Tracks loaded/loading/error/staleness without storing panel data.
 */
export function useSectionCache() {
  const sections = ref(
    Object.fromEntries(ALL_SECTIONS.map((id) => [id, createSectionState()])),
  );

  const getSection = (id) => sections.value[id] ?? createSectionState();

  const isStale = (id) => {
    const section = getSection(id);
    if (!section.loaded || !section.lastLoadedAt) return true;
    return Date.now() - section.lastLoadedAt > SECTION_STALE_MS;
  };

  const shouldAutoLoad = (id) => {
    const section = getSection(id);
    if (section.loading) return false;
    if (!section.loaded) return true;
    return isStale(id);
  };

  const markLoading = (id) => {
    const section = getSection(id);
    section.loading = true;
    section.error = null;
    sections.value = { ...sections.value, [id]: { ...section } };
  };

  const markLoaded = (id) => {
    sections.value = {
      ...sections.value,
      [id]: {
        loaded: true,
        loading: false,
        error: null,
        lastLoadedAt: Date.now(),
      },
    };
  };

  const markError = (id, error) => {
    const section = getSection(id);
    sections.value = {
      ...sections.value,
      [id]: {
        ...section,
        loading: false,
        error: error ?? true,
      },
    };
  };

  const invalidate = (id) => {
    sections.value = {
      ...sections.value,
      [id]: createSectionState(),
    };
  };

  const invalidateAll = () => {
    sections.value = Object.fromEntries(
      ALL_SECTIONS.map((id) => [id, createSectionState()]),
    );
  };

  return {
    sections,
    getSection,
    isStale,
    shouldAutoLoad,
    markLoading,
    markLoaded,
    markError,
    invalidate,
    invalidateAll,
  };
}
