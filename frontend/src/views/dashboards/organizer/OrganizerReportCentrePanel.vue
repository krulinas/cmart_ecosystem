<template>
  <section class="space-y-6" data-testid="organizer-report-centre">
    <div class="flex flex-wrap gap-2" role="tablist" :aria-label="t('reports.organizer.tablistAria')">
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
      {{ t('reports.organizer.loading') }}
    </div>

    <template v-else>
      <!-- Requests -->
      <section v-show="activeTab === 'requests'" class="space-y-3">
        <div v-if="!requests.length" class="rounded-2xl border border-dashed border-ink-200 bg-white px-5 py-12 text-center text-sm text-ink-500">
          {{ t('reports.organizer.emptyRequests') }}
        </div>
        <article v-for="item in requests" :key="item.id" class="rounded-2xl border border-ink-200 bg-white p-4 shadow-sm">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
              <h3 class="font-bold text-ink-900">{{ item.event?.title || t('reports.organizer.eventFallback') }}</h3>
              <p class="text-xs text-ink-500">
                {{ item.requester?.name || t('reports.organizer.requesterFallback') }} · {{ item.report_type_label || item.report_type }} · {{ formatDate(item.created_at) }}
              </p>
              <p class="mt-2 text-sm text-ink-700">{{ item.message || t('reports.organizer.noMessage') }}</p>
              <p v-if="item.preferred_due_date" class="mt-1 text-xs text-ink-500">{{ t('reports.organizer.preferredDue', { date: item.preferred_due_date }) }}</p>
            </div>
            <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold uppercase text-blue-900 ring-1 ring-blue-200">{{ statusLabel(item.status, t) }}</span>
          </div>
          <div class="mt-3 flex flex-wrap gap-2">
            <button
              v-if="['requested'].includes(item.status)"
              type="button"
              class="ml-btn-ghost text-sm"
              @click="runRequestAction('acknowledge', item.id)"
            >{{ t('reports.organizer.acknowledge') }}</button>
            <button
              v-if="['requested', 'acknowledged'].includes(item.status)"
              type="button"
              class="ml-btn-ghost text-sm"
              @click="runRequestAction('start', item.id)"
            >{{ t('reports.organizer.startPreparation') }}</button>
            <button
              v-if="['requested', 'acknowledged', 'in_progress'].includes(item.status)"
              type="button"
              class="ml-btn-primary text-sm"
              @click="generateFromRequest(item)"
            >{{ t('reports.organizer.generateDraft') }}</button>
            <button
              v-if="['requested', 'acknowledged'].includes(item.status)"
              type="button"
              class="ml-btn-ghost text-sm text-rose-700"
              @click="declineRequest(item.id)"
            >{{ t('reports.organizer.decline') }}</button>
            <button type="button" class="ml-btn-ghost text-sm" @click="openRequestDetail(item.id)">{{ t('reports.organizer.timeline') }}</button>
          </div>
        </article>
      </section>

      <div v-if="requestDetail" class="rounded-2xl border border-ink-200 bg-white p-4 shadow-sm">
        <div class="mb-2 flex items-center justify-between gap-2">
          <h3 class="font-bold text-ink-900">{{ t('reports.organizer.requestTimeline') }}</h3>
          <button type="button" class="ml-btn-ghost text-sm" @click="requestDetail = null">{{ t('reports.organizer.close') }}</button>
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
          <h2 class="text-lg font-extrabold text-ink-900">{{ t('reports.organizer.generateProactiveTitle') }}</h2>
          <p class="text-sm text-ink-500">{{ t('reports.organizer.generateProactiveLead') }}</p>
          <div class="flex flex-wrap gap-2">
            <select v-model="proactiveEventId" class="ml-input min-w-[16rem]">
              <option disabled value="">{{ t('reports.organizer.selectEvent') }}</option>
              <option v-for="event in events" :key="event.id" :value="event.id">{{ event.title }}</option>
            </select>
            <button type="button" class="ml-btn-primary" :disabled="!proactiveEventId || busy" @click="generateProactive">
              {{ t('reports.organizer.generateDraft') }}
            </button>
          </div>
        </div>

        <div v-if="!drafts.length" class="rounded-2xl border border-dashed border-ink-200 bg-white px-5 py-12 text-center text-sm text-ink-500">
          {{ t('reports.organizer.emptyDrafts') }}
        </div>
        <article v-for="item in drafts" :key="item.id" class="rounded-2xl border border-ink-200 bg-white p-4 shadow-sm">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 class="font-bold text-ink-900">{{ item.event_title_snapshot || item.event?.title || t('reports.organizer.draftFallback') }}</h3>
              <p class="text-xs text-ink-500">
                {{ t('reports.organizer.version', { n: item.version }) }} · {{ item.report_request_id ? t('reports.organizer.fromRequest') : t('reports.organizer.organizerInitiated') }}
              </p>
              <p class="text-xs text-ink-500">{{ t('reports.organizer.createdAt', { date: formatDate(item.created_at) }) }}</p>
              <p class="text-xs text-ink-500">{{ t('reports.organizer.snapshotUpdatedAt', { date: formatDate(item.snapshot_generated_at || item.updated_at) }) }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="ml-btn-ghost text-sm" @click="openReport(item.id)">{{ t('reports.organizer.preview') }}</button>
              <button
                type="button"
                class="ml-btn-ghost text-sm"
                :disabled="regeneratingId === item.id"
                @click="regenerate(item.id)"
              >{{ regeneratingId === item.id ? t('reports.organizer.regenerating') : t('reports.organizer.regenerate') }}</button>
              <button type="button" class="ml-btn-primary text-sm" @click="publish(item.id)">{{ t('reports.organizer.publish') }}</button>
              <button type="button" class="ml-btn-ghost text-sm text-rose-700" @click="removeDraft(item.id)">{{ t('reports.organizer.delete') }}</button>
            </div>
          </div>
        </article>
      </section>

      <!-- Published -->
      <section v-show="activeTab === 'published'" class="space-y-3">
        <div v-if="!published.length" class="rounded-2xl border border-dashed border-ink-200 bg-white px-5 py-12 text-center text-sm text-ink-500">
          {{ t('reports.organizer.emptyPublished') }}
        </div>
        <article v-for="item in published" :key="item.id" class="rounded-2xl border border-ink-200 bg-white p-4 shadow-sm">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 class="font-bold text-ink-900">{{ item.event_title_snapshot || t('reports.organizer.reportFallback') }}</h3>
              <p class="text-xs text-ink-500">{{ t('reports.organizer.version', { n: item.version }) }} · {{ statusLabel(item.status, t) }} · {{ formatDate(item.published_at) }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="ml-btn-ghost text-sm" @click="openReport(item.id)">{{ t('reports.organizer.view') }}</button>
              <button
                v-if="item.status === 'published'"
                type="button"
                class="ml-btn-primary text-sm"
                @click="createRevision(item.id)"
              >{{ t('reports.organizer.createRevision') }}</button>
              <button type="button" class="ml-btn-ghost text-sm" @click="downloadPdf(item.id)">{{ t('reports.organizer.downloadPdf') }}</button>
            </div>
          </div>
        </article>
      </section>

      <div v-if="activeReport" class="space-y-4 rounded-2xl border border-ink-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap gap-2">
          <button type="button" class="ml-btn-ghost text-sm" @click="activeReport = null">{{ t('reports.organizer.back') }}</button>
          <button
            type="button"
            class="ml-btn-ghost text-sm"
            @click="downloadPdf(activeReport.id)"
          >{{ t('reports.organizer.downloadPdf') }}</button>
          <button
            v-if="activeReport.status === 'draft'"
            type="button"
            class="ml-btn-ghost text-sm"
            @click="saveNarratives"
          >{{ t('reports.organizer.saveNarratives') }}</button>
          <button
            v-if="activeReport.status === 'draft'"
            type="button"
            class="ml-btn-primary text-sm"
            @click="publish(activeReport.id)"
          >{{ t('reports.organizer.publish') }}</button>
        </div>

        <div v-if="activeReport.status === 'draft'" class="space-y-4">
          <div v-if="activeReport.publish_readiness" class="rounded-xl border border-ink-200 bg-ink-50/60 p-4" data-testid="publish-readiness">
            <h3 class="text-sm font-bold text-ink-900">{{ t('reports.organizer.publishReadinessTitle') }}</h3>
            <ul class="mt-2 space-y-1 text-sm">
              <li
                v-for="item in activeReport.publish_readiness.items || []"
                :key="item.id"
                :class="item.satisfied ? 'text-emerald-700' : 'text-amber-800'"
              >
                {{ item.satisfied ? '✓' : '○' }} {{ item.label }}
              </li>
            </ul>
            <p v-if="!activeReport.publish_readiness.ready" class="mt-2 text-xs text-amber-800">
              {{ t('reports.organizer.publishReadinessBlocked') }}
            </p>
          </div>

          <label class="block text-sm font-semibold text-ink-700">
            {{ t('reports.organizer.introductionLabel') }}
            <textarea v-model="narrative.introduction" rows="4" class="ml-input mt-1 w-full" />
          </label>

          <div class="space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <span class="text-sm font-semibold text-ink-700">{{ t('reports.organizer.objectivesLabel') }}</span>
              <label class="flex items-center gap-2 text-xs text-ink-600">
                <input v-model="narrative.objectivesNotApplicable" type="checkbox" class="rounded border-ink-300" />
                {{ t('reports.organizer.objectivesNotApplicable') }}
              </label>
            </div>
            <div v-if="!narrative.objectivesNotApplicable" class="space-y-2">
              <div v-for="(objective, idx) in narrative.objectives" :key="idx" class="flex gap-2">
                <input v-model="narrative.objectives[idx]" type="text" class="ml-input flex-1" :placeholder="t('reports.organizer.objectivePlaceholder')" />
                <button type="button" class="ml-btn-ghost text-sm" @click="narrative.objectives.splice(idx, 1)">{{ t('reports.organizer.removeObjective') }}</button>
              </div>
              <button type="button" class="ml-btn-ghost text-sm" @click="narrative.objectives.push('')">{{ t('reports.organizer.addObjective') }}</button>
            </div>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <label class="block text-sm font-semibold text-ink-700">
              {{ t('reports.organizer.observationsLabel') }}
              <textarea v-model="narrative.observations" rows="5" class="ml-input mt-1 w-full" />
            </label>
            <label class="block text-sm font-semibold text-ink-700">
              {{ t('reports.organizer.recommendationsLabel') }}
              <textarea v-model="narrative.recommendations" rows="5" class="ml-input mt-1 w-full" />
            </label>
          </div>

          <label class="block text-sm font-semibold text-ink-700">
            {{ t('reports.organizer.conclusionLabel') }}
            <textarea v-model="narrative.conclusion" rows="5" class="ml-input mt-1 w-full" />
          </label>
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
import { getCarbootEvents } from '../../../services/organizerEventLayoutApi';
import { formatLocaleDateTime } from '../../../utils/localeFormat';
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
  downloadAuthorizedPdf,
  buildPostEventPdfFilename,
  organizerGeneratedReportPdfUrl,
  publishOrganizerReport,
  regenerateOrganizerReport,
  reviseOrganizerReport,
  startReportRequestPreparation,
  updateOrganizerReportNarratives,
} from '../../../services/reportWorkflowApi';

const { t } = useI18n();
const toast = useToast();
const tabs = computed(() => [
  { id: 'requests', label: t('reports.organizer.tabs.requests') },
  { id: 'drafts', label: t('reports.organizer.tabs.drafts') },
  { id: 'published', label: t('reports.organizer.tabs.published') },
]);

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
const narrative = ref({
  observations: '',
  recommendations: '',
  introduction: '',
  objectives: [],
  objectivesNotApplicable: false,
  conclusion: '',
});
const requestDetail = ref(null);
const regeneratingId = ref(null);

const applyReportToState = (report) => {
  if (!report?.id) return;
  const idx = drafts.value.findIndex((row) => row.id === report.id);
  if (idx >= 0) {
    drafts.value.splice(idx, 1, { ...drafts.value[idx], ...report });
  }
  if (activeReport.value?.id === report.id) {
    activeReport.value = report;
    syncNarrativeFromReport(report);
  }
};

const syncNarrativeFromReport = (report) => {
  narrative.value = {
    observations: report.organizer_observations ?? '',
    recommendations: report.organizer_recommendations ?? '',
    introduction: report.programme_introduction ?? report.snapshot?.programme?.introduction ?? '',
    objectives: Array.isArray(report.programme_objectives) && report.programme_objectives.length
      ? [...report.programme_objectives]
      : [],
    objectivesNotApplicable: Boolean(report.objectives_not_applicable),
    conclusion: report.conclusion ?? report.snapshot?.programme?.conclusion ?? '',
  };
};

const actionRequiredCount = computed(() =>
  requests.value.filter((row) => ['requested', 'acknowledged'].includes(row.status)).length,
);

const formatDate = (value) => {
  if (!value) return '—';
  try {
    return formatLocaleDateTime(value, { dateStyle: 'medium', timeStyle: 'short' });
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
      toast.error(error.response?.data?.message || t('reports.organizer.toastLoadError'));
    }
  } finally {
    loading.value = false;
  }
};

