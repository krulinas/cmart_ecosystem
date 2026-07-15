<template>
  <Teleport to="body">
    <div
      v-if="modelValue && booking"
      class="fixed inset-0 z-[150] flex items-end justify-center p-0 sm:items-center sm:p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="attendance-exception-title"
      @keydown.esc="close"
    >
      <div class="absolute inset-0 bg-[rgba(15,23,42,0.72)] backdrop-blur-[4px]" @click="close" />
      <div
        class="relative z-10 max-h-[94vh] w-full overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:max-w-2xl sm:rounded-2xl"
        data-testid="organizer-attendance-exception-modal"
      >
        <header class="sticky top-0 z-10 flex items-center justify-between border-b border-ink-100 bg-white px-5 py-4">
          <div>
            <p class="text-xs font-bold uppercase tracking-wider text-cyan-700">Full-Event Attendance</p>
            <h2 id="attendance-exception-title" class="text-lg font-extrabold text-ink-900">
              Apply Attendance Exception
            </h2>
          </div>
          <button type="button" class="ml-btn-ghost" :disabled="submitting" @click="close">Cancel</button>
        </header>

        <div class="space-y-5 p-5 sm:p-6">
          <p class="text-sm text-ink-600">
            Select every EventDay that will remain assigned. Physical sites
            <strong>{{ policy.site_labels?.join(', ') || 'remain unchanged' }}</strong>
            stay the same on all retained days.
          </p>

          <fieldset class="space-y-2" data-testid="attendance-event-day-list">
            <legend class="text-sm font-bold text-ink-900">Operational EventDays</legend>
            <label
              v-for="day in allDays"
              :key="day.id"
              class="flex items-start gap-3 rounded-xl border p-3"
              :class="isDayDisabled(day) ? 'border-ink-100 bg-ink-50 text-ink-500' : 'border-ink-200 bg-white'"
              data-testid="attendance-event-day-option"
              :data-event-day-id="day.id"
            >
              <input
                v-model="retainedDayIds"
                type="checkbox"
                :value="day.id"
                :disabled="isDayDisabled(day) || submitting"
                class="mt-1 h-4 w-4 rounded border-ink-300 text-cyan-700"
              />
              <span class="min-w-0">
                <span class="block font-semibold">{{ formatDay(day) }}</span>
                <span class="block text-xs">
                  {{ dayStateLabel(day) }}
                </span>
              </span>
            </label>
          </fieldset>

          <div class="grid grid-cols-2 gap-3 text-sm">
            <div class="rounded-xl bg-emerald-50 p-3 text-emerald-900" data-testid="attendance-retained-count">
              <span class="block text-xs font-bold uppercase">Retained</span>
              <strong>{{ retainedDayIds.length }} days</strong>
            </div>
            <div class="rounded-xl bg-rose-50 p-3 text-rose-900" data-testid="attendance-released-count">
              <span class="block text-xs font-bold uppercase">To release</span>
              <strong>{{ releaseCount }} days</strong>
            </div>
          </div>

          <div class="rounded-xl border border-ink-200 bg-ink-50 p-4 text-sm">
            <p><strong>Payment:</strong> {{ paymentLabel }}</p>
            <p><strong>Invoice:</strong> RM {{ booking.invoice?.amount || '0.00' }}</p>
          </div>

          <div>
            <label for="attendance-reason" class="ml-label">Reason</label>
            <textarea
              id="attendance-reason"
              v-model="reason"
              rows="4"
              maxlength="1000"
              class="ml-input"
              placeholder="Explain the operational reason for reducing attendance days."
              data-testid="attendance-exception-reason"
              :disabled="submitting"
            />
          </div>

          <div
            v-if="requiresAcknowledgement"
            class="rounded-xl border border-amber-200 bg-amber-50 p-4"
            data-testid="attendance-no-refund-warning"
          >
            <p class="text-sm font-bold text-amber-900">{{ policy.no_refund_warning }}</p>
            <label class="mt-3 flex items-start gap-3 text-sm text-amber-900">
              <input
                v-model="acknowledged"
                type="checkbox"
                class="mt-1 h-4 w-4 rounded"
                data-testid="attendance-no-refund-acknowledgement"
                :disabled="submitting"
              />
              <span>I acknowledge that the Invoice amount remains unchanged and no refund will be issued.</span>
            </label>
          </div>

          <p v-if="validationError" class="text-sm font-semibold text-rose-700" data-testid="attendance-validation-error">
            {{ validationError }}
          </p>
          <p v-if="apiError" class="text-sm font-semibold text-rose-700" data-testid="attendance-api-error">
            {{ apiError }}
          </p>

          <div class="flex justify-end gap-3 border-t border-ink-100 pt-4">
            <button type="button" class="ml-btn-ghost" :disabled="submitting" @click="close">Cancel</button>
            <button
              type="button"
              class="ml-btn-primary"
              data-testid="attendance-exception-confirm"
              :disabled="!canSubmit"
              @click="submit"
            >
              {{ submitting ? 'Applying…' : 'Confirm Exception' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import api from '../../services/api';
import {
  attendanceExceptionValidation,
  attendanceReleaseCount,
  attendanceRetainedDayIds,
  organizerPaymentStateLabel,
} from '../../utils/bookingDisplay';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  booking: { type: Object, default: null },
});
const emit = defineEmits(['update:modelValue', 'applied']);

