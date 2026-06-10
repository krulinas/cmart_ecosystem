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
      <div v-if="!events.length" class="text-ink-500 text-sm">No events yet.</div>
      <ul class="space-y-3">
        <li v-for="ev in events" :key="ev.id" class="rounded-lg border border-ink-200 p-3 flex justify-between gap-3">
          <div>
            <div class="font-bold text-ink-900">{{ ev.title }}</div>
            <div class="text-xs text-ink-500">{{ ev.starts_at }} → {{ ev.ends_at }}</div>
            <span class="mt-1 inline-block ml-badge bg-brand-100 text-brand-800">{{ ev.status }}</span>
          </div>
          <div class="flex flex-col gap-1 shrink-0">
            <button class="ml-btn-ghost text-sm" @click="edit(ev)">Edit</button>
            <button class="ml-btn-ghost text-sm text-rose-600" @click="remove(ev.id)">Delete</button>
          </div>
        </li>
      </ul>
    </section>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import api from '../../../services/api';

const toast = useToast();
const statuses = ['Available', 'Almost Full', 'Closed'];
const events = ref([]);
const saving = ref(false);
const editingId = ref(null);

const emptyForm = () => ({
  title: '',
  starts_at: '',
  ends_at: '',
  status: 'Available',
  description: '',
  max_slots: null,
});

const form = reactive(emptyForm());

const toLocalInput = (iso) => {
  if (!iso) return '';
  const d = new Date(iso);
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const load = async () => {
  const { data } = await api.get('/carboot-events');
  events.value = Array.isArray(data) ? data : [];
};

const resetForm = () => {
  editingId.value = null;
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
};

const save = async () => {
  saving.value = true;
  const payload = { ...form, max_slots: form.max_slots || null };
  try {
    if (editingId.value) {
      await api.put(`/carboot-events/${editingId.value}`, payload);
      toast.success('Event updated.');
    } else {
      await api.post('/carboot-events', payload);
      toast.success('Event created.');
    }
    resetForm();
    await load();
  } catch {
    toast.error('Could not save event.');
  } finally {
    saving.value = false;
  }
};

const remove = async (id) => {
  if (!window.confirm('Delete this event?')) return;
  await api.delete(`/carboot-events/${id}`);
  toast.success('Event deleted.');
  await load();
};

onMounted(load);

defineExpose({ load });
</script>
