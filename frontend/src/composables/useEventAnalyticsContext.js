import { computed, ref, watch } from 'vue';

const STORAGE_KEY = 'cmart.eventAnalytics.selectedEventId';

const selectedEventId = ref('');
const selectedEvent = ref(null);

function readStoredEventId() {
  try {
    return sessionStorage.getItem(STORAGE_KEY) || '';
  } catch {
    return '';
  }
}

function persistEventId(id) {
  try {
    if (id) sessionStorage.setItem(STORAGE_KEY, String(id));
    else sessionStorage.removeItem(STORAGE_KEY);
  } catch {
    /* ignore */
  }
}

if (!selectedEventId.value) {
  selectedEventId.value = readStoredEventId();
}

watch(selectedEventId, (id) => persistEventId(id));

export function useEventAnalyticsContext() {
  const hasEvent = computed(() => Boolean(selectedEventId.value));

  const setSelectedEventId = (id) => {
    selectedEventId.value = id ? String(id) : '';
  };

  const setSelectedEvent = (event) => {
    selectedEvent.value = event || null;
    setSelectedEventId(event?.id || '');
  };

  const clearSelectedEvent = () => {
    selectedEvent.value = null;
    setSelectedEventId('');
  };

  return {
    selectedEventId,
    selectedEvent,
    hasEvent,
    setSelectedEventId,
    setSelectedEvent,
    clearSelectedEvent,
  };
}
