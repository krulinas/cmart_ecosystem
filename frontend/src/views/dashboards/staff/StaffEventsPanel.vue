<template>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <section class="ml-card">
      <h2 class="text-lg font-extrabold text-ink-900 mb-4">{{ editingId ? 'Edit Event' : 'Create Carboot Event' }}</h2>
      <form @submit.prevent="save" class="space-y-3">
        <div>
          <label class="ml-label">Title</label>
          <input v-model="form.title" required class="ml-input" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="ml-label">Starts at</label>
            <input v-model="form.starts_at" type="datetime-local" required class="ml-input" />
          </div>
          <div>
            <label class="ml-label">Ends at</label>
            <input v-model="form.ends_at" type="datetime-local" required class="ml-input" />
          </div>
        </div>
        <div>
          <label class="ml-label">Status</label>
          <select v-model="form.status" required class="ml-input">
            <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>
        <div>
          <label class="ml-label">Max slots (optional)</label>
          <input v-model.number="form.max_slots" type="number" min="1" class="ml-input" />
        </div>
        <div>
          <label class="ml-label">Item reservation service fee (RM, optional)</label>
          <input
            v-model="form.item_reservation_service_fee"
            type="number"
            min="0"
            max="99999999.99"
            step="0.01"
            class="ml-input"
            placeholder="Not configured"
          />
          <p class="mt-1 text-xs text-ink-500">
            Leave blank to keep item reservations closed. RM0.00 means no service charge is required.
          </p>
        </div>
        <div>
          <label class="ml-label">Description</label>
          <textarea v-model="form.description" rows="3" class="ml-input"></textarea>
        </div>
        <MultiImageUploadField
          ref="imageField"
          label="Event images (optional)"
          :existing="editingImages"
          :legacy-field="legacyImagePath"
          @update:files="imageFiles = $event"
          @update:removeIds="removeImageIds = $event"
        />
        <div class="flex gap-2">
          <button type="submit" class="ml-btn-primary" :disabled="saving">{{ saving ? 'Saving…' : 'Save Event' }}</button>
          <button v-if="editingId" type="button" class="ml-btn-ghost" @click="resetForm">Cancel Edit</button>
        </div>
      </form>
      <p class="text-xs text-ink-500 mt-3">
        Events appear on the <router-link to="/calendar" class="text-brand-600 font-semibold">public calendar</router-link>
        and portal schedule cards.
      </p>
    </section>

    <section class="ml-card">
      <h2 class="text-lg font-extrabold text-ink-900 mb-4">Scheduled Events</h2>
      <div v-if="loading && !hasLoaded" class="text-ink-500 text-sm">Loading events…</div>
      <div v-else-if="hasLoaded && !events.length" class="text-ink-500 text-sm">No events yet.</div>
      <ul v-else class="space-y-3">
        <li v-for="ev in events" :key="ev.id" class="rounded-lg border border-ink-200 p-3 flex justify-between gap-3">
          <div class="flex gap-3 min-w-0">
            <img
              v-if="resolveEventImageUrl(ev)"
              :src="resolveEventImageUrl(ev)"
              :alt="`${ev.title} poster`"
              class="w-16 h-16 rounded-lg object-cover border border-ink-200 shrink-0"
            />
            <div v-else class="w-16 h-16 rounded-lg border border-dashed border-ink-200 bg-ink-50 shrink-0 flex items-center justify-center text-[10px] font-bold text-ink-400">
              No image
            </div>
            <div class="min-w-0">
              <div class="font-bold text-ink-900">{{ ev.title }}</div>
              <div class="text-xs text-ink-500">{{ formatEventDateTime(ev.starts_at) }} → {{ formatEventDateTime(ev.ends_at) }}</div>
              <span class="mt-1 inline-block ml-badge bg-brand-100 text-brand-800">{{ ev.status }}</span>
              <p class="text-xs text-ink-500 mt-1">
                Reservation fee:
                {{ ev.item_reservation_service_fee == null ? 'Not configured' : `RM ${Number(ev.item_reservation_service_fee).toFixed(2)}` }}
              </p>
              <p v-if="ev.description" class="text-xs text-ink-500 mt-1 line-clamp-2">{{ ev.description }}</p>
            </div>
          </div>
          <div class="flex flex-col gap-1 shrink-0">
            <button class="ml-btn-ghost text-sm" @click="edit(ev)">Edit</button>
            <button
              class="ml-btn-ghost text-sm text-cyan-800"
              data-testid="manage-layout-button"
              @click="openLayout(ev)"
            >
              Urus Susun Atur
            </button>
            <button class="ml-btn-ghost text-sm text-rose-600" :disabled="deletingId === ev.id" @click="remove(ev.id)">
              {{ deletingId === ev.id ? 'Deleting…' : 'Delete' }}
            </button>
          </div>
        </li>
      </ul>
    </section>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import api from '../../../services/api';
import MultiImageUploadField from '../../../components/MultiImageUploadField.vue';
import { resolveEventImageUrl, normalizeEvent } from '../../../utils/imageUrl';
import {
  formatEventDateTime,
  fromDatetimeLocalValue,
  toDatetimeLocalValue,
} from '../../../utils/eventDisplay';

