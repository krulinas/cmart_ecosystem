<template>
  <section class="ml-card">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
      <div>
        <h2 class="text-lg font-extrabold text-ink-900">Community Feedback Moderation</h2>
        <p class="text-sm text-ink-500">Includes hidden reviews. Public portal only shows visible entries.</p>
      </div>
      <button class="ml-btn-ghost shrink-0" @click="load" :disabled="loading">
        {{ loading ? 'Loading…' : 'Refresh' }}
      </button>
    </div>

    <div class="mb-4 flex flex-wrap gap-2" role="tablist" aria-label="Feedback filters">
      <button
        v-for="filter in FILTERS"
        :key="filter.value"
        type="button"
        role="tab"
        class="rounded-full px-3 py-1.5 text-xs font-semibold transition border"
        :class="activeFilter === filter.value
          ? 'bg-brand-500 text-white border-brand-500'
          : 'bg-white text-ink-600 border-ink-200 hover:border-brand-300'"
        :aria-selected="activeFilter === filter.value"
        @click="setFilter(filter.value)"
      >
        {{ filter.label }}
      </button>
    </div>

    <div v-if="loading && !hasLoaded" class="text-center text-ink-500 py-10">Loading feedback…</div>
    <div v-else-if="loadError" class="rounded-xl border border-rose-200 bg-rose-50/60 px-4 py-8 text-center text-sm text-rose-800">
      {{ loadError }}
    </div>
    <div v-else-if="!items.length" class="text-center text-ink-500 py-10">No feedback records match this filter.</div>

    <div v-else class="space-y-4">
      <article
        v-for="item in items"
        :key="item.id"
        class="rounded-xl border p-4 cursor-pointer transition hover:shadow-md hover:border-brand-200"
        :class="item.is_hidden ? 'border-rose-200 bg-rose-50/40' : 'border-ink-200 bg-white'"
        role="button"
        tabindex="0"
        :aria-label="`View feedback from ${item.user_name || 'community member'}`"
        @click="openDetail(item)"
        @keydown.enter="openDetail(item)"
        @keydown.space.prevent="openDetail(item)"
      >
        <div class="flex flex-wrap justify-between gap-2 mb-2">
          <div class="flex flex-wrap items-center gap-1.5">
            <span class="font-bold text-ink-900">{{ item.user_name || 'Community Member' }}</span>
            <span v-if="item.role" class="text-xs font-semibold text-brand-700">{{ item.role }}</span>
            <span v-if="item.rating" class="text-xs text-brand-500">
              {{ '★'.repeat(item.rating) }}{{ '☆'.repeat(5 - item.rating) }}
            </span>
          </div>
          <div class="text-xs text-ink-500">#{{ item.id }} · {{ formatDate(item.created_at) }}</div>
        </div>

        <div class="flex flex-wrap gap-1.5 mb-2">
          <span class="ml-badge text-[10px]" :class="item.is_hidden ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800'">
            {{ item.is_hidden ? 'Hidden' : 'Visible' }}
          </span>
          <span class="ml-badge text-[10px]" :class="item.reviewed_at ? 'bg-sky-100 text-sky-800' : 'bg-amber-100 text-amber-800'">
            {{ item.reviewed_at ? 'Reviewed' : 'Unreviewed' }}
          </span>
          <span v-if="proofUrl(item)" class="ml-badge text-[10px] bg-violet-100 text-violet-800">Has Photo</span>
          <span v-if="item.official_reply?.status === 'draft'" class="ml-badge text-[10px] bg-orange-100 text-orange-800">Reply Draft</span>
          <span v-if="item.official_reply?.status === 'published'" class="ml-badge text-[10px] bg-emerald-100 text-emerald-800">Reply Published</span>
        </div>

        <p class="text-sm text-ink-700 italic mb-3 line-clamp-2">"{{ item.comment || item.comments }}"</p>

        <button
          v-if="proofUrl(item)"
          type="button"
          class="rounded-lg overflow-hidden border border-ink-200 mb-3 hover:border-brand-300 hover:ring-2 hover:ring-brand-500/20 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
          :aria-label="`View photo proof from ${item.user_name}`"
          @click.stop="openImagePreview(proofUrl(item), item.user_name)"
        >
          <img
            :src="proofUrl(item)"
            :alt="`Photo proof from ${item.user_name || 'community member'}`"
            class="h-16 w-16 object-cover"
            loading="lazy"
          />
        </button>

        <div class="flex flex-wrap gap-2" @click.stop>
          <button type="button" class="ml-btn-ghost text-sm" @click="toggleHidden(item)">
            {{ item.is_hidden ? 'Unhide' : 'Hide' }}
          </button>
          <button
            v-if="!item.reviewed_at"
            type="button"
            class="ml-btn-primary text-sm !bg-sky-600 hover:!bg-sky-700 focus:!ring-sky-500"
            @click="markReviewed(item)"
          >
            Mark Reviewed
          </button>
          <button
            v-if="canDeleteFeedback"
            type="button"
            class="ml-btn-danger text-sm"
            @click="requestDelete(item)"
          >
            Delete
          </button>
        </div>
      </article>
    </div>

    <FeedbackDetailModal
      v-model:open="detailOpen"
      :item="selectedItem"
      :can-delete-feedback="canDeleteFeedback"
      :can-publish-official-reply="canPublishOfficialReply"
      @toggle-hidden="toggleHidden"
      @mark-reviewed="markReviewed"
      @request-delete="requestDelete"
      @preview-image="openImagePreview"
      @save-reply-draft="saveReplyDraft"
      @publish-reply="publishReply"
      @close="selectedItem = null"
    />

    <ImageLightbox
      v-model:open="lightbox.open"
      :image-url="lightbox.url"
      :alt-text="lightbox.alt"
      :caption="lightbox.caption"
    />

    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-if="deleteConfirm.open"
          class="fixed inset-0 z-[120] flex items-center justify-center p-4"
          role="alertdialog"
          aria-modal="true"
          aria-labelledby="delete-feedback-title"
          @keydown.esc="cancelDelete"
        >
          <div class="absolute inset-0 bg-black/50" aria-hidden="true" @click="cancelDelete" />
          <div class="relative z-10 w-full max-w-md rounded-2xl border border-ink-200 bg-white p-6 shadow-2xl">
            <h3 id="delete-feedback-title" class="text-lg font-bold text-ink-900">Delete feedback?</h3>
            <p class="mt-2 text-sm text-ink-600">
              Permanently delete feedback #{{ deleteConfirm.item?.id }}? This cannot be undone.
            </p>
            <div class="mt-6 flex justify-end gap-2">
              <button type="button" class="ml-btn-ghost" @click="cancelDelete">Cancel</button>
              <button type="button" class="ml-btn-danger" :disabled="deleting" @click="confirmDelete">
                {{ deleting ? 'Deleting…' : 'Delete' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </section>
</template>

<script setup>
import { ref } from 'vue';
import { useToast } from 'vue-toastification';
import api from '../../../services/api';
import { resolveStorageUrl } from '../../../utils/imageUrl';
import { useManagementAccess } from '../../../composables/useManagementAccess';
import FeedbackDetailModal from '../../../components/management/FeedbackDetailModal.vue';
import ImageLightbox from '../../../components/management/ImageLightbox.vue';

const FILTERS = [
  { value: 'all', label: 'All' },
  { value: 'visible', label: 'Visible' },
  { value: 'hidden', label: 'Hidden' },
  { value: 'unreviewed', label: 'Unreviewed' },
  { value: 'reviewed', label: 'Reviewed' },
  { value: 'with_photo', label: 'With Photo' },
  { value: 'low_rating', label: 'Low Rating' },
];

const toast = useToast();
const { canDeleteFeedback, canPublishOfficialReply } = useManagementAccess();

const items = ref([]);
const loading = ref(false);
const hasLoaded = ref(false);
const loadError = ref(null);
const activeFilter = ref('all');
const detailOpen = ref(false);
const selectedItem = ref(null);
const lightbox = ref({ open: false, url: null, alt: 'Photo proof', caption: '' });
const deleteConfirm = ref({ open: false, item: null });
const deleting = ref(false);

const formatDate = (iso) => (iso ? new Date(iso).toLocaleDateString('en-GB') : '');
const proofUrl = (item) => resolveStorageUrl(item.proof_url || item.media_path || null);

const setFilter = async (filter) => {
  activeFilter.value = filter;
  await load();
};

const load = async () => {
  loading.value = true;
  loadError.value = null;
  try {
    const params = activeFilter.value !== 'all' ? { filter: activeFilter.value } : {};
    const { data } = await api.get('/staff/feedbacks', { params });
    items.value = Array.isArray(data) ? data : [];
    hasLoaded.value = true;

    if (selectedItem.value) {
      const updated = items.value.find((row) => row.id === selectedItem.value.id);
      if (updated) selectedItem.value = updated;
    }
  } catch (e) {
    loadError.value = e.forbiddenMessage || e.response?.data?.message || 'Unable to load feedback for moderation.';
    if (!e.forbiddenMessage) {
      toast.error(loadError.value);
    }
    throw e;
  } finally {
    loading.value = false;
  }
};

const openDetail = (item) => {
  selectedItem.value = item;
  detailOpen.value = true;
};

const openImagePreview = (url, caption = '') => {
  lightbox.value = {
    open: true,
    url,
    alt: `Photo proof from ${caption || 'community member'}`,
    caption: caption ? `Photo proof from ${caption}` : '',
  };
};

const toggleHidden = async (item) => {
  try {
    await api.put(`/feedbacks/${item.id}`, { is_hidden: !item.is_hidden });
    toast.success(item.is_hidden ? 'Review unhidden.' : 'Review hidden from public portal.');
    await load();
  } catch (e) {
    if (!e.forbiddenMessage) {
      toast.error(e.response?.data?.message || 'Unable to update visibility.');
    }
  }
};

const markReviewed = async (item) => {
  try {
    await api.post(`/feedbacks/${item.id}/reviewed`);
    toast.success('Feedback marked as reviewed.');
    await load();
  } catch (e) {
    if (!e.forbiddenMessage) {
      toast.error(e.response?.data?.message || 'Unable to mark as reviewed.');
    }
  }
};

const saveReplyDraft = async (item, text) => {
  try {
    await api.put(`/feedbacks/${item.id}/official-reply`, { official_reply_text: text });
    toast.success(text ? 'Official reply draft saved.' : 'Official reply removed.');
    await load();
  } catch (e) {
    if (!e.forbiddenMessage) {
      toast.error(e.response?.data?.message || 'Unable to save reply draft.');
    }
  }
};

const publishReply = async (item, text) => {
  try {
    await api.post(`/feedbacks/${item.id}/official-reply/publish`, { official_reply_text: text });
    toast.success('Official reply published.');
    await load();
  } catch (e) {
    if (!e.forbiddenMessage) {
      toast.error(e.response?.data?.message || 'Unable to publish reply.');
    }
  }
};

const requestDelete = (item) => {
  if (!canDeleteFeedback.value) return;
  deleteConfirm.value = { open: true, item };
};

const cancelDelete = () => {
  deleteConfirm.value = { open: false, item: null };
};

const confirmDelete = async () => {
  const id = deleteConfirm.value.item?.id;
  if (!id) return;

  deleting.value = true;
  try {
    await api.delete(`/feedbacks/${id}`);
    toast.success('Review deleted.');
    detailOpen.value = false;
    selectedItem.value = null;
    cancelDelete();
    await load();
  } catch (e) {
    if (!e.forbiddenMessage) {
      toast.error(e.response?.data?.message || 'Unable to delete feedback.');
    }
  } finally {
    deleting.value = false;
  }
};

defineExpose({ load });
</script>
