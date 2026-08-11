/**
 * Organizer event-layout operational copy (English).
 * Vendor-facing Malay lives in vendor components / eventSiteSelection / vendorCategoriesApi.
 */

export const LAYOUT_COPY = {
  pageTitle: 'Site Layout',
  navLabel: 'Layout Management',
  manageLayoutAction: 'Layout Management',
  manageLayout: 'Manage Layout',
  manageLayoutExit: 'Done managing',
  manageLayoutActive: 'Management mode',
  manageLayoutHint: 'Select a site tile to open its actions. Use each row menu for row actions.',
  rowMenuLabel: 'Row actions',
  moveUpDisabled: 'Already the first row.',
  moveDownDisabled: 'Already the last row.',
  generateSitesComplete: 'This row already has its complete set of sites.',
  reorderSitesDisabled: 'At least two sites are required to reorder.',
  selectEvent: 'Select event',
  refresh: 'Refresh Layout',
  backToEvents: 'Back to Events',
  addRow: 'Add Row',
  editRow: 'Edit Row',
  saveOrder: 'Save Order',
  moveUp: 'Move Up',
  moveDown: 'Move Down',
  deleteRow: 'Delete Row',
  archiveRow: 'Archive Row',
  unarchiveRow: 'Unarchive Row',
  addSite: 'Add Site',
  generateSites: 'Generate Sites',
  editSite: 'Edit Site',
  moveSite: 'Move Site',
  disableSite: 'Disable',
  enableSite: 'Enable',
  deleteSite: 'Delete Site',
  save: 'Save',
  cancel: 'Cancel',
  tryAgain: 'Try Again',
  locked: 'Locked',
  advanced: 'Advanced Settings',
  unresolvedTitle: 'Unassigned Sites',
  emptyTitle: 'No parking layout has been created yet.',
  emptyBody:
    'Set Vendor sites to open, then generate the standard 4×16 parking layout, or add an unused physical row.',
  generateStandardLayout: 'Generate Standard Parking Layout',
  generateStandardLayoutHelp:
    'Creates physical rows A–D with 64 sites. Sites start as NOT OPEN until you select which ones vendors may book.',
  generateStandardPreview: 'Preview: A01–A16, B01–B16, C01–C16, D01–D16',
  generateStandardConfirm:
    'This venue has 64 physical parking sites. After generation you will manually choose exactly which sites open for this event.',
  generateStandardOpenCount: (n) =>
    `Vendor sites to open for this event: ${n == null ? 'not set' : n}`,
  generateStandardLimitRequired:
    'Set Vendor sites to open on the event before generating the standard layout.',
  standardLayoutGenerated: 'Standard parking layout generated. Select which sites to open.',
  selectOpenSitesTitle: 'Select Open Sites',
  selectOpenSitesHelp:
    'Choose exactly the number of vendor sites configured for this event. Unselected sites stay NOT OPEN.',
  selectOpenSitesCount: (selected, limit) => `Selected ${selected} of ${limit} sites`,
  confirmOpenSites: 'Confirm Open Sites',
  openSitesConfirmed: 'Open sites confirmed successfully.',
  startSelectOpenSites: 'Select Open Sites',
  layoutExistsHint:
    'A layout already exists. Use site controls to update individual sites. The standard template can only run on an empty layout.',
  missingEventDaysWarning:
    'The physical layout is ready, but event days must be configured before vendors can book.',
  setupNoticeTitle: 'Layout Readiness',
  technicalDetails: 'Technical details',
  focusedSiteTitle: 'Site management',
  focusedRowTitle: 'Row settings',
  rowActionsTitle: 'Row actions',
  noSiteSelected: 'Select a site on the parking layout to manage it.',
  closeSitePanel: 'Close',
  setActive: 'Set Active / Open',
  setUnavailable: 'Set Unavailable',
  setDisabled: 'Set NOT OPEN',
  updatingStatus: 'Updating…',
  siteCountsTitle: 'Site counts',
  advancedRowsTitle: 'Advanced row tools',
  loadError: 'Unable to load the layout.',
  loadingLayoutFor: (name) => `Loading layout for ${name}…`,
  allPhysicalRowsInUse: 'All physical rows for this venue are already in use.',
  outsideVenueTemplateBadge: 'Outside venue definition',
  outsideVenueTemplateHelp:
    'This row is outside the current venue physical definition (A–D). It was not deleted automatically.',
  vendorSitesToOpen: 'Vendor sites to open',
  vendorSitesToOpenHelp: 'How many vendor parking sites may be booked for this event (1–64).',
  physicalSitesSummary: (physical, active, limit) =>
    `${physical} physical · ${active} open${limit != null ? ` / ${limit} allowed` : ''}`,
  conflictRefreshHint: 'Refresh Layout',
  operationalReady: 'Ready for Booking',
  operationalNotReady: 'Not Ready for Booking',
  publicReady: 'Ready for Public Display',
  publicNotReady: 'Not Ready for Public Display',
  rowCreated: 'Row added with 16 NOT OPEN sites.',
  rowUpdated: 'Row updated successfully.',
  rowDeleted: 'Row deleted successfully.',
  rowArchived: 'Row archived successfully.',
  rowUnarchived: 'Row unarchived successfully.',
  rowsReordered: 'Row order saved successfully.',
  siteCreated: 'Site added successfully.',
  sitesGenerated: 'Sites generated successfully.',
  siteUpdated: 'Site updated successfully.',
  sitesReordered: 'Site order saved successfully.',
  siteDeleted: 'Site deleted successfully.',
  fallbackError: 'The action could not be completed. Refresh the layout and try again.',
  renameLockedHint:
    'This row name cannot be changed because sites in the row have booking history.',
  categoryLockedHint:
    'This row category cannot be changed because sites in the row have booking history.',
  structureLockedHint: 'This site structure is locked because it has booking history.',
  disableLockedHint: 'This site cannot be closed while it has an active booking.',
  archiveBlockedHint: 'This row cannot be archived while it still has active bookings.',
  unarchiveHint:
    'The row will be reactivated, but sites inside it will not be enabled automatically.',
  generateAtomicHint:
    'All requested sites are created in one step, or none are created. Existing sites are not deleted or replaced.',
  availabilityStatus: 'Layout Readiness',
  availabilityStatusHelp: 'Booking readiness and public visibility are evaluated separately.',
  publicationTitle: 'Public Map Publication',
  publicationHelp: 'The visitor map can only be published when public readiness is complete.',
  published: 'Published',
  notPublished: 'Not Published',
  publishPublicMap: 'Publish Public Map',
  unpublishPublicMap: 'Unpublish Public Map',
  entranceNoteLabel: 'Public entrance guidance (optional)',
  selectEventPrompt: 'Select an event to manage its layout',
  selectEventOption: '— Select event —',
  loadingLayout: 'Loading layout…',
  noSpace: 'No space type',
  noCategory: 'No category',
  physicalRowLabel: 'Physical row',
  selectPhysicalRow: 'Select unused physical row',
  siteGrid: 'Site Grid',
  reorderSites: 'Reorder Sites',
  noSitesInRow: 'No sites in this row yet.',
  noReadinessBlockers: 'No readiness blockers reported at this time.',
  selectCategory: 'Select category',
  selectSpaceType: 'Select space type',
  rowCategoryA: 'Row A category',
  rowCategoryB: 'Row B category',
  rowCategoryC: 'Row C category',
  rowCategoryD: 'Row D category',
  targetRow: 'Target row',
  displayOrder: 'Display order',
  rowLabelPrefix: 'Row',
  noPreview: 'No preview',
  generateSitesAction: (count) => `Generate ${count} sites`,
  generating: 'Generating…',
  available: 'Available',
  reserved: 'Booked',
  confirmed: 'Confirmed',
  lockedLegend: 'Locked',
  delete: 'Delete',
  deleteLocked: 'Delete locked',
  renameLocked: 'Rename locked',
  categoryLocked: 'Category locked',
  archiveLocked: 'Archive locked',
  rowStillHasSites: 'This row still has sites.',
  unresolvedHelp:
    'These sites are not linked to a layout row. Automatic mapping is not performed.',
  publicPublishedToast: 'Public layout published.',
  publicUnpublishedToast: 'Public layout unpublished.',
  confirmUnpublish:
    'Unpublish this public map? Visitors will no longer be able to see it.',
  confirmDeleteRow: 'Delete this row?\n\nThis empty row will be permanently removed.',
  confirmArchiveRow:
    'Archive this row?\n\nThe row will be deactivated and hidden from public display. Active sites in the row will also be disabled. Booking history is retained.',
  confirmDeleteSite:
    'Delete this site?\n\nA site can only be deleted if it has never had booking history.',
  confirmReorderSites: (label) =>
    `Reorder sites in ${label} using the current reverse order?\nYou can reorder again afterward.`,
  confirmGenerateSites: (count, label) =>
    `Generate ${count} sites for ${label}?\nExisting sites will not be deleted or replaced.`,
};

