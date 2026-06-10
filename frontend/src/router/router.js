import { createRouter, createWebHistory } from 'vue-router';

// ==========================================
// 1. The Architectural Split (Guest vs Auth)
// ==========================================
import PublicLanding from '../views/public/PublicLanding.vue';
import Registration from '../views/auth/Registration.vue';
import AdminDashboard from '../views/dashboards/AdminDashboard.vue';
import Login from '../views/auth/Login.vue';
import Register from '../views/auth/Register.vue';
import UumDashboard from '../views/dashboards/UumDashboard.vue';
import VendorDashboard from '../views/dashboards/VendorDashboard.vue';
import EventCalendar from '../components/EventCalendar.vue';

import { useAuthStore } from '../stores/auth';
import { useBossPreviewStore } from '../stores/bossPreview';
import { ALL_WORKSPACE_HASHES, BOSS_ONLY_HASHES } from '../config/workspaceNav';

const routes = [
  // Zone 1: The Public Face
  { path: '/', component: PublicLanding }, // Guests land here
  { path: '/login', component: Login },
  { path: '/register', component: Register },
  { path: '/calendar', component: EventCalendar },
  
  // Zone 2: The Vendor Hub
  {
    path: '/dashboard',
    component: VendorDashboard,
    meta: { requiresAuth: true, roles: ['community'] },
  },
  {
    path: '/vendor-booking',
    component: Registration,
    meta: { requiresAuth: true, vendorApproved: true },
  },
  
  // Zone 3: The CMart back-office
  {
    path: '/admin',
    component: AdminDashboard,
    meta: { requiresAuth: true, roles: ['cmart_staff', 'cmart_admin'] },
  },

  // Zone 4: UUM Oversight
  {
    path: '/uum',
    component: UumDashboard,
    meta: { requiresAuth: true, roles: ['uum'] },
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();

  // Rehydrate user session if they have a token but no user data loaded
  if (auth.token && !auth.user) {
    try {
      await auth.fetchMe();
    } catch {
      return { path: '/login', query: { redirect: to.fullPath } };
    }
  }

  // Guard: Must be authenticated
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { path: '/login', query: { redirect: to.fullPath } };
  }

  // Guard: Must have correct role
  if (to.meta.roles && !auth.hasAnyRole(to.meta.roles)) {
    return auth.isAuthenticated ? auth.homeForUser() : '/login';
  }

  // Guard: Vendor approval check
  if (to.meta.vendorApproved && !auth.isApprovedVendor) {
    return auth.isAuthenticated ? auth.homeForUser() : '/login';
  }

  // ==========================================
  // 2. The Guest-Only Guard
  // ==========================================
  // If an authenticated user tries to view the public landing page or login/register,
  // redirect them instantly to their respective dashboard.
  if ((to.path === '/' || to.path === '/login' || to.path === '/register') && auth.isAuthenticated) {
    return auth.homeForUser();
  }

  // Guard: Admin sub-view permissions
  if (to.path === '/admin') {
    const bossPreview = useBossPreviewStore();
    const hash = (to.hash || '#bookings').replace('#', '');
    const effectiveRole =
      auth.role === 'cmart_admin' && bossPreview.viewAsStaff ? 'cmart_staff' : auth.role;

    if (BOSS_ONLY_HASHES.includes(hash) && effectiveRole !== 'cmart_admin') {
      return { path: '/admin', hash: '#bookings' };
    }

    if (hash && !ALL_WORKSPACE_HASHES.includes(hash)) {
      return { path: '/admin', hash: '#bookings' };
    }
  }

  return true;
});

export default router;