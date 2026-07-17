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
          <p class="sr-only" aria-live="polite" data-testid="booking-live-announcement">
            {{ liveAnnouncement }}
          </p>

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

          <VendorBookingCategorySelector
            v-model="bookingForm.vendor_category_id"
            :categories="vendorCategories"
            :profile-suggested-category-id="profileCategoryId"
            :loading="loadingCategories"
            :load-error="categoryLoadError"
            @retry="loadVendorCategories"
          />

          <p
            v-if="categoryChangeNotice"
            class="rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm font-semibold text-cyan-900"
            data-testid="booking-category-change-notice"
            role="status"
          >
            {{ categoryChangeNotice }}
          </p>

          <div>
            <label class="ml-label">Your name</label>
            <input v-model="userName" type="text" required class="ml-input" placeholder="e.g. Ahmad bin Ali" data-testid="booking-business-name" />
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
              :rows="availabilityRows"
              :selected-category="selectedCategory"
              :operational-days="availabilityDays"
              :loading="availabilityLoading"
              :load-error="availabilityError"
              :readiness-message="availabilityReadiness"
              :selection-error="siteSelectionError"
              :removed-stale-site-labels="removedStaleSiteLabels"
              @retry="loadSiteAvailability(selectedEvent.id)"
              @choose-category="chooseAnotherCategory"
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
import VendorBookingCategorySelector from '../../components/vendor/VendorBookingCategorySelector.vue';
import api from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import { fetchVendorCategories, categoryConflictMessage } from '../../services/vendorCategoriesApi';
import { DEFAULT_EVENT_LOCATION, mapApiEventToCard } from '../../utils/eventDisplay';
import {
  getSelectedSites,
  pruneInvalidSelections,
  selectionValidationMessage,
} from '../../utils/eventSiteSelection';

const EVENT_UNAVAILABLE_MESSAGE = 'This event is no longer available for booking. Please choose another event.';
const SITE_CONFLICT_MESSAGE =
  'One or more selected sites are no longer available. The latest layout has been refreshed.';
const CATEGORY_CLEARED_MESSAGE = 'Kategori telah ditukar. Pilihan tapak sebelumnya telah dikosongkan.';

const toast = useToast();
const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const bookingForm = reactive({
  vendor_category_id: '',
  product_details: '',
});

const vendorCategories = ref([]);
const profileCategoryId = ref(null);
const loadingCategories = ref(false);
const categoryLoadError = ref('');
const liveAnnouncement = ref('');
const categoryChangeNotice = ref('');
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
const availabilityRows = ref([]);
const availabilitySites = ref([]);
const selectedSiteIds = ref([]);
const removedStaleSiteLabels = ref([]);
const siteSelectionError = ref('');
let availabilityRequestToken = 0;
let suppressCategoryWatch = false;

const selectedCategoryLabel = computed(() => {
  const id = String(bookingForm.vendor_category_id || '');
  const match = vendorCategories.value.find((c) => String(c.id) === id);
  return match?.label || '';
});

const selectedCategory = computed(() => {
  const id = String(bookingForm.vendor_category_id || '');
  return vendorCategories.value.find((category) => String(category.id) === id) || null;
});

const routeEventId = computed(() => {
  const raw = route.query.event_id;
  if (!raw) return null;
  const parsed = Number(raw);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
});

const selectedSites = computed(() => getSelectedSites(availabilitySites.value, selectedSiteIds.value));

const canSubmit = computed(() => {
  if (!selectedEvent.value || eventLoadError.value) return false;
  if (!bookingForm.vendor_category_id) return false;
  if (availabilityLoading.value || availabilityError.value) return false;
  if (availabilityReadiness.value) return false;
  if (!selectedSiteIds.value.length) return false;
  return !selectionValidationMessage(selectedSites.value);
});

const loadVendorCategories = async () => {
  loadingCategories.value = true;
  categoryLoadError.value = '';
  try {
    vendorCategories.value = await fetchVendorCategories(api);
  } catch (error) {
    console.error('Failed to load vendor categories:', error);
    vendorCategories.value = [];
    categoryLoadError.value = 'Kategori jualan tidak dapat dimuatkan.';
  } finally {
    loadingCategories.value = false;
  }
};

