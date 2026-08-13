<template>
  <section class="space-y-6" data-testid="organizer-report-centre">
    <div class="flex flex-wrap gap-2" role="tablist" aria-label="Organizer report centre">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        role="tab"
        class="rounded-full px-4 py-2 text-sm font-semibold transition"
        :class="activeTab === tab.id ? 'bg-blue-700 text-white shadow-sm' : 'bg-white text-ink-600 ring-1 ring-ink-200'"
        :aria-selected="activeTab === tab.id"
        @click="activeTab = tab.id"
      >
        {{ tab.label }}
        <span
          v-if="tab.id === 'requests' && actionRequiredCount"
          class="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-400 px-1.5 text-[10px] font-bold text-amber-950"
        >{{ actionRequiredCount }}</span>
      </button>
    </div>

    <div v-if="loading" class="rounded-2xl border border-ink-200 bg-white py-16 text-center text-sm text-ink-500">
      Loading report centre…
    </div>

    <template v-else>
      <!-- Requests -->
      <section v-show="activeTab === 'requests'" class="space-y-3">
        <div v-if="!requests.length" class="rounded-2xl border border-dashed border-ink-200 bg-white px-5 py-12 text-center text-sm text-ink-500">
          No CMart report requests yet.
        </div>
        <article v-for="item in requests" :key="item.id" class="rounded-2xl border border-ink-200 bg-white p-4 shadow-sm">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
              <h3 class="font-bold text-ink-900">{{ item.event?.title || 'Event' }}</h3>
              <p class="text-xs text-ink-500">
                {{ item.requester?.name || 'CMart Management' }} · {{ item.report_type_label || item.report_type }} · {{ formatDate(item.created_at) }}
              </p>
              <p class="mt-2 text-sm text-ink-700">{{ item.message || 'No message.' }}</p>
              <p v-if="item.preferred_due_date" class="mt-1 text-xs text-ink-500">Preferred due: {{ item.preferred_due_date }}</p>
            </div>
            <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold uppercase text-blue-900 ring-1 ring-blue-200">{{ item.status }}</span>
          </div>
          <div class="mt-3 flex flex-wrap gap-2">
            <button
              v-if="['requested'].includes(item.status)"
              type="button"
              class="ml-btn-ghost text-sm"
              @click="runRequestAction('acknowledge', item.id)"
            >Acknowledge</button>
            <button
              v-if="['requested', 'acknowledged'].includes(item.status)"
              type="button"
              class="ml-btn-ghost text-sm"
              @click="runRequestAction('start', item.id)"
            >Start preparation</button>
            <button
              v-if="['requested', 'acknowledged', 'in_progress'].includes(item.status)"
              type="button"
              class="ml-btn-primary text-sm"
              @click="generateFromRequest(item)"
            >Generate draft</button>
            <button
              v-if="['requested', 'acknowledged'].includes(item.status)"
              type="button"
              class="ml-btn-ghost text-sm text-rose-700"
              @click="declineRequest(item.id)"
            >Decline</button>
            <button type="button" class="ml-btn-ghost text-sm" @click="openRequestDetail(item.id)">Timeline</button>
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

      <!-- Drafts -->
      <section v-show="activeTab === 'drafts'" class="space-y-4">
        <div class="ml-card space-y-3">
          <h2 class="text-lg font-extrabold text-ink-900">Generate proactive draft</h2>
          <p class="text-sm text-ink-500">Create a Post-Event Summary without a CMart request.</p>
          <div class="flex flex-wrap gap-2">
            <select v-model="proactiveEventId" class="ml-input min-w-[16rem]">
              <option disabled value="">Select event</option>
              <option v-for="event in events" :key="event.id" :value="event.id">{{ event.title }}</option>
            </select>
            <button type="button" class="ml-btn-primary" :disabled="!proactiveEventId || busy" @click="generateProactive">
              Generate draft
            </button>
          </div>
        </div>

        <div v-if="!drafts.length" class="rounded-2xl border border-dashed border-ink-200 bg-white px-5 py-12 text-center text-sm text-ink-500">
          No draft reports.
        </div>
        <article v-for="item in drafts" :key="item.id" class="rounded-2xl border border-ink-200 bg-white p-4 shadow-sm">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 class="font-bold text-ink-900">{{ item.event_title_snapshot || item.event?.title || 'Draft' }}</h3>
              <p class="text-xs text-ink-500">
                Version {{ item.version }} · {{ item.report_request_id ? 'From request' : 'Organizer initiated' }} · {{ formatDate(item.created_at) }}
              </p>
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="ml-btn-ghost text-sm" @click="openReport(item.id)">Preview</button>
              <button type="button" class="ml-btn-ghost text-sm" @click="regenerate(item.id)">Regenerate</button>
              <button type="button" class="ml-btn-primary text-sm" @click="publish(item.id)">Publish</button>
              <button type="button" class="ml-btn-ghost text-sm text-rose-700" @click="removeDraft(item.id)">Delete</button>
            </div>
          </div>
        </article>
      </section>

      <!-- Published -->
      <section v-show="activeTab === 'published'" class="space-y-3">
        <div v-if="!published.length" class="rounded-2xl border border-dashed border-ink-200 bg-white px-5 py-12 text-center text-sm text-ink-500">
          No published reports yet.
        </div>
        <article v-for="item in published" :key="item.id" class="rounded-2xl border border-ink-200 bg-white p-4 shadow-sm">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 class="font-bold text-ink-900">{{ item.event_title_snapshot || 'Report' }}</h3>
              <p class="text-xs text-ink-500">Version {{ item.version }} · {{ item.status }} · {{ formatDate(item.published_at) }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="ml-btn-ghost text-sm" @click="openReport(item.id)">View</button>
              <button
                v-if="item.status === 'published'"
                type="button"
                class="ml-btn-primary text-sm"
                @click="createRevision(item.id)"
              >Create revision</button>
              <button type="button" class="ml-btn-ghost text-sm" @click="downloadPdf(item.id)">Download PDF</button>
            </div>
          </div>
        </article>
      </section>

      <div v-if="activeReport" class="space-y-4 rounded-2xl border border-ink-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap gap-2">
          <button type="button" class="ml-btn-ghost text-sm" @click="activeReport = null">Back</button>
          <button
            type="button"
            class="ml-btn-ghost text-sm"
            @click="downloadPdf(activeReport.id)"
          >Download PDF</button>
          <button
            v-if="activeReport.status === 'draft'"
            type="button"
            class="ml-btn-ghost text-sm"
            @click="saveNarratives"
          >Save narratives</button>
          <button
            v-if="activeReport.status === 'draft'"
            type="button"
            class="ml-btn-primary text-sm"
            @click="publish(activeReport.id)"
          >Publish</button>
        </div>

        <div v-if="activeReport.status === 'draft'" class="grid gap-3 sm:grid-cols-2">
          <label class="block text-sm font-semibold text-ink-700">
            Organizer observations
            <textarea v-model="narrative.observations" rows="5" class="ml-input mt-1 w-full" />
          </label>
          <label class="block text-sm font-semibold text-ink-700">
            Organizer recommendations
            <textarea v-model="narrative.recommendations" rows="5" class="ml-input mt-1 w-full" />
          </label>
        </div>

        <PostEventSummaryView :report="activeReport" />
      </div>
    </template>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useToast } from 'vue-toastification';
