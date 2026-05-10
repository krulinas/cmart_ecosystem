import { createRouter, createWebHistory } from 'vue-router';
import Registration from './Registration.vue';
import AdminDashboard from './AdminDashboard.vue';

const routes = [
  { path: '/', component: Registration },
  { path: '/admin', component: AdminDashboard }
];

const router = createRouter({
  history: createWebHistory(),
  routes
});

export default router;