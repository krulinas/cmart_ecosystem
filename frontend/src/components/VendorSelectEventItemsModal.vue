<template>
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
        v-if="modelValue"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6"
        role="dialog"
        aria-modal="true"
        aria-labelledby="vendor-select-event-items-title"
        data-testid="vendor-select-event-items-modal"
        @keydown.esc="close"
      >
        <div class="absolute inset-0 bg-[rgba(15,23,42,0.65)] backdrop-blur-[6px]" @click="close" />

        <div
          class="relative z-10 w-full max-w-2xl rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 p-6 max-h-[90vh] overflow-y-auto"
          @click.stop
        >
          <h2 id="vendor-select-event-items-title" class="text-xl font-extrabold text-ink-900">
            {{ t('items.eventSelect.title') }}
          </h2>
          <p class="mt-1 text-sm text-ink-500">{{ t('items.eventSelect.lead') }}</p>

          <div class="mt-5">
            <label class="ml-label" for="vendor-select-event-items-event">{{ t('items.eventSelect.eventLabel') }}</label>
            <select
              id="vendor-select-event-items-event"
              v-model="selectedEventId"
              class="ml-input"
              :disabled="eventsLoading || !events.length"
              data-testid="vendor-select-event-items-event"
            >
              <option value="">
                {{ eventsLoading ? t('items.eventSelect.loadingEvents') : t('items.eventSelect.selectEvent') }}
              </option>
              <option v-for="event in events" :key="event.carboot_event_id" :value="String(event.carboot_event_id)">
                {{ eventOptionLabel(event) }}
              </option>
            </select>
            <p v-if="eventsError" class="mt-2 text-sm text-rose-700">{{ eventsError }}</p>
            <p
              v-else-if="!eventsLoading && !events.length"
              class="mt-3 rounded-xl border border-dashed border-ink-300 bg-ink-50/50 p-4 text-sm text-ink-500"
              data-testid="vendor-select-event-items-no-events"
            >
              {{ t('items.eventSelect.noEligibleEvents') }}
            </p>
          </div>

          <template v-if="selectedEventId">
            <section class="mt-6">
              <h3 class="text-sm font-bold uppercase tracking-wider text-ink-500">
                {{ t('items.eventSelect.alreadySelected') }}
              </h3>

              <p v-if="listingsLoading" class="mt-2 text-sm text-ink-500">{{ t('items.eventSelect.loadingListings') }}</p>
              <p v-else-if="listingsError" class="mt-2 text-sm text-rose-700">{{ listingsError }}</p>
              <p
                v-else-if="!listings.length"
                class="mt-2 rounded-xl border border-dashed border-ink-300 bg-ink-50/50 p-4 text-sm text-ink-500"
                data-testid="vendor-select-event-items-none-selected"
              >
                {{ t('items.eventSelect.noneSelected') }}
              </p>
              <ul v-else class="mt-2 divide-y divide-ink-100 rounded-xl border border-ink-100">
                <li
                  v-for="listing in listings"
                  :key="listing.id"
                  class="flex items-center justify-between gap-3 px-4 py-3"
                  data-testid="vendor-selected-event-item"
                >
                  <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-ink-900">{{ listing.item?.name }}</p>
                    <p class="mt-0.5 text-xs text-ink-500">
                      <span class="ml-badge" :class="itemDisplayStatusBadgeClass(listing.item)">
                        {{ itemDisplayStatusLabel(listing.item, t) }}
                      </span>
                      <span class="ml-2">{{ formatItemPrice(listing.item, t) }}</span>
                    </p>
                  </div>
                  <button
                    type="button"
                    class="ml-btn-ghost text-sm text-rose-600 shrink-0"
                    :disabled="removingItemId === listing.item?.id || submitting"
                    data-testid="vendor-remove-event-item"
                    @click="removeListing(listing)"
                  >
                    {{ removingItemId === listing.item?.id ? t('items.eventSelect.removing') : t('items.eventSelect.remove') }}
                  </button>
                </li>
              </ul>
            </section>

            <section class="mt-6">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-bold uppercase tracking-wider text-ink-500">
                  {{ t('items.eventSelect.availableItems') }}
                </h3>
                <div class="flex flex-wrap gap-2">
                  <button
                    type="button"
                    class="ml-btn-ghost text-sm"
                    :disabled="!selectableItems.length || submitting"
                    data-testid="vendor-select-all-unsold"
                    @click="submitSelectAllUnsold"
                  >
                    {{ t('items.eventSelect.selectAllUnsold') }}
                  </button>
                  <button
                    v-if="checkedItemIds.length"
                    type="button"
                    class="ml-btn-ghost text-sm"
                    :disabled="submitting"
                    @click="checkedItemIds = []"
                  >
                    {{ t('items.eventSelect.clearSelection') }}
                  </button>
                </div>
              </div>

              <p
                v-if="!selectableItems.length"
                class="mt-2 rounded-xl border border-dashed border-ink-300 bg-ink-50/50 p-4 text-sm text-ink-500"
                data-testid="vendor-select-event-items-empty"
              >
                {{ t('items.eventSelect.noUnsoldItems') }}
              </p>
              <ul v-else class="mt-2 max-h-64 divide-y divide-ink-100 overflow-y-auto rounded-xl border border-ink-100">
                <li v-for="item in selectableItems" :key="item.id">
                  <label class="flex cursor-pointer items-center gap-3 px-4 py-3 hover:bg-ink-50/60">
                    <input
                      v-model="checkedItemIds"
                      type="checkbox"
                      :value="item.id"
                      class="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500"
                      :disabled="submitting"
                      data-testid="vendor-selectable-event-item"
                    />
                    <span class="min-w-0">
                      <span class="block truncate text-sm font-semibold text-ink-900">{{ item.name }}</span>
                      <span class="mt-0.5 block text-xs text-ink-500">
                        {{ item.category }} · {{ formatItemPrice(item, t) }}
                      </span>
                    </span>
                  </label>
                </li>
              </ul>

              <p v-if="submitError" class="mt-3 text-sm text-rose-700" data-testid="vendor-select-event-items-error">
                {{ submitError }}
              </p>
            </section>
          </template>

          <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" class="ml-btn-ghost" :disabled="submitting" @click="close">
              {{ t('items.eventSelect.close') }}
            </button>
            <button
              type="button"
              class="ml-btn-primary"
              :disabled="!selectedEventId || !checkedItemIds.length || submitting"
              data-testid="vendor-select-event-items-submit"
              @click="submitSelection"
            >
              {{ submitting ? t('items.eventSelect.submitting') : submitLabel }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from 'vue-toastification';
import {
  getEligibleItemEvents,
  getEventItemListings,
  removeEventItemListing,
  selectAllUnsoldEventItems,
  selectEventItems,
} from '../services/vendorItemEventListingsApi';
import { extractApiError } from '../utils/apiErrors';
import { formatLocaleDate } from '../utils/localeFormat';
import {
  formatItemPrice,
  itemDisplayStatus,
  itemDisplayStatusBadgeClass,
  itemDisplayStatusLabel,
  itemVisibility,
} from '../utils/vendorCatalog';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  items: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'changed']);

