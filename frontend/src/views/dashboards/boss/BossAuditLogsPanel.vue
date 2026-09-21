<template>
  <section class="ml-card">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h2 class="text-lg font-extrabold text-ink-900">{{ t('audit.title') }}</h2>
        <p class="text-sm text-ink-500">{{ t('audit.lead') }}</p>
      </div>
      <button class="ml-btn-ghost" @click="load" :disabled="loading">{{ loading ? t('audit.loading') : t('audit.refresh') }}</button>
    </div>

    <div v-if="loading && !logs.length" class="text-center text-ink-500 py-10">{{ t('audit.loadingEntries') }}</div>
    <div v-else-if="!logs.length" class="text-center text-ink-500 py-10">{{ t('audit.empty') }}</div>

    <div v-else class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-xs uppercase tracking-wider text-ink-500 border-b border-ink-200">
            <th class="px-3 py-2">{{ t('audit.colWhen') }}</th>
            <th class="px-3 py-2">{{ t('audit.colActor') }}</th>
            <th class="px-3 py-2">{{ t('audit.colBooking') }}</th>
            <th class="px-3 py-2">{{ t('audit.colVendor') }}</th>
            <th class="px-3 py-2">{{ t('audit.colTransition') }}</th>
            <th class="px-3 py-2">{{ t('audit.colComment') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-ink-100">
          <tr v-for="entry in logs" :key="entry.id" class="hover:bg-ink-50/60">
            <td class="px-3 py-3 whitespace-nowrap text-ink-600">{{ formatDate(entry.created_at) }}</td>
            <td class="px-3 py-3">
              <div class="font-semibold text-ink-900">{{ entry.actor?.name || '—' }}</div>
              <div class="text-xs text-ink-500">{{ entry.actor?.role }}</div>
            </td>
            <td class="px-3 py-3 font-semibold">{{ formatBookingReference(entry.booking_id) }}</td>
            <td class="px-3 py-3">{{ entry.booking?.user?.name || '—' }}</td>
            <td class="px-3 py-3">
              <span class="text-ink-500">{{ statusLabel(entry.from_status, t) }}</span>
              →
              <span class="font-semibold">{{ statusLabel(entry.to_status, t) }}</span>
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
      >{{ t('audit.previous') }}</button>
      <span class="text-sm text-ink-500 self-center">{{ t('audit.pageOf', { current: pagination.current_page, last: pagination.last_page }) }}</span>
      <button
        class="ml-btn-ghost text-sm"
        :disabled="pagination.current_page >= pagination.last_page"
        @click="goPage(pagination.current_page + 1)"
      >{{ t('audit.next') }}</button>
    </div>
  </section>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from 'vue-toastification';
import { statusLabel } from '../../../i18n';
import api from '../../../services/api';
import { formatBookingReference } from '../../../utils/bookingDisplay';

const { t } = useI18n();
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
      toast.error(e.response?.data?.message || t('audit.toastLoadError'));
    }
  } finally {
    loading.value = false;
  }
};

const goPage = (page) => load(page);

defineExpose({ load });
</script>
