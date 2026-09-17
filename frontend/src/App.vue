<template>
  <IdleSessionWatcher />
  <SessionExpiredModal />
  <router-view />
</template>

<script setup>
import IdleSessionWatcher from './components/IdleSessionWatcher.vue';
import SessionExpiredModal from './components/SessionExpiredModal.vue';
import { useRouteMeta } from './composables/useRouteMeta';

useRouteMeta();
</script>
