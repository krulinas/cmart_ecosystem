<template>
  <div class="space-y-6 pb-10">
    <div v-if="loading && !hasLoaded" class="rounded-2xl border border-ink-200 bg-white py-16 text-center shadow-sm">
      <div class="mx-auto h-10 w-10 animate-pulse rounded-full bg-gradient-to-br from-cyan-100 to-sky-100" />
      <p class="mt-4 text-sm font-medium text-ink-500">Loading bookings…</p>
    </div>

    <div v-else-if="loadError" class="rounded-2xl border border-rose-200 bg-rose-50/60 px-6 py-12 text-center">
      <p class="text-sm font-semibold text-rose-800">Unable to load bookings</p>
      <p class="mt-1 text-sm text-rose-700/80">{{ loadError }}</p>
    </div>

    <template v-else>
    <!-- KPI overview -->
    <section>
      <div class="mb-4">
        <h2 class="text-sm font-bold uppercase tracking-wider text-ink-500">Operations overview</h2>
        <p class="text-xs text-ink-400 mt-0.5">{{ overviewHint }}</p>
      </div>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <ManagementKpiCard
          v-for="card in kpiCards"
          :key="card.key"
          :title="card.title"
          :description="card.description"
          :value="card.value"
          :icon="card.icon"
          :accent="card.accent"
        />
      </div>
    </section>

    <!-- Approval queue -->
    <section class="overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-sm">
      <div class="px-5 py-4 text-white sm:px-6" :class="theme.queueHeader">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/70">Active queue</p>
            <h2 class="text-lg font-extrabold tracking-tight">{{ queueTitle }}</h2>
            <p class="mt-1 text-sm text-white/80 max-w-2xl">{{ queueDescription }}</p>
          </div>
          <span class="mt-2 inline-flex w-fit items-center rounded-full bg-white/15 px-3 py-1 text-xs font-bold ring-1 ring-white/25 sm:mt-0">
            {{ queueBookings.length }} pending
          </span>
        </div>
      </div>

      <div class="p-4 sm:p-6">
        <ManagementEmptyState
          v-if="hasLoaded && !queueBookings.length"
          :title="emptyQueueTitle"
          :description="emptyQueueDescription"
          :icon="emptyQueueIcon"
          :accent="themeAccent"
        />

        <div v-else class="overflow-x-auto rounded-xl border border-ink-100">
          <table class="min-w-full text-sm">
            <thead class="bg-ink-50/80">
              <tr class="text-left text-[11px] uppercase tracking-wider text-ink-500">
                <th class="px-4 py-3 font-semibold">Booking</th>
                <th class="px-4 py-3 font-semibold">Vendor product</th>
                <th class="px-4 py-3 font-semibold">Space</th>
                <th class="px-4 py-3 font-semibold">Event date</th>
                <th class="px-4 py-3 font-semibold">Status</th>
                <th class="px-4 py-3 text-right font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-ink-100">
              <tr v-for="b in queueBookings" :key="'q-' + b.id" class="transition hover:bg-ink-50/50">
                <td class="px-4 py-3.5">
                  <div class="font-bold text-ink-900">#{{ b.id }}</div>
                  <div class="text-xs text-ink-500">{{ b.user?.name || 'Vendor' }}</div>
                </td>
                <td class="px-4 py-3.5">
                  <div class="font-medium text-ink-800">{{ b.product_category || 'Others' }}</div>
                  <div class="max-w-xs truncate text-xs text-ink-500">{{ b.product_details || '—' }}</div>
                </td>
                <td class="px-4 py-3.5 text-ink-700">{{ b.space?.space_size || b.space_id }}</td>
                <td class="px-4 py-3.5 whitespace-nowrap text-ink-700">{{ formatBookingDate(b.booking_date) }}</td>
                <td class="px-4 py-3.5">
                  <ManagementStatusChip :status="b.approval_status" />
                </td>
                <td class="px-4 py-3.5">
                  <div class="flex justify-end gap-1.5 flex-wrap">
                    <button class="ml-btn-ghost text-xs px-3 py-1.5" @click="viewPdf(b.id)">PDF</button>
                    <template v-if="isStaffView">
                      <button class="ml-btn-success text-xs px-3 py-1.5" @click="updateStatus(b.id, 'Pending_Boss')">
                        Forward
                      </button>
                      <button class="ml-btn-danger text-xs px-3 py-1.5" @click="updateStatus(b.id, 'Rejected')">
                        Reject
                      </button>
                      <button
                        class="inline-flex items-center rounded-xl border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-900 hover:bg-amber-100"
                        @click="requestRevision(b.id)"
                      >
                        Revision
                      </button>
                    </template>
                    <template v-if="canFinalApproveBookings">
                      <button class="ml-btn-success text-xs px-3 py-1.5" @click="updateStatus(b.id, 'Approved')">
                        Approve
                      </button>
                      <button class="ml-btn-danger text-xs px-3 py-1.5" @click="updateStatus(b.id, 'Rejected')">
                        Reject
                      </button>
                      <button
                        class="inline-flex items-center rounded-xl border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-900 hover:bg-amber-100"
                        @click="requestRevision(b.id)"
                      >
                        Revision
                      </button>
                    </template>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Registry -->
    <section class="overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-sm">
      <div class="border-b border-ink-100 px-5 py-4 sm:px-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="text-lg font-extrabold text-ink-900">All Bookings Registry</h2>
              <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1"
                :class="registryBadgeClass"
              >
                {{ registryLabel }}
              </span>
            </div>
            <p class="mt-1 text-sm text-ink-500">{{ registryDescription }}</p>
          </div>
          <div class="flex w-full max-w-md flex-col gap-2 sm:flex-row sm:items-center">
            <div class="relative flex-1">
              <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-400 text-xs">⌕</span>
              <input
                v-model="searchQuery"
                type="search"
                placeholder="Search vendor, category, or booking ID…"
                class="ml-input pl-8 text-sm"
              />
            </div>
            <select v-model="statusFilter" class="ml-input w-36 text-sm shrink-0">
              <option value="all">All status</option>
              <option value="Pending_Staff">Staff queue</option>
              <option value="Pending_Boss">Manager queue</option>
              <option value="Needs_Revision">Needs revision</option>
              <option value="Approved">Approved</option>
              <option value="Rejected">Rejected</option>
            </select>
          </div>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-ink-50/60">
            <tr class="text-left text-[11px] uppercase tracking-wider text-ink-500">
              <th class="px-4 py-3 font-semibold">ID</th>
              <th class="px-4 py-3 font-semibold">Vendor</th>
              <th class="px-4 py-3 font-semibold">Category</th>
              <th class="px-4 py-3 font-semibold">Details</th>
              <th class="px-4 py-3 font-semibold">Space</th>
              <th class="px-4 py-3 font-semibold">Date</th>
              <th class="px-4 py-3 font-semibold">Status</th>
              <th v-if="canDeleteBookings" class="px-4 py-3 text-right font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-ink-100">
            <tr
              v-for="b in filteredRegistry"
              :key="b.id"
              class="transition hover:bg-ink-50/40"
            >
              <td class="px-4 py-3.5 font-bold text-ink-900">#{{ b.id }}</td>
              <td class="px-4 py-3.5 text-ink-800">{{ b.user?.name || '—' }}</td>
              <td class="px-4 py-3.5 text-ink-700">{{ b.product_category || 'Others' }}</td>
              <td class="px-4 py-3.5 max-w-[200px] truncate text-ink-600">{{ b.product_details || '—' }}</td>
              <td class="px-4 py-3.5 text-ink-700">{{ b.space?.space_size || b.space_id }}</td>
              <td class="px-4 py-3.5 whitespace-nowrap text-ink-700">{{ formatBookingDate(b.booking_date) }}</td>
              <td class="px-4 py-3.5">
                <ManagementStatusChip :status="b.approval_status" />
              </td>
              <td v-if="canDeleteBookings" class="px-4 py-3.5 text-right">
                <button
                  class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100"
                  @click="deleteBooking(b.id)"
                >
                  Delete
                </button>
              </td>
            </tr>
            <tr v-if="hasLoaded && !filteredRegistry.length">
              <td :colspan="canDeleteBookings ? 8 : 7" class="px-4 py-0">
                <div class="py-12">
                  <ManagementEmptyState
                    title="No bookings found"
                    :description="registryEmptyDescription"
                    icon="⌕"
                    accent="cyan"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
    </template>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useToast } from 'vue-toastification';
