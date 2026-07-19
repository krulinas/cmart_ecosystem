import { strict as assert } from 'node:assert';
import { describe, it } from 'node:test';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  canCommunityCancel,
  canCompleteReservation,
  canOrganizerConfirmCharge,
  canShowReserveCta,
  canVendorCancel,
  feeExplanation,
  formatReservationFee,
  isZeroFee,
  requiresNoRefundAcknowledgement,
  reservationStatusLabel,
  reserveCtaMode,
} from '../../src/utils/itemReservationDisplay.js';
import { COMMUNITY_VISITOR_LINKS, VENDOR_DASHBOARD_SECTION_LINKS } from '../../src/config/navigation.js';

const here = dirname(fileURLToPath(import.meta.url));
const root = join(here, '../..');
const read = (relativePath) => readFileSync(join(root, relativePath), 'utf8');

const apiModule = read('src/services/itemReservationsApi.js');
const detailsModal = read('src/components/MarketplaceItemDetailsModal.vue');
const confirmModal = read('src/components/ItemReservationConfirmModal.vue');
const myPanel = read('src/components/MyItemReservationsPanel.vue');
const vendorPanel = read('src/components/VendorItemReservationsPanel.vue');
const organizerPanel = read('src/views/dashboards/organizer/OrganizerItemReservationsPanel.vue');
const adminDashboard = read('src/views/dashboards/AdminDashboard.vue');
const vendorDashboard = read('src/views/dashboards/VendorDashboard.vue');
const communityPortal = read('src/views/public/CommunityPortal.vue');
const itemManager = read('src/components/VendorItemManager.vue');
const workspaceNav = read('src/config/workspaceNav.js');
const workspaceTheme = read('src/config/managementWorkspaceTheme.js');

describe('Phase 4.4 reservation display helpers', () => {
  it('maps reservation and charge labels', () => {
    assert.equal(reservationStatusLabel('pending_charge'), 'Pending Charge');
    assert.equal(reservationStatusLabel('confirmed'), 'Confirmed');
    assert.equal(formatReservationFee(10, 'MYR'), 'RM 10.00');
    assert.equal(isZeroFee(0), true);
    assert.match(feeExplanation(5), /manually outside the platform/i);
    assert.match(feeExplanation(0), /No reservation service fee/i);
  });

  it('gates reserve CTA by role and ownership', () => {
    const reservable = { is_reservable: true, is_own_item: false };
    assert.equal(canShowReserveCta({ item: reservable }), true);
    assert.equal(reserveCtaMode({ item: reservable }), 'login');
    assert.equal(reserveCtaMode({
      item: reservable,
      isAuthenticated: true,
      isCommunityMember: true,
    }), 'reserve');
    assert.equal(canShowReserveCta({
      item: { ...reservable, is_own_item: true },
      isAuthenticated: true,
      isCommunityMember: true,
    }), false);
    assert.equal(canShowReserveCta({
      item: reservable,
      isAuthenticated: true,
      isCmartWorker: true,
    }), false);
  });

  it('gates cancel, acknowledgement, and completion actions', () => {
    assert.equal(canCommunityCancel({ reservation_status: 'pending_charge' }), true);
    assert.equal(canCommunityCancel({ reservation_status: 'confirmed' }), false);
    assert.equal(canVendorCancel({ reservation_status: 'confirmed' }), true);
    assert.equal(requiresNoRefundAcknowledgement({ charge_status: 'confirmed' }), true);
    assert.equal(requiresNoRefundAcknowledgement({ charge_status: 'waived' }), false);
    assert.equal(canCompleteReservation({ reservation_status: 'confirmed' }), true);
    assert.equal(canCompleteReservation({ reservation_status: 'pending_charge' }), false);
    assert.equal(canOrganizerConfirmCharge({
      reservation_status: 'pending_charge',
      charge_status: 'required',
    }), true);
    assert.equal(canOrganizerConfirmCharge({
      reservation_status: 'pending_charge',
      charge_status: 'not_required',
    }), false);
  });
});

