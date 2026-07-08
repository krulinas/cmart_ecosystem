import { createRouter, createWebHistory } from 'vue-router';

import PublicLanding from '../views/public/PublicLanding.vue';
import CommunityPortal from '../views/public/CommunityPortal.vue';
import Registration from '../views/auth/Registration.vue';
import AdminDashboard from '../views/dashboards/AdminDashboard.vue';
import PublicLogin from '../views/auth/PublicLogin.vue';
import ManagementLogin from '../views/auth/ManagementLogin.vue';
import Register from '../views/auth/Register.vue';
import UumDashboard from '../views/dashboards/UumDashboard.vue';
import VendorDashboard from '../views/dashboards/VendorDashboard.vue';
import VendorProfile from '../views/vendor/VendorProfile.vue';
import VendorCheckoutPage from '../views/vendor/VendorCheckoutPage.vue';
import ReuseMarketplace from '../views/public/ReuseMarketplace.vue';
import EventCalendar from '../components/EventCalendar.vue';
import StaffVerifyBooking from '../views/staff/StaffVerifyBooking.vue';

import { useAuthStore } from '../stores/auth';
import { useBossPreviewStore } from '../stores/bossPreview';
import { ALL_WORKSPACE_HASHES, MANAGER_ONLY_HASHES } from '../config/workspaceNav';
import { isManagerOrAbove, normalizeRole, ROLES, workflowRoleKey } from '../utils/managementRoles';
const MANAGEMENT_PROTECTED_PREFIXES = ['/admin', '/staff/'];

function isManagementProtectedRoute(path) {
  return MANAGEMENT_PROTECTED_PREFIXES.some((prefix) => path.startsWith(prefix));
}

function loginRedirectFor(to) {
  if (isManagementProtectedRoute(to.path)) {
    return { path: '/management/login', query: { redirect: to.fullPath } };
  }

  return { path: '/login', query: { redirect: to.fullPath } };
}

const MANAGEMENT_ROLES = ['staff', 'manager', 'super_admin', 'cmart_staff', 'cmart_admin', 'boss'];

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
  // Zone 1: Public face
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

  // Zone 2: Vendor hub
  {
    path: '/dashboard',
    name: 'vendor-dashboard',
    component: VendorDashboard,
    meta: { requiresAuth: true, roles: ['community'] },
  },
  {
    path: '/profile',
    name: 'vendor-profile',
    component: VendorProfile,
    meta: { requiresAuth: true, roles: ['community'] },
  },
  {
    path: '/dashboard/checkout/:bookingId',
    name: 'vendor-checkout',
    component: VendorCheckoutPage,
    meta: { requiresAuth: true, roles: ['community'] },
  },
  {
    path: '/vendor-booking',
    name: 'vendor-booking',
    component: Registration,
    meta: { requiresAuth: true, roles: ['community'] },
  },

  {
    path: '/staff/verify-booking/:bookingId',
    name: 'staff-verify-booking',
    component: StaffVerifyBooking,
    meta: { requiresAuth: true, roles: ['staff', 'manager', 'super_admin', 'cmart_staff', 'cmart_admin', 'boss'] },
  },
  {
    path: '/verify-booking/:bookingId',
    redirect: (to) => `/staff/verify-booking/${to.params.bookingId}`,
  },

  // Zone 3: CMart back-office
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

  // Zone 4: UUM oversight
  {
    path: '/uum',
    name: 'uum',
    component: UumDashboard,
    meta: { requiresAuth: true, roles: ['uum'] },
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

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return auth.homeForUser();
  }

  if (to.path === '/admin' && isManagementRole(auth)) {
    const bossPreview = useBossPreviewStore();
    const hash = (to.hash || '#bookings').replace('#', '');
    const effectiveRole =
      isManagerOrAbove(auth.role) && bossPreview.viewAsStaff
        ? ROLES.STAFF
        : normalizeRole(auth.role);

    if (MANAGER_ONLY_HASHES.includes(hash) && workflowRoleKey(effectiveRole) !== ROLES.MANAGER) {
      return { path: '/admin', hash: '#bookings' };
    }

    if (hash && !ALL_WORKSPACE_HASHES.includes(hash)) {
      return { path: '/admin', hash: '#bookings' };
    }
  }

  return true;
});

export default router;
