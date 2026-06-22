<template>
  <div class="min-h-screen bg-gradient-to-b from-sky-50/80 to-gray-50">
    <AppNavbar :variant="auth.isCommunityMember ? 'vendor' : 'public'" />

    <div class="py-8 px-4 sm:px-6 lg:px-8">
      <div class="max-w-6xl mx-auto space-y-6">
        <!-- Page header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <div>
            <p class="text-xs font-bold uppercase tracking-wider text-brand-600 mb-1">Discover Events</p>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-900">CMart Carboot Schedule</h1>
            <p class="mt-2 text-sm text-gray-600 max-w-xl">
              Browse upcoming carboot sales, preview event details, and book your vendor space.
            </p>
          </div>
          <router-link
            :to="backLink"
            class="inline-flex items-center text-gray-500 hover:text-brand-600 font-medium transition-colors duration-200 shrink-0"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            {{ backLabel }}
          </router-link>
        </div>

        <!-- Summary stats -->
        <div v-if="hasLoaded" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div class="rounded-xl border border-sky-100 bg-white px-4 py-3 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Events this month</p>
            <p class="mt-1 text-xl font-extrabold text-gray-900">
              {{ monthEventCount }}
              <span class="text-sm font-medium text-gray-500">{{ monthEventCount === 1 ? 'event' : 'events' }}</span>
            </p>
          </div>
          <div class="rounded-xl border border-sky-100 bg-white px-4 py-3 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Next event</p>
            <p v-if="nextEventSummary" class="mt-1 text-sm font-bold text-gray-900 leading-snug">{{ nextEventSummary }}</p>
            <p v-else class="mt-1 text-sm text-gray-500">No upcoming events</p>
          </div>
          <div class="rounded-xl border border-emerald-100 bg-white px-4 py-3 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Available for booking</p>
            <p class="mt-1 text-xl font-extrabold text-emerald-700">
              {{ availableCount }}
              <span class="text-sm font-medium text-gray-500">{{ availableCount === 1 ? 'event' : 'events' }}</span>
            </p>
          </div>
        </div>

        <!-- Filter chips -->
        <div class="flex flex-wrap gap-2" role="group" aria-label="Filter events">
          <button
            v-for="chip in filterChips"
            :key="chip.id"
            type="button"
            class="rounded-full px-4 py-1.5 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
            :class="activeFilter === chip.id
              ? 'bg-brand-600 text-white shadow-sm'
              : 'bg-white text-gray-600 border border-gray-200 hover:border-brand-300 hover:text-brand-700'"
            :aria-pressed="activeFilter === chip.id"
            @click="setFilter(chip.id)"
          >
            {{ chip.label }}
          </button>
        </div>

        <p v-if="auth.isCmartWorker" class="text-sm text-brand-700 font-medium rounded-lg bg-brand-50 border border-brand-100 px-4 py-2">
          Staff mode: select a date range on the calendar to create a new carboot event.
        </p>

        <!-- Calendar card -->
        <div class="bg-white p-4 sm:p-6 rounded-2xl shadow-lg border border-gray-100 relative">
          <div v-if="loading" class="absolute inset-0 z-10 flex items-center justify-center rounded-2xl bg-white/80 backdrop-blur-sm">
            <p class="text-sm font-medium text-gray-500">Loading events…</p>
          </div>

          <div
            v-if="calendarTitle"
            class="mb-4 text-center border-b border-gray-100 pb-4"
            aria-live="polite"
          >
            <h2 class="text-lg sm:text-xl font-extrabold text-gray-900 tracking-tight">
              {{ calendarTitle }}
            </h2>
          </div>

          <div class="event-calendar-root">
            <FullCalendar ref="calendarRef" :options="calendarOptions" />
          </div>

          <!-- Empty state overlay for current month -->
          <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
          >
            <div
              v-if="hasLoaded && showMonthEmptyState"
              class="mt-6 rounded-xl border border-dashed border-sky-200 bg-sky-50/50 px-6 py-8 text-center"
            >
              <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-sky-100">
                <svg class="h-7 w-7 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
              <p class="text-base font-bold text-gray-800">No carboot events scheduled for this month.</p>
              <p class="mt-1 text-sm text-gray-500">
                {{ activeFilter === 'all' ? 'Try another month or check back soon.' : 'No events match this filter in the current month.' }}
              </p>
              <div class="mt-4 flex flex-wrap justify-center gap-2">
                <button
                  type="button"
                  class="rounded-full bg-brand-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
                  @click="goToToday"
                >
                  Go to today
                </button>
                <button
                  type="button"
                  class="rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
                  @click="goNextMonth"
                >
                  Check next month
                </button>
              </div>
            </div>
          </Transition>
        </div>
      </div>
    </div>

    <!-- Hover preview (desktop) -->
    <EventCalendarHoverCard
      :visible="hoverVisible"
      :event="hoverEvent"
      :anchor-rect="hoverRect"
    />

    <!-- Rich event preview modal -->
    <EventDetailsModal
      v-model="showEventModal"
      :event="selectedEvent"
      :booking-link="vendorBookingLink(selectedEvent?.id, auth)"
      booking-label="Book a Space"
    />

    <!-- Staff create-event modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showStaffModal"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
          role="dialog"
          aria-modal="true"
          aria-labelledby="staff-create-title"
          @keydown.esc="closeStaffModal"
        >
          <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl overflow-hidden" @click.stop>
            <div class="flex items-center justify-between px-5 py-4 bg-gray-900">
              <h3 id="staff-create-title" class="text-lg font-bold text-white">Create Carboot Event</h3>
              <button
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                aria-label="Close"
                @click="closeStaffModal"
              >
                ×
              </button>
            </div>
            <div class="p-6 space-y-4">
              <div class="rounded-xl bg-sky-50 border border-sky-100 p-4 text-sm text-gray-700 space-y-1">
                <div><strong>Start:</strong> {{ formatDateTime(staffModal.start) }}</div>
                <div v-if="staffModal.end"><strong>End:</strong> {{ formatDateTime(staffModal.end) }}</div>
              </div>
              <div>
                <label for="staff-event-title" class="block text-sm font-semibold mb-1">Event title</label>
                <input
                  id="staff-event-title"
                  v-model="newEventTitle"
                  class="w-full border rounded-lg p-3 border-gray-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
                  placeholder="CMart Weekly Carboot"
                />
              </div>
              <div>
                <label for="staff-event-status" class="block text-sm font-semibold mb-1">Status</label>
                <select
                  id="staff-event-status"
                  v-model="newEventStatus"
                  class="w-full border rounded-lg p-3 border-gray-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
                >
                  <option>Available</option>
                  <option>Almost Full</option>
                  <option>Closed</option>
                </select>
              </div>
              <div class="flex justify-end gap-3 pt-2">
                <button
                  type="button"
                  class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50"
                  @click="closeStaffModal"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-bold text-white hover:bg-brand-700"
                  @click="createEvent"
                >
                  Create Event
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import AppNavbar from './navigation/AppNavbar.vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import api from '../services/api';
import { useAuthStore } from '../stores/auth';
import { vendorBookingLink } from '../utils/vendorBooking';
import EventDetailsModal from './EventDetailsModal.vue';
import EventCalendarHoverCard from './EventCalendarHoverCard.vue';
import {
  DEFAULT_EVENT_LOCATION,
  EVENT_TZ,
  eventDateKey,
  filterEventsByChip,
  formatEventChipTime,
  formatEventDateTime,
  formatEventShortDate,
  formatCalendarMonthTitle,
  getEventZonedParts,
  mapApiEventToCard,
  parseEventInstant,
} from '../utils/eventDisplay';

