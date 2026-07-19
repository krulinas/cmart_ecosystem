<template>
  <section
    id="vendor-reuse-listings"
    data-testid="vendor-item-preparation-root"
    class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-7 sm:p-9 shadow-xl shadow-brand-900/5"
  >
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
      <div>
        <h2 class="text-2xl font-extrabold text-ink-900">Item Preparation</h2>
        <p class="text-base text-ink-500 leading-relaxed">
          Prepare item previews here. Active items may appear publicly after your booking is approved.
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button type="button" class="ml-btn-primary" @click="openCreateModal">Add Item</button>
        <button type="button" class="ml-btn-ghost" :disabled="loading" @click="loadItems">
          {{ loading ? 'Refreshing…' : 'Refresh' }}
        </button>
      </div>
    </div>

    <div class="mb-4">
      <input
        v-model="itemsSearchQuery"
        type="search"
        placeholder="Search items by name, category, or status…"
        class="w-full sm:max-w-md rounded-xl border border-ink-200 bg-white/80 px-4 py-2.5 text-sm text-ink-900 placeholder:text-ink-400 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
      />
    </div>

    <div class="flex flex-wrap gap-2 mb-5">
      <button
        v-for="tab in ITEM_STATUS_TABS"
        :key="tab.id"
        type="button"
        class="rounded-full px-4 py-1.5 text-sm font-semibold transition"
        :class="filterTabClass(selectedItemStatus === tab.id)"
        @click="selectedItemStatus = tab.id"
      >
        {{ tab.label }}
        <span class="ml-1 opacity-75">({{ statusCounts[tab.id] || 0 }})</span>
      </button>
    </div>

    <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="n in 3" :key="n" class="rounded-2xl border border-ink-100 bg-white/70 p-4 animate-pulse">
        <div class="h-32 rounded-xl bg-ink-100 mb-4"></div>
        <div class="h-4 w-2/3 rounded bg-ink-100 mb-2"></div>
        <div class="h-4 w-1/2 rounded bg-ink-100"></div>
      </div>
    </div>

    <div v-else-if="loadError" class="rounded-2xl border border-amber-200 bg-amber-50/70 p-8 text-center">
      <p class="text-sm text-amber-900 font-semibold">Unable to load your reuse items.</p>
      <button type="button" class="mt-4 ml-btn-ghost text-sm" @click="loadItems">Try Again</button>
    </div>

    <div
      v-else-if="!items.length"
      class="rounded-2xl border border-dashed border-ink-300 bg-ink-50/50 p-10 text-center text-ink-500"
    >
      No private items yet. Add your first preparation record for goods you plan to bring to the carboot.
    </div>

    <template v-else>
      <div
        v-if="!filteredItems.length"
        class="rounded-2xl border border-dashed border-ink-300 bg-ink-50/50 p-10 text-center text-ink-500"
      >
        No items match your search.
      </div>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <article
          v-for="item in visibleItems"
          :key="item.id"
          class="rounded-2xl border border-ink-100 bg-white/70 overflow-hidden shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all"
        >
          <div class="h-36 bg-ink-50 border-b border-ink-100 overflow-hidden">
            <button
              v-if="itemImageSrc(item)"
              type="button"
              class="group relative block h-full w-full cursor-zoom-in focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-inset"
              :aria-label="`View full image for ${item.name}`"
              @click="openItemImagePreview(item)"
            >
              <img
                :src="itemImageSrc(item)"
                :alt="item.name"
                class="pointer-events-none h-full w-full object-cover object-center transition duration-300 group-hover:opacity-90"
                @error="onItemImageError(item.id)"
              />
            </button>
            <div v-else class="h-full flex items-center justify-center text-xs font-semibold uppercase tracking-wide text-ink-400">
              No image
            </div>
          </div>

          <div class="p-4">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <h3 class="font-bold text-ink-900 truncate">{{ item.name }}</h3>
                <p class="text-xs text-ink-500 mt-0.5">{{ item.category }} · {{ item.condition }}</p>
              </div>
              <span
                class="ml-badge capitalize shrink-0"
                :class="item.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-ink-100 text-ink-700'"
              >
                {{ item.status }}
              </span>
            </div>

            <p
              class="mt-2 text-[11px] font-semibold"
              :class="item.status === 'active' ? 'text-brand-700' : 'text-ink-500'"
            >
              {{ marketplaceVisibilityLabel(item.status) }}
            </p>

            <p
              v-if="item.has_active_reservation"
              class="mt-2 inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-bold text-amber-800 ring-1 ring-amber-200"
              data-testid="vendor-item-active-reservation-badge"
            >
              Active reservation hold
            </p>

            <p class="mt-3 text-sm font-semibold text-brand-700">{{ formatItemPrice(item) }}</p>
            <p v-if="item.description" class="mt-2 text-xs text-ink-500 line-clamp-2">{{ item.description }}</p>

            <div class="mt-4 flex flex-wrap gap-2">
              <button type="button" class="ml-btn-ghost text-sm" @click="openDetails(item)">View</button>
              <button type="button" class="ml-btn-ghost text-sm" @click="openEditModal(item)">Edit</button>
              <button
                type="button"
                class="ml-btn-ghost text-sm text-rose-600"
                :disabled="deletingId === item.id"
                @click="removeItem(item)"
              >
                {{ deletingId === item.id ? 'Deleting…' : 'Delete' }}
              </button>
            </div>
          </div>
        </article>
      </div>

      <div v-if="filteredItems.length > VISIBLE_LIST_LIMIT" class="mt-4 flex justify-center">
        <button type="button" class="ml-btn-ghost text-sm font-semibold" @click="itemsExpanded = !itemsExpanded">
          {{ itemsExpanded ? 'Show Less' : `View All Items (${filteredItems.length})` }}
        </button>
      </div>
    </template>

    <VendorItemFormModal v-model="showFormModal" :item="editingItem" @saved="handleSaved" />
    <VendorItemDetailsModal
      v-model="showDetailsModal"
      :item="selectedItem"
      @edit="openEditFromDetails"
    />

    <ImageLightbox
      v-model:open="imageLightbox.open"
      :images="imageLightbox.images"
      :start-index="imageLightbox.startIndex"
      :alt-text="imageLightbox.altText"
      :caption="imageLightbox.caption"
    />
  </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useToast } from 'vue-toastification';
