<template>
  <div class="space-y-4" data-testid="vendor-comments-panel">
    <div class="rounded-xl border border-sky-100 bg-white p-3">
      <h3 class="text-sm font-extrabold text-ink-900">Vendor Comments</h3>
      <p class="mt-0.5 text-xs text-ink-500">
        What vendors wrote, grouped by question · no sentiment scoring
        <template v-if="respondentCount != null"> · n = {{ respondentCount }}</template>
      </p>
      <div v-if="qualitative" class="mt-3 flex flex-wrap gap-3 text-xs text-ink-600">
        <span>
          Substantive comments:
          <strong class="text-ink-900">{{ qualitative.substantive_count ?? 0 }}</strong>
        </span>
        <span>
          Actionable suggestions:
          <strong class="text-ink-900">{{ qualitative.actionable_suggestion_count ?? 0 }}</strong>
        </span>
      </div>
      <div v-if="themeSummary.length" class="mt-2 flex flex-wrap gap-1.5">
        <span
          v-for="theme in themeSummary"
          :key="theme.label"
          class="rounded-md bg-sky-50 px-2 py-0.5 text-[11px] font-semibold text-brand-800"
        >
          {{ theme.label }} ({{ theme.count }})
        </span>
      </div>
    </div>

    <section
      v-for="group in commentGroups"
      :key="group.key"
      class="rounded-xl border border-sky-100 bg-white p-3"
    >
      <h4 class="text-sm font-extrabold text-ink-900">{{ group.label }}</h4>
      <p class="text-xs text-ink-500">Vendor Survey CSV</p>
      <ul v-if="group.items.length" class="mt-3 max-h-64 space-y-2 overflow-auto">
        <li
          v-for="(item, idx) in group.items"
          :key="`${group.key}-${idx}`"
          class="rounded-lg bg-ink-50/80 px-3 py-2 text-sm text-ink-800"
        >
          <p>{{ commentText(item) }}</p>
          <p v-if="commentSource(item)" class="mt-1 text-[11px] text-ink-500">
            {{ commentSource(item) }}
          </p>
        </li>
      </ul>
      <p v-else class="mt-3 text-sm text-ink-500">No substantive responses in this group.</p>
    </section>

    <p
      v-if="!hasAnySurveyComments"
      class="rounded-xl border border-dashed border-ink-200 bg-white px-3 py-6 text-center text-sm text-ink-500"
    >
      {{ surveyEmptyMessage }}
    </p>

    <!-- Separated sources -->
    <div class="grid gap-4 lg:grid-cols-2">
      <section class="rounded-xl border border-sky-100 bg-white p-3">
        <div class="mb-2 flex items-start justify-between gap-2">
          <div>
            <h4 class="text-sm font-extrabold text-ink-900">Community Feedback</h4>
            <p class="text-xs text-ink-500">Event-scoped reviews only · hidden feedback excluded</p>
          </div>
          <span v-if="feedbackLoading" class="text-xs text-ink-400">Loading…</span>
        </div>

        <p
          v-if="feedbackLinkReady === false"
          class="mb-2 rounded-lg border border-amber-200 bg-amber-50 px-2 py-1.5 text-xs text-amber-950"
        >
          Community feedback is not yet linked to individual events. Vendor survey comments above still apply.
        </p>

        <p v-if="feedbackError" class="text-sm text-rose-700">{{ feedbackError }}</p>
        <template v-else-if="feedbackTerms.length >= wordCloudThreshold">
          <div class="flex min-h-[120px] flex-wrap items-center justify-center gap-x-3 gap-y-2 rounded-lg bg-sky-50/60 p-3">
            <span
              v-for="term in feedbackTerms"
              :key="`fb-${term.text}`"
              class="font-semibold text-brand-700"
              :style="{ fontSize: `${termSize(term.weight, feedbackTerms)}px` }"
              :title="`${term.text}: ${term.weight}`"
            >
              {{ term.text }}
            </span>
          </div>
        </template>
        <p v-else class="py-6 text-center text-sm text-ink-500">
          {{ feedbackWordCloudMessage }}
        </p>
      </section>

      <section class="rounded-xl border border-sky-100 bg-white p-3">
        <div class="mb-2">
          <h4 class="text-sm font-extrabold text-ink-900">Vendor Product Descriptions</h4>
          <p class="text-xs text-ink-500">Approved booking product details for this event</p>
        </div>
        <p v-if="productsError" class="text-sm text-rose-700">{{ productsError }}</p>
        <template v-else-if="productTerms.length >= wordCloudThreshold">
          <div class="flex min-h-[120px] flex-wrap items-center justify-center gap-x-3 gap-y-2 rounded-lg bg-emerald-50/50 p-3">
            <span
              v-for="term in productTerms"
              :key="`pd-${term.text}`"
              class="font-semibold text-emerald-700"
              :style="{ fontSize: `${termSize(term.weight, productTerms)}px` }"
              :title="`${term.text}: ${term.weight}`"
            >
              {{ term.text }}
            </span>
          </div>
        </template>
        <p v-else class="py-6 text-center text-sm text-ink-500">
          {{
            productTerms.length
              ? 'More written responses are needed for a meaningful word cloud.'
              : 'No approved vendor product descriptions for this event yet.'
          }}
        </p>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { getEventWordcloud } from '../../services/eventAnalyticsApi';

