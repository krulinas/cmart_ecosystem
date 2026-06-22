const MY_TZ = 'Asia/Kuala_Lumpur';

export const PIPELINE_STEPS = [
  { index: 1, status: 'Pending_Staff', label: 'Staff Review' },
  { index: 2, status: 'Pending_Boss', label: 'Manager Review' },
  { index: 3, status: 'Approved', label: 'Approved' },
];

export const PENDING_STATUSES = ['Pending_Staff', 'Pending_Boss', 'Needs_Revision'];

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
    Pending_Staff: 'Pending',
    Needs_Revision: 'Needs Revision',
    Pending_Boss: 'Pending',
    Approved: 'Approved',
    Rejected: 'Rejected',
    Cancelled: 'Cancelled',
    Withdrawn: 'Withdrawn',
  }[status] || status);

export const statusBadgeClass = (status) =>
  ({
    Pending_Staff: 'ml-badge bg-brand-100 text-brand-800',
    Pending_Boss: 'ml-badge bg-purple-100 text-purple-800',
    Needs_Revision: 'ml-badge bg-amber-100 text-amber-800',
    Approved: 'ml-badge bg-emerald-100 text-emerald-800',
    Rejected: 'ml-badge bg-rose-100 text-rose-800',
    Cancelled: 'ml-badge bg-ink-100 text-ink-700',
    Withdrawn: 'ml-badge bg-slate-100 text-slate-700 border border-slate-200',
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

export const boothLabelForBooking = (booking) => {
  if (!booking || booking.approval_status !== 'Approved') return '—';
  const prefix = String.fromCharCode(65 + (booking.id % 3));
  return `${prefix}-${String(booking.id).padStart(2, '0')}`;
};

export const progressIndex = (status) => {
  if (status === 'Cancelled' || status === 'Rejected' || status === 'Withdrawn') return 0;
  if (status === 'Pending_Staff' || status === 'Needs_Revision') return 1;
  if (status === 'Pending_Boss') return 2;
  if (status === 'Approved') return 3;
  return 0;
};

export const progressWidth = (status) => {
  if (status === 'Approved') return '100%';
  if (status === 'Pending_Boss') return '50%';
  if (status === 'Pending_Staff' || status === 'Needs_Revision') return '16%';
  return '0%';
};

export const progressBarClass = (status) =>
  ({
    Pending_Staff: 'bg-brand-500',
    Pending_Boss: 'bg-purple-500',
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
    Pending_Staff: 'border-brand-500 text-brand-700',
    Pending_Boss: 'border-purple-500 text-purple-700',
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
  ['Pending_Staff', 'Needs_Revision'].includes(booking?.approval_status);

export const canVendorWithdraw = (booking) => {
  if (typeof booking?.can_withdraw === 'boolean') {
    return booking.can_withdraw;
  }

  return PENDING_STATUSES.includes(booking?.approval_status)
    && booking?.invoice?.payment_status !== 'Paid';
};

export const canVendorResubmit = (booking) => booking?.approval_status === 'Needs_Revision';

export const canVendorRequestChange = (booking) => booking?.approval_status === 'Approved';

export const boothTypeLabel = (booking) =>
  booking?.booth_type_label ||
  booking?.booth_type ||
  booking?.space_type ||
  booking?.boothType ||
  booking?.space?.space_size ||
  (booking?.space_id ? `Space #${booking.space_id}` : null) ||
  'Standard (1 Parking Lot)';

export const productSummary = (booking) => {
  const category = booking?.product_category || 'Others';
  const details = booking?.product_details?.trim();
  return details ? `${category} · ${details}` : category;
};
