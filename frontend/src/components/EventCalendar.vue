<template>
  <div class="min-h-screen bg-gray-50">
    <AppNavbar :variant="auth.isCommunityMember ? 'vendor' : 'public'" />
    <div class="py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-6xl mx-auto bg-white p-6 rounded-2xl shadow-lg border border-gray-100">
      <div class="flex flex-col sm:flex-row justify-between items-center mb-8 pb-4 border-b border-gray-100">
        <h1 class="text-3xl font-extrabold text-black mb-4 sm:mb-0">CMart Carboot Schedule</h1>
        <router-link :to="backLink" class="flex items-center text-[#757575] hover:text-[#0277BD] font-medium transition-colors duration-200">
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
          {{ backLabel }}
        </router-link>
      </div>
      <p v-if="auth.isCmartWorker" class="text-sm text-brand-700 mb-4 font-medium">
        Staff mode: select a date range to create a new carboot event (saved to the server).
      </p>
      <div class="event-calendar-root">
        <FullCalendar :options="calendarOptions" />
      </div>
    </div>

    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
      <div class="relative p-4 w-full max-w-md">
        <div class="relative bg-white rounded-2xl shadow-2xl overflow-hidden">
          <div class="flex items-center justify-between p-5 bg-black">
            <h3 class="text-xl font-bold text-white">{{ modalData.title }}</h3>
            <button @click="closeModal" type="button" class="text-gray-400 hover:text-white rounded-lg text-sm w-8 h-8">×</button>
          </div>
          <div class="p-6">
            <div class="mb-4 bg-sky-50 p-4 rounded-xl border border-sky-100 text-sm">
              <div><strong>Start:</strong> {{ formatDateTime(modalData.start) }}</div>
              <div v-if="modalData.end"><strong>End:</strong> {{ formatDateTime(modalData.end) }}</div>
              <div v-if="modalData.status"><strong>Status:</strong> {{ modalData.status }}</div>
            </div>
            <div v-if="modalData.isNew && auth.isCmartWorker" class="mt-4 space-y-3">
              <div>
                <label class="block text-sm font-semibold mb-1">Event title</label>
                <input v-model="newEventTitle" class="w-full border rounded-lg p-3 border-gray-300" placeholder="CMart Weekly Carboot" />
              </div>
              <div>
                <label class="block text-sm font-semibold mb-1">Status</label>
                <select v-model="newEventStatus" class="w-full border rounded-lg p-3 border-gray-300">
                  <option>Available</option>
                  <option>Almost Full</option>
                  <option>Closed</option>
                </select>
              </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
              <button @click="closeModal" class="text-gray-600 border px-4 py-2 rounded-lg">Cancel</button>
              <button v-if="modalData.isNew && auth.isCmartWorker" @click="createEvent" class="text-white bg-[#29B6F6] px-4 py-2 rounded-lg font-bold">Create Event</button>
              <router-link v-else-if="!modalData.isNew" to="/vendor-booking" class="text-white bg-[#29B6F6] px-4 py-2 rounded-lg font-bold">Book a Space</router-link>
            </div>
          </div>
        </div>
      </div>
    </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import AppNavbar from './navigation/AppNavbar.vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import api from '../services/api';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();

const backLink = computed(() => (auth.isAuthenticated ? auth.homeForUser() : '/'));
const backLabel = computed(() => (auth.isAuthenticated ? 'Back to Dashboard' : 'Back to Home'));

const showModal = ref(false);
const newEventTitle = ref('');
const newEventStatus = ref('Available');
const apiEvents = ref([]);

const modalData = reactive({
  title: '',
  start: '',
  end: '',
  status: '',
  isNew: false,
  selectInfo: null,
});

const formatDateTime = (dateStr) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleString('en-GB', {
    weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
  });
};

const eventColor = (status) => {
  if (status === 'Almost Full') return '#0277BD';
  if (status === 'Closed') return '#757575';
  return '#29B6F6';
};

const mapToCalendarEvents = (list) =>
  list.map((ev) => ({
    id: String(ev.id),
    title: ev.title,
    start: ev.starts_at,
    end: ev.ends_at,
    color: eventColor(ev.status),
    extendedProps: { status: ev.status },
  }));

const loadEvents = async () => {
  try {
    const { data } = await api.get('/events');
    apiEvents.value = Array.isArray(data) ? data : [];
    calendarOptions.events = mapToCalendarEvents(apiEvents.value);
  } catch (e) {
    console.error('Failed to load calendar events:', e);
  }
};

const handleEventClick = (clickInfo) => {
  modalData.title = clickInfo.event.title;
  modalData.start = clickInfo.event.startStr;
  modalData.end = clickInfo.event.endStr;
  modalData.status = clickInfo.event.extendedProps?.status || '';
  modalData.isNew = false;
  showModal.value = true;
};

const handleDateSelect = (selectInfo) => {
  if (!auth.isCmartWorker) {
    selectInfo.view.calendar.unselect();
    return;
  }
  modalData.title = 'New Carboot Event';
  modalData.start = selectInfo.startStr;
  modalData.end = selectInfo.endStr;
  modalData.isNew = true;
  modalData.selectInfo = selectInfo;
  newEventTitle.value = '';
  newEventStatus.value = 'Available';
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
};

const createEvent = async () => {
  if (!newEventTitle.value.trim() || !modalData.selectInfo) return;
  try {
    await api.post('/carboot-events', {
      title: newEventTitle.value.trim(),
      starts_at: modalData.selectInfo.startStr,
      ends_at: modalData.selectInfo.endStr,
      status: newEventStatus.value,
    });
    modalData.selectInfo.view.calendar.unselect();
    closeModal();
    await loadEvents();
  } catch (e) {
    console.error('Failed to create event:', e);
    alert('Could not create event. Ensure you are logged in as CMart staff.');
  }
};

const calendarOptions = reactive({
  plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
  initialView: 'dayGridMonth',
  initialDate: '2026-05-01',
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
  },
  editable: false,
  selectable: auth.isCmartWorker,
  selectMirror: true,
  dayMaxEvents: true,
  weekends: true,
  select: handleDateSelect,
  eventClick: handleEventClick,
  events: [],
});

onMounted(loadEvents);
</script>

<style scoped>
.event-calendar-root :deep(.fc) { font-family: inherit; }
.event-calendar-root :deep(.fc .fc-button-primary) {
  background-color: #29B6F6; border-color: #29B6F6; color: #fff; font-weight: 600;
  border-radius: 6px; padding: 0.4rem 1rem;
}
.event-calendar-root :deep(.fc .fc-button-primary:hover) { background-color: #0277BD; border-color: #0277BD; }
.event-calendar-root :deep(.fc-toolbar-title) { font-size: 1.5rem !important; font-weight: 800; color: #000; }
.event-calendar-root :deep(.fc-day-today) { background-color: rgba(41, 182, 246, 0.08) !important; }
</style>
