/**
 * Organizer event-layout operational copy (vue-i18n).
 * Proxy re-reads tt() on each access so locale switches update labels without remounting.
 */

import { tt } from '../i18n';

function buildLayoutCopy(t = tt) {
  return {
    pageTitle: t('organizer.layout.pageTitle'),
    navLabel: t('organizer.layout.navLabel'),
    manageLayoutAction: t('organizer.layout.manageLayoutAction'),
    manageParkingLayout: t('organizer.layout.manageParkingLayout'),
    manageLayout: t('organizer.layout.manageLayout'),
    manageLayoutExit: t('organizer.layout.manageLayoutExit'),
    manageLayoutActive: t('organizer.layout.manageLayoutActive'),
    manageLayoutHint: t('organizer.layout.manageLayoutHint'),
    editLayoutStructure: t('organizer.layout.editLayoutStructure'),
    editLayoutStructureHelp: t('organizer.layout.editLayoutStructureHelp'),
    chooseBookingSitesMenuHelp: t('organizer.layout.chooseBookingSitesMenuHelp'),
    recommendedBadge: t('organizer.layout.recommendedBadge'),
    advancedBadge: t('organizer.layout.advancedBadge'),
    rowMenuLabel: t('organizer.layout.rowMenuLabel'),
    moveUpDisabled: t('organizer.layout.moveUpDisabled'),
    moveDownDisabled: t('organizer.layout.moveDownDisabled'),
    generateSitesComplete: t('organizer.layout.generateSitesComplete'),
    reorderSitesDisabled: t('organizer.layout.reorderSitesDisabled'),
    selectEvent: t('organizer.layout.selectEvent'),
    refresh: t('organizer.layout.refresh'),
    backToEvents: t('organizer.layout.backToEvents'),
    addRow: t('organizer.layout.addRow'),
    editRow: t('organizer.layout.editRow'),
    saveOrder: t('organizer.layout.saveOrder'),
    moveUp: t('organizer.layout.moveUp'),
    moveDown: t('organizer.layout.moveDown'),
    deleteRow: t('organizer.layout.deleteRow'),
    archiveRow: t('organizer.layout.archiveRow'),
    unarchiveRow: t('organizer.layout.unarchiveRow'),
    addSite: t('organizer.layout.addSite'),
    generateSites: t('organizer.layout.generateSites'),
    editSite: t('organizer.layout.editSite'),
    moveSite: t('organizer.layout.moveSite'),
    disableSite: t('organizer.layout.disableSite'),
    enableSite: t('organizer.layout.enableSite'),
    deleteSite: t('organizer.layout.deleteSite'),
    physicalSitesOfTotal: (present, total) =>
      t('organizer.layout.physicalSitesOfTotal', { present, total }),
    sitesCountFallback: (count) => t('organizer.layout.sitesCountFallback', { count }),
    restoreSite: (label) => t('organizer.layout.restoreSite', { label }),
    restoreMissingSites: t('organizer.layout.restoreMissingSites'),
    restoreAllMissingSites: t('organizer.layout.restoreAllMissingSites'),
    restoringSite: (label) => t('organizer.layout.restoringSite', { label }),
    restoringAllMissingSites: t('organizer.layout.restoringAllMissingSites'),
    siteRestoredNotOpen: (label) => t('organizer.layout.siteRestoredNotOpen', { label }),
    sitesRestoredNotOpen: (count) => t('organizer.layout.sitesRestoredNotOpen', { count }),
    canonicalSiteDeleteForbidden: t('organizer.layout.canonicalSiteDeleteForbidden'),
    save: t('organizer.layout.save'),
    cancel: t('organizer.layout.cancel'),
    tryAgain: t('organizer.layout.tryAgain'),
    locked: t('organizer.layout.locked'),
    advanced: t('organizer.layout.advanced'),
    unresolvedTitle: t('organizer.layout.unresolvedTitle'),
    emptyTitle: t('organizer.layout.emptyTitle'),
    emptyBody: t('organizer.layout.emptyBody'),
    generateStandardLayout: t('organizer.layout.generateStandardLayout'),
    generateStandardLayoutHelp: t('organizer.layout.generateStandardLayoutHelp'),
    generateStandardPreview: t('organizer.layout.generateStandardPreview'),
    generateStandardConfirm: t('organizer.layout.generateStandardConfirm'),
    generateStandardOpenCount: (n) =>
      (n == null
        ? t('organizer.layout.generateStandardOpenCountUnset')
        : t('organizer.layout.generateStandardOpenCountSet', { n })),
    generateStandardLimitRequired: t('organizer.layout.generateStandardLimitRequired'),
    standardLayoutGenerated: t('organizer.layout.standardLayoutGenerated'),
    deleteParkingLayout: t('organizer.layout.deleteParkingLayout'),
    confirmDeleteParkingLayout: t('organizer.layout.confirmDeleteParkingLayout'),
    parkingLayoutDeleted: t('organizer.layout.parkingLayoutDeleted'),
    selectOpenSitesTitle: t('organizer.layout.selectOpenSitesTitle'),
    selectOpenSitesHelp: t('organizer.layout.selectOpenSitesHelp'),
    selectOpenSitesCount: (selected) =>
      t('organizer.layout.selectOpenSitesCount', { selected, n: selected, count: selected }),
    selectOpenSitesMinimum: t('organizer.layout.selectOpenSitesMinimum'),
    confirmOpenSites: (count) => t('organizer.layout.confirmOpenSites', { count, n: count }),
    openSitesConfirmed: t('organizer.layout.openSitesConfirmed'),
    startSelectOpenSites: t('organizer.layout.startSelectOpenSites'),
    selectionModeBadge: t('organizer.layout.selectionModeBadge'),
    selectAllSites: t('organizer.layout.selectAllSites'),
    clearAllSites: t('organizer.layout.clearAllSites'),
    selectRowSites: t('organizer.layout.selectRowSites'),
    clearRowSites: t('organizer.layout.clearRowSites'),
    protectedSiteHint: t('organizer.layout.protectedSiteHint'),
    manageBookingSites: t('organizer.layout.manageBookingSites'),
    vendorBookingSitesConfigured: (n) =>
      t('organizer.layout.vendorBookingSitesConfigured', { n }),
    vendorBookingSitesNotConfigured: t('organizer.layout.vendorBookingSitesNotConfigured'),
    vendorBookingSetupRequired: t('organizer.layout.vendorBookingSetupRequired'),
    vendorBookingSetupMessage: t('organizer.layout.vendorBookingSetupMessage'),
    vendorBookingSelectingMessage: t('organizer.layout.vendorBookingSelectingMessage'),
    vendorBookingOpen: t('organizer.layout.vendorBookingOpen'),
    vendorBookingOpenMessage: (n) => t('organizer.layout.vendorBookingOpenMessage', { n }),
    layoutExistsHint: t('organizer.layout.layoutExistsHint'),
    missingEventDaysWarning: t('organizer.layout.missingEventDaysWarning'),
    setupNoticeTitle: t('organizer.layout.setupNoticeTitle'),
    technicalDetails: t('organizer.layout.technicalDetails'),
    focusedSiteTitle: t('organizer.layout.focusedSiteTitle'),
    focusedRowTitle: t('organizer.layout.focusedRowTitle'),
    rowActionsTitle: t('organizer.layout.rowActionsTitle'),
    noSiteSelected: t('organizer.layout.noSiteSelected'),
    closeSitePanel: t('organizer.layout.closeSitePanel'),
    setActive: t('organizer.layout.setActive'),
    setUnavailable: t('organizer.layout.setUnavailable'),
    setDisabled: t('organizer.layout.setDisabled'),
    updatingStatus: t('organizer.layout.updatingStatus'),
    siteCountsTitle: t('organizer.layout.siteCountsTitle'),
    advancedRowsTitle: t('organizer.layout.advancedRowsTitle'),
    loadError: t('organizer.layout.loadError'),
    loadingLayoutFor: (name) => t('organizer.layout.loadingLayoutFor', { name }),
    allPhysicalRowsInUse: t('organizer.layout.allPhysicalRowsInUse'),
    outsideVenueTemplateBadge: t('organizer.layout.outsideVenueTemplateBadge'),
    outsideVenueTemplateHelp: t('organizer.layout.outsideVenueTemplateHelp'),
    vendorSitesToOpen: t('organizer.layout.vendorSitesToOpen'),
    vendorSitesToOpenHelp: t('organizer.layout.vendorSitesToOpenHelp'),
    physicalSitesSummary: (physical, active, limit) =>
      (limit != null
        ? t('organizer.layout.physicalSitesSummaryWithLimit', { physical, active, limit })
        : t('organizer.layout.physicalSitesSummary', { physical, active })),
    conflictRefreshHint: t('organizer.layout.conflictRefreshHint'),
    operationalReady: t('organizer.layout.operationalReady'),
    operationalNotReady: t('organizer.layout.operationalNotReady'),
    publicReady: t('organizer.layout.publicReady'),
    publicNotReady: t('organizer.layout.publicNotReady'),
    rowCreated: t('organizer.layout.rowCreated'),
    rowUpdated: t('organizer.layout.rowUpdated'),
    rowDeleted: t('organizer.layout.rowDeleted'),
    rowArchived: t('organizer.layout.rowArchived'),
    rowUnarchived: t('organizer.layout.rowUnarchived'),
    rowsReordered: t('organizer.layout.rowsReordered'),
    siteCreated: t('organizer.layout.siteCreated'),
    sitesGenerated: t('organizer.layout.sitesGenerated'),
    siteUpdated: t('organizer.layout.siteUpdated'),
    sitesReordered: t('organizer.layout.sitesReordered'),
    siteDeleted: t('organizer.layout.siteDeleted'),
    fallbackError: t('organizer.layout.fallbackError'),
    renameLockedHint: t('organizer.layout.renameLockedHint'),
    categoryLockedHint: t('organizer.layout.categoryLockedHint'),
    structureLockedHint: t('organizer.layout.structureLockedHint'),
    disableLockedHint: t('organizer.layout.disableLockedHint'),
    archiveBlockedHint: t('organizer.layout.archiveBlockedHint'),
    unarchiveHint: t('organizer.layout.unarchiveHint'),
    generateAtomicHint: t('organizer.layout.generateAtomicHint'),
    availabilityStatus: t('organizer.layout.availabilityStatus'),
    availabilityStatusHelp: t('organizer.layout.availabilityStatusHelp'),
    publicationTitle: t('organizer.layout.publicationTitle'),
    publicationHelp: t('organizer.layout.publicationHelp'),
    published: t('organizer.layout.published'),
    notPublished: t('organizer.layout.notPublished'),
    publishPublicMap: t('organizer.layout.publishPublicMap'),
    unpublishPublicMap: t('organizer.layout.unpublishPublicMap'),
    entranceNoteLabel: t('organizer.layout.entranceNoteLabel'),
    selectEventPrompt: t('organizer.layout.selectEventPrompt'),
    selectEventOption: t('organizer.layout.selectEventOption'),
    loadingLayout: t('organizer.layout.loadingLayout'),
    noSpace: t('organizer.layout.noSpace'),
    noCategory: t('organizer.layout.noCategory'),
    physicalRowLabel: t('organizer.layout.physicalRowLabel'),
    selectPhysicalRow: t('organizer.layout.selectPhysicalRow'),
    siteGrid: t('organizer.layout.siteGrid'),
    reorderSites: t('organizer.layout.reorderSites'),
    noSitesInRow: t('organizer.layout.noSitesInRow'),
    noReadinessBlockers: t('organizer.layout.noReadinessBlockers'),
    selectCategory: t('organizer.layout.selectCategory'),
    selectSpaceType: t('organizer.layout.selectSpaceType'),
    rowCategoryA: t('organizer.layout.rowCategoryA'),
    rowCategoryB: t('organizer.layout.rowCategoryB'),
    rowCategoryC: t('organizer.layout.rowCategoryC'),
    rowCategoryD: t('organizer.layout.rowCategoryD'),
    targetRow: t('organizer.layout.targetRow'),
    displayOrder: t('organizer.layout.displayOrder'),
    rowLabelPrefix: t('organizer.layout.rowLabelPrefix'),
    noPreview: t('organizer.layout.noPreview'),
    generateSitesAction: (count) => t('organizer.layout.generateSitesAction', { count }),
    generating: t('organizer.layout.generating'),
    available: t('organizer.layout.available'),
    reserved: t('organizer.layout.reserved'),
    confirmed: t('organizer.layout.confirmed'),
    lockedLegend: t('organizer.layout.lockedLegend'),
    delete: t('organizer.layout.delete'),
    deleteLocked: t('organizer.layout.deleteLocked'),
    renameLocked: t('organizer.layout.renameLocked'),
    categoryLocked: t('organizer.layout.categoryLocked'),
    archiveLocked: t('organizer.layout.archiveLocked'),
    rowStillHasSites: t('organizer.layout.rowStillHasSites'),
    unresolvedHelp: t('organizer.layout.unresolvedHelp'),
    publicPublishedToast: t('organizer.layout.publicPublishedToast'),
    publicUnpublishedToast: t('organizer.layout.publicUnpublishedToast'),
    confirmUnpublish: t('organizer.layout.confirmUnpublish'),
    confirmDeleteRow: t('organizer.layout.confirmDeleteRow'),
    confirmArchiveRow: t('organizer.layout.confirmArchiveRow'),
    confirmDeleteSite: t('organizer.layout.confirmDeleteSite'),
    confirmReorderSites: (label) => t('organizer.layout.confirmReorderSites', { label }),
    confirmGenerateSites: (count, label) =>
      t('organizer.layout.confirmGenerateSites', { count, label }),
  };
}

