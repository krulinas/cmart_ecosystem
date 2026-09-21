<template>
  <div class="min-h-screen bg-gray-50" data-testid="marketplace-preview-root">
    <AppNavbar :variant="auth.isVendorUser ? 'vendor' : 'public'" />

    <header class="bg-gradient-to-br from-brand-700 via-brand-600 to-brand-500 pt-16 pb-20 px-6 relative overflow-hidden">
      <div
        class="absolute inset-0 opacity-10 pointer-events-none"
        style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 24px 24px;"
      ></div>
      <div class="max-w-7xl mx-auto relative z-10 text-center text-white">
        <p class="text-brand-200 font-bold uppercase tracking-wider text-sm mb-3">{{ t('marketplace.eyebrow') }}</p>
        <h1 class="text-4xl md:text-5xl font-black tracking-tight mb-4">{{ t('marketplace.title') }}</h1>
        <p class="text-lg text-brand-100 max-w-3xl mx-auto leading-relaxed">
          {{ t('marketplace.subtitle') }}
        </p>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-10 -mt-8 space-y-6">
      <section
        v-if="carbootEventId"
        class="rounded-2xl border border-brand-200 bg-brand-50/60 px-4 py-3.5 sm:px-5 sm:py-4 shadow-sm"
        data-testid="marketplace-event-scope"
      >
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <p class="text-sm font-semibold text-brand-900">
            {{ t('marketplace.eventScopeLabel', { title: scopedEventTitle }) }}
          </p>
          <router-link
            to="/marketplace"
            class="text-sm font-bold text-brand-700 hover:text-brand-900 no-underline"
            data-testid="marketplace-event-scope-clear"
          >
            {{ t('marketplace.eventScopeClear') }}
          </router-link>
        </div>
      </section>

      <section
        class="rounded-2xl border border-amber-400 bg-[#FFFBEB] px-4 py-3.5 sm:px-5 sm:py-4 shadow-sm"
        role="note"
        :aria-label="t('marketplace.policyAria')"
        data-testid="marketplace-preview-notice"
      >
        <div class="flex gap-3 sm:items-start">
          <div class="shrink-0 flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center rounded-full bg-[#FDE68A] text-[#B45309]">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="min-w-0 space-y-1">
            <p class="text-sm font-extrabold text-[#78350F]">{{ t('marketplace.policyTitle') }}</p>
            <p class="text-sm text-[#92400E]/95 leading-relaxed">
              {{ t('marketplace.policyBody') }}
            </p>
          </div>
        </div>
      </section>

      <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 space-y-5">
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
          <div>
            <h2 class="text-xl font-extrabold text-gray-900">{{ t('marketplace.browseItems') }}</h2>
            <p class="mt-1 text-sm text-gray-600">
              {{ t('marketplace.browseLead') }}
            </p>
          </div>
          <p v-if="!loading" class="text-sm font-semibold text-gray-500">
            {{ t('marketplace.itemsAvailable', { n: totalItems }) }}
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
          <div class="md:col-span-2">
            <label for="marketplace-search" class="sr-only">{{ t('marketplace.searchLabel') }}</label>
            <input
              id="marketplace-search"
              v-model="searchQuery"
              type="search"
              :placeholder="t('marketplace.searchPlaceholder')"
              class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
              data-testid="marketplace-search"
            />
          </div>
          <select v-model="selectedCategory" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm">
            <option value="">{{ t('marketplace.allCategories') }}</option>
            <option v-for="category in PRODUCT_CATEGORIES" :key="category" :value="category">
              {{ categoryLabel(category) }}
            </option>
          </select>
          <select v-model="selectedSort" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm">
            <option v-for="option in sortOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>

        <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          <div v-for="n in 6" :key="n" class="h-80 rounded-2xl border border-gray-100 bg-gray-50 animate-pulse"></div>
        </div>

        <div
          v-else-if="!items.length"
          class="rounded-2xl border border-gray-100 bg-gray-50 px-6 py-14 text-center"
          data-testid="marketplace-empty-state"
        >
          <p class="text-lg font-semibold text-gray-700">
            {{ t('marketplace.emptyTitle') }}
          </p>
          <p class="mt-2 text-sm text-gray-500">
            {{ t('marketplace.emptyHint') }}
          </p>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          <MarketplaceItemCard
            v-for="item in items"
            :key="item.id"
            :item="item"
            @select="openItemDetails"
          />
        </div>
      </section>

      <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
        <h2 class="text-lg font-extrabold text-gray-900">{{ t('marketplace.planVisitTitle') }}</h2>
        <p class="mt-2 text-sm text-gray-600 max-w-2xl">
          {{ t('marketplace.planVisitLead') }}
        </p>
        <div class="mt-5 flex flex-wrap gap-3">
          <router-link to="/calendar" class="ml-btn-primary text-sm no-underline">
            {{ t('marketplace.viewEventsCalendar') }}
          </router-link>
          <router-link to="/community" class="ml-btn-ghost text-sm no-underline">
            {{ t('marketplace.communityPortal') }}
          </router-link>
        </div>
      </section>
    </main>

    <MarketplaceItemDetailsModal
      v-model="showDetailsModal"
      :item-id="selectedItemId"
      :carboot-event-id="carbootEventId"
      @reserved="onItemReserved"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import AppNavbar from '../../components/navigation/AppNavbar.vue';
