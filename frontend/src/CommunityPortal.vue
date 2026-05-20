<template>
  <div class="min-h-screen bg-gray-50">
    <nav class="bg-white shadow-md sticky top-0 z-50">
      <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
        <div class="text-3xl font-extrabold tracking-tight">
          <span class="text-cmart-accent">Carboot@</span><span class="text-brand-500">CMart</span>
        </div>

        <div class="hidden md:flex items-center space-x-6">
          <router-link to="/" class="text-gray-600 hover:text-brand-600 font-semibold transition">Home</router-link>
          <router-link to="/vendor-booking" class="text-gray-600 hover:text-brand-600 font-semibold transition">Vendor Booking</router-link>
          
          <router-link v-if="!auth.isAuthenticated" to="/register" class="text-gray-600 hover:text-brand-600 font-semibold transition">Join Community</router-link>
          
          <router-link
            v-if="auth.isAuthenticated"
            :to="auth.homeForUser()"
            class="text-gray-600 hover:text-brand-600 font-semibold transition"
          >Workspace</router-link>
          
          <router-link
            v-if="!auth.isAuthenticated"
            to="/login"
            class="bg-brand-500 text-white px-5 py-2 rounded-lg shadow hover:bg-brand-600 transition text-sm font-bold"
          >Login</router-link>
          
          <button
            v-else
            @click="logout"
            class="bg-brand-500 text-white px-5 py-2 rounded-lg shadow hover:bg-brand-600 transition text-sm font-bold"
          >Logout</button>
        </div>

        <div class="md:hidden flex items-center">
          <button @click="toggleMobileMenu" class="text-gray-800 hover:text-brand-600 focus:outline-none transition">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path v-if="!isMobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
              <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      </div>

      <transition 
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="transform -translate-y-4 opacity-0"
        enter-to-class="transform translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="transform translate-y-0 opacity-100"
        leave-to-class="transform -translate-y-4 opacity-0"
      >
        <div v-show="isMobileMenuOpen" class="md:hidden bg-white border-t border-gray-100 absolute w-full shadow-lg">
          <div class="px-6 py-4 flex flex-col space-y-4">
            <router-link to="/" @click="isMobileMenuOpen = false" class="text-gray-700 hover:text-brand-600 font-semibold text-lg">Home</router-link>
            <router-link to="/vendor-booking" @click="isMobileMenuOpen = false" class="text-gray-700 hover:text-brand-600 font-semibold text-lg">Vendor Booking</router-link>
            
            <router-link v-if="!auth.isAuthenticated" to="/register" @click="isMobileMenuOpen = false" class="text-gray-700 hover:text-brand-600 font-semibold text-lg">Join Community</router-link>
            
            <router-link
              v-if="auth.isAuthenticated"
              :to="auth.homeForUser()"
              @click="isMobileMenuOpen = false"
              class="text-gray-700 hover:text-brand-600 font-semibold text-lg"
            >Workspace</router-link>
            
            <hr class="border-gray-200">
            
            <router-link
              v-if="!auth.isAuthenticated"
              to="/login"
              @click="isMobileMenuOpen = false"
              class="bg-brand-500 text-white px-4 py-3 rounded-lg text-center font-bold shadow hover:bg-brand-600 transition"
            >Login</router-link>
            
            <button
              v-else
              @click="() => { logout(); isMobileMenuOpen = false; }"
              class="bg-brand-500 text-white px-4 py-3 rounded-lg text-center font-bold shadow w-full hover:bg-brand-600 transition"
            >Logout</button>
          </div>
        </div>
      </transition>
    </nav>

    <header class="bg-gradient-to-br from-brand-600 to-brand-500 text-white text-center py-24 px-6 shadow-inner">
      <h1 class="text-4xl sm:text-6xl font-extrabold mb-6 drop-shadow-md">
        Changlun Weekend Market
      </h1>
      <p class="text-lg sm:text-2xl mb-10 font-medium max-w-2xl mx-auto text-white/90">
        Join our community, find amazing deals, or start your micro-business this weekend.
      </p>
      <div class="flex flex-col sm:flex-row justify-center items-center space-y-4 sm:space-y-0 sm:space-x-6">
        <router-link to="/calendar" class="bg-white text-brand-500 font-semibold py-2 px-6 rounded-full hover:bg-gray-100 transition duration-300 inline-block text-center">
          View Event Calendar
        </router-link>
        <router-link to="/vendor-booking" class="w-full sm:w-auto bg-transparent border-2 border-white text-white font-bold py-3 px-8 rounded-full shadow-lg hover:bg-white hover:text-brand-600 transition transform hover:-translate-y-1">
          Book a Space
        </router-link>
      </div>
    </header>

    <section class="mt-20 max-w-4xl mx-auto bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden">
        
        <div class="bg-gradient-to-r from-brand-600 to-brand-500 p-8 text-center text-white">
          <h2 class="text-3xl font-extrabold mb-2">Community Voice</h2>
          <p class="text-lg opacity-90">Help us improve the Carboot@CMart experience for everyone!</p>
        </div>
        
        <div class="p-8 border-b border-gray-100">
          <CommunityFeedback @submitted="onFeedbackSubmitted" />
        </div>

        <div class="p-8 bg-gray-50">
          <h3 class="text-2xl font-bold text-gray-800 mb-6 text-center">What the Community Says</h3>
          
          <div v-if="loadingReviews" class="text-center text-gray-500 animate-pulse font-semibold">
            Loading reviews...
          </div>
          
          <div v-else-if="communityReviews.length === 0" class="text-center text-gray-500 italic">
            No reviews yet. Be the first!
          </div>
          
          <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div 
              v-for="review in communityReviews" 
              :key="review.id" 
              class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition"
            >
              <div class="flex items-center justify-between mb-3">
                <div class="font-bold text-gray-800 flex items-center space-x-2">
                  <div class="bg-brand-100 text-brand-600 rounded-full h-8 w-8 flex items-center justify-center text-sm">
                    {{ review.user?.name ? review.user.name.charAt(0).toUpperCase() : 'A' }}
                  </div>
                  <span>{{ review.user?.name || 'Anonymous' }}</span>
                </div>
                <div class="text-right text-xs text-ink-500 space-y-0.5">
                  <div class="text-brand-500">
                    Service:
                    <span v-for="star in 5" :key="'s-' + star">{{ star <= (review.service_rating || 0) ? '★' : '☆' }}</span>
                  </div>
                  <div class="text-brand-600">
                    Value:
                    <span v-for="star in 5" :key="'v-' + star">{{ star <= (review.value_rating || 0) ? '★' : '☆' }}</span>
                  </div>
                </div>
              </div>
              <p v-if="review.comments" class="text-gray-600 text-sm italic mt-2">"{{ review.comments }}"</p>
            </div>
          </div>
        </div>
      </section>

    <main class="py-16 px-6 max-w-7xl mx-auto">
      
      <section ref="calendarSection" class="mb-20 scroll-mt-24">
        <div class="flex justify-between items-end mb-8">
          <h2 class="text-3xl font-extrabold text-gray-800 border-l-4 border-brand-600 pl-4">Upcoming Carboot Dates</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div v-for="event in upcomingEvents" :key="event.id" class="bg-white rounded-xl shadow-md border border-gray-100 p-6 hover:shadow-lg transition">
            <div class="flex items-center space-x-3 mb-4">
              <div class="bg-brand-100 text-brand-600 rounded-lg p-3 text-center min-w-[60px]">
                <span class="block text-2xl font-black">{{ event.day }}</span>
                <span class="block text-xs uppercase font-bold">{{ event.month }}</span>
              </div>
              <div>
                <h3 class="text-lg font-bold text-gray-800">{{ event.title }}</h3>
                <p class="text-sm text-gray-500">{{ event.time }}</p>
              </div>
            </div>
            <div class="flex justify-between items-center mt-4 pt-4 border-t border-gray-100">
              <span :class="['text-xs font-bold px-3 py-1 rounded-full', event.statusClass]">{{ event.status }}</span>
              <router-link to="/vendor-booking" class="text-sm font-semibold text-brand-600 hover:text-brand-700">
                Book Now >
              </router-link>
            </div>
          </div>
        </div>
      </section>

      <section>
        <div class="flex justify-between items-end mb-8">
          <h2 class="text-3xl font-extrabold text-gray-800 border-l-4 border-brand-500 pl-4">Latest CMart Updates</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <div v-for="news in latestNews" :key="news.id" class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-xl transition group">
            <div class="h-48 bg-gray-200 relative overflow-hidden">
              <img :src="news.image" alt="News Cover" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
              <div class="absolute top-4 left-4 bg-gray-900 bg-opacity-75 text-white text-xs font-bold px-3 py-1 rounded-full">
                {{ news.category }}
              </div>
            </div>
            <div class="p-6">
              <p class="text-xs text-gray-400 mb-2">{{ news.date }}</p>
              <h3 class="text-xl font-bold text-gray-800 mb-3 group-hover:text-brand-600 transition">{{ news.title }}</h3>
              <p class="text-gray-600 text-sm line-clamp-3">{{ news.excerpt }}</p>
              <button class="mt-4 text-brand-600 font-semibold text-sm hover:underline">Read More</button>
            </div>
          </div>
        </div>
      </section>

    </main>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useAuthStore } from './stores/auth';
