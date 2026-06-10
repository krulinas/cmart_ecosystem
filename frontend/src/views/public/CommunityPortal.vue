<template>
  <div class="min-h-screen bg-gray-50 flex flex-col">
    <nav class="bg-white shadow-sm sticky top-0 z-50 border-b border-gray-200">
      <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
        <router-link to="/" class="flex items-center">
          <img src="/cmart_logo.png" alt="Carboot Logo" class="h-10 w-auto object-contain" />
          <span class="ml-3 text-lg font-black text-gray-800 tracking-tight hidden sm:block">Vendor Portal</span>
        </router-link>

        <div class="flex items-center space-x-6">
          <router-link to="/calendar" class="text-sm font-bold text-gray-600 hover:text-brand-600 transition">Calendar</router-link>
          <div class="h-6 w-px bg-gray-300"></div>
          <button
            @click="logout"
            class="text-gray-500 hover:text-red-600 font-bold text-sm transition flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
            Logout
          </button>
        </div>
      </div>
    </nav>

    <header class="bg-brand-600 pt-16 pb-24 px-6 relative overflow-hidden">
      <div class="absolute inset-0 opacity-10 pointer-events-none" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 24px 24px;"></div>
      
      <div class="max-w-7xl mx-auto relative z-10 flex flex-col md:flex-row items-center justify-between gap-8">
        <div>
          <p class="text-brand-200 font-bold uppercase tracking-wider text-sm mb-2">Vendor Dashboard</p>
          <h1 class="text-4xl md:text-5xl font-black text-white tracking-tight mb-2">
            Welcome back, {{ userDisplayName }}!
          </h1>
          <p class="text-brand-100 text-lg">Ready to secure your spot for the upcoming weekend?</p>
        </div>
        
        <router-link
          to="/vendor-booking"
          class="shrink-0 bg-white text-brand-600 font-black py-4 px-8 rounded-xl shadow-[0_8px_30px_rgb(0,0,0,0.12)] hover:-translate-y-1 hover:shadow-[0_10px_40px_rgb(0,0,0,0.2)] transition-all duration-300 flex items-center gap-3 text-lg"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
          Book a Space Now
        </router-link>
      </div>
    </header>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 w-full -mt-12 relative z-20 pb-20">
      
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex items-center justify-between">
          <div>
            <p class="text-sm font-bold text-gray-500 mb-1">Upcoming Events</p>
            <p class="text-3xl font-black text-gray-900">{{ upcomingEvents.length }}</p>
          </div>
          <div class="bg-brand-50 p-4 rounded-full text-brand-600">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
          </div>
        </div>
        </div>

      <section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 mb-12">
        <div class="flex justify-between items-center mb-8 border-b border-gray-100 pb-4">
          <h2 class="text-2xl font-black text-gray-900">Available Dates</h2>
          <router-link to="/calendar" class="text-sm font-bold text-brand-600 hover:underline">View All</router-link>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <div v-for="event in upcomingEvents.slice(0,4)" :key="event.id" class="flex items-center justify-between p-4 rounded-xl border border-gray-100 hover:border-brand-200 hover:bg-brand-50/50 transition group">
            <div class="flex items-center gap-4">
              <div class="bg-gray-100 text-gray-700 rounded-lg p-2 text-center min-w-[60px] group-hover:bg-brand-500 group-hover:text-white transition">
                <span class="block text-2xl font-black leading-none">{{ event.day }}</span>
                <span class="block text-[10px] uppercase font-bold">{{ event.month }}</span>
              </div>
              <div>
                <h3 class="font-bold text-gray-900">{{ event.title }}</h3>
                <p class="text-xs text-gray-500">{{ event.time }}</p>
              </div>
            </div>
            <router-link to="/vendor-booking" class="text-sm font-bold bg-brand-100 text-brand-700 px-4 py-2 rounded-lg hover:bg-brand-200 transition">
              Book
            </router-link>
          </div>
        </div>
      </section>

    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useAuthStore } from '../../stores/auth';
import api from '../../services/api';

const auth = useAuthStore();
const router = useRouter();
const toast = useToast();

const userDisplayName = computed(() => auth.user?.name || 'Vendor');
const upcomingEvents = ref([]);

const logout = async () => {
  await auth.logout();
  toast.success('Session terminated successfully.');
  router.push('/login');
};

const fetchDashboardData = async () => {
  try {
    const response = await api.get('/events');
    const events = Array.isArray(response.data) ? response.data : [];
    
    upcomingEvents.value = events.map(ev => {
      const start = new Date(ev.starts_at);
      return {
        id: ev.id,
        day: String(start.getDate()),
        month: start.toLocaleString('en-GB', { month: 'short' }),
        title: ev.title,
        time: `${start.toLocaleTimeString('en-GB', { hour: 'numeric', minute: '2-digit' })}`,
        status: ev.status,
      };
    });
  } catch (error) {
    console.error('Failed to load dashboard data', error);
  }
};

onMounted(async () => {
  if (!auth.isAuthenticated) {
    router.push('/login');
    return;
  }
  await fetchDashboardData();
});
</script>