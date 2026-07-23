<template>
  <article class="post-event-summary space-y-6 print:space-y-4" data-testid="post-event-summary-view">
    <header class="border-b border-ink-200 pb-4 print:border-ink-400">
      <p class="text-xs font-bold uppercase tracking-wider text-blue-800">{{ reportTypeLabel }}</p>
      <h2 class="mt-1 text-2xl font-extrabold text-ink-900">
        {{ report.event_title_snapshot || snapshot?.event?.title || 'Event report' }}
      </h2>
      <div class="mt-2 flex flex-wrap gap-2 text-xs text-ink-600">
        <span class="rounded-full bg-ink-100 px-2.5 py-0.5 font-semibold">Version {{ report.version }}</span>
        <span class="rounded-full px-2.5 py-0.5 font-semibold ring-1" :class="statusClass">{{ report.status }}</span>
        <span v-if="snapshot?.provisional" class="rounded-full bg-amber-50 px-2.5 py-0.5 font-semibold text-amber-900 ring-1 ring-amber-200">
          Provisional draft
        </span>
      </div>
      <dl class="mt-3 grid gap-2 text-sm text-ink-700 sm:grid-cols-2">
        <div><dt class="text-xs uppercase text-ink-400">Venue</dt><dd>{{ snapshot?.event?.venue || snapshot?.venue || '—' }}</dd></div>
        <div><dt class="text-xs uppercase text-ink-400">Event dates</dt><dd>{{ dateRange }}</dd></div>
        <div v-if="report.published_at"><dt class="text-xs uppercase text-ink-400">Published</dt><dd>{{ formatDate(report.published_at) }}</dd></div>
        <div v-if="publisherName"><dt class="text-xs uppercase text-ink-400">Published by</dt><dd>{{ publisherName }}</dd></div>
        <div v-if="report.revision_reason"><dt class="text-xs uppercase text-ink-400">Revision reason</dt><dd>{{ report.revision_reason }}</dd></div>
      </dl>
    </header>

    <section v-if="participation" class="rounded-2xl border border-ink-100 bg-white p-4 print:border-ink-300">
      <h3 class="text-sm font-bold uppercase tracking-wider text-ink-500">Participation summary</h3>
      <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div v-for="item in participationCards" :key="item.label" class="rounded-xl bg-ink-50 p-3">
          <div class="text-xs text-ink-500">{{ item.label }}</div>
          <div class="text-xl font-extrabold text-ink-900">{{ item.value }}</div>
        </div>
      </div>
    </section>

    <section v-if="sites" class="rounded-2xl border border-ink-100 bg-white p-4 print:border-ink-300">
      <h3 class="text-sm font-bold uppercase tracking-wider text-ink-500">Site utilization</h3>
      <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div v-for="item in siteCards" :key="item.label" class="rounded-xl bg-ink-50 p-3">
          <div class="text-xs text-ink-500">{{ item.label }}</div>
          <div class="text-xl font-extrabold text-ink-900">{{ item.value }}</div>
        </div>
      </div>
    </section>

    <section v-if="payments" class="rounded-2xl border border-ink-100 bg-white p-4 print:border-ink-300">
      <h3 class="text-sm font-bold uppercase tracking-wider text-ink-500">Financial summary</h3>
      <p class="mt-1 text-xs text-ink-500">Invoice-based totals for approved bookings. Expected ≠ collected unless payment status is Paid.</p>
      <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-xl bg-blue-50 p-3">
          <div class="text-xs text-blue-700">Expected</div>
          <div class="text-xl font-extrabold text-blue-950">RM {{ money(payments.expected) }}</div>
        </div>
        <div class="rounded-xl bg-emerald-50 p-3">
          <div class="text-xs text-emerald-700">Collected (Paid)</div>
          <div class="text-xl font-extrabold text-emerald-900">RM {{ money(payments.collected) }}</div>
        </div>
        <div class="rounded-xl bg-amber-50 p-3">
          <div class="text-xs text-amber-800">Outstanding (Unpaid)</div>
          <div class="text-xl font-extrabold text-amber-950">RM {{ money(payments.outstanding) }}</div>
        </div>
      </div>
    </section>

    <section v-if="categories.length" class="rounded-2xl border border-ink-100 bg-white p-4 print:border-ink-300">
      <h3 class="text-sm font-bold uppercase tracking-wider text-ink-500">Vendor category distribution</h3>
      <ul class="mt-3 space-y-1 text-sm text-ink-700">
        <li v-for="row in categories" :key="row.label" class="flex justify-between gap-4 border-b border-ink-50 py-1.5">
          <span>{{ row.label }}</span>
          <span class="font-semibold">{{ row.count }}</span>
        </li>
      </ul>
    </section>

    <section v-if="reservations" class="rounded-2xl border border-ink-100 bg-white p-4 print:border-ink-300">
      <h3 class="text-sm font-bold uppercase tracking-wider text-ink-500">Marketplace / reservation activity</h3>
      <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
        <div v-for="item in reservationCards" :key="item.label" class="rounded-xl bg-ink-50 p-3">
          <div class="text-xs text-ink-500">{{ item.label }}</div>
          <div class="text-xl font-extrabold text-ink-900">{{ item.value }}</div>
        </div>
      </div>
    </section>

    <section v-if="feedback" class="rounded-2xl border border-ink-100 bg-white p-4 print:border-ink-300">
      <h3 class="text-sm font-bold uppercase tracking-wider text-ink-500">Community feedback</h3>
      <p v-if="feedback.available === false" class="mt-2 text-sm text-ink-500">
        Feedback aggregates are not event-scoped in the current schema and are omitted from this official snapshot.
      </p>
      <template v-else>
        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
          <div class="rounded-xl bg-ink-50 p-3">
            <div class="text-xs text-ink-500">Submissions</div>
            <div class="text-xl font-extrabold text-ink-900">{{ feedback.response_count ?? feedback.count ?? 0 }}</div>
          </div>
          <div class="rounded-xl bg-ink-50 p-3">
            <div class="text-xs text-ink-500">Average rating</div>
            <div class="text-xl font-extrabold text-ink-900">{{ feedback.average_rating ?? '—' }}</div>
          </div>
        </div>
      </template>
    </section>

    <section v-if="report.organizer_observations" class="rounded-2xl border border-ink-100 bg-white p-4 print:border-ink-300">
      <h3 class="text-sm font-bold uppercase tracking-wider text-ink-500">Organizer observations</h3>
      <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-ink-800">{{ report.organizer_observations }}</p>
    </section>

    <section v-if="report.organizer_recommendations" class="rounded-2xl border border-ink-100 bg-white p-4 print:border-ink-300">
      <h3 class="text-sm font-bold uppercase tracking-wider text-ink-500">Organizer recommendations</h3>
      <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-ink-800">{{ report.organizer_recommendations }}</p>
    </section>

    <section v-if="availabilityNotes.length" class="rounded-2xl border border-amber-100 bg-amber-50/50 p-4 print:border-ink-300">
      <h3 class="text-sm font-bold uppercase tracking-wider text-amber-900">Data availability notes</h3>
      <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-950">
        <li v-for="note in availabilityNotes" :key="note">{{ note }}</li>
      </ul>
    </section>
  </article>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  report: { type: Object, required: true },
});

