<template>
  <section class="space-y-6" data-testid="cmart-report-centre">
    <div class="flex flex-wrap gap-2" role="tablist" aria-label="CMart reports">
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
      Loading reports workspace…
    </div>

    <template v-else>
      <!-- Request form -->
      <section v-show="activeTab === 'request'" class="ml-card space-y-4" data-testid="cmart-request-report">
        <div>
          <h2 class="text-lg font-extrabold text-ink-900">Request a report</h2>
          <p class="mt-1 text-sm text-ink-500">
            Submit a Post-Event Summary request to the Organizer. This creates an in-app request for the Organizer workspace.
            It does not send email or WhatsApp.
          </p>
        </div>
        <form class="space-y-4" @submit.prevent="submitRequest">
          <label class="block text-sm font-semibold text-ink-700">
            Event
            <select v-model="form.carboot_event_id" required class="ml-input mt-1 w-full">
              <option disabled value="">Select an event</option>
              <option v-for="event in events" :key="event.id" :value="event.id">
                {{ event.title }} ({{ formatDate(event.starts_at) }})
              </option>
            </select>
          </label>
          <label class="block text-sm font-semibold text-ink-700">
            Report type
            <select v-model="form.report_type" class="ml-input mt-1 w-full">
              <option value="post_event_summary">Post-Event Summary</option>
            </select>
          </label>
          <label class="block text-sm font-semibold text-ink-700">
            Message
            <textarea v-model="form.message" rows="4" maxlength="5000" class="ml-input mt-1 w-full" placeholder="Optional context for the Organizer" />
          </label>
          <label class="block text-sm font-semibold text-ink-700">
            Preferred due date (optional)
            <input v-model="form.preferred_due_date" type="date" class="ml-input mt-1 w-full" />
          </label>
          <button type="submit" class="ml-btn-primary" :disabled="submitting">
            {{ submitting ? 'Submitting…' : 'Submit request to Organizer' }}
          </button>
        </form>
      </section>

      <!-- My requests -->
      <section v-show="activeTab === 'requests'" class="space-y-3" data-testid="cmart-my-requests">
        <div v-if="!requests.length" class="rounded-2xl border border-dashed border-ink-200 bg-white px-5 py-12 text-center text-sm text-ink-500">
          No report requests yet.
        </div>
        <article
          v-for="item in requests"
          :key="item.id"
          class="rounded-2xl border border-ink-200 bg-white p-4 shadow-sm"
        >
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 class="font-bold text-ink-900">{{ item.event?.title || 'Event' }}</h3>
              <p class="text-xs text-ink-500">{{ item.report_type_label || item.report_type }} · {{ formatDate(item.created_at) }}</p>
              <p class="mt-2 text-sm text-ink-700">{{ item.message || 'No message provided.' }}</p>
              <p v-if="item.preferred_due_date" class="mt-1 text-xs text-ink-500">Preferred due: {{ item.preferred_due_date }}</p>
              <p v-if="item.decline_reason" class="mt-2 text-sm text-rose-700">Declined: {{ item.decline_reason }}</p>
              <p v-if="item.response_message" class="mt-1 text-sm text-ink-600">Organizer response: {{ item.response_message }}</p>
              <button type="button" class="mt-2 text-xs font-semibold text-cyan-800 underline" @click="openRequestDetail(item.id)">
                View timeline & notification activity
              </button>
            </div>
            <div class="flex flex-col items-end gap-2">
              <span class="rounded-full bg-ink-100 px-2.5 py-0.5 text-xs font-bold uppercase text-ink-700">{{ item.status }}</span>
              <button
                v-if="item.status === 'requested'"
                type="button"
                class="ml-btn-ghost text-sm"
                @click="cancelRequest(item.id)"
              >
                Cancel request
              </button>
            </div>
          </div>
        </article>
      </section>

      <!-- Published -->
      <section v-show="activeTab === 'published'" class="space-y-3" data-testid="cmart-published-reports">
        <div v-if="!published.length" class="rounded-2xl border border-dashed border-ink-200 bg-white px-5 py-12 text-center text-sm text-ink-500">
          No published reports are available yet.
        </div>
        <article
          v-for="item in published"
          :key="item.id"
          class="rounded-2xl border border-ink-200 bg-white p-4 shadow-sm"
        >
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 class="font-bold text-ink-900">{{ item.event_title_snapshot || 'Post-Event Summary' }}</h3>
              <p class="text-xs text-ink-500">
                Version {{ item.version }} · {{ item.status }} · {{ formatDate(item.published_at) }}
              </p>
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="ml-btn-primary text-sm" @click="openReport(item.id)">View</button>
              <button type="button" class="ml-btn-ghost text-sm" @click="downloadPdf(item.id)">Download PDF</button>
            </div>
          </div>
        </article>
      </section>

      <div v-if="requestDetail" class="rounded-2xl border border-ink-200 bg-white p-4 shadow-sm">
        <div class="mb-2 flex items-center justify-between gap-2">
          <h3 class="font-bold text-ink-900">Request timeline</h3>
          <button type="button" class="ml-btn-ghost text-sm" @click="requestDetail = null">Close</button>
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
          <button type="button" class="ml-btn-ghost text-sm" @click="activeReport = null">Back</button>
          <button type="button" class="ml-btn-ghost text-sm" @click="downloadPdf(activeReport.id)">Download PDF</button>
        </div>
        <PostEventSummaryView :report="activeReport" />
      </div>
    </template>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useToast } from 'vue-toastification';
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

const toast = useToast();
const tabs = [
  { id: 'request', label: 'Request Report' },
  { id: 'requests', label: 'My Requests' },
  { id: 'published', label: 'Published Reports' },
];

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
      toast.error(error.response?.data?.message || 'Unable to load reports workspace.');
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
    toast.success('Report request submitted to the Organizer.');
    form.value.message = '';
    form.value.preferred_due_date = '';
    activeTab.value = 'requests';
    await load();
  } catch (error) {
    toast.error(error.response?.data?.message || error.response?.data?.errors?.carboot_event_id?.[0] || 'Unable to submit request.');
  } finally {
    submitting.value = false;
  }
};

const cancelRequest = async (id) => {
  try {
    await cancelCmartReportRequest(id);
    toast.success('Request cancelled.');
    await load();
  } catch (error) {
    toast.error(error.response?.data?.message || 'Unable to cancel request.');
  }
};

const openRequestDetail = async (id) => {
  try {
    const { data } = await getCmartReportRequest(id);
    requestDetail.value = data.data || data.report_request || data;
  } catch (error) {
    toast.error(error.response?.data?.message || 'Unable to load request detail.');
  }
};

const openReport = async (id) => {
  try {
    const { data } = await getCmartGeneratedReport(id);
    activeReport.value = data.data || data;
    await markCmartGeneratedReportViewed(id);
    activeTab.value = 'published';
  } catch (error) {
    toast.error(error.response?.data?.message || 'Unable to open report.');
  }
};

const downloadPdf = async (id) => {
  try {
    await openAuthorizedPdf(cmartGeneratedReportPdfUrl(id));
  } catch {
    toast.error('Unable to download PDF.');
  }
};

onMounted(load);
defineExpose({ load });
</script>