const runRequestAction = async (action, id) => {
  try {
    if (action === 'acknowledge') await acknowledgeReportRequest(id);
    if (action === 'start') await startReportRequestPreparation(id);
    toast.success(t('reports.organizer.toastRequestUpdated'));
    await load();
  } catch (error) {
    toast.error(error.response?.data?.message || t('reports.organizer.toastRequestUpdateError'));
  }
};

const declineRequest = async (id) => {
  const reason = window.prompt(t('reports.organizer.declineReasonPrompt'));
  if (!reason || !reason.trim()) {
    toast.error(t('reports.organizer.toastDeclineReasonRequired'));
    return;
  }
  try {
    await declineReportRequest(id, { decline_reason: reason.trim() });
    toast.success(t('reports.organizer.toastDeclined'));
    await load();
  } catch (error) {
    toast.error(error.response?.data?.message || t('reports.organizer.toastDeclineError'));
  }
};

const openRequestDetail = async (id) => {
  try {
    const { data } = await getOrganizerReportRequest(id);
    requestDetail.value = data.data || data.report_request || data;
  } catch (error) {
    toast.error(error.response?.data?.message || t('reports.organizer.toastRequestDetailError'));
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
    toast.success(t('reports.organizer.toastDraftFromRequest'));
    activeTab.value = 'drafts';
    await load();
  } catch (error) {
    toast.error(error.response?.data?.message || t('reports.organizer.toastGenerateError'));
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
    toast.success(t('reports.organizer.toastProactiveDraft'));
    await load();
  } catch (error) {
    toast.error(error.response?.data?.message || t('reports.organizer.toastGenerateError'));
  } finally {
    busy.value = false;
  }
};

