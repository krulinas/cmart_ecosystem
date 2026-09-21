<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="modelValue && bookingId"
        data-testid="vendor-booking-details-overlay"
        class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center p-0 sm:p-4"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        @keydown.esc="close"
      >
        <div class="absolute inset-0 bg-[rgba(15,23,42,0.65)] backdrop-blur-[6px]" @click="close" />

        <Transition
          appear
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
          enter-to-class="opacity-100 translate-y-0 sm:scale-100"
        >
          <div
            v-if="modelValue"
            ref="panelRef"
            data-testid="vendor-booking-details-modal"
            class="relative z-10 w-full sm:max-w-3xl max-h-[92vh] overflow-y-auto rounded-t-2xl sm:rounded-2xl bg-white shadow-2xl"
            tabindex="-1"
            @click.stop
          >
            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-ink-100 bg-white/95 px-5 py-4 backdrop-blur">
              <div>
                <p class="text-xs font-bold uppercase tracking-wider text-brand-600">{{ t('booking.details.title') }}</p>
                <h2 :id="titleId" class="text-lg font-extrabold text-ink-900">
                  Booking {{ formatBookingReference(booking?.id || bookingId) }}
                </h2>
              </div>
              <button
                type="button"
                class="flex h-10 w-10 items-center justify-center rounded-full border border-ink-200 text-ink-500 hover:bg-ink-50"
                :aria-label="t('booking.details.closeAria')"
                @click="close"
              >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <div v-if="loading" class="p-10 text-center text-ink-500">{{ t('booking.details.loading') }}</div>

            <div v-else-if="booking" class="p-5 sm:p-6 space-y-6">
              <div class="flex flex-wrap items-center gap-2">
                <span :class="statusBadgeClass(booking.approval_status)">
                  {{ statusLabel(booking.approval_status, t) }}
                </span>
                <span v-if="booking.vendor_request_type" class="ml-badge bg-amber-100 text-amber-800">
                  {{ booking.vendor_request_type === 'change' ? t('booking.details.changeRequested') : t('booking.details.cancellationRequested') }}
                </span>
              </div>

              <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
                  <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">{{ t('booking.details.eventDate') }}</dt>
                  <dd class="mt-1 font-semibold text-ink-900">{{ formatBookingDate(booking.booking_date) }}</dd>
                </div>
                <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
                  <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">{{ t('booking.details.boothType') }}</dt>
                  <dd class="mt-1 font-semibold text-ink-900">{{ boothTypeLabel(booking) }}</dd>
                </div>
                <div class="rounded-xl border border-ink-100 bg-ink-50/50 p-4 sm:col-span-2">
                  <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">{{ t('booking.details.productCategory') }}</dt>
                  <dd class="mt-1 font-semibold text-ink-900">{{ booking.product_category || t('payment.checkout.othersFallback') }}</dd>
                  <dd v-if="booking.product_details" class="mt-1 text-ink-600 whitespace-pre-line">{{ booking.product_details }}</dd>
                </div>
                <div v-if="booking.invoice" class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
                  <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">{{ t('booking.details.invoiceAmount') }}</dt>
                  <dd class="mt-1 font-semibold text-ink-900">RM {{ Number(booking.invoice.amount).toFixed(2) }}</dd>
                </div>
                <div v-if="booking.invoice" class="rounded-xl border border-ink-100 bg-ink-50/50 p-4">
                  <dt class="text-xs font-bold uppercase tracking-wider text-ink-400">{{ t('booking.details.paymentStatus') }}</dt>
                  <dd class="mt-1 font-semibold text-ink-900">{{ vendorPaymentStatusLabel(booking) }}</dd>
                  <dd v-if="isVendorPaymentLockedUntilApproval(booking)" class="mt-1 text-xs text-ink-500">
                    {{ t('payment.availableAfterApproval') }}
                  </dd>
                </div>
              </dl>

              <section
                v-if="siteSummary"
                class="rounded-2xl border border-brand-100 bg-brand-50/40 p-5"
                data-testid="vendor-booking-site-selection"
              >
                <h3 class="font-bold text-brand-900">{{ t('booking.details.physicalSiteSelection') }}</h3>
                <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                  <div class="rounded-xl border border-brand-100 bg-white p-4 sm:col-span-2">
                    <dt class="text-xs font-bold uppercase tracking-wider text-brand-600">{{ t('booking.details.assignedSites') }}</dt>
                    <dd class="mt-1 text-lg font-extrabold text-ink-900">{{ siteSummary.labels }}</dd>
                  </div>
                  <div class="rounded-xl border border-brand-100 bg-white p-4">
                    <dt class="text-xs font-bold uppercase tracking-wider text-brand-600">{{ t('booking.details.siteCount') }}</dt>
                    <dd class="mt-1 font-semibold text-ink-900">{{ siteSummary.siteCount }}</dd>
                  </div>
                  <div class="rounded-xl border border-brand-100 bg-white p-4">
                    <dt class="text-xs font-bold uppercase tracking-wider text-brand-600">{{ t('booking.details.allocationStatus') }}</dt>
                    <dd class="mt-1 font-semibold text-ink-900">{{ siteSummary.allocationStatus }}</dd>
                  </div>
                  <div v-if="siteSummary.spaceName" class="rounded-xl border border-brand-100 bg-white p-4">
                    <dt class="text-xs font-bold uppercase tracking-wider text-brand-600">{{ t('booking.details.spaceType') }}</dt>
                    <dd class="mt-1 font-semibold text-ink-900">{{ siteSummary.spaceName }}</dd>
                  </div>
                  <div v-if="siteSummary.days" class="rounded-xl border border-brand-100 bg-white p-4 sm:col-span-2">
                    <dt class="text-xs font-bold uppercase tracking-wider text-brand-600">{{ t('booking.details.eventDays') }}</dt>
                    <dd class="mt-1 font-semibold text-ink-900">{{ siteSummary.days }}</dd>
                  </div>
                </dl>
              </section>

              <section
                v-if="attendancePolicy"
                class="rounded-2xl border border-cyan-100 bg-cyan-50/40 p-5"
                data-testid="vendor-attendance-policy"
              >
                <h3 class="font-bold text-ink-900">{{ t('booking.details.fullEventAttendance') }}</h3>
                <p v-if="!attendancePolicy.has_exception" class="mt-2 text-sm text-ink-700">
                  {{ t('booking.details.coversAllDays') }}
                </p>
                <template v-else>
                  <p class="mt-2 font-semibold text-cyan-900">{{ t('booking.details.attendanceException') }}</p>
                  <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl bg-white p-4 ring-1 ring-emerald-100" data-testid="vendor-retained-event-days">
                      <p class="text-xs font-bold uppercase text-emerald-700">{{ t('booking.details.retainedEventDays') }}</p>
                      <p v-for="day in attendancePolicy.retained_days" :key="day.id" class="mt-2 text-sm">
                        {{ formatAttendanceDay(day) }}
                      </p>
                    </div>
                    <div class="rounded-xl bg-white p-4 ring-1 ring-rose-100" data-testid="vendor-released-event-days">
                      <p class="text-xs font-bold uppercase text-rose-700">{{ t('booking.details.releasedEventDays') }}</p>
                      <p v-for="day in attendancePolicy.released_days" :key="day.id" class="mt-2 text-sm">
                        {{ formatAttendanceDay(day) }}
                      </p>
                    </div>
                  </div>
                  <p class="mt-3 text-sm text-ink-700">{{ t('booking.details.reasonPrefix') }} {{ attendancePolicy.reason }}</p>
                  <p class="mt-1 text-sm text-ink-700">
                    {{ t('booking.details.sitesUnchangedOnRetained', { labels: attendancePolicy.site_labels?.join(', ') || '—' }) }}
                  </p>
                  <p
                    v-if="attendancePolicy.no_refund_applied"
                    class="mt-2 text-sm font-semibold text-rose-700"
                    data-testid="vendor-attendance-no-refund-notice"
                  >
                    {{ t('booking.details.noRefundReleasedDays') }}
                  </p>
                </template>
              </section>

              <section v-if="booking.approval_status === 'Approved'" class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-5">
                <h3 class="font-bold text-emerald-900">{{ t('booking.details.boothAssignmentQr') }}</h3>
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div class="rounded-xl border border-emerald-100 bg-white p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-600">{{ t('booking.details.assignedBooth') }}</p>
                    <p class="mt-2 text-2xl font-black text-ink-900">{{ boothLabelForBooking(booking) }}</p>
                  </div>
                  <div
                    v-if="isBookingPaymentPaid(booking)"
                    class="flex flex-col items-center justify-center rounded-xl border border-emerald-100 bg-white p-4"
                  >
                    <div class="flex h-28 w-28 items-center justify-center rounded-xl border-2 border-dashed border-ink-300 bg-ink-50">
                      <svg class="h-20 w-20 text-ink-800" viewBox="0 0 100 100" fill="currentColor" aria-hidden="true">
                        <rect x="8" y="8" width="24" height="24" rx="2" />
                        <rect x="8" y="40" width="24" height="24" rx="2" />
                        <rect x="40" y="8" width="16" height="16" rx="2" />
                        <rect x="64" y="8" width="28" height="8" rx="2" />
                        <rect x="68" y="72" width="24" height="24" rx="2" />
                      </svg>
                    </div>
                    <p class="mt-2 text-xs font-bold uppercase tracking-wider text-ink-500">{{ t('booking.details.vendorQrPass') }}</p>
                    <p class="mt-1 text-xs text-emerald-700">{{ t('booking.details.viewPassInEventPasses') }}</p>
                  </div>
                  <div
                    v-else-if="isVendorPaymentPendingVerification(booking)"
                    class="flex flex-col items-center justify-center rounded-xl border border-amber-100 bg-amber-50/60 p-4 text-center"
                    data-testid="vendor-pass-pending-verification"
                  >
                    <p class="text-sm font-semibold text-amber-900">{{ t('booking.details.paymentSubmitted') }}</p>
                    <p class="mt-1 text-xs text-amber-800">{{ t('booking.details.waitingVerification') }}</p>
                  </div>
                  <div
                    v-else
                    class="flex flex-col items-center justify-center rounded-xl border border-amber-100 bg-amber-50/60 p-4 text-center"
                    data-testid="vendor-pass-locked-message"
                  >
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                      <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                      </svg>
                    </div>
                    <p class="mt-3 text-sm font-semibold text-amber-900">{{ t('booking.details.completePaymentUnlock') }}</p>
                  </div>
                </div>
              </section>

              <section
                v-if="canVendorAccessWhatsAppGroup(booking)"
                class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-5 space-y-3"
                data-testid="vendor-whatsapp-group-section"
              >
                <h3 class="font-bold text-emerald-900">{{ t('booking.details.whatsappGroup') }}</h3>
                <p class="text-sm text-emerald-800 leading-relaxed">
                  {{ t('booking.details.whatsappGroupBody') }}
                </p>
                <a
                  :href="VENDOR_WHATSAPP_GROUP_URL"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex ml-btn-primary"
                  data-testid="vendor-whatsapp-group-link"
                >
                  {{ t('booking.details.joinWhatsapp') }}
                </a>
              </section>

              <section
                v-if="isVendorPaymentLockedUntilApproval(booking)"
                class="rounded-xl border border-ink-200 bg-ink-50/70 p-5 space-y-2"
                data-testid="vendor-booking-payment-locked"
              >
                <h3 class="font-bold text-ink-900">{{ t('booking.details.paymentLockedTitle') }}</h3>
                <p class="text-sm text-ink-600 leading-relaxed">
                  {{ t('payment.availableAfterApproval') }}
                </p>
              </section>

              <section
                v-else-if="isVendorPaymentPendingVerification(booking)"
                class="rounded-xl border border-amber-200 bg-amber-50/50 p-5 space-y-2"
                data-testid="vendor-booking-payment-submitted"
              >
                <h3 class="font-bold text-ink-900">Payment submitted</h3>
                <p class="text-sm text-ink-600 leading-relaxed">
                  {{ t('booking.details.waitingVerification') }}
                </p>
              </section>

              <section
                v-else-if="canVendorPayBooking(booking)"
                class="rounded-xl border border-brand-200 bg-brand-50/50 p-5 space-y-3"
                data-testid="vendor-booking-payment-cta"
              >
                <h3 class="font-bold text-ink-900">{{ t('booking.details.paymentRequired') }}</h3>
                <p class="text-sm text-ink-600 leading-relaxed">
                  {{ t('booking.details.paymentRequiredBody') }}
                </p>
                <button
                  type="button"
                  class="ml-btn-primary"
                  data-testid="vendor-booking-proceed-payment"
                  @click="goToCheckout"
                >
                  {{ t('booking.details.proceedToPayment') }}
                </button>
              </section>

              <section>
                <h3 class="font-bold text-ink-900 mb-4">{{ t('booking.details.approvalTimeline') }}</h3>
                <div class="relative px-2">
                  <div class="absolute left-0 right-0 top-4 h-1 rounded-full bg-ink-200"></div>
                  <div
                    class="absolute left-0 top-4 h-1 rounded-full transition-all duration-700"
                    :class="progressBarClass(booking.approval_status)"
                    :style="{ width: progressWidth(booking.approval_status) }"
                  ></div>
                  <div class="relative grid grid-cols-3 gap-2">
                    <div v-for="step in PIPELINE_STEPS" :key="step.status" class="flex flex-col items-center text-center">
                      <div
                        class="h-9 w-9 rounded-full border-4 bg-white flex items-center justify-center text-xs font-extrabold"
                        :class="stepClass(booking.approval_status, step.status)"
                      >
                        {{ step.index }}
                      </div>
                      <div class="mt-2 text-xs font-semibold text-ink-700">{{ pipelineStepLabel(step, t) }}</div>
                    </div>
                  </div>
                </div>

                <ul v-if="booking.audit_logs?.length" class="mt-5 space-y-2">
                  <li
                    v-for="log in booking.audit_logs"
                    :key="log.id"
                    class="rounded-lg border border-ink-100 bg-ink-50/50 px-3 py-2 text-xs text-ink-600"
                  >
                    <span class="font-semibold text-ink-800">{{ log.actor?.name || t('booking.details.systemActor') }}</span>
                    · {{ log.from_status || '—' }} → {{ log.to_status || log.action }}
                    <span v-if="log.revision_comment"> — {{ log.revision_comment }}</span>
                  </li>
                </ul>
              </section>

              <section
                v-if="isWithdrawnBooking(booking)"
                class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-2"
                data-testid="vendor-booking-withdrawn-summary"
              >
                <h3 class="font-bold text-slate-800">{{ t('booking.details.withdrawnTitle') }}</h3>
                <p class="text-sm text-slate-700">
                  {{ t('booking.details.withdrawnOn', { date: formatWithdrawnDate(booking.withdrawn_at) }) }}
                </p>
                <p v-if="booking.withdrawal_reason" class="text-sm text-slate-600">
                  {{ t('booking.details.reasonPrefix') }} {{ booking.withdrawal_reason }}
                </p>
                <p
                  v-if="withdrawnNotice"
                  class="text-sm font-medium text-slate-800"
                  data-testid="vendor-booking-no-refund-notice"
                >
                  {{ withdrawnNotice }}
                </p>
                <p class="text-xs text-slate-500">
                  {{ t('booking.details.withdrawnCannotEdit') }}
                </p>
              </section>

              <section
                v-if="booking.approval_status === 'Needs_Revision'"
                class="rounded-xl border border-amber-200 bg-amber-50 p-4"
              >
                <h3 class="font-bold text-amber-900">{{ t('booking.details.revisionRequired') }}</h3>
                <p class="mt-1 text-sm text-amber-800">{{ booking.revision_comment || t('booking.details.revisionFallback') }}</p>
              </section>

              <section v-if="canVendorEdit(booking)" class="rounded-xl border border-brand-100 bg-brand-50/40 p-4 space-y-3">
                <h3 class="font-bold text-ink-900">{{ t('booking.details.editBooking') }}</h3>
                <div>
                  <label class="ml-label">{{ t('booking.details.category') }}</label>
                  <select v-model="editForm.product_category" class="ml-input">
                    <option v-for="cat in PRODUCT_CATEGORIES" :key="cat" :value="cat">{{ cat }}</option>
                  </select>
                </div>
                <div>
                  <label class="ml-label">{{ t('booking.details.productDetails') }}</label>
                  <textarea v-model="editForm.product_details" rows="3" class="ml-input"></textarea>
                </div>
                <button class="ml-btn-primary" :disabled="saving" @click="saveEdits">
                  {{ saving ? t('booking.details.saving') : t('booking.details.saveChanges') }}
                </button>
              </section>

              <section v-if="canVendorResubmit(booking)" class="rounded-xl border border-brand-100 bg-brand-50/40 p-4">
                <button class="ml-btn-primary" :disabled="saving" @click="resubmit">
                  {{ saving ? t('booking.details.submitting') : t('booking.details.resubmit') }}
                </button>
              </section>

              <section v-if="canVendorRequestChange(booking)" class="rounded-xl border border-ink-100 p-4 space-y-3">
                <h3 class="font-bold text-ink-900">{{ t('booking.details.requestChange') }}</h3>
                <textarea v-model="requestNote" rows="3" class="ml-input" :placeholder="t('booking.details.changePlaceholder')"></textarea>
                <button class="ml-btn-ghost" :disabled="saving" @click="submitRequest('change')">{{ t('booking.details.submitChangeRequest') }}</button>
              </section>

              <section v-if="canVendorRequestChange(booking)" class="rounded-xl border border-rose-100 bg-rose-50/40 p-4 space-y-3">
                <h3 class="font-bold text-rose-900">{{ t('booking.details.requestCancellation') }}</h3>
                <textarea v-model="requestNote" rows="3" class="ml-input" :placeholder="t('booking.details.cancelPlaceholder')"></textarea>
                <button class="ml-btn-ghost text-rose-700" :disabled="saving" @click="submitRequest('cancellation')">
                  {{ t('booking.details.submitCancellationRequest') }}
                </button>
              </section>

              <div class="flex flex-wrap gap-3 pt-2 border-t border-ink-100">
                <button class="ml-btn-ghost" @click="viewPdf">{{ t('booking.details.downloadPdf') }}</button>
                <button
                  v-if="canVendorWithdraw(booking)"
                  class="ml-btn-ghost text-rose-700"
                  data-testid="vendor-booking-action-withdraw"
                  :disabled="saving"
                  @click="openWithdrawModal"
                >
                  {{ t('booking.details.withdrawBooking') }}
                </button>
                <button class="ml-btn-ghost" @click="close">{{ t('booking.details.close') }}</button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>

  <WithdrawBookingModal
    v-model="showWithdrawModal"
    :booking="booking"
    @confirm="handleWithdrawConfirm"
  />
