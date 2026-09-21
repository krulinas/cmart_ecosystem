<template>
  <footer class="bg-gray-900 text-gray-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-14">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
        <div>
          <img src="/cmart_logo.png" :alt="t('public.footer.logoAlt')" class="h-12 w-auto mb-4 brightness-0 invert opacity-90" />
          <p class="text-sm leading-relaxed text-gray-400">
            {{ t('public.footer.tagline') }}
          </p>
        </div>

        <div>
          <h3 class="text-white font-bold mb-4">{{ t('public.footer.quickLinks') }}</h3>
          <ul class="space-y-2 text-sm">
            <li v-for="link in footerLinks" :key="link.to">
              <router-link :to="link.to" class="hover:text-brand-400 transition">{{ t(link.labelKey) }}</router-link>
            </li>
            <li>
              <router-link to="/login" class="hover:text-brand-400 transition">{{ t('public.footer.signIn') }}</router-link>
            </li>
          </ul>
        </div>

        <div>
          <h3 class="text-white font-bold mb-4">{{ t('public.footer.contact') }}</h3>
          <ul class="space-y-2 text-sm text-gray-400">
            <li>{{ t('public.footer.address1') }}</li>
            <li>{{ t('public.footer.address2') }}</li>
            <li>
              <a href="mailto:enquiries@cmart.com.my" class="hover:text-brand-400 transition">{{ t('public.footer.email') }}</a>
            </li>
          </ul>
        </div>
      </div>

      <div class="mt-12 pt-8 border-t border-gray-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-500">
        <p>{{ t('public.footer.copyright', { year: currentYear }) }}</p>
        <router-link to="/management/login" class="hover:text-brand-400 transition font-medium">
          {{ t('public.footer.managementLogin') }}
        </router-link>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { PUBLIC_LINKS } from '../../config/navigation';

const { t } = useI18n();
const currentYear = new Date().getFullYear();
const footerLinks = PUBLIC_LINKS;
</script>