export const READINESS_BLOCKER_MESSAGES = {
  VENDOR_SITE_OPEN_LIMIT_NOT_SET:
    'Set Vendor sites to open for this event before the layout can be ready for booking.',
  ACTIVE_SITE_COUNT_BELOW_VENDOR_LIMIT:
    'Open exactly the configured number of vendor sites. Select the remaining sites to open.',
  ACTIVE_SITE_COUNT_EXCEEDS_VENDOR_LIMIT:
    'Too many sites are open. Close extra sites or raise Vendor sites to open.',
  ROW_OUTSIDE_VENUE_TEMPLATE:
    'One or more rows are outside this venue’s physical definition (A–D). Resolve or archive them.',
  NO_ACTIVE_EVENT_DAYS: 'No active event days are configured.',
  NO_ACTIVE_LAYOUT_ROWS: 'No active layout rows are configured.',
  ACTIVE_ROW_MISSING_CATEGORY: 'One or more rows do not have a category.',
  ROW_CATEGORY_INACTIVE: 'One or more rows use a category that is no longer active.',
  ACTIVE_ROW_HAS_NO_ACTIVE_SITES: 'One or more active rows have no physical sites.',
  ACTIVE_SITE_MISSING_ROW: 'An active site is not linked to a row.',
  SITE_EVENT_ROW_MISMATCH: 'A site does not match its row event.',
  ACTIVE_SITE_MISSING_SPACE: 'An active site is missing a valid space type.',
  ACTIVE_SITE_INVALID_LABEL: 'An active site has an invalid label.',
  UNRESOLVED_ACTIVE_SITES: 'Legacy sites still need to be assigned to rows.',
  DUPLICATE_ACTIVE_SITE_IDENTITY: 'Active sites share a duplicate identity.',
  NO_PUBLIC_ROWS: 'No rows are configured for public display.',
  PUBLIC_ROW_CATEGORY_NOT_PUBLIC: 'A public row category is not allowed for public display.',
  PUBLIC_ROW_HAS_NO_VISIBLE_SITES: 'A public row has no visible sites.',
  EMPTY_PUBLIC_LAYOUT: 'The public layout is still empty.',
  INVALID_PUBLIC_ROW_ORDER: 'The public row order is invalid.',
};

