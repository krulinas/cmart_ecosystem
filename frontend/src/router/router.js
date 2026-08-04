import { createRouter, createWebHistory } from 'vue-router';

import PublicLanding from '../views/public/PublicLanding.vue';
import CommunityPortal from '../views/public/CommunityPortal.vue';
import Registration from '../views/auth/Registration.vue';
import AdminDashboard from '../views/dashboards/AdminDashboard.vue';
import PublicLogin from '../views/auth/PublicLogin.vue';
import ManagementLogin from '../views/auth/ManagementLogin.vue';
import Register from '../views/auth/Register.vue';
import VendorDashboard from '../views/dashboards/VendorDashboard.vue';
import VendorProfile from '../views/vendor/VendorProfile.vue';
import VendorCheckoutPage from '../views/vendor/VendorCheckoutPage.vue';
import VendorManageBookingsPage from '../views/vendor/VendorManageBookingsPage.vue';
import VendorManageEventPassesPage from '../views/vendor/VendorManageEventPassesPage.vue';
import VendorManageItemsPage from '../views/vendor/VendorManageItemsPage.vue';
import VendorManageCustomerReservationsPage from '../views/vendor/VendorManageCustomerReservationsPage.vue';
import MyReservationsPage from '../views/vendor/MyReservationsPage.vue';
import VendorInsightsPage from '../views/vendor/VendorInsightsPage.vue';
import VendorPaymentHistoryPage from '../views/vendor/VendorPaymentHistoryPage.vue';
import ReuseMarketplace from '../views/public/ReuseMarketplace.vue';
import EventCalendar from '../components/EventCalendar.vue';
import StaffVerifyBooking from '../views/staff/StaffVerifyBooking.vue';

import { useAuthStore } from '../stores/auth';
import {
  ALL_WORKSPACE_HASHES,
  ANALYTICS_HUB_TAB_STORAGE_KEY,
  CARBOOT_ANALYTICS_HASHES,
  LEGACY_ANALYTICS_HASH_REDIRECTS,
} from '../config/workspaceNav';
import { hasCapability, CAPABILITIES } from '../utils/managementCapabilities';
import { isOrganizerEquivalent, MANAGEMENT_WORKSPACE_ROLES, normalizeRole } from '../utils/managementRoles';
import { resolveVendorDashboardLegacyHash } from '../utils/vendorDashboardLegacy';

const MANAGEMENT_PROTECTED_PREFIXES = ['/admin', '/organizer/'];

/** Community-authenticated vendor workspace routes (same gate as /dashboard today). */
const COMMUNITY_AUTH_META = { requiresAuth: true, roles: ['community'] };

function isManagementProtectedRoute(path) {
  return MANAGEMENT_PROTECTED_PREFIXES.some((prefix) => path.startsWith(prefix));
}

function loginRedirectFor(to) {
  if (isManagementProtectedRoute(to.path)) {
    return { path: '/management/login', query: { redirect: to.fullPath } };
  }

  return { path: '/login', query: { redirect: to.fullPath } };
}

const MANAGEMENT_ROLES = MANAGEMENT_WORKSPACE_ROLES;

function isManagementRole(auth) {
  return auth.hasAnyRole(MANAGEMENT_ROLES);
}

function homeRedirectFor(auth, fromPath) {
  const destination = auth.homeForUser();
  const safePath = destination === fromPath ? '/community' : destination;
  return { path: safePath, replace: true };
}

function managementAccessRedirect(auth, to) {
  if (!auth.token || !auth.isAuthenticated) {
    return loginRedirectFor(to);
  }
  if (!isManagementRole(auth)) {
    return homeRedirectFor(auth, to.path);
  }
  return true;
}

