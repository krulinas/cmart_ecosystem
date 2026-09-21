<template>
  <section class="space-y-6" data-testid="cmart-report-centre">
    <div class="flex flex-wrap gap-2" role="tablist" :aria-label="t('reports.cmart.tablistAria')">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        role="tab"
        class="rounded-full px-4 py-2 text-sm font-semibold transition"
        :class="activeTab === tab.id ? 'bg-cyan-600 text-white shadow-sm' : 'bg-white text-ink-600 ring-1 ring-ink-200'"
        :aria-selected="activeTab === tab.id"
        @click="activeTab = tab.id"
      >
        {{ tab.label }}
        <span
          v-if="tab.id === 'published' && unreadPublishedHint"
          class="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-400 px-1 text-[10px] font-bold text-amber-950"
        >!</span>
      </button>
    </div>

    <div v-if="loading" class="rounded-2xl border border-ink-200 bg-white py-16 text-center text-sm text-ink-500">
      {{ t('reports.cmart.loading') }}
    </div>

    <template v-else>
      <!-- Request form -->
      <section v-show="activeTab === 'request'" class="ml-card space-y-4" data-testid="cmart-request-report">
        <div>
          <h2 class="text-lg font-extrabold text-ink-900">{{ t('reports.cmart.requestTitle') }}</h2>
          <p class="mt-1 text-sm text-ink-500">
            {{ t('reports.cmart.requestLead') }}
          </p>
        </div>
        <form class="space-y-4" @submit.prevent="submitRequest">
          <label class="block text-sm font-semibold text-ink-700">
            {{ t('reports.cmart.eventLabel') }}
            <select v-model="form.carboot_event_id" required class="ml-input mt-1 w-full">
              <option disabled value="">{{ t('reports.cmart.selectEvent') }}</option>
              <option v-for="event in events" :key="event.id" :value="event.id">
                {{ event.title }} ({{ formatDate(event.starts_at) }})
              </option>
            </select>
          </label>
          <label class="block text-sm font-semibold text-ink-700">
            {{ t('reports.cmart.reportTypeLabel') }}
            <select v-model="form.report_type" class="ml-input mt-1 w-full">
              <option value="post_event_summary">{{ t('reports.cmart.reportTypePostEvent') }}</option>
            </select>
          </label>
          <label class="block text-sm font-semibold text-ink-700">
            {{ t('reports.cmart.messageLabel') }}
            <textarea v-model="form.message" rows="4" maxlength="5000" class="ml-input mt-1 w-full" :placeholder="t('reports.cmart.messagePlaceholder')" />
          </label>
          <label class="block text-sm font-semibold text-ink-700">
            {{ t('reports.cmart.preferredDueLabel') }}
            <input v-model="form.preferred_due_date" type="date" class="ml-input mt-1 w-full" />
          </label>
          <button type="submit" class="ml-btn-primary" :disabled="submitting">
            {{ submitting ? t('reports.cmart.submitting') : t('reports.cmart.submit') }}
          </button>
        </form>
      </section>

      <!-- My requests -->
      <section v-show="activeTab === 'requests'" class="space-y-3" data-testid="cmart-my-requests">
        <div v-if="!requests.length" class="rounded-2xl border border-dashed border-ink-200 bg-white px-5 py-12 text-center text-sm text-ink-500">
          {{ t('reports.cmart.emptyRequests') }}
        </div>
        <article
          v-for="item in requests"
          :key="item.id"
          class="rounded-2xl border border-ink-200 bg-white p-4 shadow-sm"
        >
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 class="font-bold text-ink-900">{{ item.event?.title || t('reports.cmart.eventFallback') }}</h3>
              <p class="text-xs text-ink-500">{{ item.report_type_label || item.report_type }} · {{ formatDate(item.created_at) }}</p>
              <p class="mt-2 text-sm text-ink-700">{{ item.message || t('reports.cmart.noMessage') }}</p>
              <p v-if="item.preferred_due_date" class="mt-1 text-xs text-ink-500">{{ t('reports.cmart.preferredDue', { date: item.preferred_due_date }) }}</p>
              <p v-if="item.decline_reason" class="mt-2 text-sm text-rose-700">{{ t('reports.cmart.declinedPrefix', { reason: item.decline_reason }) }}</p>
              <p v-if="item.response_message" class="mt-1 text-sm text-ink-600">{{ t('reports.cmart.organizerResponsePrefix', { message: item.response_message }) }}</p>
              <button type="button" class="mt-2 text-xs font-semibold text-cyan-800 underline" @click="openRequestDetail(item.id)">
                {{ t('reports.cmart.viewTimeline') }}
              </button>
            </div>
            <div class="flex flex-col items-end gap-2">
              <span class="rounded-full bg-ink-100 px-2.5 py-0.5 text-xs font-bold uppercase text-ink-700">{{ statusLabel(item.status, t) }}</span>
              <button
                v-if="item.status === 'requested'"
                type="button"
                class="ml-btn-ghost text-sm"
                @click="cancelRequest(item.id)"
              >
                {{ t('reports.cmart.cancelRequest') }}
              </button>
            </div>
          </div>
        </article>
      </section>

      <!-- Published -->
      <section v-show="activeTab === 'published'" class="space-y-3" data-testid="cmart-published-reports">
        <div v-if="!published.length" class="rounded-2xl border border-dashed border-ink-200 bg-white px-5 py-12 text-center text-sm text-ink-500">
          {{ t('reports.cmart.emptyPublished') }}
        </div>
        <article
          v-for="item in published"
          :key="item.id"
          class="rounded-2xl border border-ink-200 bg-white p-4 shadow-sm"
        >
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 class="font-bold text-ink-900">{{ item.event_title_snapshot || t('reports.cmart.reportFallback') }}</h3>
              <p class="text-xs text-ink-500">
                {{ t('reports.cmart.version', { n: item.version }) }} · {{ statusLabel(item.status, t) }} · {{ formatDate(item.published_at) }}
              </p>
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="ml-btn-primary text-sm" @click="openReport(item.id)">{{ t('reports.cmart.view') }}</button>
              <button type="button" class="ml-btn-ghost text-sm" @click="downloadPdf(item.id)">{{ t('reports.cmart.downloadPdf') }}</button>
            </div>
          </div>
        </article>
      </section>

      <div v-if="requestDetail" class="rounded-2xl border border-ink-200 bg-white p-4 shadow-sm">
        <div class="mb-2 flex items-center justify-between gap-2">
          <h3 class="font-bold text-ink-900">{{ t('reports.cmart.requestTimeline') }}</h3>
          <button type="button" class="ml-btn-ghost text-sm" @click="requestDetail = null">{{ t('reports.cmart.close') }}</button>
        </div>
        <ul class="space-y-1 text-sm text-ink-700">
          <li v-for="row in (requestDetail.timeline || [])" :key="row.id">
            {{ row.label }}
            <span v-if="row.created_at" class="text-xs text-ink-400"> · {{ formatDate(row.created_at) }}</span>
          </li>
        </ul>
        <ReportNotificationActivity :items="requestDetail.notification_activity || []" />
      </div>

      <div v-if="activeReport" class="rounded-2xl border border-ink-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex flex-wrap gap-2">
          <button type="button" class="ml-btn-ghost text-sm" @click="activeReport = null">{{ t('reports.cmart.back') }}</button>
          <button type="button" class="ml-btn-ghost text-sm" @click="downloadPdf(activeReport.id)">{{ t('reports.cmart.downloadPdf') }}</button>
        </div>
        <PostEventSummaryView :report="activeReport" />
      </div>
    </template>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from 'vue-toastification';
