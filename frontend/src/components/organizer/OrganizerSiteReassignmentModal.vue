<template>
  <Teleport to="body">
    <div
      v-if="modelValue && booking"
      class="fixed inset-0 z-[150] flex items-end justify-center p-0 sm:items-center sm:p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="site-reassignment-title"
      @keydown.esc="requestClose"
    >
      <div class="absolute inset-0 bg-[rgba(15,23,42,0.72)] backdrop-blur-[4px]" @click="requestClose" />
      <div
        class="relative z-10 max-h-[94vh] w-full overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:max-w-3xl sm:rounded-2xl"
        data-testid="organizer-site-reassignment-modal"
      >
        <header class="sticky top-0 z-10 flex items-center justify-between border-b border-ink-100 bg-white px-5 py-4">
          <div>
            <p class="text-xs font-bold uppercase tracking-wider text-cyan-700">Penempatan Tapak</p>
            <h2 id="site-reassignment-title" class="text-lg font-extrabold text-ink-900">Susun Semula Tapak</h2>
          </div>
          <button type="button" class="ml-btn-ghost" :disabled="submitting" @click="requestClose">Batal</button>
        </header>

        <div v-if="optionsLoading" class="p-10 text-center text-sm text-ink-500" data-testid="reassignment-options-loading">
          Memuatkan pilihan tapak…
        </div>
        <div v-else-if="optionsError" class="p-8 text-center text-sm text-rose-700" data-testid="reassignment-options-error">
          {{ optionsError }}
        </div>
        <div v-else-if="options" class="space-y-5 p-5 sm:p-6">
          <div class="rounded-xl border border-ink-200 bg-ink-50 p-4 text-sm">
            <p><strong>Kategori Tempahan:</strong> {{ options.booking_category?.label }}</p>
            <p><strong>Bilangan tapak diperlukan:</strong> {{ options.requirements.site_count }}</p>
            <p><strong>Jenis ruang:</strong> {{ options.requirements.space_label }} · RM {{ options.requirements.unit_price }}</p>
            <p><strong>Tapak semasa:</strong> {{ currentSiteLabels }}</p>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm font-bold text-ink-900">Baris tersedia</p>
            <button
              v-if="hasHiddenMismatchRows"
              type="button"
              class="text-sm font-semibold text-cyan-700 underline"
              data-testid="reveal-mismatched-rows"
              @click="showMismatchRows = true"
            >
              Tunjukkan baris kategori lain
            </button>
          </div>

          <div class="space-y-4" data-testid="reassignment-row-list">
            <section
              v-for="row in visibleRows"
              :key="row.id"
              class="rounded-xl border p-4"
              :class="row.category_compatible ? 'border-emerald-200 bg-emerald-50/40' : 'border-amber-200 bg-amber-50/40'"
              :data-row-id="row.id"
              data-testid="reassignment-row-option"
            >
              <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p class="font-bold text-ink-900">{{ row.label }}</p>
                  <p class="text-sm text-ink-600">{{ row.category?.label }}</p>
                </div>
                <span
                  class="rounded-full px-3 py-1 text-xs font-bold"
                  :class="row.category_compatible ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900'"
                  data-testid="reassignment-row-badge"
                >
                  {{ row.category_compatible ? 'Sepadan dengan kategori tempahan' : 'Kategori berbeza — memerlukan pengecualian' }}
                </span>
              </div>

              <div class="mt-3 flex flex-wrap gap-2" data-testid="reassignment-site-list">
                <button
                  v-for="site in row.sites"
                  :key="site.id"
                  type="button"
                  class="rounded-lg border px-3 py-2 text-sm font-semibold transition"
                  :class="siteButtonClass(site)"
                  :disabled="!site.is_selectable || submitting"
                  :data-site-id="site.id"
                  data-testid="reassignment-site-option"
                  @click="toggleSite(site)"
                >
                  {{ site.label }}
                </button>
              </div>
            </section>
          </div>

          <div
            v-if="overrideRequired"
            class="rounded-xl border border-amber-300 bg-amber-50 p-4"
            data-testid="reassignment-override-warning"
          >
            <p class="text-sm font-bold text-amber-900">
              Kategori baris yang dipilih tidak sepadan dengan kategori tempahan vendor.
            </p>
            <p class="mt-2 text-sm text-amber-900">
              Pengecualian ini hanya boleh diteruskan dengan pengesahan dan sebab yang jelas. Kategori asal tempahan vendor tidak akan diubah.
            </p>
            <label class="mt-4 flex items-start gap-3 text-sm text-amber-900">
              <input
                v-model="acknowledged"
                type="checkbox"
                class="mt-1 h-4 w-4 rounded"
                data-testid="reassignment-override-acknowledgement"
                :disabled="submitting"
              />
              <span>Saya faham dan mahu meluluskan pengecualian kategori ini.</span>
            </label>
            <div class="mt-4">
              <label for="override-reason" class="ml-label">Sebab Pengecualian</label>
              <textarea
                id="override-reason"
                v-model="overrideReason"
                rows="3"
                maxlength="1000"
                class="ml-input"
                placeholder="Terangkan sebab penempatan kategori berbeza."
                data-testid="reassignment-override-reason"
                :disabled="submitting"
              />
            </div>
          </div>

          <p v-if="validationError" class="text-sm font-semibold text-rose-700" data-testid="reassignment-validation-error">
            {{ validationError }}
          </p>
          <p v-if="apiError" class="text-sm font-semibold text-rose-700" data-testid="reassignment-api-error">
            {{ apiError }}
          </p>

          <div class="flex justify-end gap-3 border-t border-ink-100 pt-4">
            <button type="button" class="ml-btn-ghost" :disabled="submitting" @click="requestClose">Batal</button>
            <button
              type="button"
              class="ml-btn-primary"
              data-testid="reassignment-confirm"
              :disabled="!canSubmit"
              @click="confirmSubmit"
            >
              {{ submitting ? 'Menyimpan…' : 'Sahkan' }}
            </button>
          </div>
        </div>
      </div>

      <div
        v-if="showConfirmDialog"
        class="fixed inset-0 z-[160] flex items-center justify-center p-4"
        data-testid="reassignment-confirm-dialog"
      >
        <div class="absolute inset-0 bg-black/40" @click="showConfirmDialog = false" />
        <div class="relative z-10 max-w-md rounded-2xl bg-white p-6 shadow-2xl">
          <p class="text-sm text-ink-800">{{ confirmMessage }}</p>
          <div class="mt-6 flex justify-end gap-3">
            <button type="button" class="ml-btn-ghost" :disabled="submitting" @click="showConfirmDialog = false">Batal</button>
            <button type="button" class="ml-btn-primary" :disabled="submitting" @click="submit">Sahkan</button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import {
  fetchReassignmentOptions,
  submitSiteReassignment,
  reassignmentErrorMessage,
} from '../../services/organizerSiteReassignmentApi';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  booking: { type: Object, default: null },
  placement: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'applied']);

