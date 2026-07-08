<template>
  <div class="min-h-screen bg-gray-50" data-testid="marketplace-preview-root">
    <AppNavbar :variant="auth.isVendorUser ? 'vendor' : 'public'" />

    <header class="bg-gradient-to-br from-brand-700 via-brand-600 to-brand-500 pt-16 pb-20 px-6 relative overflow-hidden">
      <div
        class="absolute inset-0 opacity-10 pointer-events-none"
        style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 24px 24px;"
      ></div>
      <div class="max-w-7xl mx-auto relative z-10 text-center text-white">
        <p class="text-brand-200 font-bold uppercase tracking-wider text-sm mb-3">Carboot@CMart</p>
        <h1 class="text-4xl md:text-5xl font-black tracking-tight mb-4">Carboot Preview</h1>
        <p class="text-lg text-brand-100 max-w-3xl mx-auto leading-relaxed">
          Browse item previews from approved vendors before you visit the CMart Carboot in person.
        </p>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-10 -mt-8 space-y-6">
      <section
        class="rounded-2xl border border-amber-400 bg-[#FFFBEB] px-4 py-3.5 sm:px-5 sm:py-4 shadow-sm"
        role="note"
        aria-label="Carboot preview policy"
        data-testid="marketplace-preview-notice"
      >
        <div class="flex gap-3 sm:items-start">
          <div class="shrink-0 flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center rounded-full bg-[#FDE68A] text-[#B45309]">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="min-w-0 space-y-1">
            <p class="text-sm font-extrabold text-[#78350F]">Visit in person on event day</p>
            <p class="text-sm text-[#92400E]/95 leading-relaxed">
              Purchases are made at vendor booths during the carboot. There is no online checkout,
              delivery, or postage for reuse items.
            </p>
          </div>
        </div>
      </section>

      <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 space-y-5">
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
          <div>
            <h2 class="text-xl font-extrabold text-gray-900">Browse item previews</h2>
            <p class="mt-1 text-sm text-gray-600">
              Items shown here are previews only. Confirm availability and purchase at vendor booths on event day.
            </p>
          </div>
          <p v-if="!loading" class="text-sm font-semibold text-gray-500">
            {{ totalItems }} preview{{ totalItems === 1 ? '' : 's' }} available
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
          <div class="md:col-span-2">
            <label for="marketplace-search" class="sr-only">Search items</label>
            <input
              id="marketplace-search"
              v-model="searchQuery"
              type="search"
              placeholder="Search by item name, category, or vendor…"
              class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
              data-testid="marketplace-search"
            />
          </div>
          <select v-model="selectedCategory" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm">
            <option value="">All categories</option>
            <option v-for="category in PRODUCT_CATEGORIES" :key="category" :value="category">{{ category }}</option>
          </select>
          <select v-model="selectedSort" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm">
            <option v-for="option in MARKETPLACE_SORT_OPTIONS" :key="option.value" :value="option.value">
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
            No public item previews are available yet. Please check again closer to the next CMart Carboot.
          </p>
          <p class="mt-2 text-sm text-gray-500">
            Vendors can publish previews after their booking is approved for an upcoming event.
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
        <h2 class="text-lg font-extrabold text-gray-900">Plan your carboot visit</h2>
        <p class="mt-2 text-sm text-gray-600 max-w-2xl">
          Check upcoming event dates, news, and community updates before you head to CMart.
        </p>
        <div class="mt-5 flex flex-wrap gap-3">
          <router-link to="/calendar" class="ml-btn-primary text-sm no-underline">
            View events calendar
          </router-link>
          <router-link to="/community" class="ml-btn-ghost text-sm no-underline">
            Community portal
          </router-link>
        </div>
      </section>
    </main>

    <MarketplaceItemDetailsModal
      v-model="showDetailsModal"
      :item-id="selectedItemId"
    />
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import AppNavbar from '../../components/navigation/AppNavbar.vue';
import MarketplaceItemCard from '../../components/public/MarketplaceItemCard.vue';
import MarketplaceItemDetailsModal from '../../components/MarketplaceItemDetailsModal.vue';
import api from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import { PRODUCT_CATEGORIES } from '../../utils/bookingDisplay';
import { MARKETPLACE_SORT_OPTIONS } from '../../utils/vendorCatalog';
import { normalizeReuseItem } from '../../utils/imageUrl';

const auth = useAuthStore();

const items = ref([]);
const loading = ref(true);
const searchQuery = ref('');
const selectedCategory = ref('');
const selectedSort = ref('newest');
const totalItems = ref(0);
const showDetailsModal = ref(false);
const selectedItemId = ref(null);

let searchTimer = null;

const fetchItems = async () => {
  loading.value = true;
  try {
    const { data } = await api.get('/marketplace/items', {
      params: {
        search: searchQuery.value.trim() || undefined,
        category: selectedCategory.value || undefined,
        sort: selectedSort.value,
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
    loading.value = false;
  }
};

const openItemDetails = (item) => {
  selectedItemId.value = item.id;
  showDetailsModal.value = true;
};

watch([selectedCategory, selectedSort], fetchItems);

watch(searchQuery, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(fetchItems, 300);
});

onMounted(fetchItems);
</script>
