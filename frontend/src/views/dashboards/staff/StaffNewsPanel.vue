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
          <label class="ml-label">Banner image (optional)</label>
          <input
            ref="bannerInput"
            type="file"
            accept="image/jpeg,image/jpg,image/png,image/webp"
            class="ml-input file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-700"
            @change="onBannerSelected"
          />
          <p class="text-xs text-ink-500 mt-1">JPG, JPEG, PNG, or WEBP up to 5 MB.</p>
          <div v-if="bannerPreviewUrl" class="mt-3">
            <img :src="bannerPreviewUrl" alt="News banner preview" class="max-h-40 rounded-lg border border-ink-200 object-cover" />
            <button type="button" class="ml-btn-ghost text-sm text-rose-600 mt-2" @click="clearBannerSelection">
              Remove selected banner
            </button>
          </div>
        </div>
        <div>
          <label class="ml-label">External image URL (optional fallback)</label>
          <input v-model="form.image_url" type="url" class="ml-input" placeholder="https://..." />
          <p class="text-xs text-ink-500 mt-1">Used only when no uploaded banner is set.</p>
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
      <div v-if="loading" class="text-ink-500 text-sm">Loading news posts…</div>
      <div v-else-if="!posts.length" class="text-ink-500 text-sm">No news posts yet.</div>
      <ul v-else class="space-y-3">
        <li v-for="post in posts" :key="post.id" class="rounded-lg border border-ink-200 p-3">
          <div class="flex justify-between gap-3">
            <div class="flex gap-3 min-w-0">
              <img
                v-if="post.banner_url"
                :src="post.banner_url"
                :alt="`${post.title} banner`"
                class="w-16 h-16 rounded-lg object-cover border border-ink-200 shrink-0"
              />
              <div class="min-w-0">
                <div class="font-bold text-ink-900">{{ post.title }}</div>
                <div class="text-xs text-ink-500">
                  {{ post.category }} · {{ post.is_published ? 'Published' : 'Draft' }}
                  <span v-if="post.published_at"> · {{ formatDateTime(post.published_at) }}</span>
                </div>
                <p v-if="post.excerpt" class="text-xs text-ink-500 mt-1 line-clamp-2">{{ post.excerpt }}</p>
              </div>
            </div>
            <div class="flex flex-col gap-1 shrink-0">
              <button class="ml-btn-ghost text-sm" @click="edit(post)">Edit</button>
              <button class="ml-btn-ghost text-sm text-rose-600" :disabled="deletingId === post.id" @click="remove(post.id)">
                {{ deletingId === post.id ? 'Deleting…' : 'Delete' }}
              </button>
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
const MY_TZ = 'Asia/Kuala_Lumpur';
const posts = ref([]);
const loading = ref(false);
const saving = ref(false);
const deletingId = ref(null);
const editingId = ref(null);
const bannerInput = ref(null);
const bannerFile = ref(null);
const bannerPreviewUrl = ref('');
const existingBannerUrl = ref('');
const removeBanner = ref(false);

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

const formatDateTime = (iso) => {
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

const revokeBannerPreview = () => {
  if (bannerPreviewUrl.value.startsWith('blob:')) {
    URL.revokeObjectURL(bannerPreviewUrl.value);
  }
};

const clearBannerSelection = () => {
  bannerFile.value = null;
  removeBanner.value = Boolean(existingBannerUrl.value);
  revokeBannerPreview();
  bannerPreviewUrl.value = '';
  if (bannerInput.value) {
    bannerInput.value.value = '';
  }
};

const onBannerSelected = (event) => {
  const file = event.target.files?.[0];
  if (!file) return;

  bannerFile.value = file;
  removeBanner.value = false;
  revokeBannerPreview();
  bannerPreviewUrl.value = URL.createObjectURL(file);
};

const buildFormData = () => {
  const fd = new FormData();
  fd.append('title', form.title.trim());
  fd.append('excerpt', form.excerpt.trim());
  fd.append('category', form.category.trim());
  fd.append('body', form.body || '');
  fd.append('is_published', form.is_published ? '1' : '0');
  if (form.image_url) {
    fd.append('image_url', form.image_url.trim());
  }
  if (form.published_at) {
    fd.append('published_at', form.published_at);
  }
  if (bannerFile.value) {
    fd.append('banner', bannerFile.value);
  }
  if (removeBanner.value) {
    fd.append('remove_banner', '1');
  }
  return fd;
};

const load = async () => {
  loading.value = true;
  try {
    const { data } = await api.get('/news-posts');
    posts.value = Array.isArray(data) ? data : [];
  } catch (error) {
    console.error('Failed to load news posts:', error);
    toast.error(extractApiError(error));
  } finally {
    loading.value = false;
  }
};

const resetForm = () => {
  editingId.value = null;
  existingBannerUrl.value = '';
  removeBanner.value = false;
  clearBannerSelection();
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
  existingBannerUrl.value = post.image_path ? post.banner_url : '';
  removeBanner.value = false;
  bannerFile.value = null;
  if (bannerInput.value) {
    bannerInput.value.value = '';
  }
  revokeBannerPreview();
  bannerPreviewUrl.value = post.image_path ? post.banner_url : '';
};

const save = async () => {
  if (!form.title.trim() || !form.excerpt.trim() || !form.category.trim()) {
    toast.error('Title, excerpt, and category are required.');
    return;
  }

  saving.value = true;
  const usesMultipart = Boolean(bannerFile.value || removeBanner.value);

  try {
    if (usesMultipart) {
      const fd = buildFormData();
      if (editingId.value) {
        fd.append('_method', 'PUT');
        await api.post(`/news-posts/${editingId.value}`, fd);
        toast.success('News post updated.');
      } else {
        await api.post('/news-posts', fd);
        toast.success('News post created.');
      }
    } else if (editingId.value) {
      await api.put(`/news-posts/${editingId.value}`, {
        title: form.title.trim(),
        excerpt: form.excerpt.trim(),
        category: form.category.trim(),
        body: form.body || null,
        image_url: form.image_url || null,
        published_at: form.published_at || null,
        is_published: form.is_published,
      });
      toast.success('News post updated.');
    } else {
      await api.post('/news-posts', {
        title: form.title.trim(),
        excerpt: form.excerpt.trim(),
        category: form.category.trim(),
        body: form.body || null,
        image_url: form.image_url || null,
        published_at: form.published_at || null,
        is_published: form.is_published,
      });
      toast.success('News post created.');
    }

    resetForm();
    await load();
  } catch (error) {
    console.error('Failed to save news post:', error);
    toast.error(extractApiError(error));
  } finally {
    saving.value = false;
  }
};

const remove = async (id) => {
  if (!window.confirm('Delete this news post? This cannot be undone.')) return;

  deletingId.value = id;
  try {
    await api.delete(`/news-posts/${id}`);
    toast.success('News post deleted.');
    await load();
  } catch (error) {
    console.error('Failed to delete news post:', error);
    toast.error(extractApiError(error));
  } finally {
    deletingId.value = null;
  }
};

onMounted(load);

defineExpose({ load });
</script>
