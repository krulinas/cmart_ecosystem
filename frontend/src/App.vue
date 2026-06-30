<template>
  <IdleSessionWatcher />
  <router-view />
</template>

<script setup>
import IdleSessionWatcher from './components/IdleSessionWatcher.vue';
import { useRouteMeta } from './composables/useRouteMeta';

useRouteMeta();
</script>
