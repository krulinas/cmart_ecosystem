<template>
  <div class="space-y-4" data-testid="vendor-comments-panel">
    <div class="rounded-xl border border-sky-100 bg-white p-3">
      <h3 class="text-sm font-extrabold text-ink-900">{{ t('organizer.analytics.comments.title') }}</h3>
      <p class="mt-0.5 text-xs text-ink-500">
        {{ t('organizer.analytics.comments.lead') }}
        <template v-if="respondentCount != null"> · n = {{ respondentCount }}</template>
      </p>
      <div v-if="qualitative" class="mt-3 flex flex-wrap gap-3 text-xs text-ink-600">
        <span>
          {{ t('organizer.analytics.comments.substantiveComments') }}
          <strong class="text-ink-900">{{ qualitative.substantive_count ?? 0 }}</strong>
        </span>
        <span>
          {{ t('organizer.analytics.comments.actionableSuggestions') }}
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
      <p class="text-xs text-ink-500">{{ t('organizer.analytics.comments.vendorSurveyCsv') }}</p>
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
      <p v-else class="mt-3 text-sm text-ink-500">{{ t('organizer.analytics.comments.noSubstantiveInGroup') }}</p>
    </section>

    <p
      v-if="!hasAnySurveyComments"
      class="rounded-xl border border-dashed border-ink-200 bg-white px-3 py-6 text-center text-sm text-ink-500"
    >
      {{ surveyEmptyMessage }}
    </p>

    <!-- Separated sources -->
    <div class="grid gap-4 lg:grid-cols-2">
      <section class="rounded-xl border border-sky-100 bg-white p-3" data-testid="wordcloud-feedback-section">
        <div class="mb-2 flex items-start justify-between gap-2">
          <div>
            <h4 class="text-sm font-extrabold text-ink-900">{{ t('organizer.analytics.comments.communityFeedback') }}</h4>
            <p class="text-xs text-ink-500">{{ t('organizer.analytics.comments.communityFeedbackHint') }}</p>
          </div>
          <span v-if="feedbackLoading" class="text-xs text-ink-400">{{ t('organizer.analytics.comments.loading') }}</span>
        </div>

        <p
          v-if="!systemIncluded"
          class="py-6 text-center text-sm text-ink-500"
          data-testid="wordcloud-feedback-excluded"
        >
          {{ t('organizer.analytics.comments.excludedBySourceMode') }}
        </p>
        <template v-else>
          <p
            v-if="feedbackLinkReady === false"
            class="mb-2 rounded-lg border border-amber-200 bg-amber-50 px-2 py-1.5 text-xs text-amber-950"
          >
            {{ t('organizer.analytics.comments.feedbackNotLinked') }}
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
        </template>
      </section>

      <section class="rounded-xl border border-sky-100 bg-white p-3" data-testid="wordcloud-products-section">
        <div class="mb-2">
          <h4 class="text-sm font-extrabold text-ink-900">{{ t('organizer.analytics.comments.productDescriptions') }}</h4>
          <p class="text-xs text-ink-500">{{ t('organizer.analytics.comments.productDescriptionsHint') }}</p>
        </div>
        <p
          v-if="!systemIncluded"
          class="py-6 text-center text-sm text-ink-500"
          data-testid="wordcloud-products-excluded"
        >
          {{ t('organizer.analytics.comments.excludedBySourceMode') }}
        </p>
        <template v-else>
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
                ? t('organizer.analytics.comments.needMoreResponses')
                : t('organizer.analytics.comments.noProductDescriptions')
            }}
          </p>
        </template>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { getEventWordcloud } from '../../services/eventAnalyticsApi';
import { fetchSystemWordcloudsIfIncluded } from '../../utils/eventWordcloudLoad.js';

const { t } = useI18n();

const WORD_CLOUD_THRESHOLD = 5;

