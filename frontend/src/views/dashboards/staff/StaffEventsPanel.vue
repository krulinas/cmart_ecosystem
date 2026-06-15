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
          <label class="ml-label">Description</label>
          <textarea v-model="form.description" rows="3" class="ml-input"></textarea>
        </div>
        <div>
          <label class="ml-label">Event poster (optional)</label>
          <input
            ref="posterInput"
            type="file"
            accept="image/jpeg,image/jpg,image/png,image/webp"
            class="ml-input file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-700"
            @change="onPosterSelected"
          />
          <p class="text-xs text-ink-500 mt-1">JPG, JPEG, PNG, or WEBP up to 5 MB.</p>
          <div v-if="posterPreviewUrl" class="mt-3">
            <img :src="posterPreviewUrl" alt="Event poster preview" class="max-h-40 rounded-lg border border-ink-200 object-cover" />
            <button type="button" class="ml-btn-ghost text-sm text-rose-600 mt-2" @click="clearPosterSelection">
              Remove selected poster
            </button>
          </div>
        </div>
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
            <div class="min-w-0">
              <div class="font-bold text-ink-900">{{ ev.title }}</div>
              <div class="text-xs text-ink-500">{{ formatEventDateTime(ev.starts_at) }} → {{ formatEventDateTime(ev.ends_at) }}</div>
              <span class="mt-1 inline-block ml-badge bg-brand-100 text-brand-800">{{ ev.status }}</span>
              <p v-if="ev.description" class="text-xs text-ink-500 mt-1 line-clamp-2">{{ ev.description }}</p>
            </div>
          </div>
          <div class="flex flex-col gap-1 shrink-0">
            <button class="ml-btn-ghost text-sm" @click="edit(ev)">Edit</button>
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
import { useToast } from 'vue-toastification';
import api from '../../../services/api';
import { resolveEventImageUrl } from '../../../utils/imageUrl';

const toast = useToast();
const MY_TZ = 'Asia/Kuala_Lumpur';
const statuses = ['Available', 'Almost Full', 'Closed'];
const events = ref([]);
const loading = ref(false);
const hasLoaded = ref(false);
const saving = ref(false);
const deletingId = ref(null);
const editingId = ref(null);
const posterInput = ref(null);
const posterFile = ref(null);
const posterPreviewUrl = ref('');
const existingPosterUrl = ref('');
const removePoster = ref(false);

const emptyForm = () => ({
  title: '',
  starts_at: '',
  ends_at: '',
  status: 'Available',
  description: '',
  max_slots: null,
});

const form = reactive(emptyForm());

const formatEventDateTime = (iso) => {
  if (!iso) return '';
  return new Date(iso).toLocaleString('en-GB', {
    timeZone: MY_TZ,
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
};

const toLocalInput = (iso) => {
  if (!iso) return '';
  const d = new Date(iso);
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const extractApiError = (error) => {
  const data = error.response?.data;
  if (data?.errors) {
    return Object.values(data.errors).flat().join(' ');
  }
  return data?.message || error.message || 'Request failed.';
};

const revokePosterPreview = () => {
  if (posterPreviewUrl.value.startsWith('blob:')) {
    URL.revokeObjectURL(posterPreviewUrl.value);
  }
};

const clearPosterSelection = () => {
  posterFile.value = null;
  removePoster.value = Boolean(existingPosterUrl.value);
  revokePosterPreview();
  posterPreviewUrl.value = '';
  if (posterInput.value) {
    posterInput.value.value = '';
  }
};

const onPosterSelected = (event) => {
  const file = event.target.files?.[0];
  if (!file) return;

  posterFile.value = file;
  removePoster.value = false;
  revokePosterPreview();
  posterPreviewUrl.value = URL.createObjectURL(file);
};

const buildFormData = () => {
  const fd = new FormData();
  fd.append('title', form.title.trim());
  fd.append('starts_at', form.starts_at);
  fd.append('ends_at', form.ends_at);
  fd.append('status', form.status);
  fd.append('description', form.description || '');
  if (form.max_slots) {
    fd.append('max_slots', String(form.max_slots));
  }
  if (posterFile.value) {
    fd.append('poster', posterFile.value);
  }
  if (removePoster.value) {
    fd.append('remove_poster', '1');
  }
  return fd;
};

const load = async () => {
  loading.value = true;
  try {
    const { data } = await api.get('/carboot-events');
    events.value = Array.isArray(data) ? data : [];
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
  existingPosterUrl.value = '';
  removePoster.value = false;
  clearPosterSelection();
  Object.assign(form, emptyForm());
};

const edit = (ev) => {
  editingId.value = ev.id;
  form.title = ev.title;
  form.starts_at = toLocalInput(ev.starts_at);
  form.ends_at = toLocalInput(ev.ends_at);
  form.status = ev.status;
  form.description = ev.description || '';
  form.max_slots = ev.max_slots;
  existingPosterUrl.value = resolveEventImageUrl(ev) || '';
  removePoster.value = false;
  posterFile.value = null;
  if (posterInput.value) {
    posterInput.value.value = '';
  }
  revokePosterPreview();
  posterPreviewUrl.value = resolveEventImageUrl(ev) || '';
};

const save = async () => {
  if (!form.title.trim() || !form.starts_at || !form.ends_at) {
    toast.error('Title, start time, and end time are required.');
    return;
  }

  saving.value = true;
  const usesMultipart = Boolean(posterFile.value || removePoster.value);

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
      await api.put(`/carboot-events/${editingId.value}`, {
        ...form,
        title: form.title.trim(),
        max_slots: form.max_slots || null,
        description: form.description || null,
      });
      toast.success('Event updated.');
    } else {
      await api.post('/carboot-events', {
        ...form,
        title: form.title.trim(),
        max_slots: form.max_slots || null,
        description: form.description || null,
      });
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
