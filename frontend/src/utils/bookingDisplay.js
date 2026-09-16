const MY_TZ = 'Asia/Kuala_Lumpur';

export const PIPELINE_STEPS = [
  { index: 1, status: 'Submitted', label: 'Submitted' },
  { index: 2, status: 'Pending_Organizer', label: 'Organizer Review' },
  { index: 3, status: 'Approved', label: 'Approved' },
];

export const PENDING_STATUSES = ['Pending_Organizer', 'Needs_Revision'];

export const TERMINAL_BOOKING_STATUSES = ['Withdrawn', 'Rejected', 'Cancelled'];

export const isTerminalBookingStatus = (status) => TERMINAL_BOOKING_STATUSES.includes(status);

export const PRODUCT_CATEGORIES = [
  'Pre-loved / Thrift',
  'Food & Beverages',
  'Clothing & Apparel',
  'Handicrafts & Art',
  'Electronics & Gadgets',
  'Others',
];

export const FILTER_TABS = [
  { id: 'all', label: 'All' },
  { id: 'pending', label: 'Pending' },
  { id: 'approved', label: 'Approved' },
  { id: 'rejected', label: 'Rejected' },
  { id: 'withdrawn', label: 'Withdrawn' },
  { id: 'cancelled', label: 'Cancelled' },
];

export const isValidBookingDate = (dateStr) => {
  if (!dateStr) return false;
  if (String(dateStr).startsWith('0000')) return false;
  const date = new Date(dateStr);
  return !Number.isNaN(date.getTime()) && date.getFullYear() > 1970;
};