</template>

<script setup>
import { computed, reactive, ref, watch, onUnmounted, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useToast } from 'vue-toastification';
import api from '../services/api';
import WithdrawBookingModal from './WithdrawBookingModal.vue';
import {
  PIPELINE_STEPS,
  pipelineStepLabel,
  PRODUCT_CATEGORIES,
  boothLabelForBooking,
  boothTypeLabel,
  canVendorAccessWhatsAppGroup,
  canVendorEdit,
  canVendorPayBooking,
  canVendorRequestChange,
  canVendorResubmit,
  canVendorWithdraw,
  formatBookingDate,
  formatBookingReference,
  formatWithdrawnDate,
  isBookingPaymentPaid,
  isVendorPaymentLockedUntilApproval,
  isVendorPaymentPendingVerification,
  isWithdrawnBooking,
  vendorPaymentBlockedMessage,
  vendorPaymentStatusLabel,
  withdrawnNoRefundNotice,
  VENDOR_WHATSAPP_GROUP_URL,
  progressBarClass,
  progressWidth,
  statusBadgeClass,
  statusLabel,
  stepClass,
  siteSelectionSummary,
} from '../utils/bookingDisplay';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  bookingId: { type: [Number, String], default: null },
});

const emit = defineEmits(['update:modelValue', 'refreshed']);