import { statusLabel } from '../../../i18n';
import PostEventSummaryView from '../../../components/reports/PostEventSummaryView.vue';
import ReportNotificationActivity from '../../../components/reports/ReportNotificationActivity.vue';
import {
  REPORT_TYPE_POST_EVENT,
  cancelCmartReportRequest,
  cmartGeneratedReportPdfUrl,
  createCmartReportRequest,
  getCmartGeneratedReport,
  getCmartReportRequest,
  listCmartGeneratedReports,
  listCmartReportEvents,
  listCmartReportRequests,
  markCmartGeneratedReportViewed,
  openAuthorizedPdf,
} from '../../../services/reportWorkflowApi';

const { t } = useI18n();
const toast = useToast();
const tabs = computed(() => [
  { id: 'request', label: t('reports.cmart.tabs.request') },
  { id: 'requests', label: t('reports.cmart.tabs.requests') },
  { id: 'published', label: t('reports.cmart.tabs.published') },
]);

const activeTab = ref('request');
const loading = ref(false);
const submitting = ref(false);
const events = ref([]);
const requests = ref([]);
const published = ref([]);
const activeReport = ref(null);
const unreadPublishedHint = ref(false);
const requestDetail = ref(null);

const form = ref({
  carboot_event_id: '',
  report_type: REPORT_TYPE_POST_EVENT,
  message: '',
  preferred_due_date: '',
});

