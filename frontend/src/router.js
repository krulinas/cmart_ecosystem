import { createRouter, createWebHistory } from 'vue-router';
import CommunityPortal from './CommunityPortal.vue';
import Registration from './Registration.vue';
import AdminDashboard from './AdminDashboard.vue';
import Login from './Login.vue';
import Register from './Register.vue';
import UumDashboard from './UumDashboard.vue';

// ==========================================
// 1. IMPORT KOMPONEN KALENDAR BARU AWAK
// ==========================================
import EventCalendar from './EventCalendar.vue'; // Pastikan awak dah buat fail EventCalendar.vue ni ya!

import { useAuthStore } from './stores/auth';

const routes = [
  // Zone 1: The Public Face
  { path: '/', component: CommunityPortal },
  { path: '/login', component: Login },
  { path: '/register', component: Register },
  
  // ==========================================
  // 2. LALUAN BARU UNTUK KALENDAR
  // ==========================================
  { path: '/calendar', component: EventCalendar },
  
  // Zone 2: The Vendor Hub
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

  if (auth.token && !auth.user) {
    try {
      await auth.fetchMe();
    } catch {
      return { path: '/login', query: { redirect: to.fullPath } };
    }
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

  if ((to.path === '/login' || to.path === '/register') && auth.isAuthenticated) {
    return auth.homeForUser();
  }

  return true;
});

export default router;