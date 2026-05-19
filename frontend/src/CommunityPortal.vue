<template>
  <div class="min-h-screen bg-gray-50">
    <nav class="bg-white shadow-md py-4 px-6 flex flex-col sm:flex-row justify-between items-center sticky top-0 z-50">
      <div class="text-3xl font-extrabold text-red-600 mb-4 sm:mb-0 tracking-tight">
        Carboot<span class="text-gray-800">@CMart</span>
      </div>
      <div class="space-x-2 sm:space-x-4 flex items-center">
        <router-link to="/" class="text-gray-600 hover:text-red-600 font-semibold transition">Home</router-link>
        <router-link to="/vendor-booking" class="text-gray-600 hover:text-red-600 font-semibold transition">Vendor Booking</router-link>
        <router-link v-if="!auth.isAuthenticated" to="/register" class="text-gray-600 hover:text-red-600 font-semibold transition">Join Community</router-link>
        <router-link
          v-if="auth.isAuthenticated"
          :to="auth.homeForUser()"
          class="text-gray-600 hover:text-red-600 font-semibold transition"
        >Workspace</router-link>
        <router-link
          v-if="!auth.isAuthenticated"
          to="/login"
          class="bg-gray-800 text-white px-4 py-2 rounded-lg shadow-md hover:bg-gray-700 transition text-sm font-bold"
        >Login</router-link>
        <button
          v-else
          @click="logout"
          class="bg-gray-800 text-white px-4 py-2 rounded-lg shadow-md hover:bg-gray-700 transition text-sm font-bold"
        >Logout</button>
      </div>
    </nav>

    <header class="bg-gradient-to-br from-red-500 to-orange-400 text-white text-center py-24 px-6 shadow-inner">
      <h1 class="text-4xl sm:text-6xl font-extrabold mb-6 drop-shadow-md">
        Changlun Weekend Market
      </h1>
      <p class="text-lg sm:text-2xl mb-10 font-medium max-w-2xl mx-auto opacity-90">
        Join our community, find amazing deals, or start your micro-business this weekend.
      </p>
      <div class="flex flex-col sm:flex-row justify-center items-center space-y-4 sm:space-y-0 sm:space-x-6">
        <router-link to="/calendar" class="bg-white text-orange-500 font-semibold py-2 px-6 rounded-full hover:bg-gray-100 transition duration-300 inline-block text-center">
  View Event Calendar
</router-link>
        <router-link to="/vendor-booking" class="w-full sm:w-auto bg-transparent border-2 border-white text-white font-bold py-3 px-8 rounded-full shadow-lg hover:bg-white hover:text-red-600 transition transform hover:-translate-y-1">
          Book a Space
        </router-link>
      </div>
    </header>

    <section class="mt-20 bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden">
        <div class="bg-gradient-to-r from-red-500 to-orange-400 p-8 text-center text-white">
          <h2 class="text-3xl font-extrabold mb-2">Suara Komuniti</h2>
          <p class="text-lg opacity-90">Bantu kami tingkatkan pengalaman Carboot CMart untuk semua!</p>
        </div>
        
        <div class="p-8 max-w-2xl mx-auto">
          <form @submit.prevent="submitFeedback" class="space-y-6">
            <div class="text-center">
              <label class="block text-gray-700 font-bold mb-4 text-xl">Berapa bintang untuk pengalaman anda? ⭐</label>
              <div class="flex justify-center space-x-2">
                <button
                  v-for="star in 5"
                  :key="star"
                  type="button"
                  @click="feedbackForm.rating = star"
                  @mouseenter="hoverRating = star"
                  @mouseleave="hoverRating = 0"
                  :class="[
                    'text-5xl focus:outline-none transition-transform transform hover:scale-110', 
                    (hoverRating || feedbackForm.rating) >= star ? 'text-yellow-400' : 'text-gray-300'
                  ]"
                >
                  ★
                </button>
              </div>
            </div>

            <div>
              <label class="block text-gray-700 font-bold mb-2">Cadangan / Komen Anda 📝</label>
              <textarea
                v-model="feedbackForm.comments"
                rows="4"
                class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-4 focus:ring-red-100 focus:border-red-500 outline-none transition resize-none"
                placeholder="Cth: Nak lebih banyak booth makanan... / Perlu tempat letak kereta yang lebih besar..."
                required
              ></textarea>
            </div>

            <button
              type="submit"
              :disabled="isSubmitting"
              class="w-full bg-gray-800 text-white font-bold py-4 px-6 rounded-xl shadow-lg hover:bg-gray-700 transition transform hover:-translate-y-1 disabled:opacity-50 disabled:hover:translate-y-0"
            >
              {{ isSubmitting ? 'Sedang Menghantar...' : 'Hantar Maklum Balas' }}
            </button>
          </form>
        </div>
      </section>

    <main class="py-16 px-6 max-w-7xl mx-auto">
      
      <section ref="calendarSection" class="mb-20 scroll-mt-24">
        <div class="flex justify-between items-end mb-8">
          <h2 class="text-3xl font-extrabold text-gray-800 border-l-4 border-red-500 pl-4">Upcoming Carboot Dates</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div v-for="event in upcomingEvents" :key="event.id" class="bg-white rounded-xl shadow-md border border-gray-100 p-6 hover:shadow-lg transition">
            <div class="flex items-center space-x-3 mb-4">
              <div class="bg-red-100 text-red-600 rounded-lg p-3 text-center min-w-[60px]">
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
              <router-link to="/vendor-booking" class="text-sm font-semibold text-red-600 hover:text-red-800">
                Book Now >
              </router-link>
            </div>
          </div>
        </div>
      </section>

      <section>
        <div class="flex justify-between items-end mb-8">
          <h2 class="text-3xl font-extrabold text-gray-800 border-l-4 border-orange-400 pl-4">Latest CMart Updates</h2>
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
              <h3 class="text-xl font-bold text-gray-800 mb-3 group-hover:text-red-600 transition">{{ news.title }}</h3>
              <p class="text-gray-600 text-sm line-clamp-3">{{ news.excerpt }}</p>
              <button class="mt-4 text-red-500 font-semibold text-sm hover:underline">Read More</button>
            </div>
          </div>
        </div>
      </section>

    </main>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useAuthStore } from './stores/auth';
