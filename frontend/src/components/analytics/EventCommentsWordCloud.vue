<template>
  <div class="space-y-4" data-testid="event-comments-wordcloud">
    <div
      v-if="feedbackLinkReady === false"
      class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950"
    >
      Community feedback is not yet linked to individual events. Global word-cloud data is not shown here.
      Vendor survey comments below still reflect this event’s imported responses.
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
      <section class="rounded-xl border border-sky-100 bg-white p-3">
        <div class="mb-2 flex items-start justify-between gap-2">
          <div>
            <h4 class="text-sm font-extrabold text-ink-900">Community feedback themes</h4>
            <p class="text-xs text-ink-500">
              Event-scoped reviews only · hidden feedback excluded
            </p>
          </div>
          <span v-if="feedbackLoading" class="text-xs text-ink-400">Loading…</span>
        </div>

        <p v-if="feedbackError" class="text-sm text-rose-700">{{ feedbackError }}</p>
        <div
          v-else-if="feedbackTerms.length"
          class="flex min-h-[160px] flex-wrap items-center justify-center gap-x-3 gap-y-2 rounded-lg bg-sky-50/60 p-3"
        >
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
        <p v-else class="py-8 text-center text-sm text-ink-500">
          {{ feedbackEmptyMessage }}
        </p>
      </section>

      <section class="rounded-xl border border-sky-100 bg-white p-3">
        <div class="mb-2">
          <h4 class="text-sm font-extrabold text-ink-900">Vendor product themes</h4>
          <p class="text-xs text-ink-500">Approved booking product details for this event</p>
        </div>
        <p v-if="productsError" class="text-sm text-rose-700">{{ productsError }}</p>
        <div
          v-else-if="productTerms.length"
          class="flex min-h-[160px] flex-wrap items-center justify-center gap-x-3 gap-y-2 rounded-lg bg-emerald-50/50 p-3"
        >
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
        <p v-else class="py-8 text-center text-sm text-ink-500">
          No approved vendor product descriptions for this event yet.
        </p>
      </section>
    </div>

    <section class="rounded-xl border border-sky-100 bg-white p-3">
      <h4 class="text-sm font-extrabold text-ink-900">Survey comments & suggestions</h4>
      <p class="mt-1 text-xs text-ink-500">
        From imported vendor survey responses for this event
        <template v-if="respondentCount != null"> · n = {{ respondentCount }}</template>
      </p>
      <ul v-if="comments.length" class="mt-3 max-h-64 space-y-2 overflow-auto">
        <li
          v-for="(comment, idx) in comments"
          :key="idx"
          class="rounded-lg bg-ink-50/80 px-3 py-2 text-sm text-ink-800"
        >
          {{ comment }}
        </li>
      </ul>
      <p v-else class="mt-3 text-sm text-ink-500">
        No substantive survey comments imported for this event.
      </p>
    </section>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { getEventWordcloud } from '../../services/eventAnalyticsApi';

const props = defineProps({
  eventId: { type: [String, Number], required: true },
  comments: { type: Array, default: () => [] },
  respondentCount: { type: Number, default: null },
  feedbackLinkReady: { type: Boolean, default: true },
});

const feedbackLoading = ref(false);
const feedbackError = ref('');
const productsError = ref('');
const feedbackData = ref(null);
const productsData = ref(null);
const feedbackUnavailableReason = ref('');

const feedbackTerms = computed(() => feedbackData.value?.terms || []);
const productTerms = computed(() => productsData.value?.terms || []);

const feedbackEmptyMessage = computed(() => {
  if (feedbackUnavailableReason.value) return feedbackUnavailableReason.value;
  if (props.feedbackLinkReady === false) {
    return 'Event-linked community feedback is not available yet.';
  }
  return 'No event-linked community feedback text for this event.';
});

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
