<template>
  <div class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-6xl mx-auto bg-white p-6 rounded-xl shadow-md">
      
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">CMart Carboot Schedule</h1>
        <router-link to="/" class="text-gray-500 hover:text-gray-800 font-medium transition">
          &larr; Go Back
        </router-link>
      </div>

      <div class="event-calendar-root">
        <FullCalendar :options="calendarOptions" />
      </div>
    </div>

    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto overflow-x-hidden bg-black bg-opacity-50 transition-opacity">
      <div class="relative p-4 w-full max-w-md max-h-full">
        <div class="relative bg-white rounded-2xl shadow-xl">
          
          <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t">
            <h3 class="text-xl font-bold text-gray-900">
              {{ modalData.title }}
            </h3>
            <button @click="closeModal" type="button" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center transition">
              <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
              </svg>
            </button>
          </div>

          <div class="p-4 md:p-5">
            <div class="mb-4">
              <div class="flex items-center text-sm text-gray-600 mb-2">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span class="font-semibold mr-2">Start:</span> {{ formatDateTime(modalData.start) }}
              </div>
              <div v-if="modalData.end" class="flex items-center text-sm text-gray-600">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span class="font-semibold mr-2">End:</span> {{ formatDateTime(modalData.end) }}
              </div>
            </div>

            <div v-if="modalData.isNew" class="mt-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Event Name / Booth Booking</label>
              <input type="text" v-model="newEventTitle" class="w-full border-gray-300 rounded-lg shadow-sm p-2.5 border focus:ring-orange-500 focus:border-orange-500" placeholder="Example: Preloved Clothes Booth">
            </div>

            <div class="mt-6 flex justify-end space-x-3">
              <button @click="closeModal" class="text-gray-600 bg-white hover:bg-gray-100 border border-gray-200 focus:ring-4 focus:outline-none focus:ring-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 transition">
                Cancel
              </button>
              <button @click="handleAction" class="text-white bg-orange-500 hover:bg-orange-600 focus:ring-4 focus:outline-none focus:ring-orange-300 font-semibold rounded-lg text-sm px-5 py-2.5 text-center transition shadow-md">
                {{ modalData.isNew ? 'Create Event' : 'Book Now' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import listPlugin from '@fullcalendar/list'
import interactionPlugin from '@fullcalendar/interaction'

// --- MODAL STATE ---
const showModal = ref(false)
const newEventTitle = ref('')
const modalData = reactive({
  title: '',
  start: '',
  end: '',
  isNew: false,
  calendarApi: null,
  selectInfo: null
})

// --- HELPER FUNCTION ---
const formatDateTime = (dateStr) => {
  if (!dateStr) return '';
  const options = { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute:'2-digit' };
  // Changed locale to en-US
  return new Date(dateStr).toLocaleDateString('en-US', options);
}

// --- ACTIONS ---
const handleEventClick = (clickInfo) => {
  modalData.title = clickInfo.event.title
  modalData.start = clickInfo.event.startStr
  modalData.end = clickInfo.event.endStr
  modalData.isNew = false
  showModal.value = true
}

const handleDateSelect = (selectInfo) => {
  modalData.title = 'New Space Booking'
  modalData.start = selectInfo.startStr
  modalData.end = selectInfo.endStr
  modalData.isNew = true
  modalData.calendarApi = selectInfo.view.calendar
  modalData.selectInfo = selectInfo
  newEventTitle.value = ''
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

const handleAction = () => {
  if (modalData.isNew && newEventTitle.value) {
    // Add event directly to calendar interface
    modalData.calendarApi.addEvent({
      title: newEventTitle.value,
      start: modalData.selectInfo.startStr,
      end: modalData.selectInfo.endStr,
      allDay: modalData.selectInfo.allDay,
      color: '#10b981' // Green color for new booking
    })
  } else if (!modalData.isNew) {
    // Simulate bringing the vendor to the actual booking page
    alert('Redirecting vendor to Laravel Booking System page...')
  }
  closeModal()
}

// --- CALENDAR CONFIGURATION ---
const calendarOptions = reactive({
  plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
  initialView: 'dayGridMonth',
  initialDate: '2026-05-01', // Set to match demo picture
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' // Google Calendar Views
  },
  editable: true,     // Can click & drag event boxes
  selectable: true,   // Can highlight dates
  selectMirror: true,
  dayMaxEvents: true,
  weekends: true,
  select: handleDateSelect,
  eventClick: handleEventClick,
  events: [
    { title: 'CMart Weekly Carboot', start: '2026-05-16T08:00:00', end: '2026-05-16T14:00:00', color: '#ea4335' },
    { title: 'CMart Weekly Carboot (Almost Full)', start: '2026-05-17T08:00:00', end: '2026-05-17T14:00:00', color: '#fbbc05' },
    { title: 'Changlun Mega Carboot', start: '2026-05-23T08:00:00', end: '2026-05-23T18:00:00', color: '#ea4335' }
  ]
})
</script>

<style scoped>
.event-calendar-root :deep(.fc) {
  font-family: inherit;
}
/* Override FullCalendar button colors to match CMart/Dark Gray theme */
.event-calendar-root :deep(.fc .fc-button-primary) {
  background-color: #1f2937;
  border-color: #1f2937;
}
.event-calendar-root :deep(.fc .fc-button-primary:hover) {
  background-color: #374151;
  border-color: #374151;
}
.event-calendar-root :deep(.fc .fc-button-primary:not(:disabled).fc-button-active),
.event-calendar-root :deep(.fc .fc-button-primary:not(:disabled):active) {
  background-color: #111827;
  border-color: #111827;
}
.event-calendar-root :deep(.fc-toolbar-title) {
  font-size: 1.5rem !important;
  color: #1a202c;
}
</style>