const retainedDayIds = ref([]);
const reason = ref('');
const acknowledged = ref(false);
const submitting = ref(false);
const apiError = ref('');

const policy = computed(() => props.booking?.attendance_policy || {});
const allDays = computed(() => [
  ...(policy.value.retained_days || []),
  ...(policy.value.released_days || []),
].sort((a, b) => String(a.starts_at || a.operational_date).localeCompare(String(b.starts_at || b.operational_date))));
const requiresAcknowledgement = computed(() => Boolean(policy.value.requires_no_refund_acknowledgement));
const releaseCount = computed(() => attendanceReleaseCount(policy.value, retainedDayIds.value));
const paymentLabel = computed(() => organizerPaymentStateLabel(policy.value.payment_state));
const validationError = computed(() =>
  attendanceExceptionValidation(policy.value, retainedDayIds.value, reason.value, acknowledged.value),
);
const canSubmit = computed(() => !submitting.value && !validationError.value);

const reset = () => {
  retainedDayIds.value = attendanceRetainedDayIds(policy.value);
  reason.value = '';
  acknowledged.value = false;
  apiError.value = '';
};

watch(() => [props.modelValue, props.booking?.id], ([open]) => {
  if (open) reset();
});

const isDayDisabled = (day) =>
  (policy.value.released_days || []).some((released) => released.id === day.id)
  || (day.has_started && retainedDayIds.value.includes(day.id));
const dayStateLabel = (day) => {
  if ((policy.value.released_days || []).some((released) => released.id === day.id)) return 'Already released · cannot be re-added';
  if (day.has_started) return 'Started or completed · must remain retained';
  return 'Future day · may be released';
};
const formatDay = (day) => {
  const start = new Date(day.starts_at || `${day.operational_date}T00:00:00`);
  if (Number.isNaN(start.getTime())) return day.operational_date;
  return start.toLocaleString('en-GB', { dateStyle: 'full', timeStyle: 'short' });
};
const close = () => {
  if (!submitting.value) emit('update:modelValue', false);
};
const submit = async () => {
  if (!canSubmit.value) return;
  submitting.value = true;
  apiError.value = '';
  try {
    const { data } = await api.patch(
      `/organizer/bookings/${props.booking.id}/attendance-exception`,
      {
        retained_event_day_ids: retainedDayIds.value,
        reason: reason.value.trim(),
        acknowledge_no_refund: acknowledged.value,
      },
    );
    emit('applied', data.booking);
    emit('update:modelValue', false);
  } catch (error) {
    apiError.value = error.response?.data?.message
      || Object.values(error.response?.data?.errors || {})?.[0]?.[0]
      || 'Unable to apply attendance exception.';
  } finally {
    submitting.value = false;
  }
};
</script>