const snapshot = computed(() => props.report?.snapshot || {});
const reportTypeLabel = computed(() => props.report?.report_type_label || 'Post-Event Summary');
const publisherName = computed(() => props.report?.published_by?.name || null);
const participation = computed(() => snapshot.value?.sections?.booking_pipeline || null);
const payments = computed(() => snapshot.value?.sections?.payments || null);
const sites = computed(() => snapshot.value?.sections?.event_sites || null);
const reservations = computed(() => snapshot.value?.sections?.item_reservations || null);
const feedback = computed(() => snapshot.value?.sections?.feedback || null);

const statusClass = computed(() => {
  const status = props.report?.status;
  if (status === 'published') return 'bg-emerald-50 text-emerald-900 ring-emerald-200';
  if (status === 'superseded') return 'bg-ink-100 text-ink-700 ring-ink-200';
  return 'bg-blue-50 text-blue-900 ring-blue-200';
});

const dateRange = computed(() => {
  const start = props.report.event_starts_at_snapshot || snapshot.value?.event?.starts_at;
  const end = props.report.event_ends_at_snapshot || snapshot.value?.event?.ends_at;
  if (!start && !end) return '—';
  return `${formatDate(start)} – ${formatDate(end)}`;
});

const participationCards = computed(() => {
  const p = participation.value;
  if (!p) return [];
  const by = p.by_approval_status || {};
  return [
    { label: 'Total bookings', value: p.total_bookings ?? 0 },
    { label: 'Approved', value: by.Approved ?? p.approved_count ?? 0 },
    { label: 'Rejected', value: by.Rejected ?? 0 },
    { label: 'Withdrawn', value: by.Withdrawn ?? 0 },
    { label: 'Cancelled', value: by.Cancelled ?? 0 },
    { label: 'Needs revision', value: by.Needs_Revision ?? 0 },
  ];
});

const siteCards = computed(() => {
  const s = sites.value;
  if (!s || s.available === false) return [];
  const by = s.by_operational_status || {};
  const active = (by.active ?? by.Active ?? by.available ?? 0);
  return [
    { label: 'Total sites', value: s.total ?? s.total_sites ?? 0 },
    { label: 'Active (status map)', value: active },
    { label: 'Status groups', value: Object.keys(by).length },
  ];
});

const reservationCards = computed(() => {
  const r = reservations.value;
  if (!r) return [];
  const cards = [{ label: 'Total reservations', value: r.total ?? 0 }];
  const by = r.by_reservation_status || {};
  Object.entries(by).forEach(([key, value]) => {
    cards.push({ label: key.replaceAll('_', ' '), value });
  });
  return cards;
});

const categories = computed(() => {
  const dist = snapshot.value?.sections?.vendor_categories?.distribution;
  if (!dist) return [];
  if (Array.isArray(dist)) {
    return dist.map((row) => ({
      label: row.label || row.category || 'Unspecified',
      count: row.count ?? 0,
    }));
  }
  return Object.entries(dist).map(([label, count]) => ({ label, count }));
});

const availabilityNotes = computed(() => {
  const raw = snapshot.value?.data_availability || {};
  return Object.values(raw).filter(Boolean);
});

const money = (value) => Number(value || 0).toFixed(2);

const formatDate = (value) => {
  if (!value) return '—';
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
};
</script>
