import { createRouter, createWebHistory } from 'vue-router';

import PublicLanding from '../views/public/PublicLanding.vue';
import CommunityPortal from '../views/public/CommunityPortal.vue';
import Registration from '../views/auth/Registration.vue';
import AdminDashboard from '../views/dashboards/AdminDashboard.vue';
import Login from '../views/auth/Login.vue';
import Register from '../views/auth/Register.vue';
import UumDashboard from '../views/dashboards/UumDashboard.vue';
import VendorDashboard from '../views/dashboards/VendorDashboard.vue';
import VendorProfile from '../views/vendor/VendorProfile.vue';
import ReuseMarketplace from '../views/public/ReuseMarketplace.vue';
import EventCalendar from '../components/EventCalendar.vue';
import StaffVerifyBooking from '../views/staff/StaffVerifyBooking.vue';

import { useAuthStore } from '../stores/auth';
import { useBossPreviewStore } from '../stores/bossPreview';
import { ALL_WORKSPACE_HASHES, MANAGER_ONLY_HASHES } from '../config/workspaceNav';
import { isManagerOrAbove, normalizeRole, ROLES, workflowRoleKey } from '../utils/managementRoles';

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
    component: Login,
    meta: { guestOnly: true },
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
    path: '/vendor-booking',
    name: 'vendor-booking',
    component: Registration,
    meta: { requiresAuth: true, roles: ['community'], vendorApproved: true },
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
    meta: { requiresAuth: true, roles: ['staff', 'manager', 'super_admin', 'cmart_staff', 'cmart_admin', 'boss'] },
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

  if (auth.token && !auth.user) {
    try {
      await auth.fetchMe();
    } catch {
      auth.clearSession();
      if (to.meta.requiresAuth) {
        return { path: '/login', query: { redirect: to.fullPath } };
      }
    }
  }

  if (to.meta.redirectIfAuthenticated && auth.isAuthenticated) {
    return auth.homeForUser();
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { path: '/login', query: { redirect: to.fullPath } };
  }

  if (to.meta.roles && !auth.hasAnyRole(to.meta.roles)) {
    return auth.isAuthenticated ? auth.homeForUser() : '/login';
  }

  if (to.meta.vendorApproved && !auth.isApprovedVendor) {
    return auth.isAuthenticated ? auth.homeForUser() : '/login';
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return auth.homeForUser();
  }

  if (to.path === '/admin') {
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