const toast = useToast();
const { t } = useI18n();
const router = useRouter();
const panelRef = ref(null);
const booking = ref(null);
const loading = ref(false);
const saving = ref(false);
const showWithdrawModal = ref(false);
const requestNote = ref('');
const editForm = reactive({
  product_category: '',
  product_details: '',
});

const titleId = computed(() =>
  props.bookingId ? `vendor-booking-modal-${props.bookingId}` : 'vendor-booking-modal',
);

const siteSummary = computed(() => siteSelectionSummary(booking.value));
const withdrawnNotice = computed(() => withdrawnNoRefundNotice(booking.value));
const attendancePolicy = computed(() => booking.value?.attendance_policy || null);
const formatAttendanceDay = (day) => {
  const date = new Date(day.starts_at || `${day.operational_date}T00:00:00`);
  if (Number.isNaN(date.getTime())) return day.operational_date;
  return date.toLocaleString('en-GB', { dateStyle: 'full', timeStyle: 'short' });
};

const close = () => emit('update:modelValue', false);

const goToCheckout = () => {
  if (!canVendorPayBooking(booking.value)) {
    toast.error(vendorPaymentBlockedMessage(booking.value));
    return;
  }
  const id = props.bookingId;
  close();
  router.push(`/dashboard/checkout/${id}`);
};