export const formatBookingDate = (dateStr) => {
  if (!isValidBookingDate(dateStr)) return '—';
  return new Date(dateStr).toLocaleDateString('en-GB', {
    timeZone: MY_TZ,
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
};

export const statusLabel = (status) =>
  ({
    Pending_Organizer: 'Pending Organizer Review',
    Needs_Revision: 'Needs Revision',
    Approved: 'Approved',
    Rejected: 'Rejected',
    Cancelled: 'Cancelled',
    Withdrawn: 'Withdrawn',
    Pending_Staff: 'Pending Organizer Review (legacy)',
    Pending_Boss: 'Pending Organizer Review (legacy)',
  }[status] || status);

export const statusBadgeClass = (status) =>
  ({
    Pending_Organizer: 'ml-badge bg-brand-100 text-brand-800',
    Needs_Revision: 'ml-badge bg-amber-100 text-amber-800',
    Approved: 'ml-badge bg-emerald-100 text-emerald-800',
    Rejected: 'ml-badge bg-rose-100 text-rose-800',
    Cancelled: 'ml-badge bg-ink-100 text-ink-700',
    Withdrawn: 'ml-badge bg-slate-100 text-slate-700 border border-slate-200',
    Pending_Staff: 'ml-badge bg-brand-100 text-brand-800',
    Pending_Boss: 'ml-badge bg-brand-100 text-brand-800',
  }[status] || 'ml-badge bg-ink-100 text-ink-700');

export const filterTabClass = (active) =>
  active
    ? 'bg-brand-600 text-white shadow-sm'
    : 'bg-white text-ink-600 border border-ink-200 hover:bg-ink-50';

export const matchesStatusFilter = (booking, filterId) => {
  const status = booking.approval_status;
  if (filterId === 'all') return true;
  if (filterId === 'pending') return PENDING_STATUSES.includes(status);
  if (filterId === 'approved') return status === 'Approved';
  if (filterId === 'rejected') return status === 'Rejected';
  if (filterId === 'withdrawn') return status === 'Withdrawn';
  if (filterId === 'cancelled') return status === 'Cancelled';
  return true;
};

export const allocationStatusLabel = (status) =>
  ({
    reserved: 'Reserved',
    confirmed: 'Confirmed',
    released: 'Released',
    cancelled: 'Cancelled',
    mixed: 'Mixed',
  }[status] || status);

export const siteLabelsForBooking = (booking) => {
  const sites = booking?.site_selection?.sites;
  if (Array.isArray(sites) && sites.length) {
    return sites.map((site) => site.label).join(', ');
  }
  return null;
};

export const siteSelectionSummary = (booking) => {
  const selection = booking?.site_selection;
  if (!selection) return null;

  return {
    labels: Array.isArray(selection.sites)
      ? selection.sites.map((site) => site.label).join(', ')
      : '—',
    siteCount: selection.site_count ?? selection.sites?.length ?? 0,
    spaceName: selection.sites?.[0]?.space_name || null,
    allocationStatus: allocationStatusLabel(selection.allocation_status),
    days: Array.isArray(selection.days)
      ? selection.days.map((day) => day.operational_date).join(', ')
      : null,
  };
};

export const boothLabelForBooking = (booking) => {
  const labels = siteLabelsForBooking(booking);
  if (labels) return labels;

  if (!booking || booking.approval_status !== 'Approved') return '—';
  const prefix = String.fromCharCode(65 + (booking.id % 3));
  return `${prefix}-${String(booking.id).padStart(2, '0')}`;
};

export const progressIndex = (status) => {
  if (status === 'Cancelled' || status === 'Rejected' || status === 'Withdrawn') return 0;
  if (status === 'Pending_Organizer' || status === 'Needs_Revision') return 2;
  if (status === 'Approved') return 3;
  return 1;
};

export const progressWidth = (status) => {
  if (status === 'Approved') return '100%';
  if (status === 'Pending_Organizer' || status === 'Needs_Revision') return '66%';
  return '33%';
};

export const progressBarClass = (status) =>
  ({
    Pending_Organizer: 'bg-brand-500',
    Needs_Revision: 'bg-amber-500',
    Approved: 'bg-emerald-500',
    Rejected: 'bg-rose-500',
    Cancelled: 'bg-ink-400',
    Withdrawn: 'bg-slate-400',
  }[status] || 'bg-ink-400');

export const stepClass = (currentStatus, stepStatus) => {
  const active = progressIndex(currentStatus) >= progressIndex(stepStatus);
  if (!active) return 'border-ink-200 text-ink-400';
  return {
    Pending_Organizer: 'border-brand-500 text-brand-700',
    Needs_Revision: 'border-amber-500 text-amber-700',
    Approved: 'border-emerald-500 text-emerald-700',
    Rejected: 'border-rose-500 text-rose-700',
    Cancelled: 'border-ink-400 text-ink-600',
    Withdrawn: 'border-slate-400 text-slate-600',
  }[currentStatus] || 'border-ink-400 text-ink-700';
};

export const isWithdrawnBooking = (booking) => booking?.approval_status === 'Withdrawn';

export const formatWithdrawnDate = (value) => {
  if (!value) return '—';
  return new Date(value).toLocaleDateString('en-GB', {
    timeZone: MY_TZ,
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
};

export const canVendorEdit = (booking) =>
  ['Pending_Organizer', 'Needs_Revision'].includes(booking?.approval_status);

/** Deferred BM copy — preserved for future locale toggle. */
export const NO_REFUND_WITHDRAWAL_WARNING_MS =
  'Anda boleh menarik diri selepas bayaran dibuat, tetapi bayaran tidak akan dipulangkan. Tapak yang telah ditempah akan dibuka semula kepada vendor lain.';

export const NO_REFUND_WITHDRAWAL_WARNING =
  'You may withdraw after payment has been made, but no refund will be issued. Any reserved sites will be released for other vendors.';

export const UNPAID_WITHDRAWAL_WARNING =
  'Withdrawing will end this booking and release your selected sites for other vendors. This action cannot be undone from your dashboard.';

export const withdrawalPolicyForBooking = (booking) =>
  booking?.withdrawal_policy || null;

export const canVendorWithdraw = (booking) => {
  const policy = withdrawalPolicyForBooking(booking);
  if (policy && typeof policy.can_withdraw === 'boolean') {
    return policy.can_withdraw;
  }

  if (typeof booking?.can_withdraw === 'boolean') {
    return booking.can_withdraw;
  }

  return PENDING_STATUSES.includes(booking?.approval_status);
};

export const requiresNoRefundAcknowledgement = (booking) => {
  const policy = withdrawalPolicyForBooking(booking);
  return Boolean(policy?.requires_no_refund_acknowledgement);
};

export const withdrawalWarningMessage = (booking) => {
  const policy = withdrawalPolicyForBooking(booking);
  if (policy?.warning_message) return policy.warning_message;
  if (requiresNoRefundAcknowledgement(booking)) return NO_REFUND_WITHDRAWAL_WARNING;
  return UNPAID_WITHDRAWAL_WARNING;
};

export const withdrawnNoRefundNotice = (booking) => {
  if (!isWithdrawnBooking(booking)) return null;
  const paymentState = withdrawalPolicyForBooking(booking)?.payment_state
    || normalizePaymentStatus(booking).toLowerCase();
  if (paymentState === 'paid' || paymentState === 'pending verification') {
    return 'Payment remains recorded. No refund was issued. Physical sites have been released.';
  }
  if (paymentState === 'payment_submitted') {
    return 'Payment proof remains on record. No refund was issued. Physical sites have been released.';
  }
  return null;
};

export const organizerWithdrawalSummary = (booking) => {
  if (!isWithdrawnBooking(booking)) return null;
  const paymentState = withdrawalPolicyForBooking(booking)?.payment_state
    || (isBookingPaymentPaid(booking) ? 'paid' : normalizePaymentStatus(booking) || 'unpaid');
  const siteLabels = siteLabelsForBooking(booking);
  return {
    status: 'Withdrawn',
    paymentState,
    noRefund: paymentState === 'paid' || paymentState === 'payment_submitted',
    sitesReleased: booking?.site_selection?.allocation_status === 'released',
    siteLabels,
    withdrawnAt: booking?.withdrawn_at || null,
  };
};

export const organizerPaymentStateLabel = (state) =>
  ({
    paid: 'Paid',
    payment_submitted: 'Payment Submitted',
    unpaid: 'Unpaid',
  }[state] || 'Unpaid');

export const organizerReconciliationForBooking = (booking) =>
  booking?.withdrawal_reconciliation || null;

export const attendancePolicyForBooking = (booking) =>
  booking?.attendance_policy || null;

export const attendanceRetainedDayIds = (policy) =>
  (policy?.retained_days || []).map((day) => day.id);

export const attendanceReleaseCount = (policy, retainedDayIds) =>
  (policy?.retained_days || []).filter((day) => !retainedDayIds.includes(day.id)).length;

export const attendanceExceptionValidation = (policy, retainedDayIds, reason, acknowledged) => {
  if (!retainedDayIds.length) return 'At least one EventDay must remain.';
  if (reason.trim().length < 10) return 'Provide a reason of at least 10 characters.';
  if (attendanceReleaseCount(policy, retainedDayIds) < 1) {
    return 'Deselect at least one future EventDay to apply an exception.';
  }
  if (policy?.requires_no_refund_acknowledgement && !acknowledged) {
    return 'No-refund acknowledgement is required.';
  }
  return '';
};

export const bookingMatchesNoRefundFilter = (booking, filter) => {
  if (filter === 'all') return true;
  const applied = Boolean(organizerReconciliationForBooking(booking)?.no_refund_applied);
  return filter === 'yes' ? applied : !applied;
};

export const safeAuditActionLabel = (item) =>
  item?.label?.trim() || 'Booking activity recorded';

export const canVendorResubmit = (booking) => booking?.approval_status === 'Needs_Revision';

export const canVendorRequestChange = (booking) => booking?.approval_status === 'Approved';

export const boothTypeLabel = (booking) =>
  booking?.booth_type_label ||
  booking?.booth_type ||
  (booking?.site_quantity != null
    ? `${booking.site_quantity} parking site${Number(booking.site_quantity) === 1 ? '' : 's'}`
    : null) ||
  'Parking site';

export const productSummary = (booking) => {
  const category = booking?.product_category || 'Others';
  const details = booking?.product_details?.trim();
  return details ? `${category} · ${details}` : category;
};

export const VENDOR_PAYMENT_APPROVAL_REQUIRED_MESSAGE =
  'Your booking must be approved by the organizer before payment can be submitted.';

export const bookingApprovalStatusOf = (bookingOrRow) =>
  bookingOrRow?.approval_status || bookingOrRow?.booking_status || null;

export const paymentStatusOf = (bookingOrRow) =>
  String(
    bookingOrRow?.invoice?.payment_status
    || bookingOrRow?.payment_status
    || '',
  ).trim();

export const normalizePaymentStatus = (booking) => paymentStatusOf(booking);

export const isBookingPaymentPaid = (booking) =>
  paymentStatusOf(booking).toLowerCase() === 'paid';

export const isVendorPaymentPendingVerification = (booking) =>
  paymentStatusOf(booking).toLowerCase() === 'pending verification';

export const isVendorInvoicePayableStatus = (bookingOrRow) => {
  const status = paymentStatusOf(bookingOrRow).toLowerCase();
  return status === 'unpaid' || status === 'failed';
};

/**
 * Canonical vendor payment eligibility. Mirrors backend submit-payment / demo-payment:
 * approval_status must be Approved and the invoice must still be unpaid.
 * Unpaid does not mean payable.
 */
export const canVendorPayBooking = (bookingOrRow) => {
  if (!bookingOrRow) return false;
  const approval = bookingApprovalStatusOf(bookingOrRow);
  if (approval !== 'Approved') return false;
  if (isTerminalBookingStatus(approval)) return false;
  if (!isVendorInvoicePayableStatus(bookingOrRow)) return false;
  if ('invoice_available' in bookingOrRow && !bookingOrRow.invoice_available) return false;
  return true;
};

export const canVendorProceedToDemoPayment = (booking) => canVendorPayBooking(booking);

export const isVendorPaymentLockedUntilApproval = (bookingOrRow) => {
  if (!bookingOrRow) return false;
  const approval = bookingApprovalStatusOf(bookingOrRow);
  if (!approval || approval === 'Approved' || isTerminalBookingStatus(approval)) return false;
  return isVendorInvoicePayableStatus(bookingOrRow) || !paymentStatusOf(bookingOrRow);
};

export const vendorPaymentBlockedMessage = (bookingOrRow) => {
  if (canVendorPayBooking(bookingOrRow)) return null;
  if (isVendorPaymentPendingVerification(bookingOrRow)) {
    return 'Your payment proof is awaiting organizer verification.';
  }
  if (isBookingPaymentPaid(bookingOrRow)) {
    return 'This booking has already been paid.';
  }
  if (bookingApprovalStatusOf(bookingOrRow) !== 'Approved') {
    return VENDOR_PAYMENT_APPROVAL_REQUIRED_MESSAGE;
  }
  return 'Payment is not available for this booking.';
};

export const vendorPaymentStatusLabel = (bookingOrRow) => {
  if (isVendorPaymentLockedUntilApproval(bookingOrRow)) return 'Locked until approval';
  if (isVendorPaymentPendingVerification(bookingOrRow)) return 'Payment submitted';
  if (isBookingPaymentPaid(bookingOrRow)) return 'Paid';
  return paymentStatusOf(bookingOrRow) || 'Unpaid';
};

export const formatVendorAmountLabel = (amount) => {
  if (amount == null || amount === '') return null;
  const numeric = Number(amount);
  if (Number.isNaN(numeric)) return null;
  return `RM${numeric.toFixed(2)}`;
};

export const vendorBookingStatusHint = (booking, boothNumber = null) => {
  if (!booking) return null;
  const approval = booking.approval_status;
  if (approval === 'Pending_Organizer' || approval === 'Pending_Staff' || approval === 'Pending_Boss') {
    return 'Waiting for organizer approval';
  }
  if (approval === 'Approved' && canVendorPayBooking(booking)) {
    return 'Ready for payment';
  }
  return boothNumber ? `Booth ${boothNumber}` : null;
};

const paymentSnapshot = (booking, paymentRecord = null) => {
  const status = String(paymentRecord?.payment_status || paymentStatusOf(booking)).trim();
  const amount = paymentRecord?.amount ?? booking?.invoice?.amount ?? null;
  return {
    ...booking,
    approval_status: booking?.approval_status,
    payment_status: status,
    invoice: {
      ...(booking?.invoice || {}),
      payment_status: status || booking?.invoice?.payment_status,
      amount,
    },
  };
};

export const resolveVendorPaymentUi = (booking, paymentRecord = null) => {
  if (!booking) {
    return {
      actionable: false,
      complete: false,
      value: 'Not started',
      hint: null,
      statusKey: 'none',
      canPay: false,
    };
  }

  if (isTerminalBookingStatus(booking.approval_status)) {
    return {
      actionable: false,
      complete: false,
      value: 'Not applicable',
      hint: statusLabel(booking.approval_status),
      statusKey: 'terminal',
      canPay: false,
    };
  }

  const snapshot = paymentSnapshot(booking, paymentRecord);
  const status = paymentStatusOf(snapshot);
  const statusLower = status.toLowerCase();
  const amount = snapshot.invoice?.amount ?? null;
  const amountLabel = formatVendorAmountLabel(amount);
  const canPay = canVendorPayBooking(snapshot);

  if (canPay) {
    return {
      actionable: true,
      complete: false,
      value: status || 'Unpaid',
      hint: amountLabel ? `${amountLabel} due` : 'Payment required',
      statusKey: 'unpaid',
      amount,
      canPay: true,
    };
  }

  if (isVendorPaymentLockedUntilApproval(snapshot)) {
    const awaitingOrganizer = [
      'Pending_Organizer',
      'Pending_Staff',
      'Pending_Boss',
    ].includes(booking.approval_status);

    return {
      actionable: false,
      complete: false,
      value: 'Locked until approval',
      hint: amountLabel ? `${amountLabel} due after approval` : 'Payment is not due yet',
      lockCopy: 'Payment will be available once the organizer approves your booking.',
      statusKey: 'locked',
      amount,
      canPay: false,
      lockedPayCta: awaitingOrganizer,
    };
  }

  if (isVendorPaymentPendingVerification(snapshot)) {
    return {
      actionable: false,
      complete: false,
      value: 'Payment submitted',
      hint: 'Waiting for organizer verification.',
      statusKey: 'pending',
      amount,
      canPay: false,
      canViewInvoice: Boolean(paymentRecord?.invoice_available || booking.invoice),
    };
  }

  if (isBookingPaymentPaid(snapshot) || statusLower === 'paid') {
    return {
      actionable: false,
      complete: true,
      value: 'Paid',
      hint: amountLabel,
      statusKey: 'paid',
      amount,
      canPay: false,
      canViewReceipt: Boolean(paymentRecord?.receipt_available),
      canViewInvoice: Boolean(paymentRecord?.invoice_available || booking.invoice),
    };
  }

  if (booking.approval_status === 'Approved' && !status) {
    return {
      actionable: false,
      complete: false,
      value: 'Awaiting invoice',
      hint: 'Check again after approval processing',
      statusKey: 'awaiting',
      canPay: false,
    };
  }

  if (!status) {
    return {
      actionable: false,
      complete: false,
      value: 'Not due yet',
      hint: 'Pay after approval',
      statusKey: 'not_due',
      canPay: false,
    };
  }

  return {
    actionable: false,
    complete: false,
    value: status,
    hint: amountLabel,
    statusKey: 'other',
    canPay: false,
  };
};

export const resolveVendorFocusPrimaryAction = (booking, payment = {}) => {
  if (!booking?.id) return null;

  if (booking.approval_status === 'Needs_Revision') {
    return { type: 'view-booking', bookingId: booking.id, label: 'Review Booking' };
  }

  if (payment.canPay) {
    return {
      type: 'pay',
      bookingId: booking.id,
      amount: payment.amount ?? null,
      label: 'Pay Now',
      emphasis: 'payment',
    };
  }

  if (payment.lockedPayCta) {
    return {
      type: 'pay',
      bookingId: booking.id,
      amount: payment.amount ?? null,
      label: 'Pay Now',
      emphasis: 'locked',
      disabled: true,
      locked: true,
      lockHint: 'Payment will be available once the organizer approves your booking.',
    };
  }

  if (payment.statusKey === 'pending' && payment.canViewInvoice) {
    return {
      type: 'view-document',
      bookingId: booking.id,
      label: 'View Invoice',
    };
  }

  if (booking.approval_status === 'Approved' && (payment.complete || isBookingPaymentPaid(booking))) {
    return {
      type: 'view-pass',
      bookingId: booking.id,
      label: 'View Event Pass',
    };
  }

  if (PENDING_STATUSES.includes(booking.approval_status)) {
    return { type: 'view-booking', bookingId: booking.id, label: 'View Booking' };
  }

  return { type: 'view-booking', bookingId: booking.id, label: 'View Details' };
};

export const canVendorAccessWhatsAppGroup = (booking) =>
  booking?.approval_status === 'Approved' && isBookingPaymentPaid(booking);

export const VENDOR_WHATSAPP_GROUP_URL = 'https://chat.whatsapp.com/CMartVendorDemoGroup';

export const formatDateTime = (value) => {
  if (!value) return 'Not recorded';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'Not recorded';
  return date.toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short', timeZone: MY_TZ });
};

export const formatOperationalDate = (dateStr) => {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('en-GB', {
    timeZone: MY_TZ,
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
};

export const recoveryStateOptions = [
  'recoverable',
  'partially_blocked',
  'fully_blocked',
  'expired',
  'operationally_unavailable',
];

export const recoveryStateLabel = (state) =>
  ({
    recoverable: 'Recoverable',
    partially_blocked: 'Partially Blocked',
    fully_blocked: 'Fully Blocked',
    expired: 'Expired',
    operationally_unavailable: 'Operationally Unavailable',
  }[state] || state);

export const recoveryStateBadgeClass = (state) =>
  ({
    recoverable: 'ml-badge bg-emerald-100 text-emerald-800',
    partially_blocked: 'ml-badge bg-amber-100 text-amber-800',
    fully_blocked: 'ml-badge bg-rose-100 text-rose-800',
    expired: 'ml-badge bg-slate-100 text-slate-700',
    operationally_unavailable: 'ml-badge bg-ink-100 text-ink-700',
  }[state] || 'ml-badge bg-ink-100 text-ink-700');

export const recoveryPaymentLabel = (state) => organizerPaymentStateLabel(state);

export const recoveryPaymentBadgeClass = (state) =>
  ({
    paid: 'inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-800',
    payment_submitted: 'inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800',
    unpaid: 'inline-flex rounded-full bg-ink-100 px-2 py-0.5 text-[10px] font-semibold text-ink-700',
  }[state] || 'inline-flex rounded-full bg-ink-100 px-2 py-0.5 text-[10px] font-semibold text-ink-700');

export const releaseReasonLabel = (reason) =>
  ({
    organizer_day_exception: 'Organizer attendance exception',
    booking_withdrawn: 'Vendor withdrawal',
    booking_cancelled: 'Booking cancelled',
    booking_rejected: 'Booking rejected',
  }[reason] || reason || '—');

export const releasedSiteLabels = (row) =>
  (row?.released_sites || []).map((site) => site.label).filter(Boolean).join(', ') || '—';

export const recoveryBlockerSummary = (row) => {
  const blockers = (row?.released_sites || [])
    .map((site) => site.blocker)
    .filter(Boolean);
  if (!blockers.length) return '';
  if (blockers.length === 1) return blockers[0];
  return `${blockers.length} sites blocked`;
};

export const buildRecoveryQueryParams = ({
  search = '',
  recoveryState = 'all',
  paymentState = 'all',
  page = 1,
  perPage = 15,
} = {}) => {
  const params = { page, per_page: perPage };
  if (search) params.search = search;
  if (recoveryState !== 'all') params.recovery_state = recoveryState;
  if (paymentState !== 'all') params.payment_state = paymentState;
  return params;
};