import CommunityFeedback from './CommunityFeedback.vue';
import api from './services/api';

const auth = useAuthStore();
const router = useRouter();
const toast = useToast();

const logout = async () => {
  await auth.logout();
  toast.success('200 OK: Session terminated successfully.');
  router.push('/');
};

// Calendar Logic
const calendarSection = ref(null);
const scrollToCalendar = () => {
  if (calendarSection.value) {
    calendarSection.value.scrollIntoView({ behavior: 'smooth' });
  }
};

// Upcoming Events (Dummy Data)
const upcomingEvents = ref([
  { id: 1, day: '16', month: 'May', title: 'CMart Weekly Carboot', time: '8:00 AM - 2:00 PM', status: 'Available', statusClass: 'bg-green-100 text-green-700' },
  { id: 2, day: '17', month: 'May', title: 'CMart Weekly Carboot', time: '8:00 AM - 2:00 PM', status: 'Almost Full', statusClass: 'bg-brand-100 text-brand-600' },
  { id: 3, day: '23', month: 'May', title: 'Changlun Mega Carboot', time: '8:00 AM - 6:00 PM', status: 'Registration Open', statusClass: 'bg-brand-100 text-brand-600' }
]);

// Latest News (Dummy Data)
const latestNews = ref([
  { id: 1, category: 'Announcement', date: 'May 12, 2026', title: 'Digital System Introduced with OIB Developers', excerpt: 'CMart proudly launches a new booking portal to simplify invoice management...', image: 'https://images.unsplash.com/photo-1556761175-5973dc0f32b7?q=80&w=800&auto=format&fit=crop' },
  { id: 2, category: 'Community', date: 'May 10, 2026', title: 'Pasar Karat Vendors Transition to CMart', excerpt: 'Over 20 vendors from outside sites have joined our ecosystem...', image: 'https://images.unsplash.com/photo-1472851294608-062f18ce0411?q=80&w=800&auto=format&fit=crop' },
  { id: 3, category: 'Vendor Tips', date: 'May 05, 2026', title: 'How to Choose the Right Space Size?', excerpt: 'Do you need an M or L sized space? Learn the exact dimensions and pricing...', image: 'https://images.unsplash.com/photo-1533900298318-6b8da08a523e?q=80&w=800&auto=format&fit=crop' }
]);

// --- FEEDBACK & COMMUNITY REVIEW LOGIC ---
const communityReviews = ref([]);
const loadingReviews = ref(true);

const fetchReviews = async () => {
  loadingReviews.value = true;
  try {
    const response = await api.get('/feedbacks');
    communityReviews.value = response.data.data || response.data;
  } catch (error) {
    console.error('Failed to fetch reviews:', error);
  } finally {
    loadingReviews.value = false;
  }
};

const onFeedbackSubmitted = async () => {
  toast.success('Feedback submitted successfully! Thank you for your support.');
  await fetchReviews();
};

// Fetch reviews automatically when the portal loads
onMounted(() => {
  fetchReviews();
});

const isMobileMenuOpen = ref(false);
const toggleMobileMenu = () => {
  isMobileMenuOpen.value = !isMobileMenuOpen.value;
};
</script>