import PostEventSummaryView from '../../../components/reports/PostEventSummaryView.vue';
import ReportNotificationActivity from '../../../components/reports/ReportNotificationActivity.vue';
import { getCarbootEvents } from '../../../services/organizerEventLayoutApi';
import {
  REPORT_TYPE_POST_EVENT,
  acknowledgeReportRequest,
  createOrganizerGeneratedReport,
  declineReportRequest,
  deleteOrganizerDraftReport,
  getOrganizerGeneratedReport,
  getOrganizerReportRequest,
  listOrganizerGeneratedReports,
  listOrganizerReportRequests,
  openAuthorizedPdf,
  organizerGeneratedReportPdfUrl,
  publishOrganizerReport,
  regenerateOrganizerReport,
  reviseOrganizerReport,
  startReportRequestPreparation,
  updateOrganizerReportNarratives,
} from '../../../services/reportWorkflowApi';

const toast = useToast();
const tabs = [
  { id: 'requests', label: 'Requests' },
  { id: 'drafts', label: 'Draft Reports' },
  { id: 'published', label: 'Published Reports' },
];

const activeTab = ref('requests');
const loading = ref(false);
const busy = ref(false);
const requests = ref([]);
const drafts = ref([]);
const published = ref([]);
const events = ref([]);
const proactiveEventId = ref('');
try {
  const preselect = sessionStorage.getItem('cmart.reportCentre.preselectEventId');
  if (preselect) {
    proactiveEventId.value = String(preselect);
    sessionStorage.removeItem('cmart.reportCentre.preselectEventId');
  }
} catch {
  /* ignore */
}
const activeReport = ref(null);
const narrative = ref({ observations: '', recommendations: '' });
const requestDetail = ref(null);

const actionRequiredCount = computed(() =>
  requests.value.filter((row) => ['requested', 'acknowledged'].includes(row.status)).length,
);

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
  return [];
};

const load = async () => {
  loading.value = true;
  try {
    const [reqRes, draftRes, pubRes, eventsRes] = await Promise.all([
      listOrganizerReportRequests(),
      listOrganizerGeneratedReports({ status: 'draft' }),
      listOrganizerGeneratedReports({ status: 'published' }),
      getCarbootEvents(),
    ]);
    requests.value = unwrapList(reqRes.data);
    drafts.value = unwrapList(draftRes.data);
    const publishedOnly = unwrapList(pubRes.data);
    const supersededRes = await listOrganizerGeneratedReports({ status: 'superseded' });
    published.value = [...publishedOnly, ...unwrapList(supersededRes.data)];
    events.value = Array.isArray(eventsRes.data) ? eventsRes.data : (eventsRes.data?.data || eventsRes.data?.events || []);
  } catch (error) {
    if (!error.forbiddenMessage) {
      toast.error(error.response?.data?.message || 'Unable to load report centre.');
    }
  } finally {
    loading.value = false;
  }
};