const labeledOtherMonths = new Set();

const CHIP_DOT_CLASS = {
  Available: 'ec-event-chip__dot--available',
  'Almost Full': 'ec-event-chip__dot--warning',
  Closed: 'ec-event-chip__dot--closed',
};

const auth = useAuthStore();
const calendarRef = ref(null);

const backLink = computed(() => (auth.isAuthenticated ? auth.homeForUser() : '/'));
const backLabel = computed(() => (auth.isAuthenticated ? 'Back to Dashboard' : 'Back to Home'));

const loading = ref(true);
const hasLoaded = ref(false);
const cardEvents = ref([]);
const activeFilter = ref('all');
const currentMonthKey = ref('');
const currentViewType = ref('dayGridMonth');
const calendarTitle = ref('');

const showEventModal = ref(false);
const selectedEvent = ref(null);

const hoverVisible = ref(false);
const hoverEvent = ref(null);
const hoverRect = ref(null);

const showStaffModal = ref(false);
const newEventTitle = ref('');
const newEventStatus = ref('Available');
const staffModal = reactive({
  start: '',
  end: '',
  selectInfo: null,
});

const filterChips = [
  { id: 'all', label: 'All' },
  { id: 'available', label: 'Available' },
  { id: 'closed', label: 'Full / Closed' },
  { id: 'this-month', label: 'This month' },
  { id: 'upcoming', label: 'Upcoming' },
];