const routes = [
  {
    path: '/',
    name: 'home',
    component: PublicLanding,
    meta: { public: true, redirectIfAuthenticated: true },
  },
  {
    path: '/community',
    name: 'community',
    component: CommunityPortal,
    meta: { public: true },
  },
  {
    path: '/login',
    name: 'login',
    component: PublicLogin,
    meta: { guestOnly: true },
  },
  {
    path: '/management/login',
    name: 'management-login',
    component: ManagementLogin,
    meta: { guestOnly: true, managementGuestOnly: true, robots: 'noindex, nofollow' },
  },
  {
    path: '/register',
    name: 'register',
    component: Register,
    meta: { guestOnly: true },
  },
  {
    path: '/marketplace',
    name: 'marketplace',
    component: ReuseMarketplace,
    meta: { public: true },
  },
  {
    path: '/calendar',
    name: 'calendar',
    component: EventCalendar,
    meta: { public: true },
  },
  {
    path: '/dashboard',
    name: 'vendor-dashboard',
    component: VendorDashboard,
    meta: COMMUNITY_AUTH_META,
  },
  {
    path: '/vendor/manage/bookings',
    name: 'vendor-manage-bookings',
    component: VendorManageBookingsPage,
    meta: COMMUNITY_AUTH_META,
  },
  {
    path: '/vendor/manage/event-passes',
    name: 'vendor-manage-event-passes',
    component: VendorManageEventPassesPage,
    meta: COMMUNITY_AUTH_META,
  },
  {
    path: '/vendor/manage/items',
    name: 'vendor-manage-items',
    component: VendorManageItemsPage,
    meta: COMMUNITY_AUTH_META,
  },
  {
    path: '/vendor/manage/customer-reservations',
    name: 'vendor-manage-customer-reservations',
    component: VendorManageCustomerReservationsPage,
    meta: COMMUNITY_AUTH_META,
  },
  {
    path: '/my-reservations',
    name: 'my-reservations',
    component: MyReservationsPage,
    meta: COMMUNITY_AUTH_META,
  },
  {
    path: '/vendor/insights',
    name: 'vendor-insights',
    component: VendorInsightsPage,
    meta: COMMUNITY_AUTH_META,
  },
  {
    path: '/vendor/payment-history',
    name: 'vendor-payment-history',
    component: VendorPaymentHistoryPage,
    meta: COMMUNITY_AUTH_META,
  },
  {
    path: '/profile',
    name: 'vendor-profile',
    component: VendorProfile,
    meta: COMMUNITY_AUTH_META,
  },
  {
    path: '/dashboard/checkout/:bookingId',
    name: 'vendor-checkout',
    component: VendorCheckoutPage,
    meta: COMMUNITY_AUTH_META,
  },
  {
    path: '/vendor-booking',
    name: 'vendor-booking',
    component: Registration,
    meta: COMMUNITY_AUTH_META,
  },
  {
    path: '/organizer/verify-booking/:bookingId',
    name: 'organizer-verify-booking',
    component: StaffVerifyBooking,
    meta: { requiresAuth: true, roles: MANAGEMENT_ROLES, organizerOnly: true },
  },
  {
    path: '/staff/verify-booking/:bookingId',
    redirect: (to) => `/organizer/verify-booking/${to.params.bookingId}`,
  },
  {
    path: '/verify-booking/:bookingId',
    redirect: (to) => `/organizer/verify-booking/${to.params.bookingId}`,
  },
  {
    path: '/admin',
    name: 'admin',
    component: AdminDashboard,
    meta: { requiresAuth: true, roles: MANAGEMENT_ROLES },
    beforeEnter: (to) => {
      const auth = useAuthStore();
      return managementAccessRedirect(auth, to);
    },
  },
  {
    path: '/uum',
    redirect: () => {
      const auth = useAuthStore();
      if (auth.isAuthenticated && isOrganizerEquivalent(auth.role)) {
        return '/admin';
      }
      return '/management/login';
    },
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    redirect: '/',
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, _from, savedPosition) {
    if (savedPosition) return savedPosition;
    if (to.hash) return { el: to.hash, behavior: 'smooth' };
    return { top: 0 };
  },
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();

  // Legacy dashboard section hashes → discrete routes (bookmarks / old links).
  if (to.path === '/dashboard' && to.hash) {
    const legacyPath = resolveVendorDashboardLegacyHash(to.hash);
    if (legacyPath) {
      return { path: legacyPath, replace: true };
    }
  }

  if (isManagementProtectedRoute(to.path) && auth.token && auth.user && !isManagementRole(auth)) {
    return homeRedirectFor(auth, to.path);
  }

  if (auth.token) {
    try {
      await auth.ensureSession();
    } catch {
      auth.clearSession();
      if (to.meta.requiresAuth) {
        return loginRedirectFor(to);
      }
    }
  }

  if (to.meta.redirectIfAuthenticated && auth.isAuthenticated && !to.hash) {
    return auth.homeForUser();
  }

  if (to.meta.managementGuestOnly && auth.isAuthenticated) {
    return auth.homeForUser();
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return loginRedirectFor(to);
  }

  if (to.meta.roles && !auth.hasAnyRole(to.meta.roles)) {
    if (!auth.isAuthenticated) {
      return loginRedirectFor(to);
    }
    return homeRedirectFor(auth, to.path);
  }

  if (to.meta.organizerOnly && !hasCapability(auth.role, CAPABILITIES.CARBOOT_OPERATIONS)) {
    return homeRedirectFor(auth, to.path);
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return auth.homeForUser();
  }

  if (to.path === '/admin' && isManagementRole(auth)) {
    const hash = (to.hash || '#bookings').replace('#', '');

    const legacy = LEGACY_ANALYTICS_HASH_REDIRECTS[hash];
    if (legacy) {
      try {
        sessionStorage.setItem(ANALYTICS_HUB_TAB_STORAGE_KEY, legacy.tab);
      } catch {
        /* ignore */
      }
      return { path: '/admin', hash: `#${legacy.section}`, replace: true };
    }

    if (
      CARBOOT_ANALYTICS_HASHES.includes(hash)
      && !hasCapability(auth.role, CAPABILITIES.CARBOOT_OPERATIONAL_ANALYTICS)
    ) {
      const fallback = normalizeRole(auth.role) === 'cmart_management' ? 'news' : 'bookings';
      return { path: '/admin', hash: `#${fallback}` };
    }

    if (hash && !ALL_WORKSPACE_HASHES.includes(hash)) {
      const fallback = normalizeRole(auth.role) === 'cmart_management' ? 'news' : 'bookings';
      return { path: '/admin', hash: `#${fallback}` };
    }
  }

  return true;
});

export default router;