const formatDate = (value) => {
  if (!value) return '—';
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
};

const unwrapList = (payload) => {
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload?.data)) return payload.data;
  if (Array.isArray(payload?.events)) return payload.events;
  return [];
};

const load = async () => {
  loading.value = true;
  try {
    const [eventsRes, requestsRes, publishedRes] = await Promise.all([
      listCmartReportEvents(),
      listCmartReportRequests(),
      listCmartGeneratedReports(),
    ]);
    events.value = unwrapList(eventsRes.data?.events || eventsRes.data);
    requests.value = unwrapList(requestsRes.data);
    published.value = unwrapList(publishedRes.data);
    unreadPublishedHint.value = published.value.some((row) => row.status === 'published');
  } catch (error) {
    if (!error.forbiddenMessage) {
      toast.error(error.response?.data?.message || t('reports.cmart.toastLoadError'));
    }
  } finally {
    loading.value = false;
  }
};

const submitRequest = async () => {
  submitting.value = true;
  try {
    await createCmartReportRequest({
      carboot_event_id: Number(form.value.carboot_event_id),
      report_type: form.value.report_type,
      message: form.value.message || null,
      preferred_due_date: form.value.preferred_due_date || null,
    });
    toast.success(t('reports.cmart.toastSubmitted'));
    form.value.message = '';
    form.value.preferred_due_date = '';
    activeTab.value = 'requests';
    await load();
  } catch (error) {
    toast.error(error.response?.data?.message || error.response?.data?.errors?.carboot_event_id?.[0] || t('reports.cmart.toastSubmitError'));
  } finally {
    submitting.value = false;
  }
};

const cancelRequest = async (id) => {
  try {
    await cancelCmartReportRequest(id);
    toast.success(t('reports.cmart.toastCancelled'));
    await load();
  } catch (error) {
    toast.error(error.response?.data?.message || t('reports.cmart.toastCancelError'));
  }
};

const openRequestDetail = async (id) => {
  try {
    const { data } = await getCmartReportRequest(id);
    requestDetail.value = data.data || data.report_request || data;
  } catch (error) {
    toast.error(error.response?.data?.message || t('reports.cmart.toastRequestDetailError'));
  }
};

const openReport = async (id) => {
  try {
    const { data } = await getCmartGeneratedReport(id);
    activeReport.value = data.data || data;
    await markCmartGeneratedReportViewed(id);
    activeTab.value = 'published';
  } catch (error) {
    toast.error(error.response?.data?.message || t('reports.cmart.toastOpenError'));
  }
};

const downloadPdf = async (id) => {
  try {
    await openAuthorizedPdf(cmartGeneratedReportPdfUrl(id));
  } catch {
    toast.error(t('reports.cmart.toastPdfError'));
  }
};

onMounted(load);
defineExpose({ load });
</script>