const formatDateTime = (dateStr) => formatEventDateTime(dateStr);

const filteredCardEvents = computed(() =>
  filterEventsByChip(cardEvents.value, activeFilter.value, currentMonthKey.value),
);

const eventsInViewMonth = computed(() => {
  if (!currentMonthKey.value) return [];
  return cardEvents.value.filter((ev) => eventDateKey(ev.startsAt).startsWith(currentMonthKey.value));
});

const filteredInViewMonth = computed(() => {
  if (!currentMonthKey.value) return [];
  return filteredCardEvents.value.filter((ev) => eventDateKey(ev.startsAt).startsWith(currentMonthKey.value));
});

const monthEventCount = computed(() => eventsInViewMonth.value.length);

const nextEventSummary = computed(() => {
  const now = new Date();
  const next = [...cardEvents.value]
    .filter((ev) => {
      const end = parseEventInstant(ev.endsAt);
      return !end || end >= now;
    })
    .sort((a, b) => (parseEventInstant(a.startsAt)?.getTime() || 0) - (parseEventInstant(b.startsAt)?.getTime() || 0))[0];

  if (!next) return '';
  return `${next.title}, ${formatEventShortDate(next.startsAt)}`;
});

const availableCount = computed(() => cardEvents.value.filter((ev) => ev.status === 'Available').length);

const showMonthEmptyState = computed(() => {
  return currentViewType.value === 'dayGridMonth' && filteredInViewMonth.value.length === 0;
});

const mapToCalendarEvents = (list) =>
  list.map((card) => ({
    id: String(card.id),
    title: card.title,
    start: card.startsAt,
    end: card.endsAt,
    extendedProps: { status: card.status, card },
  }));

const syncCalendarEvents = () => {
  calendarOptions.events = mapToCalendarEvents(filteredCardEvents.value);
};

const setFilter = (id) => {
  activeFilter.value = id;
  syncCalendarEvents();
};

const loadEvents = async () => {
  loading.value = true;
  try {
    const { data } = await api.get('/events');
    const raw = Array.isArray(data) ? data : [];
    cardEvents.value = raw.map((ev) => mapApiEventToCard(ev, DEFAULT_EVENT_LOCATION));
    hasLoaded.value = true;
    syncCalendarEvents();
  } catch (e) {
    console.error('Failed to load calendar events:', e);
  } finally {
    loading.value = false;
  }
};

const openEventPreview = (card) => {
  hoverVisible.value = false;
  selectedEvent.value = card;
  showEventModal.value = true;
};

const handleEventClick = (clickInfo) => {
  const card = clickInfo.event.extendedProps?.card;
  if (card) {
    openEventPreview(card);
  }
};

const handleDateSelect = (selectInfo) => {
  if (!auth.isCmartWorker) {
    selectInfo.view.calendar.unselect();
    return;
  }
  staffModal.start = selectInfo.startStr;
  staffModal.end = selectInfo.endStr;
  staffModal.selectInfo = selectInfo;
  newEventTitle.value = '';
  newEventStatus.value = 'Available';
  showStaffModal.value = true;
};

const closeStaffModal = () => {
  showStaffModal.value = false;
  staffModal.selectInfo?.view?.calendar?.unselect();
};

const createEvent = async () => {
  if (!newEventTitle.value.trim() || !staffModal.selectInfo) return;
  try {
    await api.post('/carboot-events', {
      title: newEventTitle.value.trim(),
      starts_at: staffModal.selectInfo.startStr,
      ends_at: staffModal.selectInfo.endStr,
      status: newEventStatus.value,
    });
    closeStaffModal();
    await loadEvents();
  } catch (e) {
    console.error('Failed to create event:', e);
    alert('Could not create event. Ensure you are logged in as CMart staff.');
  }
};