import api from '../../../services/api';
import ManagementKpiCard from '../../../components/management/ManagementKpiCard.vue';
import ManagementEmptyState from '../../../components/management/ManagementEmptyState.vue';
import ManagementStatusChip from '../../../components/management/ManagementStatusChip.vue';
import { useManagementAccess } from '../../../composables/useManagementAccess';
import { formatBookingDate, statusLabel } from '../../../utils/bookingDisplay';

const emit = defineEmits(['refreshed']);

const toast = useToast();
const {
  isStaffView,
  isManagerView,
  isSuperAdminView,
  canDeleteBookings,
  canFinalApproveBookings,
  bookingsListEndpoint,
  queueStatusForView,
  workspaceTheme,
} = useManagementAccess();

const theme = computed(() => workspaceTheme.value);
const themeAccent = computed(() => 'cyan');

const allBookings = ref([]);
const searchQuery = ref('');
const statusFilter = ref('all');
const loading = ref(false);
const hasLoaded = ref(false);
const loadError = ref(null);

const queueBookings = computed(() =>
  allBookings.value.filter((b) => b.approval_status === queueStatusForView.value),
);

const filteredRegistry = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  return allBookings.value.filter((b) => {
    if (statusFilter.value !== 'all' && b.approval_status !== statusFilter.value) return false;
    if (!q) return true;
    const haystack = [
      b.id,
      b.user?.name,
      b.product_category,
      b.product_details,
      b.space?.space_size,
    ].filter(Boolean).join(' ').toLowerCase();
    return haystack.includes(q);
  });
});

