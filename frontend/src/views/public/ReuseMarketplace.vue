<template>
  <div class="min-h-screen bg-gray-50">
    <AppNavbar :variant="auth.isCommunityMember ? 'vendor' : 'public'" />

    <header class="bg-gradient-to-br from-brand-700 via-brand-600 to-brand-500 pt-16 pb-20 px-6 relative overflow-hidden">
      <div
        class="absolute inset-0 opacity-10 pointer-events-none"
        style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 24px 24px;"
      ></div>
      <div class="max-w-7xl mx-auto relative z-10 text-center text-white">
        <p class="text-brand-200 font-bold uppercase tracking-wider text-sm mb-3">Carboot@CMart Reuse</p>
        <h1 class="text-4xl md:text-5xl font-black tracking-tight mb-4">Reuse Marketplace</h1>
        <p class="text-lg text-brand-100 max-w-2xl mx-auto">
          Discover pre-loved finds from approved CMart vendors. Browse active listings and support circular shopping at our weekend carboot.
        </p>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-10 -mt-8 space-y-6">
      <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-5 sm:p-6">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
          <div class="lg:col-span-2">
            <label class="ml-label">Search</label>
            <input
              v-model="filters.search"
              type="search"
              placeholder="Search items, categories, or vendors…"
              class="ml-input"
              @input="debouncedFetch"
            />
          </div>
          <div>
            <label class="ml-label">Sort</label>
            <select v-model="filters.sort" class="ml-input" @change="fetchItems(1)">
              <option v-for="option in MARKETPLACE_SORT_OPTIONS" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
          <div class="flex items-end">
            <button type="button" class="ml-btn-ghost w-full" :disabled="loading" @click="resetFilters">
              Reset Filters
            </button>
          </div>
        </div>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="ml-label">Category</label>
            <select v-model="filters.category" class="ml-input" @change="fetchItems(1)">
              <option value="">All categories</option>
              <option v-for="category in PRODUCT_CATEGORIES" :key="category" :value="category">
                {{ category }}
              </option>
            </select>
          </div>
          <div>
            <label class="ml-label">Condition</label>
            <select v-model="filters.condition" class="ml-input" @change="fetchItems(1)">
              <option value="">All conditions</option>
              <option v-for="condition in ITEM_CONDITIONS" :key="condition" :value="condition">
                {{ condition }}
              </option>
            </select>
          </div>
          <div>
            <label class="ml-label">Pricing</label>
            <select v-model="filters.pricing_type" class="ml-input" @change="fetchItems(1)">
              <option value="">All pricing types</option>
              <option v-for="option in ITEM_PRICING_TYPES" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
        </div>
      </section>

      <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <div v-for="n in 8" :key="n" class="bg-white rounded-2xl border border-gray-100 overflow-hidden animate-pulse">
          <div class="h-40 bg-gray-100"></div>
          <div class="p-4 space-y-3">
            <div class="h-4 bg-gray-100 rounded w-3/4"></div>
            <div class="h-4 bg-gray-100 rounded w-1/2"></div>
          </div>
        </div>
      </div>

      <div v-else-if="loadError" class="bg-white rounded-2xl border border-amber-200 p-10 text-center">
        <p class="text-amber-900 font-semibold">Unable to load marketplace listings.</p>
        <button type="button" class="mt-4 ml-btn-ghost" @click="fetchItems(page)">Try Again</button>
      </div>

      <div
        v-else-if="!items.length"
        class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 text-center text-gray-500"
      >
        No active reuse listings match your search right now. Check back after vendors publish new items.
      </div>

      <template v-else>
        <p class="text-sm text-gray-500">{{ meta.total }} active listing{{ meta.total === 1 ? '' : 's' }} found</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
          <article
            v-for="item in items"
            :key="item.id"
            class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all cursor-pointer"
            @click="openDetails(item.id)"
          >
            <div class="h-40 bg-gray-50 border-b border-gray-100">
              <img
                v-if="item.image_url"
                :src="item.image_url"
                :alt="item.name"
                class="h-full w-full object-cover"
              />
              <div v-else class="h-full flex items-center justify-center text-xs font-semibold uppercase tracking-wide text-gray-400">
                No image
              </div>
            </div>

            <div class="p-4">
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <h2 class="font-bold text-gray-900 truncate">{{ item.name }}</h2>
                  <p class="text-xs text-gray-500 mt-0.5">{{ item.category }} · {{ item.condition }}</p>
                </div>
                <span class="text-sm font-bold text-brand-700 shrink-0">{{ formatItemPrice(item) }}</span>
              </div>

              <p class="mt-2 text-xs font-semibold text-gray-700 truncate">
                {{ item.vendor?.business_name || 'CMart Vendor' }}
              </p>
              <p v-if="item.description" class="mt-2 text-xs text-gray-500 line-clamp-2">{{ item.description }}</p>
              <p class="mt-3 text-[11px] uppercase tracking-wide text-gray-400">Listed {{ formatListedDate(item.listed_at) }}</p>
            </div>
          </article>
        </div>

        <div v-if="meta.last_page > 1" class="flex justify-center gap-2 pt-4">
          <button
            type="button"
            class="ml-btn-ghost text-sm"
            :disabled="page <= 1 || loading"
            @click="fetchItems(page - 1)"
          >
            Previous
          </button>
          <span class="px-3 py-2 text-sm text-gray-600">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
          <button
            type="button"
            class="ml-btn-ghost text-sm"
            :disabled="page >= meta.last_page || loading"
            @click="fetchItems(page + 1)"
          >
            Next
          </button>
        </div>
      </template>
    </main>

    <MarketplaceItemDetailsModal v-model="showDetails" :item-id="selectedItemId" />
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import AppNavbar from '../../components/navigation/AppNavbar.vue';
import MarketplaceItemDetailsModal from '../../components/MarketplaceItemDetailsModal.vue';
import api from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import { PRODUCT_CATEGORIES } from '../../utils/bookingDisplay';
import {
  formatItemPrice,
  formatListedDate,
  ITEM_CONDITIONS,
  ITEM_PRICING_TYPES,
  MARKETPLACE_SORT_OPTIONS,
} from '../../utils/vendorCatalog';