const goToToday = () => {
  calendarRef.value?.getApi()?.today();
};

const goNextMonth = () => {
  calendarRef.value?.getApi()?.next();
};

const dateKeyFromFcDate = (date) => {
  const parts = getEventZonedParts(date instanceof Date ? date.toISOString() : date);
  if (!parts) return '';
  return `${parts.year}-${parts.month}-${parts.day}`;
};

const countEventsOnDay = (dateKey) =>
  filteredCardEvents.value.filter((ev) => eventDateKey(ev.startsAt) === dateKey).length;

const escapeHtml = (text) => {
  const el = document.createElement('span');
  el.textContent = text || '';
  return el.innerHTML;
};

const buildEventChip = (arg) => {
  const card = arg.event.extendedProps?.card;
  const time = card ? formatEventChipTime(card.startsAt) : arg.timeText;
  const status = card?.status || arg.event.extendedProps?.status || '';
  const dotClass = CHIP_DOT_CLASS[status] || 'ec-event-chip__dot--default';

  const chip = document.createElement('div');
  chip.className = 'ec-event-chip';
  chip.innerHTML = `
    <span class="ec-event-chip__dot ${dotClass}" aria-hidden="true"></span>
    ${time ? `<span class="ec-event-chip__time">${escapeHtml(time)}</span>` : ''}
    <span class="ec-event-chip__title">${escapeHtml(arg.event.title)}</span>
  `;
  return chip;
};

const attachHoverHandlers = (info) => {
  const card = info.event.extendedProps?.card;
  if (!card) return;

  info.el.setAttribute('tabindex', '0');
  info.el.setAttribute('role', 'button');

  const label = [card.title, card.dateLabel, card.dateNumeric, card.time].filter(Boolean).join(', ');
  info.el.setAttribute('aria-label', label);

  const showHover = () => {
    hoverEvent.value = card;
    hoverRect.value = info.el.getBoundingClientRect();
    hoverVisible.value = true;
  };

  const hideHover = () => {
    hoverVisible.value = false;
  };

  const onKeydown = (event) => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      openEventPreview(card);
    }
  };

  info.el.addEventListener('mouseenter', showHover);
  info.el.addEventListener('mouseleave', hideHover);
  info.el.addEventListener('focus', showHover);
  info.el.addEventListener('blur', hideHover);
  info.el.addEventListener('keydown', onKeydown);

  info.el._ecCleanup = () => {
    info.el.removeEventListener('mouseenter', showHover);
    info.el.removeEventListener('mouseleave', hideHover);
    info.el.removeEventListener('focus', showHover);
    info.el.removeEventListener('blur', hideHover);
    info.el.removeEventListener('keydown', onKeydown);
  };
};

const markEventMount = (info) => {
  attachHoverHandlers(info);

  if (currentViewType.value !== 'dayGridMonth' || !currentMonthKey.value) return;

  const card = info.event.extendedProps?.card;
  if (!card) return;

  const eventMonthKey = eventDateKey(card.startsAt).slice(0, 7);
  if (eventMonthKey !== currentMonthKey.value) {
    info.el.classList.add('ec-event--other-month');
  }
};

const decorateDayCell = (info) => {
  const isOther = info.el.classList.contains('fc-day-other');
  const key = dateKeyFromFcDate(info.date);
  const monthKey = key.slice(0, 7);

  if (isOther) {
    info.el.classList.add('ec-day-other-month');

    if (!labeledOtherMonths.has(monthKey)) {
      labeledOtherMonths.add(monthKey);
      const top = info.el.querySelector('.fc-daygrid-day-top');
      if (top && !top.querySelector('.ec-other-month-label')) {
        const label = document.createElement('span');
        label.className = 'ec-other-month-label';
        label.textContent = `${key.slice(5, 7)}/${key.slice(0, 4)}`;
        label.title = 'Dates from another month';
        top.appendChild(label);
      }
    }
  }

  const count = countEventsOnDay(key);
  if (count <= 0) return;

  if (isOther) {
    info.el.classList.add('ec-day-has-events-other');
  } else {
    info.el.classList.add('ec-day-has-events');

    const frame = info.el.querySelector('.fc-daygrid-day-frame');
    if (frame && !frame.querySelector('.ec-day-badge')) {
      const badge = document.createElement('span');
      badge.className = 'ec-day-badge';
      badge.textContent = count === 1 ? '1 event' : `${count} events`;
      frame.insertBefore(badge, frame.firstChild);
    }
  }
};