const runRequestAction = async (action, id) => {
  try {
    if (action === 'acknowledge') await acknowledgeReportRequest(id);
    if (action === 'start') await startReportRequestPreparation(id);
    toast.success('Request updated.');
    await load();
  } catch (error) {
    toast.error(error.response?.data?.message || 'Unable to update request.');
  }
};

const declineRequest = async (id) => {
  const reason = window.prompt('Decline reason (required):');
  if (!reason || !reason.trim()) {
    toast.error('A decline reason is required.');
    return;
  }
  try {
    await declineReportRequest(id, { decline_reason: reason.trim() });
    toast.success('Request declined. CMart will see an in-app notification.');
    await load();
  } catch (error) {
    toast.error(error.response?.data?.message || 'Unable to decline request.');
  }
};

const openRequestDetail = async (id) => {
  try {
    const { data } = await getOrganizerReportRequest(id);
    requestDetail.value = data.data || data.report_request || data;
  } catch (error) {
    toast.error(error.response?.data?.message || 'Unable to load request detail.');
  }
};

const generateFromRequest = async (item) => {
  busy.value = true;
  try {
    await createOrganizerGeneratedReport({
      carboot_event_id: item.carboot_event_id || item.event?.id,
      report_request_id: item.id,
      report_type: item.report_type || REPORT_TYPE_POST_EVENT,
    });
    toast.success('Draft generated from request.');
    activeTab.value = 'drafts';
    await load();
  } catch (error) {
    toast.error(error.response?.data?.message || 'Unable to generate draft.');
  } finally {
    busy.value = false;
  }
};

const generateProactive = async () => {
  busy.value = true;
  try {
    await createOrganizerGeneratedReport({
      carboot_event_id: Number(proactiveEventId.value),
      report_type: REPORT_TYPE_POST_EVENT,
    });
    toast.success('Proactive draft generated.');
    await load();
  } catch (error) {
    toast.error(error.response?.data?.message || 'Unable to generate draft.');
  } finally {
    busy.value = false;
  }
};

const openReport = async (id) => {
  try {
    const { data } = await getOrganizerGeneratedReport(id);
    const report = data.data || data.generated_report || data;
    activeReport.value = report;
    narrative.value = {
      observations: report.organizer_observations || '',
      recommendations: report.organizer_recommendations || '',
    };
  } catch (error) {
    toast.error(error.response?.data?.message || 'Unable to open report.');
  }
};

const saveNarratives = async () => {
  if (!activeReport.value) return;
  try {
    const { data } = await updateOrganizerReportNarratives(activeReport.value.id, {
      organizer_observations: narrative.value.observations,
      organizer_recommendations: narrative.value.recommendations,
    });
    activeReport.value = data.generated_report || data.data || activeReport.value;
    toast.success('Narratives saved.');
  } catch (error) {
    toast.error(error.response?.data?.message || 'Unable to save narratives.');
  }
};

const regenerate = async (id) => {
  try {
    await regenerateOrganizerReport(id);
    toast.success('Snapshot regenerated.');
    await load();
    if (activeReport.value?.id === id) await openReport(id);
  } catch (error) {
    toast.error(error.response?.data?.message || 'Unable to regenerate.');
  }
};

const publish = async (id) => {
  try {
    if (activeReport.value?.id === id && activeReport.value.status === 'draft') {
      await saveNarratives();
    }
    await publishOrganizerReport(id);
    toast.success('A published report is now available to CMart Management.');
    activeReport.value = null;
    activeTab.value = 'published';
    await load();
  } catch (error) {
    toast.error(error.response?.data?.message || error.response?.data?.errors?.carboot_event_id?.[0] || 'Unable to publish.');
  }
};

const removeDraft = async (id) => {
  if (!window.confirm('Delete this unpublished draft?')) return;
  try {
    await deleteOrganizerDraftReport(id);
    toast.success('Draft deleted.');
    if (activeReport.value?.id === id) activeReport.value = null;
    await load();
  } catch (error) {
    toast.error(error.response?.data?.message || 'Unable to delete draft.');
  }
};

const createRevision = async (id) => {
  const reason = window.prompt('Revision reason (required):');
  if (!reason || !reason.trim()) {
    toast.error('A revision reason is required.');
    return;
  }
  try {
    await reviseOrganizerReport(id, { revision_reason: reason.trim() });
    toast.success('Revision draft created.');
    activeTab.value = 'drafts';
    await load();
  } catch (error) {
    toast.error(error.response?.data?.message || 'Unable to create revision.');
  }
};

const downloadPdf = async (id) => {
  try {
    await openAuthorizedPdf(organizerGeneratedReportPdfUrl(id));
  } catch {
    toast.error('Unable to download PDF.');
  }
};

onMounted(load);
defineExpose({ load });
</script>