const loadProfileCategorySuggestion = async () => {
  try {
    const { data } = await api.get('/vendor/business-profile');
    const id = data?.profile?.vendor_category_id;
    if (id) {
      profileCategoryId.value = id;
      if (!bookingForm.vendor_category_id) {
        suppressCategoryWatch = true;
        bookingForm.vendor_category_id = String(id);
        liveAnnouncement.value = 'Kategori cadangan daripada profil anda telah dipilih. Anda boleh menukarnya.';
        suppressCategoryWatch = false;
      }
    }
  } catch (error) {
    console.error('Failed to load vendor profile category suggestion:', error);
  }
};

const loadBookableEvents = async () => {
  const { data } = await api.get('/events');
  bookableEvents.value = (Array.isArray(data) ? data : [])
    .map((ev) => mapApiEventToCard(ev, DEFAULT_EVENT_LOCATION));
};

const resetSiteSelection = () => {
  selectedSiteIds.value = [];
  removedStaleSiteLabels.value = [];
  availabilityRows.value = [];
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

  if (!bookingForm.vendor_category_id) {
    resetSiteSelection();
    availabilityReadiness.value = 'Sila pilih kategori jualan terlebih dahulu.';
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
    const { data } = await api.get(`/vendor/events/${eventId}/site-availability`, {
      params: { vendor_category_id: bookingForm.vendor_category_id },
    });
    if (requestToken !== availabilityRequestToken) return;

    if (data.category_required) {
      availabilityRows.value = [];
      availabilitySites.value = [];
      availabilityDays.value = Array.isArray(data.operational_days) ? data.operational_days : [];
      availabilityReadiness.value = data.readiness?.message || 'Sila pilih kategori jualan terlebih dahulu.';
      return;
    }

    availabilityRows.value = Array.isArray(data.rows) ? data.rows : [];
    availabilitySites.value = Array.isArray(data.sites) ? data.sites : [];
    availabilityDays.value = Array.isArray(data.operational_days) ? data.operational_days : [];
    availabilityReadiness.value = data.readiness?.status === 'no_compatible_sites'
      ? 'Tiada tapak tersedia untuk kategori ini.'
      : (data.readiness?.message || '');
    liveAnnouncement.value = availabilityRows.value.length
      ? `${availabilitySites.value.filter((site) => site.is_selectable).length} tapak tersedia telah dimuatkan.`
      : availabilityReadiness.value;
  } catch (error) {
    if (requestToken !== availabilityRequestToken) return;

    if (error.response?.status === 422) {
      availabilitySites.value = [];
      availabilityDays.value = [];
      availabilityReadiness.value =
        categoryConflictMessage(error.response?.data?.error, error.response?.data?.message)
        || 'This event is not ready for site selection yet.';
      return;
    }

    resetSiteSelection();
    availabilityError.value = 'Maklumat tapak tidak dapat dimuatkan.';
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

  if (event?.id && bookingForm.vendor_category_id) {
    loadSiteAvailability(event.id);
  } else if (event?.id) {
    availabilityReadiness.value = 'Sila pilih kategori jualan terlebih dahulu.';
  }
};

const applySavedPreference = (preference) => {
  if (preference.name) {
    userName.value = preference.name;
  }
  if (preference.product_category && !bookingForm.vendor_category_id) {
    const match = vendorCategories.value.find((c) => c.label === preference.product_category);
    if (match) {
      suppressCategoryWatch = true;
      bookingForm.vendor_category_id = String(match.id);
      suppressCategoryWatch = false;
    }
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
    product_category: selectedCategoryLabel.value || null,
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

const chooseAnotherCategory = () => {
  bookingForm.vendor_category_id = '';
  liveAnnouncement.value = 'Pilih kategori jualan lain.';
  requestAnimationFrame(() => {
    document.getElementById('vendor-category-heading')?.focus?.();
  });
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

watch(
  () => bookingForm.vendor_category_id,
  (next, prev) => {
    if (suppressCategoryWatch) return;
    if (String(next || '') === String(prev || '')) return;

    const hadSites = selectedSiteIds.value.length > 0;
    selectedSiteIds.value = [];
    removedStaleSiteLabels.value = [];
    siteSelectionError.value = '';
    if (prev) {
      categoryChangeNotice.value = CATEGORY_CLEARED_MESSAGE;
      toast.info(CATEGORY_CLEARED_MESSAGE);
      liveAnnouncement.value = hadSites
        ? CATEGORY_CLEARED_MESSAGE
        : 'Kategori jualan telah ditukar. Tapak yang sesuai sedang dimuatkan.';
    }

    if (!next) {
      resetSiteSelection();
      availabilityReadiness.value = 'Pilih kategori jualan untuk meneruskan.';
      return;
    }

    if (selectedEvent.value?.id) {
      loadSiteAvailability(selectedEvent.value.id);
    }
  },
);

onMounted(async () => {
  userName.value = auth.user?.name || '';
  loadingEvents.value = true;
  eventLoadError.value = '';

  try {
    await loadVendorCategories();
    await Promise.all([
      loadBookableEvents(),
      loadSavedPreference(),
      loadProfileCategorySuggestion(),
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
  bookingForm.vendor_category_id = profileCategoryId.value ? String(profileCategoryId.value) : '';
  bookingForm.product_details = '';
  resetSiteSelection();
  if (!routeEventId.value) {
    applySelectedEvent(null);
  } else if (selectedEvent.value?.id && bookingForm.vendor_category_id) {
    loadSiteAvailability(selectedEvent.value.id);
  }
};

const handleBookingConflict = async () => {
  await refreshAvailabilityAfterConflict(SITE_CONFLICT_MESSAGE);
};

const refreshAvailabilityAfterConflict = async (message) => {
  toast.error(message);
  if (!selectedEvent.value?.id) {
    siteSelectionError.value = message;
    return;
  }

  const previousSelection = [...selectedSiteIds.value];
  const previousLabels = new Map(
    availabilitySites.value.map((site) => [Number(site.id), site.label]),
  );
  await loadSiteAvailability(selectedEvent.value.id, { preserveSelection: true });
  const nextSelection = pruneInvalidSelections(previousSelection, availabilitySites.value);
  const nextSet = new Set(nextSelection.map(Number));
  removedStaleSiteLabels.value = previousSelection
    .map(Number)
    .filter((id) => !nextSet.has(id))
    .map((id) => previousLabels.get(id))
    .filter(Boolean);
  selectedSiteIds.value = nextSelection;
  siteSelectionError.value = 'Pilihan tapak telah dikemas kini kerana susun atur atau ketersediaan berubah.';
  liveAnnouncement.value = removedStaleSiteLabels.value.length
    ? `${siteSelectionError.value} Tapak dialih keluar: ${removedStaleSiteLabels.value.join(', ')}.`
    : siteSelectionError.value;
};

const submitBooking = async () => {
  if (!canSubmit.value || !selectedEvent.value) {
    toast.error(
      bookingForm.vendor_category_id
        ? 'Please select a valid event and at least one physical site before submitting.'
        : 'Sila pilih kategori jualan terlebih dahulu.',
    );
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
      vendor_category_id: Number(bookingForm.vendor_category_id),
      product_category: selectedCategoryLabel.value,
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
      const code = e.response?.data?.error;
      if (code === 'EVENT_LAYOUT_NOT_READY' || code === 'LAYOUT_CHANGED') {
        const message = categoryConflictMessage(code, e.response?.data?.message);
        await refreshAvailabilityAfterConflict(message);
        return;
      }
      await handleBookingConflict();
      return;
    }

    if (e.response?.status === 422) {
      const code = e.response?.data?.error;
      const message = categoryConflictMessage(code, e.response?.data?.message || 'Unable to submit booking.');
      siteSelectionError.value =
        e.response?.data?.errors?.event_site_ids?.[0]
        || message;
      toast.error(message);
      return;
    }

    toast.error(e.response?.data?.message || '500 Internal Server Error: Unable to communicate with the API.');
  } finally {
    submitting.value = false;
  }
};
</script>