import VendorItemFormModal from './VendorItemFormModal.vue';
import VendorItemDetailsModal from './VendorItemDetailsModal.vue';
import ImageLightbox from './management/ImageLightbox.vue';
import api from '../services/api';
import { extractApiError } from '../utils/apiErrors';
import { filterTabClass } from '../utils/bookingDisplay';
import { resolveReuseItemGallery, resolveReuseItemImageUrl, normalizeReuseItem } from '../utils/imageUrl';
import { formatItemPrice, ITEM_STATUS_TABS, marketplaceVisibilityLabel } from '../utils/vendorCatalog';

const emit = defineEmits(['changed']);

const toast = useToast();
const VISIBLE_LIST_LIMIT = 5;

const items = ref([]);
const loading = ref(false);
const loadError = ref(false);
const deletingId = ref(null);
const itemsSearchQuery = ref('');
const selectedItemStatus = ref('all');
const itemsExpanded = ref(false);
const showFormModal = ref(false);
const showDetailsModal = ref(false);
const editingItem = ref(null);
const selectedItem = ref(null);
const brokenImageIds = ref(new Set());
const imageLightbox = ref({
  open: false,
  images: [],
  startIndex: 0,
  altText: 'Item image',
  caption: '',
});

const normalizeVendorItem = (item) => normalizeReuseItem(item);

const itemImageSrc = (item) => {
  if (!item || brokenImageIds.value.has(item.id)) return null;
  return item.image_url || resolveReuseItemImageUrl(item);
};

const onItemImageError = (id) => {
  brokenImageIds.value = new Set([...brokenImageIds.value, id]);
};

const openItemImagePreview = (item) => {
  const gallery = resolveReuseItemGallery(item).filter((image) => image.image_url);
  if (!gallery.length) return;

  const cardImageUrl = itemImageSrc(item);
  const matchedIndex = gallery.findIndex((image) => image.image_url === cardImageUrl);

  imageLightbox.value = {
    open: true,
    images: gallery.map((image) => image.image_url),
    startIndex: matchedIndex >= 0 ? matchedIndex : 0,
    altText: item.name,
    caption: item.name,
  };
};

const normalizeSearch = (value) => String(value ?? '').toLowerCase().trim();

const matchesItemStatus = (item, filterId) => {
  if (filterId === 'all') return true;
  return item.status === filterId;
};

const itemMatchesSearch = (item, query) => {
  if (!query) return true;

  const haystack = [
    item.name,
    item.category,
    item.condition,
    item.description,
    item.status,
    item.pricing_type,
    formatItemPrice(item),
    item.price,
  ]
    .filter((part) => part != null && part !== '')
    .join(' ')
    .toLowerCase();

  return haystack.includes(query);
};

const filteredItems = computed(() => {
  const query = normalizeSearch(itemsSearchQuery.value);
  return items.value.filter(
    (item) => matchesItemStatus(item, selectedItemStatus.value) && itemMatchesSearch(item, query),
  );
});

const visibleItems = computed(() =>
  itemsExpanded.value ? filteredItems.value : filteredItems.value.slice(0, VISIBLE_LIST_LIMIT),
);

const statusCounts = computed(() =>
  ITEM_STATUS_TABS.reduce((counts, tab) => {
    counts[tab.id] = items.value.filter((item) => matchesItemStatus(item, tab.id)).length;
    return counts;
  }, {}),
);

watch([itemsSearchQuery, selectedItemStatus], () => {
  itemsExpanded.value = false;
});

const loadItems = async () => {
  loading.value = true;
  loadError.value = false;
  try {
    const { data } = await api.get('/vendor/items');
    items.value = (Array.isArray(data?.items) ? data.items : []).map(normalizeVendorItem);
    emit('changed', items.value);
  } catch (error) {
    console.error('Unable to load vendor items:', error);
    loadError.value = true;
    items.value = [];
  } finally {
    loading.value = false;
  }
};

const openCreateModal = () => {
  editingItem.value = null;
  showFormModal.value = true;
};

const openEditModal = (item) => {
  editingItem.value = item;
  showFormModal.value = true;
};

const openDetails = (item) => {
  selectedItem.value = item;
  showDetailsModal.value = true;
};

const openEditFromDetails = (item) => {
  showDetailsModal.value = false;
  openEditModal(item);
};

const handleSaved = async () => {
  await loadItems();
};

const removeItem = async (item) => {
  if (!window.confirm(`Delete "${item.name}" from your listings?`)) return;

  deletingId.value = item.id;
  try {
    await api.delete(`/vendor/items/${item.id}`);
    toast.success('Reuse item deleted.');
    if (selectedItem.value?.id === item.id) {
      showDetailsModal.value = false;
      selectedItem.value = null;
    }
    await loadItems();
  } catch (error) {
    console.error('Unable to delete vendor item:', error);
    toast.error(extractApiError(error));
  } finally {
    deletingId.value = null;
  }
};

onMounted(loadItems);

defineExpose({ loadItems });
</script>
