<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="modelValue"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6"
        role="dialog"
        aria-modal="true"
        aria-labelledby="vendor-profile-edit-title"
        @keydown.esc="close"
      >
        <div class="absolute inset-0 bg-[rgba(15,23,42,0.65)] backdrop-blur-[6px]" @click="close" />

        <div class="relative z-10 w-full max-w-md rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 p-6" @click.stop>
          <h2 id="vendor-profile-edit-title" class="text-xl font-extrabold text-ink-900">Edit Business Profile</h2>
          <p class="mt-1 text-sm text-ink-500">Update your vendor contact and product details shown on your dashboard.</p>

          <form class="mt-6 space-y-4" @submit.prevent="save">
            <div>
              <label class="ml-label">Display name</label>
              <input v-model="form.name" class="ml-input" required />
            </div>
            <div>
              <label class="ml-label">Phone</label>
              <input v-model="form.phone_number" class="ml-input" />
            </div>
            <div>
              <label class="ml-label">Email</label>
              <input v-model="form.email" type="email" class="ml-input" disabled />
            </div>
            <div>
              <label class="ml-label">Registered product / category</label>
              <input v-model="form.product_summary" class="ml-input" />
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
              Backend profile update is not wired yet. A vendor-safe endpoint such as
              <code class="font-mono text-xs">PATCH /api/vendor/profile</code>
              is needed to save these changes to the database.
            </div>

            <div class="flex gap-2 pt-2">
              <button type="submit" class="ml-btn-primary" disabled title="Waiting for profile update API">
                Save Profile
              </button>
              <button type="button" class="ml-btn-ghost" @click="close">Close</button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { reactive, watch } from 'vue';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  profile: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue']);

const form = reactive({
  name: '',
  phone_number: '',
  email: '',
  product_summary: '',
});

watch(
  () => props.profile,
  (profile) => {
    form.name = profile?.name || '';
    form.phone_number = profile?.phone_number || '';
    form.email = profile?.email || '';
    form.product_summary = profile?.product_summary || '';
  },
  { immediate: true },
);

const close = () => emit('update:modelValue', false);

const save = () => {
  close();
};
</script>