const calendarOptions = reactive({
  plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
  timeZone: EVENT_TZ,
  initialView: 'dayGridMonth',
  headerToolbar: {
    left: 'prev,next today',
    center: '',
    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
  },
  editable: false,
  selectable: auth.isCmartWorker,
  selectMirror: true,
  dayMaxEvents: 2,
  moreLinkClick: 'popover',
  weekends: true,
  height: 'auto',
  select: handleDateSelect,
  eventClick: handleEventClick,
  events: [],
  eventContent: (arg) => ({ domNodes: [buildEventChip(arg)] }),
  eventDidMount: markEventMount,
  eventWillUnmount: (info) => {
    info.el._ecCleanup?.();
  },
  dayCellDidMount: decorateDayCell,
  datesSet: (info) => {
    currentViewType.value = info.view.type;
    const parts = getEventZonedParts(info.view.currentStart.toISOString());
    if (parts) {
      currentMonthKey.value = `${parts.year}-${parts.month}`;
    }
    labeledOtherMonths.clear();

    if (info.view.type === 'dayGridMonth') {
      calendarTitle.value = formatCalendarMonthTitle(info.view.currentStart);
    } else {
      calendarTitle.value = info.view.title;
    }
  },
});

watch(activeFilter, syncCalendarEvents);

onMounted(loadEvents);
</script>

<style scoped>
.event-calendar-root :deep(.fc) {
  font-family: inherit;
}

.event-calendar-root :deep(.fc .fc-button-primary) {
  background-color: #0284c7;
  border-color: #0284c7;
  color: #fff;
  font-weight: 600;
  border-radius: 8px;
  padding: 0.4rem 1rem;
  transition: background-color 0.15s, border-color 0.15s;
}

.event-calendar-root :deep(.fc .fc-button-primary:hover) {
  background-color: #0369a1;
  border-color: #0369a1;
}

.event-calendar-root :deep(.fc .fc-button-primary:not(:disabled).fc-button-active),
.event-calendar-root :deep(.fc .fc-button-primary:not(:disabled):active) {
  background-color: #0c4a6e;
  border-color: #0c4a6e;
}

.event-calendar-root :deep(.fc-toolbar) {
  flex-wrap: wrap;
  gap: 0.5rem;
}

.event-calendar-root :deep(.fc-toolbar-chunk) {
  display: flex;
  align-items: center;
}

.event-calendar-root :deep(.fc-toolbar-chunk:nth-child(2):empty) {
  display: none;
}

.event-calendar-root :deep(.fc-day-today) {
  background-color: rgba(14, 165, 233, 0.06) !important;
}

.event-calendar-root :deep(.fc-daygrid-day.ec-day-other-month) {
  background-color: #f3f4f6 !important;
}

.event-calendar-root :deep(.fc-daygrid-day.ec-day-other-month .fc-daygrid-day-number) {
  opacity: 0.4;
  color: #9ca3af !important;
  font-weight: 500;
}

.event-calendar-root :deep(.fc-daygrid-day.ec-day-other-month .fc-daygrid-day-frame) {
  border: none !important;
  border-radius: 0;
  margin: 0;
}

.event-calendar-root :deep(.fc-day-other.ec-day-has-events-other) {
  background-color: #eceff2 !important;
}

.event-calendar-root :deep(.ec-other-month-label) {
  display: inline-block;
  margin: 0 0 2px 4px;
  padding: 1px 5px;
  border-radius: 4px;
  background: #e5e7eb;
  font-size: 9px;
  font-weight: 700;
  color: #6b7280;
  vertical-align: middle;
}

.event-calendar-root :deep(.fc-daygrid-day.ec-day-has-events) {
  background-color: rgba(224, 242, 254, 0.45);
}