const { t } = useI18n();
const toast = useToast();

const events = ref([]);
const eventsLoading = ref(false);
const eventsError = ref('');
const selectedEventId = ref('');
const listings = ref([]);
const listingsLoading = ref(false);
const listingsError = ref('');
const checkedItemIds = ref([]);
const submitting = ref(false);
const submitError = ref('');
const removingItemId = ref(null);

const listedItemIds = computed(() =>
  new Set(listings.value.map((listing) => String(listing.item?.id ?? listing.vendor_item_id))),
);

const selectableItems = computed(() =>
  props.items.filter(
    (item) =>
      itemDisplayStatus(item) !== 'sold'
      && itemVisibility(item) === 'visible'
      && !listedItemIds.value.has(String(item.id)),
  ),
);

const submitLabel = computed(() =>
  checkedItemIds.value.length
    ? t('items.eventSelect.submitCount', { count: checkedItemIds.value.length })
    : t('items.eventSelect.submit'),
);

const eventOptionLabel = (event) => {
  const title = event.title || t('items.eventSelect.untitledEvent');
  const date = formatLocaleDate(event.starts_at, { day: 'numeric', month: 'short', year: 'numeric' });
  return date ? `${title} · ${date}` : title;
};

const close = () => {
  if (submitting.value) return;
  emit('update:modelValue', false);
};

const loadEvents = async () => {
  eventsLoading.value = true;
  eventsError.value = '';
  try {
    const { data } = await getEligibleItemEvents();
    events.value = Array.isArray(data?.events) ? data.events : [];
    if (events.value.length === 1) {
      selectedEventId.value = String(events.value[0].carboot_event_id);
    }
  } catch (error) {
    eventsError.value = extractApiError(error);
    events.value = [];
  } finally {
    eventsLoading.value = false;
  }
};

const loadListings = async () => {
  if (!selectedEventId.value) {
    listings.value = [];
    return;
  }
  listingsLoading.value = true;
  listingsError.value = '';
  try {
    const { data } = await getEventItemListings(selectedEventId.value);
    listings.value = Array.isArray(data?.listings) ? data.listings : [];
  } catch (error) {
    listingsError.value = extractApiError(error);
    listings.value = [];
  } finally {
    listingsLoading.value = false;
  }
};

const submitSelection = async () => {
  if (!selectedEventId.value || !checkedItemIds.value.length || submitting.value) return;
  submitting.value = true;
  submitError.value = '';
  try {
    await selectEventItems(selectedEventId.value, checkedItemIds.value);
    toast.success(t('items.eventSelect.toastSelected', { count: checkedItemIds.value.length }));
    checkedItemIds.value = [];
    await loadListings();
    emit('changed');
  } catch (error) {
    submitError.value = extractApiError(error);
    toast.error(submitError.value);
  } finally {
    submitting.value = false;
  }
};

const submitSelectAllUnsold = async () => {
  if (!selectedEventId.value || submitting.value) return;
  submitting.value = true;
  submitError.value = '';
  try {
    const { data } = await selectAllUnsoldEventItems(selectedEventId.value);
    toast.success(
      t('items.eventSelect.toastSelected', { count: (data?.listings || []).length }),
    );
    checkedItemIds.value = [];
    await loadListings();
    emit('changed');
  } catch (error) {
    submitError.value = extractApiError(error);
    toast.error(submitError.value);
  } finally {
    submitting.value = false;
  }
};

const removeListing = async (listing) => {
  const itemId = listing.item?.id ?? listing.vendor_item_id;
  if (!itemId || removingItemId.value) return;
  removingItemId.value = itemId;
  submitError.value = '';
  try {
    await removeEventItemListing(selectedEventId.value, itemId);
    toast.success(t('items.eventSelect.toastRemoved'));
    await loadListings();
    emit('changed');
  } catch (error) {
    submitError.value = extractApiError(error);
    toast.error(submitError.value);
  } finally {
    removingItemId.value = null;
  }
};

watch(selectedEventId, () => {
  checkedItemIds.value = [];
  submitError.value = '';
  loadListings();
});

watch(
  () => props.modelValue,
  (open) => {
    if (!open) {
      checkedItemIds.value = [];
      submitError.value = '';
      return;
    }
    listings.value = [];
    loadEvents();
    if (selectedEventId.value) loadListings();
  },
);
</script>
