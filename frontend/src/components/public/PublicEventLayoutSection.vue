<template>
  <section
    class="border-t border-gray-100 px-5 py-6 sm:px-6 lg:px-8"
    aria-labelledby="public-layout-heading"
    data-testid="public-event-layout-section"
  >
    <div class="max-w-4xl">
      <p class="text-xs font-bold uppercase tracking-wider text-brand-600">Panduan Pelawat</p>
      <h3 id="public-layout-heading" class="mt-1 text-xl font-extrabold text-gray-900">
        Peta Susun Atur Acara
      </h3>
      <p class="mt-1 text-sm text-gray-600">Cari baris dan kawasan mengikut kategori jualan.</p>
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
      Memuatkan susun atur acara…
    </div>

    <div
      v-else-if="loadError"
      class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-5"
      role="alert"
      data-testid="public-layout-error"
    >
      <p class="text-sm font-semibold text-rose-900">Susun atur acara tidak dapat dimuatkan.</p>
      <button
        type="button"
        class="mt-3 min-h-11 rounded-lg bg-rose-700 px-4 py-2 text-sm font-bold text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2"
        data-testid="public-layout-retry"
        @click="loadLayout"
      >
        Cuba Lagi
      </button>
    </div>

    <div
      v-else-if="unavailable"
      class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-5 text-sm font-semibold text-amber-950"
      role="status"
      data-testid="public-layout-unavailable"
    >
      Susun atur acara belum diterbitkan.
    </div>

    <div
      v-else-if="layout && !layout.rows.length"
      class="mt-5 rounded-xl border border-gray-200 bg-gray-50 px-4 py-5 text-sm text-gray-700"
      role="status"
      data-testid="public-layout-empty"
    >
      Tiada susun atur awam tersedia buat masa ini.
    </div>

    <template v-else-if="layout">
      <div
        v-if="layout.historical"
        class="mt-5 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900"
        role="note"
        data-testid="public-layout-historical"
      >
        Ini ialah peta susun atur sejarah untuk acara yang telah tamat atau ditutup.
      </div>

      <p
        v-if="layout.entrance_note"
        class="mt-5 rounded-xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-900"
        data-testid="public-layout-entrance-note"
      >
        <strong>Petunjuk masuk:</strong> {{ layout.entrance_note }}
      </p>

      <div class="mt-6">
        <h4 class="text-sm font-extrabold text-gray-900">Cari Mengikut Kategori</h4>
        <p class="mt-1 text-xs text-gray-600">Pilih kategori untuk mencari kawasan yang berkaitan.</p>
        <div
          class="mt-3 flex flex-wrap gap-2"
          role="group"
          aria-label="Tapis peta mengikut kategori jualan"
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
        Tiada baris tersedia untuk kategori ini.
      </div>

      <div v-else class="mt-5 space-y-4" data-testid="public-layout-map">
        <article
          v-for="row in visibleRows"
          :key="row.id"
          class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5"
          :aria-labelledby="`public-layout-row-heading-${row.id}`"
          :data-row-id="row.id"
          data-testid="public-layout-row"
        >
          <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <h4 :id="`public-layout-row-heading-${row.id}`" class="text-base font-extrabold text-gray-900">
                {{ row.label }}
              </h4>
              <p class="mt-0.5 text-sm font-bold text-brand-700">{{ row.category.label }}</p>
              <p v-if="row.description" class="mt-1 text-sm text-gray-600">{{ row.description }}</p>
            </div>
            <p class="text-xs font-semibold text-gray-600">
              {{ row.sites.length }} tapak fizikal
            </p>
          </div>

          <ul
            class="mt-4 grid grid-cols-2 gap-2 min-[420px]:grid-cols-3 sm:grid-cols-4 md:grid-cols-5"
            :aria-label="`Label tapak untuk ${row.label}`"
            data-testid="public-layout-site-grid"
          >
            <li
              v-for="site in row.sites"
              :key="site.id"
              class="flex min-h-16 min-w-0 flex-col items-center justify-center rounded-xl border border-sky-200 bg-sky-50 px-2 py-2 text-center"
              data-testid="public-layout-site"
              :aria-label="siteAccessibleLabel(site, row)"
            >
              <span class="text-sm font-extrabold text-gray-900">{{ site.label }}</span>
              <span v-if="site.space?.name" class="mt-1 line-clamp-1 text-[11px] text-gray-600">
                {{ site.space.name }}
              </span>
            </li>
          </ul>
        </article>
      </div>

      <p class="mt-5 text-xs leading-relaxed text-gray-600">
        Label tapak membantu anda mengenal pasti kedudukan vendor semasa acara.
      </p>
    </template>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import {
  getPublicEventLayout,
  isPublicLayoutUnavailable,
} from '../../services/publicEventLayoutApi';
import {
  filterPublicLayoutRows,
  normalizePublicLayout,
  publicLayoutFilterAnnouncement,
} from '../../utils/publicEventLayout';

const props = defineProps({
  eventId: { type: [Number, String], required: true },
});

const loading = ref(false);
const loadError = ref(false);
const unavailable = ref(false);
const layout = ref(null);
const activeCategoryId = ref('all');
const liveAnnouncement = ref('');
let loadToken = 0;

const filterCategories = computed(() => [
  { id: 'all', label: 'Semua Kategori' },
  ...(layout.value?.categories || []),
]);

const visibleRows = computed(() =>
  filterPublicLayoutRows(layout.value?.rows || [], activeCategoryId.value));

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
      'Semua Kategori',
      layout.value.rows.length,
    );
  } catch (error) {
    if (token !== loadToken) return;
    layout.value = null;
    if (isPublicLayoutUnavailable(error)) {
      unavailable.value = true;
      liveAnnouncement.value = 'Susun atur acara belum diterbitkan.';
    } else {
      loadError.value = true;
      liveAnnouncement.value = 'Susun atur acara tidak dapat dimuatkan.';
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
  );
}

function siteAccessibleLabel(site, row) {
  const space = site.space?.name ? `, jenis ruang ${site.space.name}` : '';
  return `Tapak ${site.label}, ${row.label}, kategori ${row.category.label}${space}`;
}

watch(() => props.eventId, loadLayout, { immediate: true });
</script>