import MarketplaceItemCard from '../../components/public/MarketplaceItemCard.vue';
import MarketplaceItemDetailsModal from '../../components/MarketplaceItemDetailsModal.vue';
import api from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import { PRODUCT_CATEGORIES } from '../../utils/bookingDisplay';
import { MARKETPLACE_SORT_OPTIONS } from '../../utils/vendorCatalog';
import { normalizeReuseItem } from '../../utils/imageUrl';

const { t } = useI18n();
const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const CATEGORY_KEYS = {
  'Pre-loved / Thrift': 'marketplace.categories.preloved',
  'Food & Beverages': 'marketplace.categories.food',
  'Clothing & Apparel': 'marketplace.categories.clothing',
  'Handicrafts & Art': 'marketplace.categories.handicrafts',
  'Electronics & Gadgets': 'marketplace.categories.electronics',
  Others: 'marketplace.categories.others',
};

const SORT_LABEL_KEYS = {
  newest: 'marketplace.sortNewest',
  oldest: 'marketplace.sortOldest',
  price_asc: 'marketplace.sortPriceAsc',
  price_desc: 'marketplace.sortPriceDesc',
};

const items = ref([]);
const loading = ref(true);
const searchQuery = ref('');
const selectedCategory = ref('');
const selectedSort = ref('newest');
const totalItems = ref(0);
const showDetailsModal = ref(false);
const selectedItemId = ref(null);

let searchTimer = null;

const carbootEventId = computed(() => {
  const raw = route.query.event;
  const value = Array.isArray(raw) ? raw[0] : raw;
  return value ? String(value) : '';
});

const scopedEventTitle = computed(() => {
  const titled = items.value.find((item) => item.event?.title);
  return titled?.event?.title || t('marketplace.eventScopeFallback');
});

const sortOptions = computed(() =>
  MARKETPLACE_SORT_OPTIONS.map((option) => ({
    value: option.value,
    label: t(SORT_LABEL_KEYS[option.value] || option.label),
  })),
);

const categoryLabel = (category) => {
  const key = CATEGORY_KEYS[category];
  return key ? t(key) : category;
};

const fetchItems = async ({ quiet = false } = {}) => {
  if (!quiet) loading.value = true;
  try {
    const { data } = await api.get('/marketplace/items', {
      params: {
        search: searchQuery.value.trim() || undefined,
        category: selectedCategory.value || undefined,
        sort: selectedSort.value,
        carboot_event_id: carbootEventId.value || undefined,
        per_page: 24,
      },
    });
    items.value = (Array.isArray(data?.data) ? data.data : []).map(normalizeReuseItem);
    totalItems.value = data?.meta?.total ?? items.value.length;
  } catch (error) {
    console.error('Unable to load marketplace previews:', error);
    items.value = [];
    totalItems.value = 0;
  } finally {
    if (!quiet) loading.value = false;
  }
};

const onItemReserved = async () => {
  const reservedId = selectedItemId.value;
  items.value = items.value.map((item) => {
    if (String(item.id) !== String(reservedId)) return item;
    return {
      ...item,
      is_reservable: false,
      has_active_reservation: true,
      reservation_availability: {
        available: false,
        code: 'already_reserved',
        message: t('marketplace.details.alreadyReserved'),
      },
    };
  });
  await fetchItems({ quiet: true });
};

const openItemDetails = (item) => {
  selectedItemId.value = item.id;
  showDetailsModal.value = true;
};

watch([selectedCategory, selectedSort, carbootEventId], () => fetchItems());

watch(searchQuery, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(fetchItems, 300);
});

const openItemFromQuery = () => {
  const itemId = route.query.item;
  if (!itemId) return;
  selectedItemId.value = itemId;
  showDetailsModal.value = true;
  const nextQuery = { ...route.query };
  delete nextQuery.item;
  router.replace({ path: '/marketplace', query: nextQuery });
};

onMounted(async () => {
  await fetchItems();
  openItemFromQuery();
});
</script>