const options = ref(null);
const optionsLoading = ref(false);
const optionsError = ref('');
const optionsToken = ref(0);
const selectedSiteIds = ref([]);
const showMismatchRows = ref(false);
const acknowledged = ref(false);
const overrideReason = ref('');
const validationError = ref('');
const apiError = ref('');
const submitting = ref(false);
const showConfirmDialog = ref(false);
const assignmentFingerprint = ref('');

const placement = computed(() => props.placement || props.booking?.category_placement || null);
const currentSiteLabels = computed(() => {
  const sites = placement.value?.current_assignment?.sites || [];
  return sites.map((site) => site.label).join(', ') || 'Tiada';
});

const hasHiddenMismatchRows = computed(() => {
  if (!options.value?.rows) return false;
  return options.value.rows.some((row) => !row.category_compatible) && !showMismatchRows.value;
});

const visibleRows = computed(() => {
  if (!options.value?.rows) return [];
  if (showMismatchRows.value) return options.value.rows;
  return options.value.rows.filter((row) => row.category_compatible);
});

const selectedRow = computed(() => {
  if (!options.value?.rows || selectedSiteIds.value.length === 0) return null;
  return options.value.rows.find((row) =>
    row.sites.some((site) => selectedSiteIds.value.includes(site.id)),
  ) || null;
});

const overrideRequired = computed(() => selectedRow.value?.override_required === true);

const confirmMessage = computed(() => {
  if (overrideRequired.value) {
    return 'Luluskan pengecualian kategori dan susun semula tapak? Kategori asal tempahan vendor akan dikekalkan. Penempatan kategori berbeza ini akan direkodkan dalam audit.';
  }
  return 'Susun semula tapak tempahan ini? Tapak lama yang tidak lagi digunakan akan dilepaskan. Jumlah tapak dan harga tempahan tidak akan berubah.';
});

const canSubmit = computed(() => {
  if (submitting.value || !options.value) return false;
  if (selectedSiteIds.value.length !== options.value.requirements.site_count) return false;
  if (overrideRequired.value) {
    if (!acknowledged.value) return false;
    if (overrideReason.value.trim().length < 10) return false;
  }
  return true;
});

const resetForm = () => {
  selectedSiteIds.value = [];
  showMismatchRows.value = false;
  acknowledged.value = false;
  overrideReason.value = '';
  validationError.value = '';
  apiError.value = '';
  showConfirmDialog.value = false;
};