export const LAYOUT_ERROR_MESSAGES = {
  LAYOUT_ALREADY_EXISTS:
    'A layout already exists for this event. The standard parking template can only be generated on an empty layout.',
  PUBLIC_LAYOUT_PUBLISHED:
    'Unpublish the public layout before generating the standard parking template.',
  ALLOCATION_HISTORY_PRESENT:
    'This event has booking allocation history and cannot receive a destructive layout template.',
  INVALID_STANDARD_TEMPLATE: 'The standard parking template request is invalid.',
  INVALID_SPACE: 'The selected space type is invalid.',
  VENDOR_SITE_OPEN_LIMIT_NOT_SET: 'Set Vendor sites to open before continuing.',
  VENDOR_SITE_OPEN_LIMIT_BELOW_PROTECTED:
    'Cannot lower Vendor sites to open below protected reserved or booked sites.',
  OPEN_SITE_SELECTION_COUNT_MISMATCH: 'Select exactly the configured number of sites to open.',
  VENUE_TEMPLATE_ROWS_EXHAUSTED: 'All physical rows for this venue are already in use.',
  ROW_OUTSIDE_VENUE_TEMPLATE: 'Physical row identity must be A, B, C, or D for this venue.',
  ACTIVE_SITE_COUNT_EXCEEDS_VENDOR_LIMIT: 'Opening this site would exceed Vendor sites to open.',
  ROW_LABEL_LOCKED: 'This row name cannot be changed because it has booking history.',
  ROW_CATEGORY_LOCKED: 'This row category cannot be changed because it has booking history.',
  ROW_NOT_EMPTY: 'This row still has sites and cannot be deleted.',
  ACTIVE_ALLOCATIONS_PRESENT: 'This action is not allowed while active bookings exist.',
  SITE_STRUCTURE_LOCKED: 'This site structure is locked because it has booking history.',
  SITE_HAS_ALLOCATION_HISTORY: 'This site has booking history and cannot be deleted.',
  SITE_LABEL_CONFLICT: 'This site label is already in use.',
  SITE_POSITION_CONFLICT: 'This site position overlaps another site.',
  ROW_LABEL_CONFLICT: 'This row name is already used for the same event.',
  LAYOUT_GENERATION_CONFLICT: 'Sites could not be generated because of a layout conflict.',
  CATEGORY_INACTIVE: 'This category is inactive and cannot be used.',
  INVALID_LAYOUT_ROW: 'The layout row is invalid.',
  INVALID_VENDOR_CATEGORY: 'The vendor category is invalid.',
  INVALID_SITE_COUNT: 'The site count is invalid.',
  INVALID_SITE_LABEL: 'The site label is invalid.',
  INVALID_DISPLAY_ORDER: 'The display order is invalid.',
  INVALID_SITE_STATUS: 'The site status is invalid.',
  ACTIVE_ROW_HAS_NO_ACTIVE_SITES: 'An active row cannot be saved without physical sites.',
};

export function readinessMessage(code) {
  return READINESS_BLOCKER_MESSAGES[code] || code;
}

export function layoutErrorMessage(error) {
  const code = error?.response?.data?.error;
  if (code && LAYOUT_ERROR_MESSAGES[code]) {
    return LAYOUT_ERROR_MESSAGES[code];
  }
  const message = error?.response?.data?.message;
  if (typeof message === 'string' && message.trim() !== '') {
    return message.replace(/^\d{3}\s+[A-Za-z ]+:\s*/, '');
  }
  return LAYOUT_COPY.fallbackError;
}

export const OCCUPANCY_LABELS = {
  available: 'Available',
  reserved: 'Booked',
  confirmed: 'Confirmed',
  'released-history': 'Released history',
};

export const SITE_STATUS_LABELS = {
  active: 'Open',
  unavailable: 'Unavailable',
  disabled: 'NOT OPEN',
};
