<template>
  <nav class="bg-white/95 backdrop-blur-md shadow-md sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-5 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
      <router-link :to="homeLink" class="flex items-center" @click="closeMobile">
        <img
          src="/cmart_logo.png"
          alt="Carboot@CMart Logo"
          class="h-12 sm:h-14 w-auto object-contain hover:opacity-90 transition-opacity"
        />
      </router-link>

      <!-- Desktop navigation -->
      <div class="hidden md:flex items-center space-x-1">
        <template v-if="isVendorNav">
          <router-link
            :to="vendorDashboardLink.to"
            :data-testid="vendorDashboardLink.testId"
            class="px-3.5 py-2.5 rounded-lg text-gray-600 hover:text-brand-600 hover:bg-brand-50 font-semibold transition text-[15px]"
            :class="{ 'text-brand-600 bg-brand-50': isDashboardActive }"
          >
            {{ vendorDashboardLink.label }}
          </router-link>

          <div
            v-for="menu in vendorMenus"
            :key="menu.id"
            class="relative"
            :data-testid="menu.testId"
          >
            <button
              type="button"
              class="px-3.5 py-2.5 rounded-lg text-gray-600 hover:text-brand-600 hover:bg-brand-50 font-semibold transition text-[15px] inline-flex items-center gap-1"
              :class="{ 'text-brand-600 bg-brand-50': isMenuActive(menu) || openMenu === menu.id }"
              :aria-expanded="openMenu === menu.id"
              :aria-haspopup="true"
              @click="toggleMenu(menu.id)"
            >
              {{ menu.label }}
              <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <div
              v-show="openMenu === menu.id"
              class="absolute right-0 mt-1 min-w-[12rem] rounded-xl border border-gray-100 bg-white py-1 shadow-lg ring-1 ring-black/5 z-50"
            >
              <router-link
                v-for="item in menu.items"
                :key="linkNavKey(item)"
                :to="linkDestination(item)"
                :data-testid="item.testId"
                class="block px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-brand-50 hover:text-brand-700"
                :class="{ 'text-brand-700 bg-brand-50': isActive(item) }"
                @click="closeMenus"
              >
                {{ item.label }}
              </router-link>
              <button
                v-if="menu.id === 'account'"
                type="button"
                class="block w-full text-left px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50"
                data-testid="nav-logout"
                @click="handleLogout"
              >
                Logout
              </button>
            </div>
          </div>
        </template>

        <template v-else-if="isCommunityVisitorNav">
          <router-link
            v-for="link in communityPrimaryLinks"
            :key="linkNavKey(link)"
            :to="linkDestination(link)"
            :data-testid="link.testId || undefined"
            class="px-3.5 py-2.5 rounded-lg text-gray-600 hover:text-brand-600 hover:bg-brand-50 font-semibold transition text-[15px]"
            :class="{ 'text-brand-600 bg-brand-50': isActive(link) }"
          >
            {{ link.label }}
          </router-link>

          <div class="relative" :data-testid="communityExploreMenu.testId">
            <button
              type="button"
              class="px-3.5 py-2.5 rounded-lg text-gray-600 hover:text-brand-600 hover:bg-brand-50 font-semibold transition text-[15px] inline-flex items-center gap-1"
              :class="{ 'text-brand-600 bg-brand-50': isMenuActive(communityExploreMenu) || openMenu === communityExploreMenu.id }"
              :aria-expanded="openMenu === communityExploreMenu.id"
              :aria-haspopup="true"
              @click="toggleMenu(communityExploreMenu.id)"
            >
              {{ communityExploreMenu.label }}
              <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <div
              v-show="openMenu === communityExploreMenu.id"
              class="absolute right-0 mt-1 min-w-[12rem] rounded-xl border border-gray-100 bg-white py-1 shadow-lg ring-1 ring-black/5 z-50"
            >
              <router-link
                v-for="item in communityExploreMenu.items"
                :key="linkNavKey(item)"
                :to="linkDestination(item)"
                :data-testid="item.testId"
                class="block px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-brand-50 hover:text-brand-700"
                :class="{ 'text-brand-700 bg-brand-50': isActive(item) }"
                @click="closeMenus"
              >
                {{ item.label }}
              </router-link>
            </div>
          </div>
        </template>

        <template v-else>
          <router-link
            v-for="link in guestLinks"
            :key="linkNavKey(link)"
            :to="linkDestination(link)"
            :data-testid="link.testId || undefined"
            class="px-3.5 py-2.5 rounded-lg text-gray-600 hover:text-brand-600 hover:bg-brand-50 font-semibold transition text-[15px]"
            :class="{ 'text-brand-600 bg-brand-50': isActive(link) }"
          >
            {{ link.label }}
          </router-link>
        </template>

        <div class="h-6 w-px bg-gray-200 mx-2"></div>

        <template v-if="isCommunityVisitorNav">
          <router-link
            :to="linkDestination(communityBecomeVendorCta)"
            :data-testid="communityBecomeVendorCta.testId"
            class="bg-brand-500 text-white px-5 py-2.5 min-h-[40px] rounded-lg shadow hover:bg-brand-600 transition text-[15px] font-bold whitespace-nowrap"
            @click="closeMenus"
          >
            {{ communityBecomeVendorCta.label }}
          </router-link>

          <div class="relative" :data-testid="communityAccountMenu.testId">
            <button
              type="button"
              class="px-3.5 py-2.5 rounded-lg text-gray-600 hover:text-brand-600 hover:bg-brand-50 font-semibold transition text-[15px] inline-flex items-center gap-1"
              :class="{ 'text-brand-600 bg-brand-50': openMenu === communityAccountMenu.id }"
              :aria-expanded="openMenu === communityAccountMenu.id"
              :aria-haspopup="true"
              @click="toggleMenu(communityAccountMenu.id)"
            >
              {{ communityAccountMenu.label }}
              <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <div
              v-show="openMenu === communityAccountMenu.id"
              class="absolute right-0 mt-1 min-w-[12rem] rounded-xl border border-gray-100 bg-white py-1 shadow-lg ring-1 ring-black/5 z-50"
            >
              <p
                v-if="showUserBadge && auth.user?.name"
                class="px-4 py-2.5 text-xs font-bold text-gray-500 truncate border-b border-gray-50"
                :title="auth.user.name"
                data-testid="nav-account-identity"
              >
                {{ auth.user.name }}
              </p>
              <button
                type="button"
                class="block w-full text-left px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50"
                data-testid="nav-logout"
                @click="handleLogout"
              >
                Logout
              </button>
            </div>
          </div>
        </template>

        <template v-else-if="variant === 'public'">
          <router-link
            :to="auth.startVendorBookingPath()"
            class="bg-brand-500 text-white px-5 py-2.5 min-h-[40px] rounded-lg shadow hover:bg-brand-600 transition text-[15px] font-bold whitespace-nowrap"
          >
            Start Vendor Booking
          </router-link>

          <router-link
            to="/login"
            class="px-5 py-2.5 min-h-[40px] rounded-lg border-2 border-brand-500 text-brand-600 hover:bg-brand-50 font-bold transition text-[15px] whitespace-nowrap"
          >
            Sign in
          </router-link>
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

    <!-- Mobile navigation -->
    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="transform -translate-y-4 opacity-0"
      enter-to-class="transform translate-y-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="transform translate-y-0 opacity-100"
      leave-to-class="transform -translate-y-4 opacity-0"
    >
      <div v-show="isMobileOpen" class="md:hidden bg-white border-t border-gray-100 absolute w-full shadow-lg max-h-[80vh] overflow-y-auto z-50">
        <div class="px-6 py-4 flex flex-col space-y-3">
          <template v-if="isVendorNav">
            <router-link
              :to="vendorDashboardLink.to"
              :data-testid="vendorDashboardLink.testId + '-mobile'"
              class="text-gray-700 hover:text-brand-600 font-semibold text-lg py-1 rounded-lg px-2 -mx-2 transition"
              :class="{ 'text-brand-600 bg-brand-50': isDashboardActive }"
              @click="closeMobile"
            >
              {{ vendorDashboardLink.label }}
            </router-link>

            <div v-for="menu in vendorMenus" :key="'m-' + menu.id" class="space-y-1">
              <p class="text-xs font-bold uppercase tracking-wider text-gray-400 px-2 pt-1">{{ menu.label }}</p>
              <router-link
                v-for="item in menu.items"
                :key="'m-' + linkNavKey(item)"
                :to="linkDestination(item)"
                :data-testid="item.testId ? item.testId + '-mobile' : undefined"
                class="block text-gray-700 hover:text-brand-600 font-semibold text-base py-1.5 rounded-lg px-4 transition"
                :class="{ 'text-brand-600 bg-brand-50': isActive(item) }"
                @click="closeMobile"
              >
                {{ item.label }}
              </router-link>
              <button
                v-if="menu.id === 'account'"
                type="button"
                class="block w-full text-left text-red-600 font-bold text-base py-1.5 rounded-lg px-4 hover:bg-red-50"
                data-testid="nav-logout-mobile"
                @click="handleMobileLogout"
              >
                Logout
              </button>
            </div>
          </template>

          <template v-else-if="isCommunityVisitorNav">
            <router-link
              v-for="link in communityPrimaryLinks"
              :key="'m-' + linkNavKey(link)"
              :to="linkDestination(link)"
              :data-testid="link.testId ? link.testId + '-mobile' : undefined"
              class="text-gray-700 hover:text-brand-600 font-semibold text-lg py-1 rounded-lg px-2 -mx-2 transition"
              :class="{ 'text-brand-600 bg-brand-50': isActive(link) }"
              @click="closeMobile"
            >
              {{ link.label }}
            </router-link>

            <div class="space-y-1">
              <p class="text-xs font-bold uppercase tracking-wider text-gray-400 px-2 pt-1">
                {{ communityExploreMenu.label }}
              </p>
              <router-link
                v-for="item in communityExploreMenu.items"
                :key="'m-' + linkNavKey(item)"
                :to="linkDestination(item)"
                :data-testid="item.testId ? item.testId + '-mobile' : undefined"
                class="block text-gray-700 hover:text-brand-600 font-semibold text-base py-1.5 rounded-lg px-4 transition"
                :class="{ 'text-brand-600 bg-brand-50': isActive(item) }"
                @click="closeMobile"
              >
                {{ item.label }}
              </router-link>
            </div>

            <hr class="border-gray-200" />

            <router-link
              :to="linkDestination(communityBecomeVendorCta)"
              :data-testid="communityBecomeVendorCta.testId + '-mobile'"
              class="bg-brand-500 text-white px-4 py-3 rounded-lg text-center font-bold shadow hover:bg-brand-600 transition"
              @click="closeMobile"
            >
              {{ communityBecomeVendorCta.label }}
            </router-link>

            <div class="space-y-1">
              <p class="text-xs font-bold uppercase tracking-wider text-gray-400 px-2 pt-1">
                {{ communityAccountMenu.label }}
              </p>
              <p
                v-if="auth.user?.name"
                class="px-4 py-1.5 text-sm font-semibold text-gray-500 truncate"
                data-testid="nav-account-identity-mobile"
              >
                {{ auth.user.name }}
              </p>
              <button
                type="button"
                class="block w-full text-left text-red-600 font-bold text-base py-1.5 rounded-lg px-4 hover:bg-red-50"
                data-testid="nav-logout-mobile"
                @click="handleMobileLogout"
              >
                Logout
              </button>
            </div>
          </template>

          <template v-else>
            <router-link
              v-for="link in guestLinks"
              :key="'m-' + linkNavKey(link)"
              :to="linkDestination(link)"
              :data-testid="link.testId ? link.testId + '-mobile' : undefined"
              class="text-gray-700 hover:text-brand-600 font-semibold text-lg py-1 rounded-lg px-2 -mx-2 transition"
              :class="{ 'text-brand-600 bg-brand-50': isActive(link) }"
              @click="closeMobile"
            >
              {{ link.label }}
            </router-link>

            <hr class="border-gray-200" />

            <router-link
              :to="auth.startVendorBookingPath()"
              class="bg-brand-500 text-white px-4 py-3 rounded-lg text-center font-bold shadow hover:bg-brand-600 transition"
              @click="closeMobile"
            >
              Start Vendor Booking
            </router-link>

            <router-link
              to="/login"
              class="border-2 border-brand-500 text-brand-600 px-4 py-3 rounded-lg text-center font-bold hover:bg-brand-50 transition"
              @click="closeMobile"
            >
              Sign in
            </router-link>
          </template>
        </div>
      </div>
    </transition>
  </nav>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import {
  PUBLIC_LINKS,
  COMMUNITY_PRIMARY_LINKS,
  COMMUNITY_EXPLORE_MENU,
  COMMUNITY_BECOME_VENDOR_CTA,
  COMMUNITY_ACCOUNT_MENU,
  VENDOR_DASHBOARD_LINK,
  VENDOR_MANAGE_MENU,
  VENDOR_EXPLORE_MENU,
  VENDOR_ACCOUNT_MENU,
} from '../../config/navigation';
import { useLogout } from '../../composables/useLogout';