const loadOptions = async () => {
  if (!props.booking?.id) return;
  const token = ++optionsToken.value;
  optionsLoading.value = true;
  optionsError.value = '';
  options.value = null;
  resetForm();

  try {
    const payload = await fetchReassignmentOptions(props.booking.id);
    if (token !== optionsToken.value) return;
    options.value = payload;
    assignmentFingerprint.value = payload.requirements?.assignment_fingerprint || '';
    const owned = [];
    payload.rows?.forEach((row) => {
      row.sites?.forEach((site) => {
        if (site.is_owned_by_booking) owned.push(site.id);
      });
    });
    if (owned.length === payload.requirements?.site_count) {
      selectedSiteIds.value = [...owned];
    }
  } catch (error) {
    if (token !== optionsToken.value) return;
    const code = error?.response?.data?.error;
    optionsError.value = reassignmentErrorMessage(code, error?.response?.data?.message);
  } finally {
    if (token === optionsToken.value) {
      optionsLoading.value = false;
    }
  }
};

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      loadOptions();
    } else {
      optionsToken.value += 1;
      resetForm();
      options.value = null;
    }
  },
);

const siteButtonClass = (site) => {
  const selected = selectedSiteIds.value.includes(site.id);
  if (!site.is_selectable) return 'border-ink-100 bg-ink-50 text-ink-400';
  if (selected) return 'border-cyan-600 bg-cyan-600 text-white';
  return 'border-ink-200 bg-white text-ink-800 hover:border-cyan-400';
};

const toggleSite = (site) => {
  if (!site.is_selectable || submitting.value) return;
  validationError.value = '';
  apiError.value = '';

  const row = options.value.rows.find((candidate) =>
    candidate.sites.some((entry) => entry.id === site.id),
  );
  if (!row) return;

  const rowSiteIds = row.sites.filter((entry) => entry.is_selectable).map((entry) => entry.id);
  const currentInRow = selectedSiteIds.value.filter((id) => rowSiteIds.includes(id));

  if (currentInRow.length > 0 && !currentInRow.includes(site.id)) {
    const otherRowSelected = selectedSiteIds.value.some((id) => !rowSiteIds.includes(id));
    if (otherRowSelected) {
      selectedSiteIds.value = [site.id];
      return;
    }
  }

  if (selectedSiteIds.value.includes(site.id)) {
    selectedSiteIds.value = selectedSiteIds.value.filter((id) => id !== site.id);
    return;
  }

  if (selectedSiteIds.value.length >= options.value.requirements.site_count) {
    validationError.value = `Pilih tepat ${options.value.requirements.site_count} tapak.`;
    return;
  }

  const next = [...selectedSiteIds.value, site.id];
  const nextRowIds = new Set();
  options.value.rows.forEach((candidate) => {
    if (candidate.sites.some((entry) => next.includes(entry.id))) {
      nextRowIds.add(candidate.id);
    }
  });
  if (nextRowIds.size > 1) {
    validationError.value = 'Semua tapak mesti dipilih daripada baris yang sama.';
    return;
  }

  selectedSiteIds.value = next;
};

const requestClose = () => {
  if (submitting.value) return;
  if (selectedSiteIds.value.length > 0 || overrideReason.value.trim()) {
    if (!window.confirm('Tutup tanpa menyimpan perubahan tapak?')) return;
  }
  emit('update:modelValue', false);
};

const confirmSubmit = () => {
  validationError.value = '';
  apiError.value = '';
  if (!canSubmit.value) {
    if (overrideRequired.value && !acknowledged.value) {
      validationError.value = 'Sila sahkan bahawa anda memahami pengecualian kategori ini.';
    } else if (overrideRequired.value && overrideReason.value.trim().length < 10) {
      validationError.value = 'Sebab pengecualian terlalu pendek.';
    } else {
      validationError.value = `Pilih tepat ${options.value?.requirements?.site_count || 0} tapak.`;
    }
    return;
  }
  showConfirmDialog.value = true;
};

const submit = async () => {
  if (!props.booking?.id || submitting.value) return;
  submitting.value = true;
  apiError.value = '';
  validationError.value = '';

  const payload = {
    event_site_ids: [...selectedSiteIds.value].sort((a, b) => a - b),
    assignment_fingerprint: assignmentFingerprint.value,
  };
  if (overrideRequired.value) {
    payload.acknowledge_category_override = true;
    payload.override_reason = overrideReason.value.trim();
  }

  try {
    const response = await submitSiteReassignment(props.booking.id, payload);
    showConfirmDialog.value = false;
    emit('applied', response.booking);
    emit('update:modelValue', false);
  } catch (error) {
    const code = error?.response?.data?.error;
    apiError.value = reassignmentErrorMessage(code, error?.response?.data?.message);
    if (code === 'ASSIGNMENT_CHANGED') {
      await loadOptions();
    }
  } finally {
    submitting.value = false;
  }
};
</script>