const toast = useToast();
const router = useRouter();
const statuses = ['Available', 'Almost Full', 'Closed'];
const events = ref([]);
const loading = ref(false);
const hasLoaded = ref(false);
const saving = ref(false);
const deletingId = ref(null);
const editingId = ref(null);
const imageField = ref(null);
const imageFiles = ref([]);
const removeImageIds = ref([]);
const editingImages = ref([]);
const legacyImagePath = ref('');

const openLayout = (event) => {
  router.push({
    path: '/admin',
    hash: '#layout',
    query: { eventId: String(event.id) },
  });
};

const emptyForm = () => ({
  title: '',
  starts_at: '',
  ends_at: '',
  status: 'Available',
  description: '',
  max_slots: null,
  item_reservation_service_fee: '',
});

const form = reactive(emptyForm());

const extractApiError = (error) => {
  const data = error.response?.data;
  if (data?.errors) {
    return Object.values(data.errors).flat().join(' ');
  }
  return data?.message || error.message || 'Request failed.';
};

const buildFormData = () => {
  const fd = new FormData();
  fd.append('title', form.title.trim());
  fd.append('starts_at', fromDatetimeLocalValue(form.starts_at));
  fd.append('ends_at', fromDatetimeLocalValue(form.ends_at));
  fd.append('status', form.status);
  fd.append('description', form.description || '');
  if (form.max_slots) {
    fd.append('max_slots', String(form.max_slots));
  }
  fd.append('item_reservation_service_fee', form.item_reservation_service_fee);

  imageFiles.value.forEach((file) => {
    fd.append('images[]', file);
  });

  removeImageIds.value.forEach((id) => {
    fd.append('remove_image_ids[]', String(id));
  });

  if (imageField.value?.hasLegacyRemoval?.()) {
    fd.append('remove_poster', '1');
  }

  return fd;
};

const load = async () => {
  loading.value = true;
  try {
    const { data } = await api.get('/carboot-events');
    events.value = (Array.isArray(data) ? data : []).map(normalizeEvent);
    hasLoaded.value = true;
  } catch (error) {
    console.error('Failed to load events:', error);
    toast.error(extractApiError(error));
    throw error;
  } finally {
    loading.value = false;
  }
};

const resetForm = () => {
  editingId.value = null;
  editingImages.value = [];
  legacyImagePath.value = '';
  imageFiles.value = [];
  removeImageIds.value = [];
  imageField.value?.reset();
  Object.assign(form, emptyForm());
};

const edit = (ev) => {
  const normalized = normalizeEvent(ev);
  editingId.value = normalized.id;
  form.title = normalized.title;
  form.starts_at = toDatetimeLocalValue(normalized.starts_at);
  form.ends_at = toDatetimeLocalValue(normalized.ends_at);
  form.status = normalized.status;
  form.description = normalized.description || '';
  form.max_slots = normalized.max_slots;
  form.item_reservation_service_fee = normalized.item_reservation_service_fee ?? '';
  editingImages.value = normalized.images?.filter((image) => image.id) || [];
  legacyImagePath.value = normalized.image_path || '';
  imageFiles.value = [];
  removeImageIds.value = [];
  imageField.value?.reset();
};

const save = async () => {
  if (!form.title.trim() || !form.starts_at || !form.ends_at) {
    toast.error('Title, start time, and end time are required.');
    return;
  }

  if (form.ends_at <= form.starts_at) {
    toast.error('End time must be after start time.');
    return;
  }

  saving.value = true;
  const payload = {
    title: form.title.trim(),
    starts_at: fromDatetimeLocalValue(form.starts_at),
    ends_at: fromDatetimeLocalValue(form.ends_at),
    status: form.status,
    description: form.description || null,
    max_slots: form.max_slots || null,
    item_reservation_service_fee: form.item_reservation_service_fee === ''
      ? null
      : form.item_reservation_service_fee,
  };

  const usesMultipart = imageFiles.value.length > 0
    || removeImageIds.value.length > 0
    || imageField.value?.hasLegacyRemoval?.();

  try {
    if (usesMultipart) {
      const fd = buildFormData();
      if (editingId.value) {
        fd.append('_method', 'PUT');
        await api.post(`/carboot-events/${editingId.value}`, fd);
        toast.success('Event updated.');
      } else {
        await api.post('/carboot-events', fd);
        toast.success('Event created.');
      }
    } else if (editingId.value) {
      await api.put(`/carboot-events/${editingId.value}`, payload);
      toast.success('Event updated.');
    } else {
      await api.post('/carboot-events', payload);
      toast.success('Event created.');
    }

    resetForm();
    await load();
  } catch (error) {
    console.error('Failed to save event:', error);
    toast.error(extractApiError(error));
  } finally {
    saving.value = false;
  }
};

const remove = async (id) => {
  if (!window.confirm('Delete this event? This cannot be undone.')) return;

  deletingId.value = id;
  try {
    await api.delete(`/carboot-events/${id}`);
    toast.success('Event deleted.');
    await load();
  } catch (error) {
    console.error('Failed to delete event:', error);
    toast.error(extractApiError(error));
  } finally {
    deletingId.value = null;
  }
};

defineExpose({ load });
</script>