const populateEditForm = () => {
  if (!booking.value) return;
  editForm.product_category = booking.value.product_category || 'Others';
  editForm.product_details = booking.value.product_details || '';
};

const loadBooking = async () => {
  if (!props.bookingId) return;
  loading.value = true;
  try {
    const { data } = await api.get(`/vendor/bookings/${props.bookingId}`);
    booking.value = {
      ...data,
      audit_logs: data.audit_logs || data.auditLogs || [],
    };
    populateEditForm();
  } catch (error) {
    toast.error(error.response?.data?.message || t('booking.details.toastUnableLoad'));
    close();
  } finally {
    loading.value = false;
  }
};

const refreshParent = () => {
  emit('refreshed');
  loadBooking();
};

const saveEdits = async () => {
  saving.value = true;
  try {
    const { data } = await api.patch(`/vendor/bookings/${props.bookingId}`, { ...editForm });
    toast.success(data.message || t('booking.details.toastUpdated'));
    refreshParent();
  } catch (error) {
    toast.error(error.response?.data?.message || t('booking.details.toastUnableUpdate'));
  } finally {
    saving.value = false;
  }
};

const resubmit = async () => {
  saving.value = true;
  try {
    const { data } = await api.patch(`/vendor/bookings/${props.bookingId}/resubmit`, {});
    toast.success(data.message || t('booking.details.toastResubmitted'));
    refreshParent();
  } catch (error) {
    toast.error(error.response?.data?.message || t('booking.details.toastUnableResubmit'));
  } finally {
    saving.value = false;
  }
};

