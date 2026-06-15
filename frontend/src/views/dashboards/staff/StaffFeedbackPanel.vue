<template>
  <section class="ml-card">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h2 class="text-lg font-extrabold text-ink-900">Community Feedback Moderation</h2>
        <p class="text-sm text-ink-500">Includes hidden reviews. Public portal only shows visible entries.</p>
      </div>
      <button class="ml-btn-ghost" @click="load" :disabled="loading">{{ loading ? 'Loading…' : 'Refresh' }}</button>
    </div>

    <div v-if="loading && !hasLoaded" class="text-center text-ink-500 py-10">Loading feedback…</div>
    <div v-else-if="loadError" class="rounded-xl border border-rose-200 bg-rose-50/60 px-4 py-8 text-center text-sm text-rose-800">
      {{ loadError }}
    </div>
    <div v-else-if="!items.length" class="text-center text-ink-500 py-10">No feedback records found.</div>

    <div v-else class="space-y-4">
      <article
        v-for="item in items"
        :key="item.id"
        class="rounded-xl border p-4"
        :class="item.is_hidden ? 'border-rose-200 bg-rose-50/40' : 'border-ink-200 bg-white'"
      >
        <div class="flex flex-wrap justify-between gap-2 mb-2">
          <div>
            <span class="font-bold text-ink-900">{{ item.user?.name || 'Community Member' }}</span>
            <span v-if="item.reviewer_role" class="ml-2 text-xs font-semibold text-brand-700">{{ item.reviewer_role }}</span>
            <span v-if="item.is_hidden" class="ml-2 ml-badge bg-rose-100 text-rose-800">Hidden</span>
          </div>
          <div class="text-xs text-ink-500">#{{ item.id }} · {{ formatDate(item.created_at) }}</div>
        </div>
        <p class="text-sm text-ink-700 italic mb-3">"{{ item.comments }}"</p>
        <div class="flex gap-2">
          <button class="ml-btn-ghost" @click="toggleHidden(item)">
            {{ item.is_hidden ? 'Unhide' : 'Hide' }}
          </button>
          <button class="ml-btn-danger" @click="remove(item.id)">Delete</button>
        </div>
      </article>
    </div>
  </section>
</template>

<script setup>
import { ref } from 'vue';
import { useToast } from 'vue-toastification';
import api from '../../../services/api';

const toast = useToast();
const items = ref([]);
const loading = ref(false);
const hasLoaded = ref(false);
const loadError = ref(null);

const formatDate = (iso) => (iso ? new Date(iso).toLocaleDateString('en-GB') : '');

const load = async () => {
  loading.value = true;
  loadError.value = null;
  try {
    const { data } = await api.get('/staff/feedbacks');
    items.value = Array.isArray(data) ? data : [];
    hasLoaded.value = true;
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

const toggleHidden = async (item) => {
  await api.put(`/feedbacks/${item.id}`, { is_hidden: !item.is_hidden });
  toast.success(item.is_hidden ? 'Review unhidden.' : 'Review hidden from public portal.');
  await load();
};

const remove = async (id) => {
  if (!window.confirm('Permanently delete this review?')) return;
  await api.delete(`/feedbacks/${id}`);
  toast.success('Review deleted.');
  await load();
};

defineExpose({ load });
</script>
