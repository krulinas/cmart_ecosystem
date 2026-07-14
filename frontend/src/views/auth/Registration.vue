<template>
  <div class="min-h-screen bg-ink-50" data-testid="booking-page-root">
    <AppNavbar :variant="auth.isVendorUser ? 'vendor' : 'public'" />
    <div class="py-12 px-4">
    <div class="max-w-4xl mx-auto">
      <router-link
        :to="auth.isVendorUser ? '/dashboard' : '/community'"
        class="inline-flex items-center text-sm text-ink-500 hover:text-brand-600 mb-6"
      >
        <span class="mr-1">←</span> {{ auth.isVendorUser ? 'Back to My Dashboard' : 'Back to Community' }}
      </router-link>

      <div class="ml-card">
        <div class="mb-6">
          <span class="ml-badge bg-brand-100 text-brand-700">Vendor Onboarding</span>
          <h1 class="mt-2 text-2xl font-extrabold text-ink-900 tracking-tight" data-testid="booking-onboarding-heading">
            Start Vendor Booking
          </h1>
          <p class="text-sm text-ink-500 mt-1">
            Choose an event and submit your booth booking. CMart staff will review your application before participation is confirmed.
          </p>
          <p class="mt-3 text-sm">
            <router-link to="/calendar" class="font-semibold text-brand-600 hover:text-brand-700 hover:underline">
              View Events calendar →
            </router-link>
          </p>
        </div>

        <div v-if="loadingEvents" class="rounded-xl border border-ink-200 bg-ink-50 px-4 py-8 text-center text-sm text-ink-500" data-testid="booking-events-loading">
          Loading available events…
        </div>

        <div
          v-else-if="eventLoadError"
          class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-4 text-sm text-rose-800 mb-6"
        >
          {{ eventLoadError }}
        </div>

        <div
          v-if="!loadingEvents && selectedEvent && !eventLoadError"
          class="rounded-xl border border-brand-200 bg-brand-50/70 p-5 mb-6"
          data-testid="booking-selected-event"
        >
          <p class="text-xs font-bold uppercase tracking-wider text-brand-700 mb-2">Selected Event</p>
          <h2 class="text-lg font-extrabold text-ink-900">{{ selectedEvent.title }}</h2>
          <dl class="mt-3 space-y-2 text-sm text-ink-700">
            <div class="flex gap-2">
              <dt class="w-20 shrink-0 font-semibold text-ink-500">Date</dt>
              <dd>{{ selectedEvent.dateLabel }}</dd>
            </div>
            <div class="flex gap-2">
              <dt class="w-20 shrink-0 font-semibold text-ink-500">Time</dt>
              <dd>{{ selectedEvent.time }}</dd>
            </div>
            <div class="flex gap-2">
              <dt class="w-20 shrink-0 font-semibold text-ink-500">Location</dt>
              <dd>{{ selectedEvent.location }}</dd>
            </div>
          </dl>
        </div>

        <div v-if="!loadingEvents && !selectedEvent && (!routeEventId || eventLoadError)" class="mb-6">
          <label for="event-select" class="ml-label">Select an event</label>
          <select
            id="event-select"
            v-model="selectedEventId"
            class="ml-input"
            required
            data-testid="booking-event-select"
          >
            <option value="" disabled>Choose an upcoming event</option>
            <option v-for="event in bookableEvents" :key="event.id" :value="String(event.id)">
              {{ event.title }} — {{ event.dateLabel }}
            </option>
          </select>
          <p v-if="!bookableEvents.length" class="mt-2 text-sm text-ink-500" data-testid="booking-no-events">
            No upcoming events are open for booking right now. Please check back later.
          </p>
        </div>

        <form v-if="!loadingEvents" @submit.prevent="submitBooking" class="space-y-4" data-testid="booking-form">
          <div
            v-if="savedPreferenceLoaded"
            class="rounded-xl border border-brand-200 bg-brand-50/60 px-4 py-3 text-sm text-ink-700"
          >
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
              <div>
                <p class="font-bold text-brand-800">Quick Rebook Details</p>
                <p class="mt-1">
                  Saved booking details loaded from your previous booking. You can edit them before submitting.
                </p>
              </div>
              <button
                type="button"
                class="shrink-0 text-sm font-semibold text-brand-700 hover:text-brand-900 underline underline-offset-2"
                @click="clearSavedPreference"
              >
                Clear saved details
              </button>
            </div>
          </div>

          <div>
            <label class="ml-label">Your name</label>
            <input v-model="userName" type="text" required class="ml-input" placeholder="e.g. Ahmad bin Ali" data-testid="booking-business-name" />
          </div>

          <div>
            <label class="ml-label">Product category</label>
            <select v-model="bookingForm.product_category" required class="ml-input" data-testid="booking-category">
              <option disabled value="">Select a category</option>
              <option v-for="category in PRODUCT_CATEGORIES" :key="category" :value="category">
                {{ category }}
              </option>
            </select>
          </div>

          <div>
            <div class="flex items-center gap-2 mb-1">
              <label class="ml-label !mb-0">Specific products you will sell</label>

              <div class="group relative flex flex-col items-center">
                <button type="button" class="flex h-5 w-5 items-center justify-center rounded-full bg-ink-200 text-xs font-bold text-ink-600 hover:bg-brand-100 hover:text-brand-700 focus:outline-none">
                  i
                </button>

                <div class="absolute bottom-full mb-2 hidden w-56 flex-col items-center group-hover:flex group-focus:flex group-focus-within:flex">
                  <span class="relative z-10 rounded-lg bg-ink-900 p-2.5 text-xs leading-relaxed text-white shadow-lg text-center">
                    e.g., Ayam Gunting, Bundle T-shirt, Ramen.
                    <br/><br/>
                    Please be specific to help us process your approval faster!
                  </span>
                  <div class="-mt-2 h-3 w-3 rotate-45 bg-ink-900"></div>
                </div>
              </div>
            </div>

            <input
              v-model="bookingForm.product_details"
              type="text"
              required
              class="ml-input"
              placeholder="e.g., Ayam Gunting, Bundle T-shirt..."
              data-testid="booking-details"
            />
          </div>

          <div v-if="selectedEvent && !eventLoadError" data-testid="booking-site-selector">
            <EventSiteSelector
              v-model:selected-site-ids="selectedSiteIds"
              :sites="availabilitySites"
              :operational-days="availabilityDays"
              :loading="availabilityLoading"
              :load-error="availabilityError"
              :readiness-message="availabilityReadiness"
              :selection-error="siteSelectionError"
              @retry="loadSiteAvailability(selectedEvent.id)"
            />
          </div>

          <div class="rounded-xl border border-ink-100 bg-ink-50/50 px-4 py-3">
            <label class="flex items-start gap-3 cursor-pointer">
              <input
                v-model="saveForNextBooking"
                type="checkbox"
                class="mt-1 h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500"
              />
              <span>
                <span class="block text-sm font-semibold text-ink-800">Save these details for my next booking</span>
                <span class="block text-xs text-ink-500 mt-1">
                  Only your product details will be saved. Event, site selection, and payment details are never reused.
                </span>
              </span>
            </label>
          </div>

          <button type="submit" class="ml-btn-primary w-full" :disabled="submitting || !canSubmit" data-testid="booking-submit">
            {{ submitting ? 'Submitting…' : 'Submit booking' }}
          </button>

          <p class="text-xs text-ink-500 text-center">
            By submitting, you agree to Carboot@CMart vendor terms. You will receive a WhatsApp confirmation after review.
          </p>
        </form>
      </div>
    </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'VendorBookingRegistration',
};
</script>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import AppNavbar from '../../components/navigation/AppNavbar.vue';
import EventSiteSelector from '../../components/vendor/EventSiteSelector.vue';
import api from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import { PRODUCT_CATEGORIES } from '../../constants/productCategories';
import { DEFAULT_EVENT_LOCATION, mapApiEventToCard } from '../../utils/eventDisplay';
import {
  getSelectedSites,
  pruneInvalidSelections,
  selectionValidationMessage,
} from '../../utils/eventSiteSelection';