const openWithdrawModal = () => {
  showWithdrawModal.value = true;
};

const handleWithdrawConfirm = async ({
  withdrawal_reason,
  acknowledge_no_refund,
  setSubmitting,
  setError,
  close: closeWithdrawModal,
}) => {
  setSubmitting(true);
  try {
    const payload = { withdrawal_reason: withdrawal_reason || null };
    if (acknowledge_no_refund) {
      payload.acknowledge_no_refund = true;
    }

    const { data } = await api.patch(`/bookings/${props.bookingId}/withdraw`, payload);
    booking.value = {
      ...data.booking,
      audit_logs: data.booking.audit_logs || data.booking.auditLogs || [],
    };
    toast.success(data.message || t('booking.details.toastWithdrawn'));
    closeWithdrawModal();
    emit('refreshed');
  } catch (error) {
    setError(error.response?.data?.message || t('booking.details.toastUnableWithdraw'));
  } finally {
    setSubmitting(false);
  }
};

const submitRequest = async (type) => {
  if (!requestNote.value.trim()) {
    toast.error(t('booking.details.toastNoteRequired'));
    return;
  }
  saving.value = true;
  const endpoint =
    type === 'change'
      ? `/vendor/bookings/${props.bookingId}/request-change`
      : `/vendor/bookings/${props.bookingId}/request-cancellation`;
  try {
    const { data } = await api.post(endpoint, { note: requestNote.value.trim() });
    toast.success(data.message || t('booking.details.toastRequestSubmitted'));
    requestNote.value = '';
    refreshParent();
  } catch (error) {
    toast.error(error.response?.data?.message || t('booking.details.toastUnableRequest'));
  } finally {
    saving.value = false;
  }
};

const viewPdf = async () => {
  try {
    const response = await api.get(`/bookings/${props.bookingId}/pdf`, { responseType: 'blob' });
    const file = new Blob([response.data], { type: 'application/pdf' });
    const fileUrl = URL.createObjectURL(file);
    window.open(fileUrl, '_blank', 'noopener,noreferrer');
    setTimeout(() => URL.revokeObjectURL(fileUrl), 60000);
  } catch {
    toast.error(t('booking.details.toastUnablePdf'));
  }
};

const onEscape = (event) => {
  if (event.key === 'Escape' && props.modelValue) close();
};

watch(
  () => [props.modelValue, props.bookingId],
  async ([open]) => {
    if (open) {
      document.body.style.overflow = 'hidden';
      document.addEventListener('keydown', onEscape);
      requestNote.value = '';
      await loadBooking();
      await nextTick();
      panelRef.value?.focus();
    } else {
      document.body.style.overflow = '';
      document.removeEventListener('keydown', onEscape);
      booking.value = null;
    }
  },
);

onUnmounted(() => {
  document.body.style.overflow = '';
  document.removeEventListener('keydown', onEscape);
});
</script>