/** Prefer in components: `computed(() => getLayoutCopy(t))` with useI18n(). */
export function getLayoutCopy(t = tt) {
  return buildLayoutCopy(t);
}

/** Proxy re-reads tt() on each access (helpers / non-setup call sites). */
export const LAYOUT_COPY = new Proxy(
  {},
  {
    get(_target, prop) {
      if (typeof prop === 'symbol') return undefined;
      const copy = buildLayoutCopy();
      return copy[prop];
    },
  },
);

function translateCodedMap(prefix, code, t = tt) {
  if (!code) return code;
  const key = `${prefix}.${code}`;
  const translated = t(key);
  return translated === key ? code : translated;
}

export function readinessMessage(code, t = tt) {
  return translateCodedMap('organizer.layout.readiness', code, t);
}

export function layoutErrorMessage(error, t = tt) {
  const code = error?.response?.data?.error;
  if (code) {
    const key = `organizer.layout.errors.${code}`;
    const translated = t(key);
    if (translated !== key) return translated;
  }
  const message = error?.response?.data?.message;
  if (typeof message === 'string' && message.trim() !== '') {
    return message.replace(/^\d{3}\s+[A-Za-z ]+:\s*/, '');
  }
  return t('organizer.layout.fallbackError');
}

export function occupancyLabel(status, t = tt) {
  return translateCodedMap('organizer.layout.occupancy', status, t);
}

export function siteStatusLabel(status, t = tt) {
  return translateCodedMap('organizer.layout.siteStatus', status, t);
}

/** Resolves through tt on each access. */
export const READINESS_BLOCKER_MESSAGES = new Proxy(
  {},
  {
    get(_target, prop) {
      if (typeof prop === 'symbol') return undefined;
      return readinessMessage(prop);
    },
  },
);

/** Resolves through tt on each access. */
export const LAYOUT_ERROR_MESSAGES = new Proxy(
  {},
  {
    get(_target, prop) {
      if (typeof prop === 'symbol') return undefined;
      return translateCodedMap('organizer.layout.errors', prop);
    },
  },
);

export const OCCUPANCY_LABELS = new Proxy(
  {},
  {
    get(_target, prop) {
      if (typeof prop === 'symbol') return undefined;
      return occupancyLabel(prop);
    },
  },
);

export const SITE_STATUS_LABELS = new Proxy(
  {},
  {
    get(_target, prop) {
      if (typeof prop === 'symbol') return undefined;
      return siteStatusLabel(prop);
    },
  },
);