const EVENT_UNAVAILABLE_MESSAGE = 'This event is no longer available for booking. Please choose another event.';
const SITE_CONFLICT_MESSAGE =
  'One or more selected sites are no longer available. The latest layout has been refreshed.';

const toast = useToast();
const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const bookingForm = reactive({
  product_category: '',
  product_details: '',
});

const userName = ref(auth.user?.name || '');
const submitting = ref(false);
const loadingEvents = ref(true);
const bookableEvents = ref([]);
const selectedEvent = ref(null);
const selectedEventId = ref('');
const eventLoadError = ref('');
const savedPreferenceLoaded = ref(false);
const saveForNextBooking = ref(false);
const clearingPreference = ref(false);

const availabilityLoading = ref(false);
const availabilityError = ref('');
const availabilityReadiness = ref('');
const availabilityDays = ref([]);
const availabilitySites = ref([]);
const selectedSiteIds = ref([]);
const siteSelectionError = ref('');
let availabilityRequestToken = 0;

const routeEventId = computed(() => {
  const raw = route.query.event_id;
  if (!raw) return null;
  const parsed = Number(raw);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
});

const selectedSites = computed(() => getSelectedSites(availabilitySites.value, selectedSiteIds.value));

const canSubmit = computed(() => {
  if (!selectedEvent.value || eventLoadError.value) return false;
  if (availabilityLoading.value || availabilityError.value) return false;
  if (availabilityReadiness.value) return false;
  if (!selectedSiteIds.value.length) return false;
  return !selectionValidationMessage(selectedSites.value);
});

