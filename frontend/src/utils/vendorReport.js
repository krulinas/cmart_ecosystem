export const downloadVendorReportJson = (report, filenamePrefix = 'vendor-dashboard-report') => {
  const blob = new Blob([JSON.stringify(report, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `${filenamePrefix}-${new Date().toISOString().slice(0, 10)}.json`;
  link.click();
  URL.revokeObjectURL(url);
};

export const downloadVendorReportCsv = (report) => {
  const rows = [
    ['Section', 'Metric', 'Value'],
    ['Generated', 'Date', report.generated_at || ''],
    ['Vendor', 'Name', report.vendor?.name || ''],
    ['Vendor', 'Email', report.vendor?.email || ''],
    ['Profile', 'Business Name', report.business_profile?.business_name || ''],
    ['Profile', 'Completion %', report.business_profile?.completion_percent ?? ''],
    ['Bookings', 'Total', report.booking_summary?.total_bookings ?? 0],
    ['Bookings', 'Upcoming', report.booking_summary?.upcoming_bookings ?? 0],
    ['Bookings', 'Completed', report.booking_summary?.completed_bookings ?? 0],
    ['Bookings', 'Cancelled', report.booking_summary?.cancelled_bookings ?? 0],
    ['Bookings', 'Rejected', report.booking_summary?.rejected_bookings ?? 0],
    ['Payments', 'Total Receipts', report.payment_summary?.total_receipts ?? 0],
    ['Payments', 'Total Paid (RM)', report.payment_summary?.total_paid_amount ?? 0],
    ['Reuse Listings', 'Total', report.reuse_listing_summary?.total_listings ?? 0],
    ['Reuse Listings', 'Active', report.reuse_listing_summary?.active_listings ?? 0],
    ['Reuse Listings', 'Inactive', report.reuse_listing_summary?.inactive_listings ?? 0],
  ];

  const csv = rows
    .map((row) => row.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(','))
    .join('\n');

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `vendor-dashboard-report-${new Date().toISOString().slice(0, 10)}.csv`;
  link.click();
  URL.revokeObjectURL(url);
};
