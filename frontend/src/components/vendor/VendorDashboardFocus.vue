<template>
  <section
    class="rounded-2xl border border-brand-100 bg-white p-5 sm:p-6 shadow-sm"
    data-testid="vendor-dashboard-focus"
  >
    <div class="flex flex-col gap-5">
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div class="min-w-0">
          <p class="text-xs font-bold uppercase tracking-wider text-brand-600">
            {{ focusEyebrow }}
          </p>
          <h2 class="mt-1 text-xl sm:text-2xl font-extrabold text-ink-900 tracking-tight truncate">
            {{ focusTitle }}
          </h2>
          <p class="mt-1 text-sm text-ink-500">
            {{ focusSubtitle }}
          </p>
        </div>
        <button
          v-if="primaryAction"
          type="button"
          class="inline-flex items-center justify-center gap-2 rounded-xl px-5 py-3 min-h-[44px] text-[15px] font-bold text-white shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 transition shrink-0"
          :class="primaryAction.emphasis === 'payment'
            ? 'bg-amber-500 shadow-amber-500/25 hover:bg-amber-600'
            : 'bg-brand-500 shadow-brand-500/20 hover:bg-brand-600'"
          data-testid="vendor-focus-primary-action"
          @click="$emit('primary-action', primaryAction)"
        >
          {{ primaryAction.label }}
        </button>
        <router-link
          v-else
          to="/vendor-booking"
          class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-5 py-3 min-h-[44px] text-[15px] font-bold text-white shadow-md shadow-brand-500/20 hover:bg-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 transition shrink-0"
          data-testid="vendor-focus-primary-action"
        >
          Start Vendor Booking
        </router-link>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <article
          v-for="card in statusCards"
          :key="card.key"
          class="rounded-xl border px-4 py-4"
          :class="card.key === 'payment' && paymentState.actionable
            ? 'border-amber-200 bg-amber-50/70'
            : 'border-ink-100 bg-ink-50/60'"
          :data-testid="card.key === 'payment' ? 'vendor-focus-payment-card' : undefined"
        >
          <div class="flex items-center gap-2">
            <span
              class="inline-flex h-9 w-9 items-center justify-center rounded-lg border bg-white"
              :class="card.iconWrap"
              aria-hidden="true"
            >
              <component :is="card.icon" />
            </span>
            <p class="text-xs font-bold uppercase tracking-wide text-ink-500">{{ card.label }}</p>
          </div>
          <p class="mt-3 text-lg font-extrabold text-ink-900 leading-snug break-words">
            {{ card.value }}
          </p>
          <p v-if="card.hint" class="mt-1 text-xs text-ink-500">{{ card.hint }}</p>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, h } from 'vue';
import {
  canVendorProceedToDemoPayment,
  formatBookingDate,
  isBookingPaymentPaid,
  isTerminalBookingStatus,
  isValidBookingDate,
  normalizePaymentStatus,
  PENDING_STATUSES,
  siteLabelsForBooking,
  statusLabel,
} from '../../utils/bookingDisplay';

const props = defineProps({
  booking: { type: Object, default: null },
  /** Matching history-receipts row for the focus booking, when available. */
  paymentRecord: { type: Object, default: null },
  boothStatus: { type: String, default: null },
  boothNumber: { type: String, default: null },
  currentEventLabel: { type: String, default: null },
  loading: { type: Boolean, default: false },
});

defineEmits(['primary-action']);

const MY_TZ = 'Asia/Kuala_Lumpur';

const todayKey = () => new Date().toLocaleDateString('en-CA', { timeZone: MY_TZ });

const bookingDateKey = (booking) => {
  const raw = String(booking?.booking_date || '').slice(0, 10);
  return isValidBookingDate(raw) ? raw : null;
};

const icon = (path) => () =>
  h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: path }),
  ]);

const eventIcon = icon('M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z');
const statusIcon = icon('M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z');
const paymentIcon = icon('M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z');

const formatAmountLabel = (amount) => {
  if (amount == null || amount === '') return null;
  const numeric = Number(amount);
  if (Number.isNaN(numeric)) return null;
  return `RM${numeric.toFixed(2)}`;
};

const resolvedPaymentStatus = computed(() => {
  const fromRecord = String(props.paymentRecord?.payment_status || '').trim();
  if (fromRecord) return fromRecord;
  return normalizePaymentStatus(props.booking);
});

const resolvedAmount = computed(() => {
  if (props.paymentRecord?.amount != null && props.paymentRecord.amount !== '') {
    return props.paymentRecord.amount;
  }
  return props.booking?.invoice?.amount ?? null;
});