.event-calendar-root :deep(.fc-daygrid-day.ec-day-has-events .fc-daygrid-day-frame) {
  border: 1px solid rgba(125, 211, 252, 0.5);
  border-radius: 8px;
  margin: 1px;
}

.event-calendar-root :deep(.fc-daygrid-day.ec-day-other-month.ec-day-has-events-other .fc-daygrid-day-frame) {
  border: 1px dashed #d1d5db !important;
  border-radius: 6px;
  margin: 1px;
  opacity: 0.85;
}

.event-calendar-root :deep(.ec-day-badge) {
  display: inline-block;
  margin: 2px 0 4px 4px;
  padding: 1px 6px;
  border-radius: 9999px;
  background: rgba(255, 255, 255, 0.9);
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #0369a1;
  border: 1px solid rgba(125, 211, 252, 0.6);
}

.event-calendar-root :deep(.fc-event) {
  background: transparent !important;
  border: none !important;
  box-shadow: none !important;
  margin-bottom: 2px !important;
  padding: 0 !important;
}

.event-calendar-root :deep(.fc-event:focus) {
  outline: none;
}

.event-calendar-root :deep(.fc-event:focus-visible .ec-event-chip) {
  outline: 2px solid #0ea5e9;
  outline-offset: 2px;
}

.event-calendar-root :deep(.ec-event-chip) {
  display: flex;
  align-items: center;
  gap: 4px;
  width: 100%;
  padding: 3px 6px;
  border-radius: 6px;
  background: #fff;
  border: 1px solid #e0f2fe;
  box-shadow: 0 1px 2px rgba(14, 165, 233, 0.08);
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
  overflow: hidden;
}

.event-calendar-root :deep(.ec-event-chip:hover) {
  transform: translateY(-1px) scale(1.01);
  box-shadow: 0 3px 8px rgba(14, 165, 233, 0.15);
  background: #f0f9ff;
  border-color: #7dd3fc;
}

.event-calendar-root :deep(.ec-event--other-month .ec-event-chip) {
  background: #f3f4f6;
  border-color: #e5e7eb;
  box-shadow: none;
  opacity: 0.72;
}

.event-calendar-root :deep(.ec-event--other-month .ec-event-chip:hover) {
  transform: none;
  opacity: 0.88;
  background: #e5e7eb;
  border-color: #d1d5db;
  box-shadow: none;
}

.event-calendar-root :deep(.ec-event--other-month .ec-event-chip__time) {
  color: #6b7280;
}

.event-calendar-root :deep(.ec-event--other-month .ec-event-chip__title) {
  color: #6b7280;
}

.event-calendar-root :deep(.ec-event--other-month .ec-event-chip__dot) {
  opacity: 0.6;
}

.event-calendar-root :deep(.fc-day-other .fc-daygrid-more-link) {
  color: #9ca3af;
  font-weight: 600;
}

.event-calendar-root :deep(.ec-event-chip__dot) {
  width: 6px;
  height: 6px;
  border-radius: 9999px;
  flex-shrink: 0;
}

.event-calendar-root :deep(.ec-event-chip__dot--available) {
  background: #10b981;
}

.event-calendar-root :deep(.ec-event-chip__dot--warning) {
  background: #f59e0b;
}

.event-calendar-root :deep(.ec-event-chip__dot--closed) {
  background: #9ca3af;
}

.event-calendar-root :deep(.ec-event-chip__dot--default) {
  background: #0ea5e9;
}

.event-calendar-root :deep(.ec-event-chip__time) {
  font-size: 10px;
  font-weight: 700;
  color: #0369a1;
  white-space: nowrap;
  flex-shrink: 0;
}

.event-calendar-root :deep(.ec-event-chip__title) {
  font-size: 11px;
  font-weight: 600;
  color: #1e293b;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
}

.event-calendar-root :deep(.fc-daygrid-more-link) {
  font-size: 10px;
  font-weight: 700;
  color: #0284c7;
  border-radius: 4px;
  padding: 2px 4px;
}

.event-calendar-root :deep(.fc-list-event:hover td) {
  background: #f0f9ff;
}

@media (max-width: 640px) {
  .event-calendar-root :deep(.ec-event-chip__time) {
    display: none;
  }

  .event-calendar-root :deep(.ec-day-badge) {
    font-size: 8px;
    padding: 0 4px;
  }
}
</style>
