<template>
  <div class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-6xl mx-auto bg-white p-6 rounded-2xl shadow-lg border border-gray-100">
      
      <div class="flex flex-col sm:flex-row justify-between items-center mb-8 pb-4 border-b border-gray-100">
        <h1 class="text-3xl font-extrabold text-black mb-4 sm:mb-0">CMart Carboot Schedule</h1>
        <router-link to="/" class="flex items-center text-[#757575] hover:text-[#0277BD] font-medium transition-colors duration-200">
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
          Go Back
        </router-link>
      </div>

      <div class="event-calendar-root">
        <FullCalendar :options="calendarOptions" />
      </div>
    </div>

    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto overflow-x-hidden bg-black bg-opacity-60 backdrop-blur-sm transition-opacity">
      <div class="relative p-4 w-full max-w-md max-h-full">
        <div class="relative bg-white rounded-2xl shadow-2xl overflow-hidden">
          
          <div class="flex items-center justify-between p-5 bg-black">
            <h3 class="text-xl font-bold text-white">
              {{ modalData.title }}
            </h3>
            <button @click="closeModal" type="button" class="text-gray-400 bg-transparent hover:bg-gray-800 hover:text-white rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center transition">
              <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
              </svg>
            </button>
          </div>

          <div class="p-6">
            <div class="mb-6 bg-sky-50 p-4 rounded-xl border border-sky-100">
              <div class="flex items-center text-sm text-gray-700 mb-3">
                <svg class="w-5 h-5 mr-3 text-[#29B6F6]" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span class="font-bold mr-2 text-black">Start:</span> {{ formatDateTime(modalData.start) }}
              </div>
              <div v-if="modalData.end" class="flex items-center text-sm text-gray-700">
                <svg class="w-5 h-5 mr-3 text-[#0277BD]" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span class="font-bold mr-2 text-black">End:</span> {{ formatDateTime(modalData.end) }}
              </div>
            </div>

            <div v-if="modalData.isNew" class="mt-4">
              <label class="block text-sm font-semibold text-black mb-2">Event Name / Booth Booking</label>
              <input type="text" v-model="newEventTitle" class="w-full border-gray-300 rounded-lg shadow-sm p-3 border focus:ring-[#29B6F6] focus:border-[#29B6F6] transition" placeholder="Example: Preloved Clothes Booth">
            </div>

            <div class="mt-8 flex justify-end space-x-3">
              <button @click="closeModal" class="text-[#757575] bg-white hover:bg-gray-50 border border-gray-200 font-semibold rounded-lg text-sm px-6 py-2.5 transition">
                Cancel
              </button>
              <button @click="handleAction" class="text-white bg-[#29B6F6] hover:bg-[#0277BD] shadow-lg shadow-blue-200 font-bold rounded-lg text-sm px-6 py-2.5 text-center transition-all duration-200">
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
    modalData.calendarApi.addEvent({
      title: newEventTitle.value,
      start: modalData.selectInfo.startStr,
      end: modalData.selectInfo.endStr,
      allDay: modalData.selectInfo.allDay,
      color: '#0277BD' // Brand secondary blue for new bookings
    })
  } else if (!modalData.isNew) {
    alert('Redirecting vendor to Laravel Booking System page...')
  }
  closeModal()
}

// --- CALENDAR CONFIGURATION ---
const calendarOptions = reactive({
  plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
  initialView: 'dayGridMonth',
  initialDate: '2026-05-01', 
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
  },
  editable: true,
  selectable: true,
  selectMirror: true,
  dayMaxEvents: true,
  weekends: true,
  select: handleDateSelect,
  eventClick: handleEventClick,
  events: [
    { title: 'CMart Weekly Carboot', start: '2026-05-16T08:00:00', end: '2026-05-16T14:00:00', color: '#29B6F6' },
    { title: 'CMart Weekly Carboot (Almost Full)', start: '2026-05-17T08:00:00', end: '2026-05-17T14:00:00', color: '#0277BD' },
    { title: 'Changlun Mega Carboot', start: '2026-05-23T08:00:00', end: '2026-05-23T18:00:00', color: '#29B6F6' }
  ]
})
</script>

<style scoped>
.event-calendar-root :deep(.fc) {
  font-family: inherit;
}

/* Base Buttons */
.event-calendar-root :deep(.fc .fc-button-primary) {
  background-color: #29B6F6;
  border-color: #29B6F6;
  color: #ffffff;
  font-weight: 600;
  text-transform: capitalize;
  border-radius: 6px;
  padding: 0.4rem 1rem;
  transition: all 0.2s ease-in-out;
}

/* Button Hover State */
.event-calendar-root :deep(.fc .fc-button-primary:hover) {
  background-color: #0277BD;
  border-color: #0277BD;
}

/* Active Button State */
.event-calendar-root :deep(.fc .fc-button-primary:not(:disabled).fc-button-active),
.event-calendar-root :deep(.fc .fc-button-primary:not(:disabled):active) {
  background-color: #000000 !important;
  border-color: #000000 !important;
  box-shadow: none !important;
}

/* Title */
.event-calendar-root :deep(.fc-toolbar-title) {
  font-size: 1.5rem !important;
  font-weight: 800;
  color: #000000;
}

/* Today's Highlight */
.event-calendar-root :deep(.fc-day-today) {
  background-color: rgba(41, 182, 246, 0.08) !important;
}

/* Event Pills Redesign */
.event-calendar-root :deep(.fc-event) {
  border: none;
  border-radius: 6px;
  padding: 3px 6px;
  margin-bottom: 2px;
  box-shadow: 0 1px 2px rgba(0,0,0,0.1);
  transition: transform 0.1s ease;
}

.event-calendar-root :deep(.fc-event:hover) {
  transform: scale(1.02);
  cursor: pointer;
}

/* Event Text Wrapping */
.event-calendar-root :deep(.fc-event-title) {
  white-space: normal !important;
  text-overflow: clip;
  overflow: visible;
  font-weight: 600;
  font-size: 0.85em;
  line-height: 1.2;
}

.event-calendar-root :deep(.fc-event-time) {
  font-weight: 400;
  font-size: 0.8em;
  opacity: 0.9;
}
</style>