const loadBookableEvents = async () => {
  const { data } = await api.get('/events');
  bookableEvents.value = (Array.isArray(data) ? data : [])
    .map((ev) => mapApiEventToCard(ev, DEFAULT_EVENT_LOCATION));
};

const resetSiteSelection = () => {
  selectedSiteIds.value = [];
  availabilitySites.value = [];
  availabilityDays.value = [];
  availabilityError.value = '';
  availabilityReadiness.value = '';
  siteSelectionError.value = '';
};

const loadSiteAvailability = async (eventId, { preserveSelection = false } = {}) => {
  if (!eventId) {
    resetSiteSelection();
    return;
  }

  const requestToken = ++availabilityRequestToken;
  availabilityLoading.value = true;
  availabilityError.value = '';
  availabilityReadiness.value = '';
  siteSelectionError.value = '';
  if (!preserveSelection) {
    selectedSiteIds.value = [];
  }

  try {
    const { data } = await api.get(`/vendor/events/${eventId}/site-availability`);
    if (requestToken !== availabilityRequestToken) return;

    availabilitySites.value = Array.isArray(data.sites) ? data.sites : [];
    availabilityDays.value = Array.isArray(data.operational_days) ? data.operational_days : [];
    availabilityReadiness.value = data.readiness?.message || '';
  } catch (error) {
    if (requestToken !== availabilityRequestToken) return;

    if (error.response?.status === 422) {
      availabilitySites.value = [];
      availabilityDays.value = [];
      availabilityReadiness.value =
        error.response?.data?.message || 'This event is not ready for site selection yet.';
      return;
    }

    resetSiteSelection();
    availabilityError.value =
      error.response?.data?.message || 'Unable to load site availability. Please try again.';
  } finally {
    if (requestToken === availabilityRequestToken) {
      availabilityLoading.value = false;
    }
  }
};

const applySelectedEvent = (event) => {
  selectedEvent.value = event || null;
  selectedEventId.value = event ? String(event.id) : '';
  eventLoadError.value = '';
  resetSiteSelection();

  if (event?.id) {
    loadSiteAvailability(event.id);
  }
};

const applySavedPreference = (preference) => {
  if (preference.name) {
    userName.value = preference.name;
  }
  if (preference.product_category) {
    bookingForm.product_category = preference.product_category;
  }
  if (preference.specific_products) {
    bookingForm.product_details = preference.specific_products;
  }
  savedPreferenceLoaded.value = true;
  saveForNextBooking.value = true;
};

const loadSavedPreference = async () => {
  try {
    const { data } = await api.get('/booking-preferences/me');
    if (data?.has_preference && data.preference) {
      applySavedPreference(data.preference);
    }
  } catch (error) {
    console.error('Failed to load saved booking preference:', error);
  }
};

const saveBookingPreference = async () => {
  await api.put('/booking-preferences/me', {
    name: userName.value.trim() || null,
    product_category: bookingForm.product_category || null,
    specific_products: bookingForm.product_details.trim() || null,
    remember_enabled: true,
  });
};

const clearSavedPreference = async () => {
  if (clearingPreference.value) {
    return;
  }

  clearingPreference.value = true;
  try {
    await api.delete('/booking-preferences/me');
    savedPreferenceLoaded.value = false;
    saveForNextBooking.value = false;
    toast.success('Saved booking details cleared.');
  } catch (error) {
    console.error('Failed to clear saved booking preference:', error);
    toast.error(error.response?.data?.message || 'Unable to clear saved booking details.');
  } finally {
    clearingPreference.value = false;
  }
};

