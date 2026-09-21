<template>
  <section
    class="border-t border-gray-100 px-5 py-6 sm:px-6 lg:px-8"
    aria-labelledby="public-layout-heading"
    data-testid="public-event-layout-section"
  >
    <div class="max-w-4xl">
      <p class="text-xs font-bold uppercase tracking-wider text-brand-600">{{ t('calendar.details.visitorGuide') }}</p>
      <h3 id="public-layout-heading" class="mt-1 text-xl font-extrabold text-gray-900">
        {{ t('calendar.details.layoutMapTitle') }}
      </h3>
      <p class="mt-1 text-sm text-gray-600">{{ t('calendar.details.layoutMapLead') }}</p>
    </div>

    <p class="sr-only" aria-live="polite" data-testid="public-layout-live-announcement">
      {{ liveAnnouncement }}
    </p>

    <div
      v-if="loading"
      class="mt-5 rounded-xl border border-gray-200 bg-gray-50 px-4 py-8 text-center text-sm text-gray-600"
      role="status"
      data-testid="public-layout-loading"
    >
      {{ t('calendar.details.loadingLayout') }}
    </div>

    <div
      v-else-if="loadError"
      class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-5"
      role="alert"
      data-testid="public-layout-error"
    >
      <p class="text-sm font-semibold text-rose-900">{{ t('calendar.details.layoutLoadError') }}</p>
      <button
        type="button"
        class="mt-3 min-h-11 rounded-lg bg-rose-700 px-4 py-2 text-sm font-bold text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2"
        data-testid="public-layout-retry"
        @click="loadLayout"
      >
        {{ t('calendar.details.tryAgain') }}
      </button>
    </div>

    <div
      v-else-if="unavailable"
      class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-5 text-sm font-semibold text-amber-950"
      role="status"
      data-testid="public-layout-unavailable"
    >
      {{ t('calendar.details.layoutNotPublished') }}
    </div>

    <div
      v-else-if="layout && !layout.rows.length"
      class="mt-5 rounded-xl border border-gray-200 bg-gray-50 px-4 py-5 text-sm text-gray-700"
      role="status"
      data-testid="public-layout-empty"
    >
      {{ t('calendar.details.noPublicLayout') }}
    </div>

    <template v-else-if="layout">
      <div
        v-if="layout.historical"
        class="mt-5 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900"
        role="note"
        data-testid="public-layout-historical"
      >
        {{ t('calendar.details.historicalLayoutNote') }}
      </div>

      <p
        v-if="layout.entrance_note"
        class="mt-5 rounded-xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-900"
        data-testid="public-layout-entrance-note"
      >
        <strong>{{ t('calendar.details.entranceGuidance') }}</strong> {{ layout.entrance_note }}
      </p>

      <div class="mt-6">
        <h4 class="text-sm font-extrabold text-gray-900">{{ t('calendar.details.browseByCategory') }}</h4>
        <p class="mt-1 text-xs text-gray-600">{{ t('calendar.details.selectCategoryHint') }}</p>
        <div
          class="mt-3 flex flex-wrap gap-2"
          role="group"
          :aria-label="t('calendar.details.categoryFilterAria')"
          data-testid="public-layout-category-filter"
        >
          <button
            v-for="category in filterCategories"
            :key="category.id"
            type="button"
            class="min-h-11 rounded-full border px-4 py-2 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
            :class="String(activeCategoryId) === String(category.id)
              ? 'border-brand-700 bg-brand-700 text-white'
              : 'border-gray-200 bg-white text-gray-700 hover:border-brand-300 hover:text-brand-700'"
            :aria-pressed="String(activeCategoryId) === String(category.id)"
            :data-category-id="category.id"
            data-testid="public-layout-category-option"
            @click="selectCategory(category)"
          >
            {{ category.label }}
          </button>
        </div>
      </div>

      <div
        v-if="!visibleRows.length"
        class="mt-5 rounded-xl border border-gray-200 bg-gray-50 px-4 py-5 text-sm text-gray-700"
        role="status"
        data-testid="public-layout-category-empty"
      >
        {{ t('calendar.details.noRowsForCategory') }}
      </div>

      <div v-else class="mt-5 space-y-4" data-testid="public-layout-map">
        <VisualParkingLayout
          mode="public"
          :rows="visualRows"
          :show-legend="false"
          :show-counts="false"
          :force-orientation="isCanonicalLayout"
        />
      </div>

      <p class="mt-5 text-xs leading-relaxed text-gray-600">
        {{ t('calendar.details.siteLabelsHelp') }}
      </p>
    </template>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import VisualParkingLayout from '../layout/VisualParkingLayout.vue';
import {
  getPublicEventLayout,
  isPublicLayoutUnavailable,
} from '../../services/publicEventLayoutApi';
import {
  filterPublicLayoutRows,
  normalizePublicLayout,
  publicLayoutFilterAnnouncement,
} from '../../utils/publicEventLayout';
import {
  adaptPublicRows,
  isStandardParkingLayout,
} from '../../utils/visualParkingLayout';

const props = defineProps({
  eventId: { type: [Number, String], required: true },
});

const { t } = useI18n();

const loading = ref(false);
const loadError = ref(false);
const unavailable = ref(false);
const layout = ref(null);
const activeCategoryId = ref('all');
const liveAnnouncement = ref('');
let loadToken = 0;

const allCategoriesLabel = computed(() => t('calendar.details.allCategories'));

const filterCategories = computed(() => [
  { id: 'all', label: allCategoriesLabel.value },
  ...(layout.value?.categories || []),
]);

const visibleRows = computed(() =>
  filterPublicLayoutRows(layout.value?.rows || [], activeCategoryId.value));
const visualRows = computed(() => adaptPublicRows(visibleRows.value));
const isCanonicalLayout = computed(() => isStandardParkingLayout(visibleRows.value));

async function loadLayout() {
  const token = ++loadToken;
  loading.value = true;
  loadError.value = false;
  unavailable.value = false;

  try {
    const { data } = await getPublicEventLayout(props.eventId);
    if (token !== loadToken) return;
    layout.value = normalizePublicLayout(data);
    activeCategoryId.value = 'all';
    liveAnnouncement.value = publicLayoutFilterAnnouncement(
      allCategoriesLabel.value,
      layout.value.rows.length,
      t,
    );
  } catch (error) {
    if (token !== loadToken) return;
    layout.value = null;
    if (isPublicLayoutUnavailable(error)) {
      unavailable.value = true;
      liveAnnouncement.value = t('calendar.details.layoutNotPublished');
    } else {
      loadError.value = true;
      liveAnnouncement.value = t('calendar.details.layoutLoadError');
    }
  } finally {
    if (token === loadToken) loading.value = false;
  }
}

function selectCategory(category) {
  activeCategoryId.value = category.id;
  liveAnnouncement.value = publicLayoutFilterAnnouncement(
    category.label,
    visibleRows.value.length,
    t,
  );
}

watch(() => props.eventId, loadLayout, { immediate: true });
</script>
