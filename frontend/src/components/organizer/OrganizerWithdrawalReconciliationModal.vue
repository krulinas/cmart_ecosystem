<template>
  <Teleport to="body">
    <div
      v-if="modelValue"
      class="fixed inset-0 z-[130] flex items-end justify-center p-0 sm:items-center sm:p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="organizer-reconciliation-title"
      @keydown.esc="close"
    >
      <div class="absolute inset-0 bg-[rgba(15,23,42,0.65)] backdrop-blur-[4px]" @click="close" />
      <div
        class="relative z-10 max-h-[94vh] w-full overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:max-w-4xl sm:rounded-2xl"
        data-testid="organizer-booking-details-modal"
      >
        <header class="sticky top-0 z-10 flex items-center justify-between border-b border-ink-100 bg-white/95 px-5 py-4 backdrop-blur">
          <div>
            <p class="text-xs font-bold uppercase tracking-wider text-cyan-700">Organizer read-only view</p>
            <h2 id="organizer-reconciliation-title" class="text-lg font-extrabold text-ink-900">
              Booking #{{ booking?.id || bookingId }}
            </h2>
          </div>
          <button type="button" class="ml-btn-ghost" aria-label="Close reconciliation" @click="close">Close</button>
        </header>

        <div v-if="loading" class="p-12 text-center text-sm text-ink-500" data-testid="organizer-booking-details-loading">
          Loading booking audit and reconciliation…
        </div>
        <div v-else-if="error" class="p-8 text-center text-sm text-rose-700">
          {{ error }}
        </div>
        <div v-else-if="booking" class="space-y-6 p-5 sm:p-6">
          <section
            v-if="reconciliation"
            class="rounded-2xl border border-slate-200 bg-slate-50 p-5"
            data-testid="organizer-withdrawal-reconciliation"
          >
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div>
                <h3 class="font-extrabold text-slate-900">Withdrawal &amp; Payment Reconciliation</h3>
                <p class="mt-1 text-sm text-slate-600">
                  Operational withdrawal only — payment and Invoice history remain unchanged.
                </p>
              </div>
              <span class="ml-badge bg-slate-200 text-slate-800">Withdrawn</span>
            </div>

            <dl class="mt-5 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
              <div class="rounded-xl bg-white p-3 ring-1 ring-slate-200">
                <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Withdrawn by</dt>
                <dd class="mt-1 font-semibold text-ink-900">{{ reconciliation.withdrawn_by?.name || 'Unknown actor' }}</dd>
                <dd class="text-xs text-ink-500">{{ formatDateTime(reconciliation.withdrawn_at) }}</dd>
              </div>
              <div class="rounded-xl bg-white p-3 ring-1 ring-slate-200">
                <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Payment</dt>
                <dd class="mt-1 font-semibold text-ink-900">{{ paymentStateLabel(reconciliation.payment_state) }}</dd>
                <dd class="text-xs text-ink-500">Invoice RM {{ reconciliation.invoice_amount || '0.00' }}</dd>
              </div>
              <div class="rounded-xl bg-white p-3 ring-1 ring-slate-200">
                <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Payment proof</dt>
                <dd class="mt-1 font-semibold text-ink-900">
                  {{ reconciliation.payment_proof_present ? 'Submitted' : 'Not submitted' }}
                </dd>
                <dd class="text-xs text-ink-500">
                  {{ reconciliation.payment_verified ? 'Payment verified' : 'Verification not completed' }}
                </dd>
              </div>
              <div class="rounded-xl bg-white p-3 ring-1 ring-slate-200">
                <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Refund</dt>
                <dd
                  class="mt-1 font-semibold"
                  :class="reconciliation.no_refund_applied ? 'text-rose-700' : 'text-ink-700'"
                  data-testid="organizer-no-refund-indicator"
                >
                  {{ reconciliation.no_refund_applied ? 'None · No-refund policy applied' : 'Not applicable' }}
                </dd>
              </div>
              <div class="rounded-xl bg-white p-3 ring-1 ring-slate-200">
                <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Allocation</dt>
                <dd class="mt-1 font-semibold text-ink-900">{{ allocationStatusLabel(reconciliation.allocation_status) }}</dd>
                <dd class="text-xs text-ink-500">{{ reconciliation.active_day_count }} EventDays</dd>
              </div>
              <div class="rounded-xl bg-white p-3 ring-1 ring-slate-200">
                <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Financial history</dt>
                <dd class="mt-1 font-semibold text-ink-900">
                  {{ reconciliation.financial_history_preserved ? 'Preserved' : 'No Invoice record' }}
                </dd>
              </div>
            </dl>

            <div class="mt-4 rounded-xl bg-white p-4 ring-1 ring-slate-200" data-testid="organizer-released-sites">
              <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Released physical sites</p>
              <p class="mt-1 font-extrabold text-ink-900">
                {{ reconciliation.released_site_labels?.join(', ') || 'No physical sites recorded' }}
              </p>
              <p class="mt-2 text-xs text-ink-500">
                EventDays:
                {{ eventDaySummary(reconciliation.event_days) }}
              </p>
            </div>
          </section>

          <section class="rounded-2xl border border-ink-200 bg-white p-5">
            <h3 class="font-extrabold text-ink-900">Booking Audit Timeline</h3>
            <p class="mt-1 text-sm text-ink-500">Read-only lifecycle events, oldest to newest.</p>

            <ol
              v-if="booking.audit_timeline?.length"
              class="mt-5 space-y-3"
              data-testid="organizer-booking-audit-timeline"
            >
              <li
                v-for="item in booking.audit_timeline"
                :key="item.id"
                class="rounded-xl border border-ink-100 bg-ink-50/50 p-4"
                data-testid="organizer-booking-audit-item"
                :data-audit-action="item.action"
              >
                <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                  <p class="font-bold text-ink-900">{{ item.label || 'Booking activity recorded' }}</p>
                  <time class="text-xs text-ink-500">{{ formatDateTime(item.occurred_at) }}</time>
                </div>
                <p class="mt-1 text-sm text-ink-600">
                  {{ item.actor?.name || 'System' }}
                  <span v-if="item.previous_status || item.new_status">
                    · {{ item.previous_status || '—' }} → {{ item.new_status || '—' }}
                  </span>
                </p>
                <p class="mt-2 text-sm text-ink-700">{{ item.summary || 'Booking activity was recorded' }}</p>
              </li>
            </ol>
            <p v-else class="mt-5 rounded-xl border border-dashed border-ink-200 p-6 text-center text-sm text-ink-500">
              No booking audit events are available.
            </p>
          </section>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue';
import { allocationStatusLabel, organizerPaymentStateLabel } from '../../utils/bookingDisplay';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  bookingId: { type: [Number, String], default: null },
  booking: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);
const reconciliation = computed(() => props.booking?.withdrawal_reconciliation || null);
const close = () => emit('update:modelValue', false);
const paymentStateLabel = (state) => organizerPaymentStateLabel(state);

const formatDateTime = (value) => {
  if (!value) return 'Not recorded';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'Not recorded';
  return date.toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' });
};

const eventDaySummary = (days) => {
  if (!Array.isArray(days) || !days.length) return 'None recorded';
  return days.map((day) => day.operational_date).filter(Boolean).join(', ');
};
</script>