const WORD_CLOUD_THRESHOLD = 5;

const props = defineProps({
  eventId: { type: [String, Number], required: true },
  qualitative: { type: Object, default: null },
  respondentCount: { type: Number, default: null },
  feedbackLinkReady: { type: Boolean, default: true },
  surveyStatus: { type: String, default: '' },
});

const wordCloudThreshold = WORD_CLOUD_THRESHOLD;
const feedbackLoading = ref(false);
const feedbackError = ref('');
const productsError = ref('');
const feedbackData = ref(null);
const productsData = ref(null);
const feedbackUnavailableReason = ref('');

const feedbackTerms = computed(() => feedbackData.value?.terms || []);
const productTerms = computed(() => productsData.value?.terms || []);

const themeSummary = computed(() =>
  (props.qualitative?.theme_summary || []).filter((t) => Number(t.count) > 0),
);

const groupsMap = computed(() => props.qualitative?.groups || {});

const commentGroups = computed(() => [
  {
    key: 'operational_difficulties',
    label: 'Operational difficulties',
    items: groupsMap.value.operational_difficulties || [],
  },
  {
    key: 'improvement_suggestions',
    label: 'Improvement suggestions',
    items: groupsMap.value.improvement_suggestions || [],
  },
  {
    key: 'general_comments',
    label: 'General comments',
    items: groupsMap.value.general_comments || [],
  },
  {
    key: 'supporting_activity_impacts',
    label: 'Supporting-activity impacts',
    items: groupsMap.value.supporting_activity_impacts || [],
  },
  {
    key: 'other_responses',
    label: 'Other responses',
    items: groupsMap.value.other_responses || [],
  },
]);

const hasAnySurveyComments = computed(() =>
  commentGroups.value.some((g) => g.items.length > 0),
);

const surveyEmptyMessage = computed(() => {
  if (props.surveyStatus === 'excluded') {
    return 'Survey comments are hidden because the current source mode excludes Survey CSV.';
  }
  if (props.surveyStatus === 'missing_source' || props.surveyStatus === 'empty') {
    return 'No CSV data is connected to this event.';
  }
  return 'No substantive vendor survey comments for this event.';
});

const feedbackWordCloudMessage = computed(() => {
  if (feedbackUnavailableReason.value) return feedbackUnavailableReason.value;
  if (props.feedbackLinkReady === false) {
    return 'Event-linked community feedback is not available yet.';
  }
  if (feedbackTerms.value.length) {
    return 'More written responses are needed for a meaningful word cloud.';
  }
  return 'No event-linked community feedback text for this event.';
});

const commentText = (item) => (typeof item === 'string' ? item : (item?.text || ''));
const commentSource = (item) => (typeof item === 'object' ? (item?.source_question || '') : '');

const termSize = (weight, terms) => {
  const weights = terms.map((t) => t.weight);
  const min = Math.min(...weights);
  const max = Math.max(...weights);
  if (max === min) return 18;
  const normalized = (weight - min) / (max - min);
  return Math.round(13 + normalized * 18);
};

const load = async () => {
  if (!props.eventId) return;
  feedbackLoading.value = true;
  feedbackError.value = '';
  productsError.value = '';
  feedbackUnavailableReason.value = '';
  feedbackData.value = null;
  productsData.value = null;

  try {
    const [feedbackRes, productsRes] = await Promise.allSettled([
      getEventWordcloud('feedback', props.eventId),
      getEventWordcloud('products', props.eventId),
    ]);

    if (feedbackRes.status === 'fulfilled') {
      feedbackData.value = feedbackRes.value.data;
    } else {
      const msg = feedbackRes.reason?.response?.data?.message
        || feedbackRes.reason?.response?.data?.detail
        || feedbackRes.reason?.message
        || '';
      if (/carboot_event_id|not available|event-scoped/i.test(msg)) {
        feedbackUnavailableReason.value = 'Community feedback is not linked to events in this environment.';
      } else {
        feedbackError.value = msg || 'Unable to load event-scoped feedback themes.';
      }
    }

    if (productsRes.status === 'fulfilled') {
      productsData.value = productsRes.value.data;
    } else {
      productsError.value = productsRes.reason?.response?.data?.message
        || 'Unable to load vendor product themes for this event.';
    }
  } finally {
    feedbackLoading.value = false;
  }
};

watch(() => props.eventId, () => load(), { immediate: true });

defineExpose({ load });
</script>
