<template>
  <section class="space-y-6" data-testid="management-reports-panel">
    <div v-if="loading" class="text-center text-ink-500 py-12">Loading generated report…</div>

    <template v-else-if="data">
      <div class="rounded-2xl border border-emerald-200 bg-gradient-to-r from-emerald-50 to-teal-50 px-5 py-4">
        <h2 class="text-sm font-bold text-emerald-950">Generated Operational Overview</h2>
        <p class="mt-2 text-sm leading-relaxed text-emerald-900/90">
          This report shows operational queue counts only. It does not include raw Carboot revenue analytics,
          word clouds, or audit trails reserved for Organizer roles.
        </p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="card in cards"
          :key="card.key"
          class="ml-card"
          :data-testid="`management-report-card-${card.key}`"
        >
          <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">{{ card.label }}</div>
          <div class="mt-1 text-2xl font-extrabold text-brand-600">{{ data[card.key] ?? 0 }}</div>
        </div>
      </div>
    </template>

    <div v-else class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-8 text-center text-sm text-rose-800">
      Unable to load the generated report. Please try again.
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import api from '../../../services/api';

const loading = ref(false);
const data = ref(null);

const cards = computed(() => [
  { key: 'pending_organizer_review', label: 'Pending Organizer Review' },
  { key: 'needs_revision', label: 'Needs Revision' },
  { key: 'payment_proofs_to_check', label: 'Payment Proofs To Check' },
  { key: 'upcoming_events', label: 'Upcoming Events' },
  { key: 'feedback_to_review', label: 'Feedback To Review' },
]);

const load = async () => {
  loading.value = true;
  try {
    const { data: payload } = await api.get('/management/reports/operational-overview');
    data.value = payload;
  } catch {
    data.value = null;
  } finally {
    loading.value = false;
  }
};

defineExpose({ load });
</script>