const loadSelectedEventFromRoute = async () => {
  if (!routeEventId.value) {
    return;
  }

  try {
    const { data } = await api.get(`/events/${routeEventId.value}`);
    applySelectedEvent(mapApiEventToCard(data, DEFAULT_EVENT_LOCATION));
  } catch {
    const fallback = bookableEvents.value.find((event) => event.id === routeEventId.value);
    if (fallback) {
      applySelectedEvent(fallback);
      return;
    }

    selectedEvent.value = null;
    selectedEventId.value = '';
    eventLoadError.value = EVENT_UNAVAILABLE_MESSAGE;
  }
};

watch(selectedEventId, (id) => {
  if (routeEventId.value && String(routeEventId.value) === id) {
    return;
  }

  if (!id) {
    if (!routeEventId.value) {
      applySelectedEvent(null);
    }
    return;
  }

  const event = bookableEvents.value.find((item) => String(item.id) === String(id));
  applySelectedEvent(event || null);
});

onMounted(async () => {
  userName.value = auth.user?.name || '';
  loadingEvents.value = true;
  eventLoadError.value = '';

  try {
    await Promise.all([
      loadBookableEvents(),
      loadSavedPreference(),
    ]);
    await loadSelectedEventFromRoute();
  } catch (error) {
    console.error('Failed to load booking events:', error);
    toast.error('Unable to load upcoming events. Please try again.');
  } finally {
    loadingEvents.value = false;
  }
});

const resetBookingForm = () => {
  bookingForm.product_category = '';
  bookingForm.product_details = '';
  resetSiteSelection();
  if (!routeEventId.value) {
    applySelectedEvent(null);
  } else if (selectedEvent.value?.id) {
    loadSiteAvailability(selectedEvent.value.id);
  }
};

const handleBookingConflict = async () => {
  toast.error(SITE_CONFLICT_MESSAGE);

  if (!selectedEvent.value?.id) {
    siteSelectionError.value = SITE_CONFLICT_MESSAGE;
    return;
  }

  const previousSelection = [...selectedSiteIds.value];
  await loadSiteAvailability(selectedEvent.value.id, { preserveSelection: true });
  selectedSiteIds.value = pruneInvalidSelections(previousSelection, availabilitySites.value);
  // Set after refresh: loadSiteAvailability() resets siteSelectionError, so the
  // conflict notice must be applied once the latest layout has loaded.
  siteSelectionError.value = SITE_CONFLICT_MESSAGE;
};

const submitBooking = async () => {
  if (!canSubmit.value || !selectedEvent.value) {
    toast.error('Please select a valid event and at least one physical site before submitting.');
    return;
  }

  const validationMessage = selectionValidationMessage(selectedSites.value);
  if (validationMessage) {
    siteSelectionError.value = validationMessage;
    toast.error(validationMessage);
    return;
  }

  submitting.value = true;
  siteSelectionError.value = '';

  try {
    const { data } = await api.post('/bookings', {
      event_id: selectedEvent.value.id,
      event_site_ids: selectedSiteIds.value,
      product_category: bookingForm.product_category,
      product_details: bookingForm.product_details,
    });
    toast.success(data.message || '201 Created: Booking submitted successfully.');

    if (saveForNextBooking.value) {
      try {
        await saveBookingPreference();
      } catch (preferenceError) {
        console.error('Failed to save booking preference:', preferenceError);
        toast.warning('Booking submitted, but your details could not be saved for next time.');
      }
    }

    resetBookingForm();
    await auth.fetchMe();
    router.push('/dashboard');
  } catch (e) {
    console.error('Booking submission failed:', e);

    if (e.response?.status === 409) {
      await handleBookingConflict();
      return;
    }

    if (e.response?.status === 422) {
      const message = e.response?.data?.message || 'Unable to submit booking.';
      siteSelectionError.value =
        e.response?.data?.errors?.event_site_ids?.[0] ||
        (e.response?.data?.error?.startsWith?.('no_active') ? message : siteSelectionError.value || message);
      toast.error(message);
      return;
    }

    toast.error(e.response?.data?.message || '500 Internal Server Error: Unable to communicate with the API.');
  } finally {
    submitting.value = false;
  }
};
</script>