const kpi = computed(() => {
  const counts = { pendingStaff: 0, pendingManager: 0, needsRevision: 0, approved: 0 };
  for (const b of allBookings.value) {
    if (b.approval_status === 'Pending_Staff') counts.pendingStaff++;
    if (b.approval_status === 'Pending_Boss') counts.pendingManager++;
    if (b.approval_status === 'Needs_Revision') counts.needsRevision++;
    if (b.approval_status === 'Approved') counts.approved++;
  }
  return counts;
});

const kpiCards = computed(() => {
  if (isStaffView.value) {
    return [
      {
        key: 'staff',
        title: 'Awaiting Staff Review',
        description: 'New vendor requests in your Tier 1 queue.',
        value: kpi.value.pendingStaff,
        icon: 'S1',
        accent: 'cyan',
      },
      {
        key: 'forwarded',
        title: 'Forwarded to Manager',
        description: 'Bookings you escalated for final decision.',
        value: kpi.value.pendingManager,
        icon: 'FM',
        accent: 'sky',
      },
      {
        key: 'revision',
        title: 'Needs Vendor Revision',
        description: 'Returned to vendors for corrections.',
        value: kpi.value.needsRevision,
        icon: 'Rv',
        accent: 'amber',
      },
      {
        key: 'approved',
        title: 'Approved Bookings',
        description: 'Confirmed vendor slots in the registry.',
        value: kpi.value.approved,
        icon: 'Ok',
        accent: 'emerald',
      },
    ];
  }

  if (isSuperAdminView.value) {
    return [
      {
        key: 'manager',
        title: 'Awaiting Manager Decision',
        description: 'Escalated bookings pending final approval.',
        value: kpi.value.pendingManager,
        icon: 'M2',
        accent: 'sky',
      },
      {
        key: 'staff',
        title: 'Awaiting Staff Review',
        description: 'First-level screening across the system.',
        value: kpi.value.pendingStaff,
        icon: 'S1',
        accent: 'cyan',
      },
      {
        key: 'revision',
        title: 'Needs Vendor Revision',
        description: 'Active revision requests system-wide.',
        value: kpi.value.needsRevision,
        icon: 'Rv',
        accent: 'amber',
      },
      {
        key: 'approved',
        title: 'Approved Bookings',
        description: 'Total confirmed vendor bookings.',
        value: kpi.value.approved,
        icon: 'HQ',
        accent: 'emerald',
      },
    ];
  }

  return [
    {
      key: 'manager',
      title: 'Awaiting Manager Decision',
      description: 'Your final approval queue.',
      value: kpi.value.pendingManager,
      icon: 'M2',
      accent: 'sky',
    },
    {
      key: 'staff',
      title: 'In Staff Review',
      description: 'Still undergoing Tier 1 screening.',
      value: kpi.value.pendingStaff,
      icon: 'S1',
      accent: 'cyan',
    },
    {
      key: 'revision',
      title: 'Needs Vendor Revision',
      description: 'Awaiting vendor resubmission.',
      value: kpi.value.needsRevision,
      icon: 'Rv',
      accent: 'amber',
    },
    {
      key: 'approved',
      title: 'Approved Bookings',
      description: 'Confirmed slots for this branch.',
      value: kpi.value.approved,
      icon: 'Ok',
      accent: 'emerald',
    },
  ];
});