const auth = useAuthStore();

const items = ref([]);
const loading = ref(false);
const loadError = ref(false);
const page = ref(1);
const meta = ref({ current_page: 1, last_page: 1, per_page: 12, total: 0 });
const showDetails = ref(false);
const selectedItemId = ref(null);

const filters = reactive({
  search: '',
  category: '',
  condition: '',
  pricing_type: '',
  sort: 'newest',
});

let debounceTimer = null;

const debouncedFetch = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => fetchItems(1), 300);
};

const resetFilters = () => {
  filters.search = '';
  filters.category = '';
  filters.condition = '';
  filters.pricing_type = '';
  filters.sort = 'newest';
  fetchItems(1);
};

const fetchItems = async (nextPage = 1) => {
  loading.value = true;
  loadError.value = false;
  page.value = nextPage;

  try {
    const { data } = await api.get('/marketplace/items', {
      params: {
        page: nextPage,
        per_page: 12,
        search: filters.search || undefined,
        category: filters.category || undefined,
        condition: filters.condition || undefined,
        pricing_type: filters.pricing_type || undefined,
        sort: filters.sort || undefined,
      },
    });

    items.value = Array.isArray(data?.data) ? data.data : [];
    meta.value = data?.meta || meta.value;
  } catch (error) {
    console.error('Unable to load marketplace items:', error);
    loadError.value = true;
    items.value = [];
  } finally {
    loading.value = false;
  }
};

const openDetails = (id) => {
  selectedItemId.value = id;
  showDetails.value = true;
};

onMounted(() => fetchItems(1));
</script>
