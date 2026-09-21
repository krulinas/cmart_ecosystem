<template>
  <Teleport to="body">
    <div
      v-if="modelValue && item"
      class="fixed inset-0 z-[135] flex items-end justify-center p-0 sm:items-center sm:p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="recovery-detail-title"
      data-testid="recovery-detail-modal"
      @keydown.esc="close"
    >
      <div class="absolute inset-0 bg-[rgba(15,23,42,0.65)] backdrop-blur-[4px]" @click="close" />
      <div class="relative z-10 max-h-[94vh] w-full overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:max-w-3xl sm:rounded-2xl">
        <header class="sticky top-0 z-10 flex items-center justify-between border-b border-ink-100 bg-white/95 px-5 py-4 backdrop-blur">
          <div>
            <p class="text-xs font-bold uppercase tracking-wider text-cyan-700">{{ t('organizer.recovery.detailEyebrow') }}</p>
            <h2 id="recovery-detail-title" class="text-lg font-extrabold text-ink-900">{{ t('organizer.recovery.detailTitle') }}</h2>
          </div>
          <button type="button" class="ml-btn-ghost" :aria-label="t('organizer.recovery.closeAria')" @click="close">{{ t('common.close') }}</button>
        </header>

        <div class="space-y-6 p-5 sm:p-6">
          <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div>
                <h3 class="font-extrabold text-slate-900">{{ item.event?.title || t('organizer.recovery.eventFallback') }}</h3>
                <p class="mt-1 text-sm text-slate-600">
                  {{ t('organizer.recovery.releasedDay', { date: formatOperationalDate(item.event_day?.operational_date) }) }}
                </p>
              </div>
              <span :class="recoveryStateBadgeClass(item.recovery_state)" data-testid="recovery-detail-state">
                {{ recoveryStateLabel(item.recovery_state, t) }}
              </span>
            </div>

            <dl class="mt-5 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
              <div class="rounded-xl bg-white p-3 ring-1 ring-slate-200">
                <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ t('organizer.recovery.sourceBooking') }}</dt>
                <dd class="mt-1 font-semibold text-ink-900">{{ formatBookingReference(item.source_booking?.id) || item.source_booking?.reference }}</dd>
                <dd class="text-xs text-ink-500">{{ statusLabel(item.source_booking?.status, t) }}</dd>
              </div>
              <div class="rounded-xl bg-white p-3 ring-1 ring-slate-200">
                <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ t('organizer.recovery.payment') }}</dt>
                <dd class="mt-1 font-semibold text-ink-900">{{ recoveryPaymentLabel(item.source_payment_state) }}</dd>
                <dd class="text-xs text-ink-500">{{ t('organizer.recovery.invoiceAmount', { amount: item.source_invoice_amount || '0.00' }) }}</dd>
              </div>
              <div class="rounded-xl bg-white p-3 ring-1 ring-slate-200">
                <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ t('organizer.recovery.release') }}</dt>
                <dd class="mt-1 font-semibold text-ink-900">{{ releaseReasonLabel(item.release?.reason, t) }}</dd>
                <dd class="text-xs text-ink-500" data-testid="recovery-detail-released-at">
                  {{ formatDateTime(item.release?.released_at) }}
                </dd>
              </div>
              <div class="rounded-xl bg-white p-3 ring-1 ring-slate-200">
                <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ t('organizer.recovery.releasedBy') }}</dt>
                <dd class="mt-1 font-semibold text-ink-900" data-testid="recovery-detail-released-by">
                  {{ item.release?.released_by?.name || t('organizer.recovery.unknownActor') }}
                </dd>
              </div>
            </dl>

            <div
              v-if="item.attendance_exception_reason"
              class="mt-4 rounded-xl bg-white p-4 ring-1 ring-slate-200"
              data-testid="recovery-exception-reason"
            >
              <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ t('organizer.recovery.exceptionReason') }}</p>
              <p class="mt-1 text-sm text-ink-800">{{ item.attendance_exception_reason }}</p>
            </div>
          </section>

          <section class="rounded-2xl border border-cyan-200 bg-cyan-50/40 p-5">
            <h3 class="font-extrabold text-ink-900">{{ t('organizer.recovery.releasedSites') }}</h3>
            <ul class="mt-3 space-y-2">
              <li
                v-for="site in item.released_sites || []"
                :key="site.id"
                class="rounded-xl bg-white px-4 py-3 ring-1 ring-cyan-100"
                data-testid="recovery-detail-site"
                :data-site-label="site.label"
                :data-site-recovery-state="site.recovery_state"
              >
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <p class="font-bold text-ink-900">{{ site.label }}</p>
                    <p class="text-xs text-ink-500">{{ site.space_name || t('common.none') }}</p>
                  </div>
                  <span :class="recoveryStateBadgeClass(site.recovery_state)">
                    {{ recoveryStateLabel(site.recovery_state, t) }}
                  </span>
                </div>
                <p v-if="site.blocker" class="mt-2 text-xs text-rose-700" data-testid="recovery-site-blocker">
                  {{ site.blocker }}
                </p>
                <p
                  v-else-if="site.recovery_state === 'recoverable'"
                  class="mt-2 text-xs text-emerald-700"
                  data-testid="recovery-recoverable-copy"
                >
                  {{ t('organizer.recovery.recoverableCopy') }}
                </p>
              </li>
            </ul>
          </section>

          <section class="rounded-2xl border border-ink-200 bg-white p-5">
            <h3 class="font-extrabold text-ink-900">{{ t('organizer.recovery.availabilityTitle') }}</h3>
            <p class="mt-2 text-sm text-ink-600" data-testid="recovery-standard-availability">
              {{
                item.standard_full_event_available
                  ? t('organizer.recovery.availableStandard')
                  : t('organizer.recovery.unavailableStandard')
              }}
            </p>
          </section>

          <section v-if="item.audit_timeline?.length" class="rounded-2xl border border-ink-200 bg-white p-5">
            <h3 class="font-extrabold text-ink-900">{{ t('organizer.recovery.auditTitle') }}</h3>
            <ul class="mt-3 space-y-2 text-sm text-ink-700">
              <li v-for="(entry, index) in item.audit_timeline" :key="index" data-testid="recovery-audit-entry">
                {{ entry.label }} · {{ formatDateTime(entry.occurred_at) }}
              </li>
            </ul>
          </section>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import {
  formatBookingReference,
  formatDateTime,
  formatOperationalDate,
  recoveryStateBadgeClass,
  recoveryStateLabel,
  recoveryPaymentLabel,
  releaseReasonLabel,
  statusLabel,
} from '../../utils/bookingDisplay';

const { t } = useI18n();

defineProps({
  modelValue: { type: Boolean, default: false },
  item: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue']);

const close = () => emit('update:modelValue', false);
</script>
