<template>
  <section class="space-y-6">
    <div v-if="loading" class="text-center text-ink-500 py-12">Loading text analytics…</div>

    <template v-else-if="!error">
      <p class="text-sm text-ink-500">
        Term frequencies from community feedback and approved vendor product listings.
        Larger words appear more often in the corpus.
      </p>

      <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="ml-card">
          <div class="flex items-start justify-between gap-3 mb-4">
            <div>
              <h3 class="text-lg font-extrabold text-ink-900">Community Feedback</h3>
              <p class="text-xs text-ink-500 mt-1">
                {{ feedbackData?.total_documents ?? 0 }} reviews ·
                {{ feedbackData?.unique_terms ?? 0 }} unique terms
              </p>
            </div>
            <span class="ml-badge bg-brand-100 text-brand-700 shrink-0">feedbacks.comments</span>
          </div>

          <div
            v-if="feedbackData?.terms?.length"
            class="flex flex-wrap gap-x-3 gap-y-2 justify-center items-center min-h-[220px] p-4 rounded-xl bg-ink-50"
          >
            <span
              v-for="term in feedbackData.terms"
              :key="`fb-${term.text}`"
              class="font-semibold text-brand-600 hover:text-brand-700 transition-colors cursor-default"
              :style="{ fontSize: `${termSize(term.weight, feedbackData.terms)}px` }"
              :title="`${term.text}: ${term.weight}`"
            >
              {{ term.text }}
            </span>
          </div>
          <p v-else class="text-sm text-ink-500 text-center py-12">
            No feedback text yet. Reviews will appear here once the community submits them.
          </p>
        </div>

        <div class="ml-card">
          <div class="flex items-start justify-between gap-3 mb-4">
            <div>
              <h3 class="text-lg font-extrabold text-ink-900">Vendor Products</h3>
              <p class="text-xs text-ink-500 mt-1">
                {{ productsData?.total_documents ?? 0 }} approved bookings ·
                {{ productsData?.unique_terms ?? 0 }} unique terms
              </p>
            </div>
            <span class="ml-badge bg-emerald-100 text-emerald-700 shrink-0">bookings.product_details</span>
          </div>

          <div
            v-if="productsData?.terms?.length"
            class="flex flex-wrap gap-x-3 gap-y-2 justify-center items-center min-h-[220px] p-4 rounded-xl bg-ink-50"
          >
            <span
              v-for="term in productsData.terms"
              :key="`pd-${term.text}`"
              class="font-semibold text-emerald-600 hover:text-emerald-700 transition-colors cursor-default"
              :style="{ fontSize: `${termSize(term.weight, productsData.terms)}px` }"
              :title="`${term.text}: ${term.weight}`"
            >
              {{ term.text }}
            </span>
          </div>
          <p v-else class="text-sm text-ink-500 text-center py-12">
            No approved vendor product descriptions yet.
          </p>
        </div>
      </div>

      <div v-if="topTerms.length" class="ml-card">
        <h3 class="text-lg font-extrabold text-ink-900 mb-4">Top Terms Comparison</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
          <div>
            <h4 class="font-semibold text-ink-700 mb-2">Feedback</h4>
            <ul class="space-y-1">
              <li
                v-for="term in feedbackTopFive"
                :key="`fb-top-${term.text}`"
                class="flex justify-between border-b border-ink-100 pb-1"
              >
                <span>{{ term.text }}</span>
                <span class="font-semibold text-brand-600">{{ term.weight }}</span>
              </li>
            </ul>
          </div>
          <div>
            <h4 class="font-semibold text-ink-700 mb-2">Products</h4>
            <ul class="space-y-1">
              <li
                v-for="term in productsTopFive"
                :key="`pd-top-${term.text}`"
                class="flex justify-between border-b border-ink-100 pb-1"
              >
                <span>{{ term.text }}</span>
                <span class="font-semibold text-emerald-600">{{ term.weight }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </template>

    <div v-else class="ml-card text-rose-600 text-sm">{{ error }}</div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useToast } from 'vue-toastification';
import api from '../../../services/api';

const toast = useToast();
const loading = ref(false);
const error = ref('');
const feedbackData = ref(null);
const productsData = ref(null);

const feedbackTopFive = computed(() => feedbackData.value?.terms?.slice(0, 5) ?? []);
const productsTopFive = computed(() => productsData.value?.terms?.slice(0, 5) ?? []);
const topTerms = computed(() => [...feedbackTopFive.value, ...productsTopFive.value]);

const termSize = (weight, terms) => {
  const weights = terms.map((t) => t.weight);
  const min = Math.min(...weights);
  const max = Math.max(...weights);
  if (max === min) return 20;
  const normalized = (weight - min) / (max - min);
  return Math.round(14 + normalized * 22);
};

const load = async () => {
  loading.value = true;
  error.value = '';
  try {
    const [feedbackRes, productsRes] = await Promise.all([
      api.get('/boss/analytics/wordcloud/feedback'),
      api.get('/boss/analytics/wordcloud/products'),
    ]);
    feedbackData.value = feedbackRes.data;
    productsData.value = productsRes.data;
  } catch (e) {
    error.value = e.forbiddenMessage || e.response?.data?.message || 'Unable to load text analytics.';
  } finally {
    loading.value = false;
  }
};

defineExpose({ load });
</script>