const paymentState = computed(() => {
  const booking = props.booking;
  if (!booking) {
    return {
      actionable: false,
      complete: false,
      value: 'Not started',
      hint: null,
      statusKey: 'none',
    };
  }

  if (isTerminalBookingStatus(booking.approval_status)) {
    return {
      actionable: false,
      complete: false,
      value: 'Not applicable',
      hint: statusLabel(booking.approval_status),
      statusKey: 'terminal',
    };
  }

  const status = resolvedPaymentStatus.value;
  const statusLower = status.toLowerCase();
  const amountLabel = formatAmountLabel(resolvedAmount.value);
  const canPay = canVendorProceedToDemoPayment(booking)
    || (
      booking.approval_status === 'Approved'
      && props.paymentRecord?.invoice_available
      && statusLower === 'unpaid'
    );

  if (canPay || statusLower === 'unpaid' || statusLower === 'failed') {
    return {
      actionable: true,
      complete: false,
      value: status || 'Unpaid',
      hint: amountLabel ? `${amountLabel} due` : 'Payment required',
      statusKey: 'unpaid',
      amount: resolvedAmount.value,
      canPay: true,
    };
  }

  if (statusLower === 'pending verification' || statusLower.includes('pending')) {
    return {
      actionable: true,
      complete: false,
      value: status || 'Pending verification',
      hint: amountLabel || 'Awaiting organizer check',
      statusKey: 'pending',
      amount: resolvedAmount.value,
      canPay: false,
      canViewInvoice: Boolean(props.paymentRecord?.invoice_available || booking.invoice),
    };
  }

  if (isBookingPaymentPaid(booking) || statusLower === 'paid') {
    return {
      actionable: false,
      complete: true,
      value: 'Payment complete',
      hint: amountLabel,
      statusKey: 'paid',
      amount: resolvedAmount.value,
      canViewReceipt: Boolean(props.paymentRecord?.receipt_available),
      canViewInvoice: Boolean(props.paymentRecord?.invoice_available || booking.invoice),
    };
  }

  if (booking.approval_status === 'Approved' && !status) {
    return {
      actionable: false,
      complete: false,
      value: 'Awaiting invoice',
      hint: 'Check again after approval processing',
      statusKey: 'awaiting',
    };
  }

  if (!status) {
    return {
      actionable: false,
      complete: false,
      value: 'Not due yet',
      hint: 'Pay after approval',
      statusKey: 'not_due',
    };
  }

  return {
    actionable: false,
    complete: false,
    value: status,
    hint: amountLabel,
    statusKey: 'other',
  };
});

const focusEyebrow = computed(() => {
  if (props.loading) return 'Loading';
  if (!props.booking) return 'Get started';
  const key = bookingDateKey(props.booking);
  if (key && key === todayKey()) return "Today's event";
  if (key && key > todayKey()) return 'Upcoming booking';
  return 'Latest booking';
});

const focusTitle = computed(() => {
  if (!props.booking) return 'No upcoming booth yet';
  return (
    props.booking.event_label
    || props.booking.carboot_event?.title
    || props.currentEventLabel
    || formatBookingDate(props.booking.booking_date)
    || `Booking #${props.booking.id}`
  );
});

const focusSubtitle = computed(() => {
  if (!props.booking) return 'Book a booth space to appear at the next CMart car boot.';
  const date = formatBookingDate(props.booking.booking_date);
  const site = siteLabelsForBooking(props.booking) || props.boothNumber;
  if (site) return `${date} · Site ${site}`;
  return date;
});

const statusCards = computed(() => [
  {
    key: 'event',
    label: 'Event',
    value: props.booking
      ? (props.currentEventLabel || focusTitle.value)
      : 'None scheduled',
    hint: props.booking ? formatBookingDate(props.booking.booking_date) : 'Book to get started',
    icon: eventIcon,
    iconWrap: 'text-brand-600 border-brand-100',
  },
  {
    key: 'booking',
    label: 'Booking status',
    value: props.booking
      ? statusLabel(props.booking.approval_status)
      : (props.boothStatus || 'No active booking'),
    hint: props.boothNumber ? `Booth ${props.boothNumber}` : null,
    icon: statusIcon,
    iconWrap: 'text-sky-700 border-sky-100',
  },
  {
    key: 'payment',
    label: 'Payment',
    value: paymentState.value.value,
    hint: paymentState.value.hint,
    icon: paymentIcon,
    iconWrap: paymentState.value.actionable
      ? 'text-amber-700 border-amber-200'
      : paymentState.value.complete
        ? 'text-emerald-700 border-emerald-100'
        : 'text-emerald-700 border-emerald-100',
  },
]);

const primaryAction = computed(() => {
  const booking = props.booking;
  if (!booking?.id) return null;

  if (booking.approval_status === 'Needs_Revision') {
    return { type: 'view-booking', bookingId: booking.id, label: 'Review Booking' };
  }

  const payment = paymentState.value;
  if (payment.canPay) {
    return {
      type: 'pay',
      bookingId: booking.id,
      amount: payment.amount ?? resolvedAmount.value,
      label: 'Pay Now',
      emphasis: 'payment',
    };
  }

  if (payment.statusKey === 'pending' && payment.canViewInvoice) {
    return {
      type: 'view-document',
      bookingId: booking.id,
      label: 'View Invoice',
      emphasis: 'payment',
    };
  }

  if (payment.complete && payment.canViewReceipt) {
    return {
      type: 'view-document',
      bookingId: booking.id,
      label: 'View Receipt',
    };
  }

  if (payment.complete && payment.canViewInvoice) {
    return {
      type: 'view-document',
      bookingId: booking.id,
      label: 'View Invoice',
    };
  }

  if (PENDING_STATUSES.includes(booking.approval_status)) {
    return { type: 'view-booking', bookingId: booking.id, label: 'View Booking' };
  }

  return { type: 'view-booking', bookingId: booking.id, label: 'View Details' };
});
</script>
