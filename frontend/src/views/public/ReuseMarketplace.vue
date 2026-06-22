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
        <h1 class="text-4xl md:text-5xl font-black tracking-tight mb-4">Carboot Reuse Preview</h1>
        <p class="text-lg text-brand-100 max-w-3xl mx-auto leading-relaxed">
          Preview selected pre-loved items from approved CMart vendors before visiting the weekend carboot.
          Items shown here are available for in-person viewing and purchase at CMart during event day.
        </p>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-10 -mt-8 space-y-6">
      <section
        class="rounded-2xl border border-amber-400 bg-[#FFFBEB] px-4 py-3.5 sm:px-5 sm:py-4 shadow-sm"
        role="note"
        aria-label="Before you visit notice"
      >
        <div class="flex gap-3 sm:items-start">
          <div class="shrink-0 flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center rounded-full bg-[#FDE68A] text-[#B45309]">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="min-w-0 space-y-1">
            <p class="text-sm font-extrabold text-[#78350F]">Before you visit</p>
            <p class="text-sm font-semibold text-[#92400E] leading-snug">
              Preview only: no online checkout, delivery, or postage.
            </p>
            <p class="text-sm text-[#92400E]/95 leading-relaxed">
              These items are shown to help you plan your CMart Carboot visit.
              Purchases are made in person at the vendor booth during event day.
            </p>
          </div>
        </div>
      </section>

      <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-5 sm:p-6">
        <div class="mb-5">
          <h2 class="text-lg font-extrabold text-gray-900">Plan your carboot visit</h2>
          <p class="mt-1 text-sm text-gray-600">
            Search items, categories, or vendors to discover what you may find at the upcoming CMart Carboot.
          </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
          <div class="lg:col-span-2">
            <label class="ml-label">Search</label>
            <input
              v-model="filters.search"
              type="search"
              placeholder="Search items to check out at the carboot..."
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
            <label class="ml-label">Budget Guide</label>
            <select v-model="filters.pricing_type" class="ml-input" @change="fetchItems(1)">
              <option value="">All budget types</option>
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
        <p class="text-amber-900 font-semibold">Unable to load preview items.</p>
        <button type="button" class="mt-4 ml-btn-ghost" @click="fetchItems(page)">Try Again</button>
      </div>

      <div
        v-else-if="!items.length"
        class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 text-center text-gray-500"
      >
        <p class="text-lg font-semibold text-gray-700">No preview items available yet.</p>
        <p class="mt-2 text-sm text-gray-500 max-w-md mx-auto">
          Approved vendors will appear here once they add items for the upcoming CMart Carboot.
        </p>
      </div>

      <template v-else>
        <p class="text-sm text-gray-500">
          {{ meta.total }} preview item{{ meta.total === 1 ? '' : 's' }} found
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
          <article
            v-for="item in items"
            :key="item.id"
            class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all flex flex-col"
          >
            <div
              class="h-40 bg-gray-50 border-b border-gray-100 overflow-hidden cursor-pointer"
              @click="openDetails(item.id)"
            >
              <img
                v-if="itemImageSrc(item)"
                :src="itemImageSrc(item)"
                :alt="item.name"
                class="h-full w-full object-cover object-center"
                @error="onItemImageError(item.id)"
              />
              <div v-else class="h-full flex items-center justify-center text-xs font-semibold uppercase tracking-wide text-gray-400">
                No image
              </div>
            </div>

            <div class="p-4 flex flex-col flex-grow">
              <h2 class="font-bold text-gray-900 line-clamp-2">{{ item.name }}</h2>
              <p class="mt-2 text-sm font-semibold text-brand-700">
                Guide Price: {{ formatItemPrice(item) }}
              </p>
              <ul class="mt-3 space-y-1.5 text-xs text-gray-600">
                <li class="flex items-start gap-1.5">
                  <span class="font-semibold text-gray-500 shrink-0">Vendor:</span>
                  <span>{{ item.vendor?.business_name || 'CMart Vendor' }}</span>
                </li>
                <li class="flex items-start gap-1.5">
                  <span class="font-semibold text-gray-500 shrink-0">Condition:</span>
                  <span>{{ item.condition }}</span>
                </li>
                <li class="text-emerald-700 font-medium">Available at CMart Carboot</li>
                <li class="text-gray-500">Purchase: In-person only</li>
              </ul>
              <p v-if="item.description" class="mt-2 text-xs text-gray-500 line-clamp-2">{{ item.description }}</p>
              <button
                type="button"
                class="mt-4 w-full rounded-full border border-brand-200 bg-brand-50 px-4 py-2 text-sm font-bold text-brand-700 transition hover:bg-brand-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
                @click="openDetails(item.id)"
              >
                View Details
              </button>
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
import { normalizeReuseItem, resolveReuseItemImageUrl } from '../../utils/imageUrl';
import { PRODUCT_CATEGORIES } from '../../utils/bookingDisplay';
import {
  formatItemPrice,
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
const brokenImageIds = ref(new Set());

const normalizeMarketplaceItem = (item) => normalizeReuseItem(item);

const itemImageSrc = (item) => {
  if (!item || brokenImageIds.value.has(item.id)) return null;
  return item.image_url || resolveReuseItemImageUrl(item);
};

const onItemImageError = (id) => {
  brokenImageIds.value = new Set([...brokenImageIds.value, id]);
};

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

    items.value = (Array.isArray(data?.data) ? data.data : []).map(normalizeMarketplaceItem);
    meta.value = data?.meta || meta.value;
  } catch (error) {
    console.error('Unable to load preview items:', error);
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