import axios from 'axios';

const auth = useAuthStore();
const router = useRouter();
const toast = useToast();

const logout = async () => {
  await auth.logout();
  toast.success('200 OK: Session terminated successfully.');
  router.push('/');
};

// Logic for Smooth Scrolling to the Calendar
const calendarSection = ref(null);
const scrollToCalendar = () => {
  if (calendarSection.value) {
    calendarSection.value.scrollIntoView({ behavior: 'smooth' });
  }
};

// Dummy Data for Calendar Events
const upcomingEvents = ref([
  {
    id: 1,
    day: '16',
    month: 'May',
    title: 'CMart Weekly Carboot',
    time: '8:00 AM - 2:00 PM',
    status: 'Available',
    statusClass: 'bg-green-100 text-green-700'
  },
  {
    id: 2,
    day: '17',
    month: 'May',
    title: 'CMart Weekly Carboot',
    time: '8:00 AM - 2:00 PM',
    status: 'Almost Full',
    statusClass: 'bg-orange-100 text-orange-700'
  },
  {
    id: 3,
    day: '23',
    month: 'May',
    title: 'Changlun Mega Carboot',
    time: '8:00 AM - 6:00 PM',
    status: 'Registration Open',
    statusClass: 'bg-blue-100 text-blue-700'
  }
]);

// Dummy Data for Latest Updates (News)
const latestNews = ref([
  {
    id: 1,
    category: 'Announcement',
    date: 'May 12, 2026',
    title: 'Digital System Introduced with OIB Developers',
    excerpt: 'CMart proudly launches a new booking portal to simplify invoice management and space registration for all Changlun vendors.',
    image: 'https://images.unsplash.com/photo-1556761175-5973dc0f32b7?q=80&w=800&auto=format&fit=crop'
  },
  {
    id: 2,
    category: 'Community',
    date: 'May 10, 2026',
    title: 'Pasar Karat Vendors Transition to CMart',
    excerpt: 'Over 20 vendors from outside sites have joined our ecosystem, promising a variety of goods from food and beverages to preloved clothing.',
    image: 'https://images.unsplash.com/photo-1472851294608-062f18ce0411?q=80&w=800&auto=format&fit=crop'
  },
  {
    id: 3,
    category: 'Vendor Tips',
    date: 'May 05, 2026',
    title: 'How to Choose the Right Space Size?',
    excerpt: 'Do you need an M or L sized space? Learn the exact dimensions and pricing to ensure your merchandise fits perfectly.',
    image: 'https://images.unsplash.com/photo-1533900298318-6b8da08a523e?q=80&w=800&auto=format&fit=crop'
  }
]);
const feedbackForm = ref({
  rating: 5,
  comments: ''
});
const hoverRating = ref(0);
const isSubmitting = ref(false);

const submitFeedback = async () => {
  isSubmitting.value = true;
  try {
    // Tembak API ke Laravel Backend
    await axios.post('http://localhost:8000/api/feedbacks', feedbackForm.value);
    
    toast.success('Maklum balas berjaya dihantar! Terima kasih atas sokongan anda.');
    
    // Reset form lepas berjaya hantar
    feedbackForm.value.comments = '';
    feedbackForm.value.rating = 5;
  } catch (error) {
    console.error(error);
    toast.error('Gagal menghantar maklum balas. Sila cuba sebentar lagi.');
  } finally {
    isSubmitting.value = false;
  }
};
</script>