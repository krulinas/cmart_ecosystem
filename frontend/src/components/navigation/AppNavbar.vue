<template>
  <nav class="bg-white/95 backdrop-blur-md shadow-md sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
      <router-link :to="homeLink" class="flex items-center" @click="closeMobile">
        <img
          src="/cmart_logo.png"
          alt="Carboot@CMart Logo"
          class="h-12 sm:h-14 w-auto object-contain hover:opacity-90 transition-opacity"
        />
      </router-link>

      <div class="hidden md:flex items-center space-x-1">
        <router-link
          v-for="link in navLinks"
          :key="link.to"
          :to="link.to"
          class="px-3 py-2 rounded-lg text-gray-600 hover:text-brand-600 hover:bg-brand-50 font-semibold transition text-sm"
          :class="{ 'text-brand-600 bg-brand-50': isActive(link) }"
        >
          {{ link.label }}
        </router-link>

        <div class="h-6 w-px bg-gray-200 mx-2"></div>

        <template v-if="variant === 'public'">
          <router-link
            :to="auth.bookingPathForUser()"
            class="bg-brand-500 text-white px-4 py-2 rounded-lg shadow hover:bg-brand-600 transition text-sm font-bold whitespace-nowrap"
          >
            Book a Space
          </router-link>

          <template v-if="auth.isAuthenticated">
            <span
              v-if="showUserBadge"
              class="hidden lg:inline text-xs font-bold text-gray-500 max-w-[140px] truncate px-2"
              :title="auth.user?.name"
            >
              {{ auth.user?.name }}
            </span>
            <button
              type="button"
              class="text-gray-500 hover:text-red-600 font-bold text-sm transition flex items-center gap-1.5 px-3 py-2 rounded-lg hover:bg-red-50"
              @click="logout"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
              Logout
            </button>
          </template>

          <router-link
            v-else
            to="/login"
            class="px-4 py-2 rounded-lg border-2 border-brand-500 text-brand-600 hover:bg-brand-50 font-bold transition text-sm whitespace-nowrap"
          >
            Sign in
          </router-link>
        </template>

        <template v-else-if="auth.isAuthenticated">
          <span
            v-if="showUserBadge"
            class="hidden lg:inline text-xs font-bold text-gray-500 max-w-[140px] truncate px-2"
            :title="auth.user?.name"
          >
            {{ auth.user?.name }}
          </span>
          <button
            type="button"
            class="text-gray-500 hover:text-red-600 font-bold text-sm transition flex items-center gap-1.5 px-3 py-2 rounded-lg hover:bg-red-50"
            @click="logout"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            Logout
          </button>
        </template>
      </div>

      <button
        type="button"
        class="md:hidden text-gray-800 hover:text-brand-600 focus:outline-none transition"
        aria-label="Toggle menu"
        @click="toggleMobile"
      >
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path v-if="!isMobileOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="transform -translate-y-4 opacity-0"
      enter-to-class="transform translate-y-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="transform translate-y-0 opacity-100"
      leave-to-class="transform -translate-y-4 opacity-0"
    >
      <div v-show="isMobileOpen" class="md:hidden bg-white border-t border-gray-100 absolute w-full shadow-lg">
        <div class="px-6 py-4 flex flex-col space-y-3">
          <router-link
            v-for="link in navLinks"
            :key="'m-' + link.to"
            :to="link.to"
            class="text-gray-700 hover:text-brand-600 font-semibold text-lg py-1"
            @click="closeMobile"
          >
            {{ link.label }}
          </router-link>

          <hr class="border-gray-200" />

          <template v-if="variant === 'public'">
            <router-link
              :to="auth.bookingPathForUser()"
              class="bg-brand-500 text-white px-4 py-3 rounded-lg text-center font-bold shadow hover:bg-brand-600 transition"
              @click="closeMobile"
            >
              Book a Space
            </router-link>

            <template v-if="auth.isAuthenticated">
              <p class="text-sm text-gray-500 font-semibold">{{ auth.user?.name }}</p>
              <button
                type="button"
                class="text-left text-red-600 font-bold text-lg py-1"
                @click="handleMobileLogout"
              >
                Logout
              </button>
            </template>

            <router-link
              v-else
              to="/login"
              class="border-2 border-brand-500 text-brand-600 px-4 py-3 rounded-lg text-center font-bold hover:bg-brand-50 transition"
              @click="closeMobile"
            >
              Sign in
            </router-link>
          </template>

          <template v-else-if="auth.isAuthenticated">
            <p class="text-sm text-gray-500 font-semibold">{{ auth.user?.name }}</p>
            <button
              type="button"
              class="text-left text-red-600 font-bold text-lg py-1"
              @click="handleMobileLogout"
            >
              Logout
            </button>
          </template>
        </div>
      </div>
    </transition>
  </nav>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { PUBLIC_LINKS, VENDOR_LINKS } from '../../config/navigation';
import { useLogout } from '../../composables/useLogout';

const props = defineProps({
  variant: {
    type: String,
    default: 'public',
    validator: (v) => ['public', 'vendor'].includes(v),
  },
  showUserBadge: {
    type: Boolean,
    default: true,
  },
});

const auth = useAuthStore();
const route = useRoute();
const { logout } = useLogout();
const isMobileOpen = ref(false);

const navLinks = computed(() => {
  if (props.variant === 'vendor' && auth.isAuthenticated && auth.role === 'community') {
    return VENDOR_LINKS;
  }
  return PUBLIC_LINKS;
});

const homeLink = computed(() => {
  if (auth.isAuthenticated) {
    return auth.homeForUser();
  }
  return '/';
});

const isActive = (link) => {
  if (link.hash) {
    return route.path === '/' && route.hash === link.hash;
  }
  if (link.exact) {
    return route.path === link.to && !route.hash;
  }
  return route.path === link.to || route.path.startsWith(`${link.to}/`);
};

const toggleMobile = () => {
  isMobileOpen.value = !isMobileOpen.value;
};

const closeMobile = () => {
  isMobileOpen.value = false;
};

const handleMobileLogout = async () => {
  closeMobile();
  await logout();
};
</script>