const openReport = async (id) => {
  try {
    const { data } = await getOrganizerGeneratedReport(id);
    const report = data.data || data.generated_report || data;
    activeReport.value = report;
    syncNarrativeFromReport(report);
  } catch (error) {
    toast.error(error.response?.data?.message || t('reports.organizer.toastOpenError'));
  }
};

const saveNarratives = async () => {
  if (!activeReport.value) return;
  try {
    const { data } = await updateOrganizerReportNarratives(activeReport.value.id, {
      organizer_observations: narrative.value.observations,
      organizer_recommendations: narrative.value.recommendations,
      programme_introduction: narrative.value.introduction,
      programme_objectives: narrative.value.objectivesNotApplicable
        ? []
        : narrative.value.objectives.filter((row) => String(row || '').trim()),
      objectives_not_applicable: narrative.value.objectivesNotApplicable,
      conclusion: narrative.value.conclusion,
    });
    const report = data.generated_report || data.data || activeReport.value;
    applyReportToState(report);
    toast.success(t('reports.organizer.toastNarrativesSaved'));
  } catch (error) {
    toast.error(error.response?.data?.message || t('reports.organizer.toastNarrativesError'));
  }
};

const regenerate = async (id) => {
  if (regeneratingId.value) return;
  regeneratingId.value = id;
  try {
    const { data } = await regenerateOrganizerReport(id);
    const report = data.generated_report || data.data;
    if (report) applyReportToState(report);
    toast.success(data.message || (
      data.metrics_changed
        ? t('reports.organizer.toastRegeneratedChanged')
        : t('reports.organizer.toastRegeneratedUnchanged')
    ));
  } catch (error) {
    toast.error(error.response?.data?.message || t('reports.organizer.toastRegenerateError'));
  } finally {
    regeneratingId.value = null;
  }
};