describe('Phase 4.4 reservation API module', () => {
  it('centralizes community, vendor, and organizer endpoints including completion', () => {
    assert.match(apiModule, /api\.post\('\/reservations'/);
    assert.match(apiModule, /\/reservations\/me/);
    assert.match(apiModule, /\/vendor\/item-reservations/);
    assert.match(apiModule, /\/vendor\/item-reservations\/\$\{publicReference\}\/complete/);
    assert.match(apiModule, /\/organizer\/events\/\$\{eventId\}\/item-reservations/);
    assert.match(apiModule, /confirm-charge/);
    assert.match(apiModule, /waive-charge/);
    assert.match(apiModule, /\/organizer\/item-reservations\/\$\{publicReference\}\/complete/);
    assert.doesNotMatch(apiModule, /payment_proof|fpx|refund_record|payout/i);
  });
});

describe('Phase 4.4 marketplace reserve UI', () => {
  it('shows role-safe Reserve CTA and login path', () => {
    assert.match(detailsModal, /data-testid="marketplace-reserve-cta"/);
    assert.match(detailsModal, /data-testid="marketplace-reserve-login"/);
    assert.match(detailsModal, /reserveCtaMode/);
    assert.match(detailsModal, /ItemReservationConfirmModal/);
  });

  it('explains manual off-platform fee and has no payment-processing fields', () => {
    assert.match(confirmModal, /data-testid="reservation-fee-explanation"/);
    assert.match(confirmModal, /feeExplanation\(/);
    assert.match(confirmModal, /does not process payment/i);
    assert.doesNotMatch(confirmModal, /card number|payment proof|fpx|bank transfer upload/i);
    assert.match(confirmModal, /item_already_reserved/);
    assert.match(confirmModal, /data-testid="reservation-success-reference"/);
  });
});

describe('Phase 4.4 community and vendor reservation UI', () => {
  it('renders My Reservations with pending-only community cancel', () => {
    assert.match(myPanel, /data-testid="my-item-reservations-root"/);
    assert.match(myPanel, /canCommunityCancel/);
    assert.match(myPanel, /cancelMyItemReservation/);
    assert.match(communityPortal, /MyItemReservationsPanel/);
    assert.match(vendorDashboard, /MyItemReservationsPanel/);
    assert.ok(COMMUNITY_VISITOR_LINKS.some((link) => link.hash === '#my-item-reservations'));
  });

  it('supports vendor cancel acknowledgement, completion, and privacy bounds', () => {
    assert.match(vendorPanel, /data-testid="vendor-item-reservations-root"/);
    assert.match(vendorPanel, /acknowledge_no_refund/);
    assert.match(vendorPanel, /data-testid="vendor-no-refund-acknowledgement"/);
    assert.match(vendorPanel, /completeVendorItemReservation/);
    assert.match(vendorPanel, /Mark Collected/);
    assert.match(vendorPanel, /Reserver contact details are not shared/);
    assert.doesNotMatch(vendorPanel, /reserving_user\?\.email|reserving_user\.email|phone/);
    assert.match(vendorDashboard, /VendorItemReservationsPanel/);
    assert.match(itemManager, /data-testid="vendor-item-active-reservation-badge"/);
    assert.ok(VENDOR_DASHBOARD_SECTION_LINKS.some((link) => link.targetId === 'vendor-item-reservations'));
  });
});

describe('Phase 4.4 organizer reservation UI', () => {
  it('gates item-reservations nav behind carboot operations', () => {
    assert.match(
      workspaceNav,
      /id:\s*'item-reservations'[\s\S]*requiredCapability:\s*CAPABILITIES\.CARBOOT_OPERATIONS/,
    );
    assert.match(
      workspaceTheme,
      /id:\s*'carboot_operations'[\s\S]*items:\s*\[[^\]]*item-reservations/,
    );
    assert.match(adminDashboard, /OrganizerItemReservationsPanel/);
    assert.match(adminDashboard, /activeSection === 'item-reservations'/);
  });

  it('covers queue filters, actions, audits, and duplicate-submit protection', () => {
    assert.match(organizerPanel, /data-testid="organizer-item-reservations-panel"/);
    assert.match(organizerPanel, /organizer-reservation-status-filter/);
    assert.match(organizerPanel, /organizer-charge-status-filter/);
    assert.match(organizerPanel, /confirmOrganizerItemReservationCharge/);
    assert.match(organizerPanel, /waiveOrganizerItemReservationCharge/);
    assert.match(organizerPanel, /expireOrganizerItemReservation/);
    assert.match(organizerPanel, /completeOrganizerItemReservation/);
    assert.match(organizerPanel, /organizer-no-refund-acknowledgement/);
    assert.match(organizerPanel, /organizer-reservation-audit-timeline/);
    assert.match(organizerPanel, /:disabled="mutating \|\| !canSubmitAction"/);
    assert.doesNotMatch(organizerPanel, /v-html/);
    assert.match(organizerPanel, /never processes payment/i);
    assert.doesNotMatch(organizerPanel, /issue a refund|process payment online|upload payment proof/i);
  });
});