defineProps({
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
const openMenu = ref(null);

const isVendorNav = computed(
  () => auth.isAuthenticated && auth.role === 'community' && auth.isVendorUser,
);

const isCommunityVisitorNav = computed(
  () => auth.isAuthenticated && auth.role === 'community' && !auth.isVendorUser,
);

const vendorDashboardLink = VENDOR_DASHBOARD_LINK;
const vendorMenus = [VENDOR_MANAGE_MENU, VENDOR_EXPLORE_MENU, VENDOR_ACCOUNT_MENU];
const communityPrimaryLinks = COMMUNITY_PRIMARY_LINKS;
const communityExploreMenu = COMMUNITY_EXPLORE_MENU;
const communityBecomeVendorCta = COMMUNITY_BECOME_VENDOR_CTA;
const communityAccountMenu = COMMUNITY_ACCOUNT_MENU;
const guestLinks = PUBLIC_LINKS;

const homeLink = computed(() => {
  if (auth.isAuthenticated) {
    return auth.homeForUser();
  }
  return '/';
});

const isDashboardActive = computed(
  () => route.path === '/dashboard' && !route.hash,
);

const linkDestination = (link) => {
  if (link.hash) {
    return { path: link.to, hash: link.hash };
  }
  return link.to;
};

const linkNavKey = (link) => `${link.to}${link.hash || ''}`;

const isActive = (link) => {
  if (link.hash) {
    return route.path === link.to && route.hash === link.hash;
  }
  if (link.exact) {
    return route.path === link.to && !route.hash;
  }
  return route.path === link.to || route.path.startsWith(`${link.to}/`);
};

const isMenuActive = (menu) => menu.items.some((item) => isActive(item));

const toggleMenu = (menuId) => {
  openMenu.value = openMenu.value === menuId ? null : menuId;
};

const closeMenus = () => {
  openMenu.value = null;
};

const handleDocumentClick = (event) => {
  if (!openMenu.value) return;
  const target = event.target;
  if (target instanceof Element && target.closest('[data-testid^="nav-"]')) return;
  closeMenus();
};

const handleDocumentKeydown = (event) => {
  if (event.key === 'Escape') {
    closeMenus();
  }
};

const handleLogout = async () => {
  closeMenus();
  await logout();
};

onMounted(() => {
  document.addEventListener('click', handleDocumentClick);
  document.addEventListener('keydown', handleDocumentKeydown);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleDocumentClick);
  document.removeEventListener('keydown', handleDocumentKeydown);
});

const toggleMobile = () => {
  isMobileOpen.value = !isMobileOpen.value;
  if (!isMobileOpen.value) closeMenus();
};

const closeMobile = () => {
  isMobileOpen.value = false;
  closeMenus();
};

const handleMobileLogout = async () => {
  closeMobile();
  await logout();
};
</script>
