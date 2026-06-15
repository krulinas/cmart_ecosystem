<template>
  <section class="ml-card">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h2 class="text-lg font-extrabold text-ink-900">Staff Audit Log</h2>
        <p class="text-sm text-ink-500">Approval and rejection actions performed by CMart staff and admin.</p>
      </div>
      <button class="ml-btn-ghost" @click="load" :disabled="loading">{{ loading ? 'Loading…' : 'Refresh' }}</button>
    </div>

    <div v-if="loading && !logs.length" class="text-center text-ink-500 py-10">Loading audit entries…</div>
    <div v-else-if="!logs.length" class="text-center text-ink-500 py-10">No audit records yet.</div>

    <div v-else class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-xs uppercase tracking-wider text-ink-500 border-b border-ink-200">
            <th class="px-3 py-2">When</th>
            <th class="px-3 py-2">Actor</th>
            <th class="px-3 py-2">Booking</th>
            <th class="px-3 py-2">Vendor</th>
            <th class="px-3 py-2">Transition</th>
            <th class="px-3 py-2">Comment</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-ink-100">
          <tr v-for="entry in logs" :key="entry.id" class="hover:bg-ink-50/60">
            <td class="px-3 py-3 whitespace-nowrap text-ink-600">{{ formatDate(entry.created_at) }}</td>
            <td class="px-3 py-3">
              <div class="font-semibold text-ink-900">{{ entry.actor?.name || '—' }}</div>
              <div class="text-xs text-ink-500">{{ entry.actor?.role }}</div>
            </td>
            <td class="px-3 py-3 font-semibold">#{{ entry.booking_id }}</td>
            <td class="px-3 py-3">{{ entry.booking?.user?.name || '—' }}</td>
            <td class="px-3 py-3">
              <span class="text-ink-500">{{ entry.from_status }}</span>
              →
              <span class="font-semibold">{{ entry.to_status }}</span>
            </td>
            <td class="px-3 py-3 text-ink-600 max-w-xs truncate" :title="entry.revision_comment || ''">
              {{ entry.revision_comment || '—' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="pagination.last_page > 1" class="mt-4 flex justify-center gap-2">
      <button
        class="ml-btn-ghost text-sm"
        :disabled="pagination.current_page <= 1"
        @click="goPage(pagination.current_page - 1)"
      >Previous</button>
      <span class="text-sm text-ink-500 self-center">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
      <button
        class="ml-btn-ghost text-sm"
        :disabled="pagination.current_page >= pagination.last_page"
        @click="goPage(pagination.current_page + 1)"
      >Next</button>
    </div>
  </section>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useToast } from 'vue-toastification';
import api from '../../../services/api';

const toast = useToast();
const loading = ref(false);
const logs = ref([]);
const pagination = reactive({
  current_page: 1,
  last_page: 1,
});

const formatDate = (iso) => {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const load = async (page = 1) => {
  loading.value = true;
  try {
    const { data } = await api.get('/boss/audit-logs', { params: { page, per_page: 25 } });
    logs.value = data.data ?? [];
    pagination.current_page = data.current_page ?? 1;
    pagination.last_page = data.last_page ?? 1;
  } catch (e) {
    if (!e.forbiddenMessage) {
      toast.error(e.response?.data?.message || 'Unable to load audit logs.');
    }
  } finally {
    loading.value = false;
  }
};

const goPage = (page) => load(page);

defineExpose({ load });
</script>
