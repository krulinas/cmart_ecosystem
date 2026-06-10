<template>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <section class="ml-card">
      <h2 class="text-lg font-extrabold text-ink-900 mb-4">{{ editingId ? 'Edit Post' : 'Create News Post' }}</h2>
      <form @submit.prevent="save" class="space-y-3">
        <div>
          <label class="ml-label">Title</label>
          <input v-model="form.title" required class="ml-input" />
        </div>
        <div>
          <label class="ml-label">Category</label>
          <input v-model="form.category" required class="ml-input" placeholder="Announcement" />
        </div>
        <div>
          <label class="ml-label">Excerpt</label>
          <textarea v-model="form.excerpt" required rows="2" class="ml-input"></textarea>
        </div>
        <div>
          <label class="ml-label">Body (optional)</label>
          <textarea v-model="form.body" rows="4" class="ml-input"></textarea>
        </div>
        <div>
          <label class="ml-label">Image URL</label>
          <input v-model="form.image_url" type="url" class="ml-input" placeholder="https://..." />
        </div>
        <div>
          <label class="ml-label">Published at</label>
          <input v-model="form.published_at" type="datetime-local" class="ml-input" />
        </div>
        <label class="flex items-center gap-2 text-sm font-medium text-ink-700">
          <input v-model="form.is_published" type="checkbox" class="rounded" />
          Published on community portal
        </label>
        <div class="flex gap-2">
          <button type="submit" class="ml-btn-primary" :disabled="saving">{{ saving ? 'Saving…' : 'Save Post' }}</button>
          <button v-if="editingId" type="button" class="ml-btn-ghost" @click="resetForm">Cancel Edit</button>
        </div>
      </form>
    </section>

    <section class="ml-card">
      <h2 class="text-lg font-extrabold text-ink-900 mb-4">All News Posts</h2>
      <ul class="space-y-3">
        <li v-for="post in posts" :key="post.id" class="rounded-lg border border-ink-200 p-3">
          <div class="flex justify-between gap-2">
            <div>
              <div class="font-bold text-ink-900">{{ post.title }}</div>
              <div class="text-xs text-ink-500">{{ post.category }} · {{ post.is_published ? 'Published' : 'Draft' }}</div>
            </div>
            <div class="flex gap-1 shrink-0">
              <button class="ml-btn-ghost text-sm" @click="edit(post)">Edit</button>
              <button class="ml-btn-ghost text-sm text-rose-600" @click="remove(post.id)">Delete</button>
            </div>
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
const posts = ref([]);
const saving = ref(false);
const editingId = ref(null);

const emptyForm = () => ({
  title: '',
  excerpt: '',
  body: '',
  category: 'Announcement',
  image_url: '',
  published_at: '',
  is_published: true,
});

const form = reactive(emptyForm());

const toLocalInput = (iso) => {
  if (!iso) return '';
  const d = new Date(iso);
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const load = async () => {
  const { data } = await api.get('/news-posts');
  posts.value = Array.isArray(data) ? data : [];
};

const resetForm = () => {
  editingId.value = null;
  Object.assign(form, emptyForm());
};

const edit = (post) => {
  editingId.value = post.id;
  form.title = post.title;
  form.excerpt = post.excerpt;
  form.body = post.body || '';
  form.category = post.category;
  form.image_url = post.image_url || '';
  form.published_at = toLocalInput(post.published_at);
  form.is_published = Boolean(post.is_published);
};

const save = async () => {
  saving.value = true;
  const payload = {
    ...form,
    published_at: form.published_at || null,
    image_url: form.image_url || null,
    body: form.body || null,
  };
  try {
    if (editingId.value) {
      await api.put(`/news-posts/${editingId.value}`, payload);
      toast.success('News post updated.');
    } else {
      await api.post('/news-posts', payload);
      toast.success('News post created.');
    }
    resetForm();
    await load();
  } catch {
    toast.error('Could not save news post.');
  } finally {
    saving.value = false;
  }
};

const remove = async (id) => {
  if (!window.confirm('Delete this news post?')) return;
  await api.delete(`/news-posts/${id}`);
  toast.success('News post deleted.');
  await load();
};

onMounted(load);

defineExpose({ load });
</script>
