<template>
  <footer class="bg-gray-900 text-gray-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-14">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
        <div>
          <img src="/cmart_logo.png" alt="Carboot@CMart" class="h-12 w-auto mb-4 brightness-0 invert opacity-90" />
          <p class="text-sm leading-relaxed text-gray-400">
            Carboot@CMart — a community carboot market at CMart Kompleks Changlun. Events, vendors, and weekend fun for everyone.
          </p>
        </div>

        <div>
          <h3 class="text-white font-bold mb-4">Quick Links</h3>
          <ul class="space-y-2 text-sm">
            <li v-for="link in footerLinks" :key="link.to">
              <router-link :to="link.to" class="hover:text-brand-400 transition">{{ link.label }}</router-link>
            </li>
            <li>
              <router-link to="/calendar" class="hover:text-brand-400 transition">Event Calendar</router-link>
            </li>
            <li>
              <router-link to="/login" class="hover:text-brand-400 transition">Sign in</router-link>
            </li>
          </ul>
        </div>

        <div>
          <h3 class="text-white font-bold mb-4">Contact</h3>
          <ul class="space-y-2 text-sm text-gray-400">
            <li>CMart Kompleks Changlun</li>
            <li>Changlun, Kedah, Malaysia</li>
            <li>
              <a href="mailto:enquiries@cmart.com.my" class="hover:text-brand-400 transition">enquiries@cmart.com.my</a>
            </li>
          </ul>
        </div>
      </div>

      <div class="mt-12 pt-8 border-t border-gray-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-500">
        <p>&copy; {{ currentYear }} Carboot@CMart · CMart Kompleks Changlun. All rights reserved.</p>
        <router-link to="/management/login" class="hover:text-brand-400 transition font-medium">
          Staff Portal
        </router-link>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { PUBLIC_LINKS } from '../../config/navigation';

const currentYear = new Date().getFullYear();
const footerLinks = PUBLIC_LINKS.filter((link) => link.label !== 'Home');
</script>