const props = defineProps({
  eventId: { type: [String, Number], required: true },
  /** When false (e.g. csv_only), System Data wordcloud endpoints must not be requested. */
  systemIncluded: { type: Boolean, default: true },
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
  (props.qualitative?.theme_summary || []).filter((row) => Number(row.count) > 0),
);

const groupsMap = computed(() => props.qualitative?.groups || {});

const commentGroups = computed(() => [
  {
    key: 'operational_difficulties',
    label: t('organizer.analytics.comments.operationalDifficulties'),
    items: groupsMap.value.operational_difficulties || [],
  },
  {
    key: 'improvement_suggestions',
    label: t('organizer.analytics.comments.improvementSuggestions'),
    items: groupsMap.value.improvement_suggestions || [],
  },
  {
    key: 'general_comments',
    label: t('organizer.analytics.comments.generalComments'),
    items: groupsMap.value.general_comments || [],
  },
  {
    key: 'supporting_activity_impacts',
    label: t('organizer.analytics.comments.supportingActivityImpacts'),
    items: groupsMap.value.supporting_activity_impacts || [],
  },
  {
    key: 'other_responses',
    label: t('organizer.analytics.comments.otherResponses'),
    items: groupsMap.value.other_responses || [],
  },
]);

const hasAnySurveyComments = computed(() =>
  commentGroups.value.some((g) => g.items.length > 0),
);

const surveyEmptyMessage = computed(() => {
  if (props.surveyStatus === 'excluded') {
    return t('organizer.analytics.comments.hiddenByMode');
  }
  if (props.surveyStatus === 'missing_source' || props.surveyStatus === 'empty') {
    return t('organizer.analytics.comments.noCsvConnected');
  }
  return t('organizer.analytics.comments.noSubstantiveComments');
});

const feedbackWordCloudMessage = computed(() => {
  if (feedbackUnavailableReason.value) return feedbackUnavailableReason.value;
  if (props.feedbackLinkReady === false) {
    return t('organizer.analytics.comments.eventLinkedUnavailable');
  }
  if (feedbackTerms.value.length) {
    return t('organizer.analytics.comments.needMoreResponses');
  }
  return t('organizer.analytics.comments.noFeedbackText');
});

const commentText = (item) => (typeof item === 'string' ? item : (item?.text || ''));
const commentSource = (item) => (typeof item === 'object' ? (item?.source_question || '') : '');

const termSize = (weight, terms) => {
  const weights = terms.map((row) => row.weight);
  const min = Math.min(...weights);
  const max = Math.max(...weights);
  if (max === min) return 18;
  const normalized = (weight - min) / (max - min);
  return Math.round(13 + normalized * 18);
};

const clearSystemWordclouds = () => {
  feedbackData.value = null;
  productsData.value = null;
  feedbackError.value = '';
  productsError.value = '';
  feedbackUnavailableReason.value = '';
  feedbackLoading.value = false;
};

const load = async () => {
  if (!props.eventId) return;

  if (!props.systemIncluded) {
    clearSystemWordclouds();
    return;
  }

  feedbackLoading.value = true;
  feedbackError.value = '';
  productsError.value = '';
  feedbackUnavailableReason.value = '';
  feedbackData.value = null;
  productsData.value = null;

  try {
    const result = await fetchSystemWordcloudsIfIncluded({
      eventId: props.eventId,
      systemIncluded: props.systemIncluded,
      getWordcloud: getEventWordcloud,
    });

    if (!result.requested) {
      clearSystemWordclouds();
      return;
    }

    const feedbackRes = result.feedback;
    const productsRes = result.products;

    if (feedbackRes.status === 'fulfilled') {
      feedbackData.value = feedbackRes.value.data;
    } else {
      const msg = feedbackRes.reason?.response?.data?.message
        || feedbackRes.reason?.response?.data?.detail
        || feedbackRes.reason?.message
        || '';
      if (/carboot_event_id|not available|event-scoped/i.test(msg)) {
        feedbackUnavailableReason.value = t('organizer.analytics.comments.feedbackNotLinkedEnv');
      } else {
        feedbackError.value = msg || t('organizer.analytics.comments.unableLoadFeedback');
      }
    }

    if (productsRes.status === 'fulfilled') {
      productsData.value = productsRes.value.data;
    } else {
      productsError.value = productsRes.reason?.response?.data?.message
        || t('organizer.analytics.comments.unableLoadProducts');
    }
  } finally {
    feedbackLoading.value = false;
  }
};

watch(
  () => [props.eventId, props.systemIncluded],
  () => {
    load();
  },
  { immediate: true },
);

defineExpose({ load, clearSystemWordclouds, feedbackData, productsData });
</script>