const overviewHint = computed(() => {
  if (isStaffView.value) return 'Tier 1 operations desk — focus on screening and escalation.';
  if (isSuperAdminView.value) return 'HQ command centre — system-wide booking pipeline visibility.';
  return 'Branch control — monitor escalations and final decisions.';
});

const queueTitle = computed(() =>
  isManagerView.value && !isStaffView.value
    ? 'Manager Approval Queue'
    : 'Staff Approval Queue',
);

const queueDescription = computed(() => {
  if (isStaffView.value) {
    return 'Review new vendor submissions, request revisions, or forward valid bookings to manager review.';
  }
  if (isSuperAdminView.value) {
    return 'Final decision queue for bookings escalated from operations staff across the network.';
  }
  return 'Bookings forwarded by staff that require your final approve, reject, or revision decision.';
});

const emptyQueueTitle = computed(() => {
  if (isStaffView.value) return 'Queue clear — no staff reviews pending';
  if (isSuperAdminView.value) return 'No pending approvals in this workspace';
  return 'Queue clear — no manager decisions pending';
});

const emptyQueueDescription = computed(() => {
  if (isStaffView.value) {
    return 'No bookings awaiting staff review. New vendor requests will appear here for first-level screening.';
  }
  if (isSuperAdminView.value) {
    return 'No pending approvals across this workspace. Escalated bookings will surface here automatically.';
  }
  return 'No bookings awaiting manager decision. Bookings forwarded by staff will appear here.';
});

const emptyQueueIcon = computed(() => (isStaffView.value ? '✓' : '—'));

const registryLabel = computed(() => theme.value.registryLabel);
const registryDescription = computed(() => theme.value.registryDescription);

const registryBadgeClass = computed(() => 'bg-cyan-50 text-cyan-800 ring-cyan-200');

const registryEmptyDescription = computed(() => {
  if (searchQuery.value.trim() || statusFilter.value !== 'all') {
    return 'No bookings match your current search or filter. Try clearing filters to see the full registry.';
  }
  return 'Bookings will appear here once vendors submit slot requests through the portal.';
});

const fetchBookings = async () => {
  loading.value = true;
  loadError.value = null;
  try {
    const { data } = await api.get(bookingsListEndpoint.value);
    allBookings.value = data.data ?? (Array.isArray(data) ? data : []);
    hasLoaded.value = true;
    emit('refreshed');
  } catch (e) {
    loadError.value = e.forbiddenMessage || e.response?.data?.message || 'Unable to load bookings.';
    if (!e.forbiddenMessage) {
      toast.error(loadError.value);
    }
    throw e;
  } finally {
    loading.value = false;
  }
};

const updateStatus = async (id, status, revisionComment = null) => {
  const payload = { approval_status: status };
  if (revisionComment) payload.revision_comment = revisionComment;
  try {
    await api.put(`/bookings/${id}`, payload);
    toast.success(`Booking #${id} updated to ${statusLabel(status)}.`);
    await fetchBookings();
  } catch (e) {
    if (!e.forbiddenMessage) {
      toast.error(e.response?.data?.message || 'Unable to update booking.');
    }
  }
};

const requestRevision = async (id) => {
  const comment = window.prompt('Enter formal revision instructions for the vendor.');
  if (!comment?.trim()) {
    toast.error('Revision instructions are required.');
    return;
  }
  await updateStatus(id, 'Needs_Revision', comment.trim());
};

const deleteBooking = async (id) => {
  if (!canDeleteBookings.value) return;
  if (!window.confirm(`Delete booking #${id}? This cannot be undone.`)) return;
  try {
    await api.delete(`/bookings/${id}`);
    toast.success(`Booking #${id} deleted.`);
    await fetchBookings();
  } catch (e) {
    if (!e.forbiddenMessage) {
      toast.error(e.response?.data?.message || 'Unable to delete booking.');
    }
  }
};

const viewPdf = async (bookingId) => {
  try {
    const response = await api.get(`/bookings/${bookingId}/pdf`, { responseType: 'blob' });
    const fileUrl = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
    window.open(fileUrl, '_blank', 'noopener,noreferrer');
    setTimeout(() => URL.revokeObjectURL(fileUrl), 60000);
  } catch (e) {
    if (!e.forbiddenMessage) {
      toast.error(e.response?.data?.message || 'Unable to open booking PDF.');
    }
  }
};

defineExpose({ fetchBookings });
</script>
