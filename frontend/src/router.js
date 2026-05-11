import { createRouter, createWebHistory } from 'vue-router';
import CommunityPortal from './CommunityPortal.vue';
import Registration from './Registration.vue';
import AdminDashboard from './AdminDashboard.vue';

const routes = [
  // Zone 1: The Public Face
  { path: '/', component: CommunityPortal },
  
  // Zone 2: The Vendor Hub
  { path: '/vendor-booking', component: Registration },
  
  // Zone 3: The CMART Back-Office
  { path: '/admin', component: AdminDashboard }
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

export default router;