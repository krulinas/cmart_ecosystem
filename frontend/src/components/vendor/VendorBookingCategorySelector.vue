<template>
  <section
    class="rounded-2xl border border-ink-200 bg-white p-4 sm:p-5"
    aria-labelledby="vendor-category-heading"
    data-testid="vendor-booking-category-selector"
  >
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-wider text-brand-700">Step 1</p>
        <h2 id="vendor-category-heading" tabindex="-1" class="mt-1 text-base font-extrabold text-ink-900 focus:outline-none">
          Select Selling Category
        </h2>
        <p class="mt-1 text-sm text-ink-600">
          Compatible sites appear after you choose a category.
        </p>
      </div>
      <button
        v-if="loadError"
        type="button"
        class="ml-btn-ghost text-sm"
        data-testid="vendor-category-retry"
        @click="$emit('retry')"
      >
        Try Again
      </button>
    </div>

    <p
      v-if="loading"
      class="mt-4 rounded-xl bg-ink-50 px-4 py-6 text-center text-sm text-ink-500"
      data-testid="vendor-category-loading"
      role="status"
    >
      Loading categories…
    </p>

    <p
      v-else-if="loadError"
      class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
      data-testid="vendor-category-error"
      role="alert"
    >
      {{ loadError }}
    </p>

    <div v-else class="mt-4 grid gap-3 sm:grid-cols-2" role="radiogroup" aria-label="Selling category">
      <button
        v-for="category in categories"
        :key="category.id"
        type="button"
        role="radio"
        class="min-h-20 rounded-xl border p-4 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
        :class="categoryClass(category)"
        :aria-checked="String(modelValue) === String(category.id)"
        :aria-label="categoryAriaLabel(category)"
        :data-category-id="category.id"
        data-testid="vendor-category-option"
        @click="$emit('update:modelValue', String(category.id))"
      >
        <span class="block font-bold text-ink-900">{{ category.label }}</span>
        <span v-if="category.description" class="mt-1 block text-xs leading-relaxed text-ink-600">
          {{ category.description }}
        </span>
        <span
          v-if="String(profileSuggestedCategoryId) === String(category.id)"
          class="mt-2 inline-flex rounded-full bg-cyan-100 px-2 py-1 text-[11px] font-bold text-cyan-800"
          data-testid="vendor-category-profile-suggestion"
        >
          Suggested from your profile
        </span>
      </button>
    </div>

    <p
      v-if="!loading && !loadError && !modelValue"
      class="mt-4 text-sm font-semibold text-amber-800"
      data-testid="vendor-category-required"
    >
      Select a selling category first to view compatible sites.
    </p>
  </section>
</template>

<script setup>
const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  categories: { type: Array, default: () => [] },
  profileSuggestedCategoryId: { type: [String, Number], default: null },
  loading: { type: Boolean, default: false },
  loadError: { type: String, default: '' },
});

defineEmits(['update:modelValue', 'retry']);

function categoryClass(category) {
  return String(props.modelValue) === String(category.id)
    ? 'border-brand-500 bg-brand-50 shadow-sm'
    : 'border-ink-200 bg-white hover:border-brand-300 hover:bg-brand-50/40';
}

function categoryAriaLabel(category) {
  const suggestion = String(props.profileSuggestedCategoryId) === String(category.id)
    ? ', suggested from your profile'
    : '';
  return `${category.label}${suggestion}`;
}
</script>