const publish = async (id) => {
  try {
    if (activeReport.value?.id === id && activeReport.value.status === 'draft') {
      await saveNarratives();
    }
    await publishOrganizerReport(id);
    toast.success(t('reports.organizer.toastPublished'));
    activeReport.value = null;
    activeTab.value = 'published';
    await load();
  } catch (error) {
    toast.error(error.response?.data?.message || error.response?.data?.errors?.carboot_event_id?.[0] || t('reports.organizer.toastPublishError'));
  }
};

const removeDraft = async (id) => {
  if (!window.confirm(t('reports.organizer.deleteDraftConfirm'))) return;
  try {
    await deleteOrganizerDraftReport(id);
    toast.success(t('reports.organizer.toastDraftDeleted'));
    if (activeReport.value?.id === id) activeReport.value = null;
    await load();
  } catch (error) {
    toast.error(error.response?.data?.message || t('reports.organizer.toastDeleteError'));
  }
};

const createRevision = async (id) => {
  const reason = window.prompt(t('reports.organizer.revisionReasonPrompt'));
  if (!reason || !reason.trim()) {
    toast.error(t('reports.organizer.toastRevisionReasonRequired'));
    return;
  }
  try {
    await reviseOrganizerReport(id, { revision_reason: reason.trim() });
    toast.success(t('reports.organizer.toastRevisionCreated'));
    activeTab.value = 'drafts';
    await load();
  } catch (error) {
    toast.error(error.response?.data?.message || t('reports.organizer.toastRevisionError'));
  }
};

const downloadPdf = async (id) => {
  try {
    const report = [...drafts.value, ...published.value].find((row) => row.id === id) || activeReport.value;
    const fallbackFilename = buildPostEventPdfFilename({
      audience: 'organizer',
      eventSlug: report?.event_title_snapshot || report?.snapshot?.event?.title || 'event',
      version: report?.version || 1,
    });
    await downloadAuthorizedPdf(organizerGeneratedReportPdfUrl(id), { fallbackFilename });
  } catch (error) {
    toast.error(error?.message || t('reports.organizer.toastPdfError'));
  }
};

onMounted(load);
defineExpose({ load });
</script>
