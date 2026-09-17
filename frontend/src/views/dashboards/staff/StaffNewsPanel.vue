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
          <label class="ml-label">Short summary</label>
          <textarea v-model="form.excerpt" required rows="5" class="ml-input"></textarea>
          <p class="text-xs text-ink-500 mt-1">Shown as a short preview on the Venue News page.</p>
        </div>
        <div>
          <label class="ml-label">Full details (optional)</label>
          <textarea v-model="form.body" rows="4" class="ml-input"></textarea>
        </div>
        <MultiImageUploadField
          ref="imageField"
          label="News images (optional)"
          :existing="editingImages"
          :legacy-field="legacyImagePath"
          @update:files="imageFiles = $event"
          @update:removeIds="removeImageIds = $event"
        />
        <div>
          <label class="ml-label" for="news-video-input">Promotional video (optional)</label>
          <input
            id="news-video-input"
            ref="videoInput"
            type="file"
            accept="video/mp4,video/webm"
            class="ml-input file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-700"
            @change="onVideoSelected"
          />
          <p class="text-xs text-ink-500 mt-1">
            One MP4 or WebM video, up to 10 MB. Images remain the cover. Video is secondary and optional.
          </p>
          <div v-if="videoPreviewUrl || (existingVideoUrl && !removeVideo)" class="mt-3 rounded-lg border border-ink-200 p-3 space-y-2">
            <p class="text-xs font-semibold text-ink-700">
              {{ videoFileName || 'Current video' }}
              <span v-if="videoFileSizeLabel" class="font-normal text-ink-500"> · {{ videoFileSizeLabel }}</span>
            </p>
            <video
              v-if="videoPreviewUrl || existingVideoUrl"
              :src="videoPreviewUrl || existingVideoUrl"
              class="w-full max-h-48 rounded-md bg-ink-900"
              controls
              preload="metadata"
              playsinline
              muted
            />
            <div class="flex flex-wrap gap-2">
              <button type="button" class="ml-btn-ghost text-sm" @click="triggerVideoReplace">Replace</button>
              <button type="button" class="ml-btn-ghost text-sm text-rose-600" @click="removeSelectedVideo">Remove</button>
            </div>
          </div>
        </div>
        <p
          v-if="draftRestoredNotice"
          class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950"
          role="status"
        >
          Your text was restored. Please select image and video files again after logging in.
        </p>
        <div>
          <label class="ml-label">External image URL (optional fallback)</label>
          <input v-model="form.image_url" type="url" class="ml-input" placeholder="https://..." />
          <p class="text-xs text-ink-500 mt-1">Used only when no uploaded images are set.</p>
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
      <div v-if="loading && !hasLoaded" class="text-ink-500 text-sm">Loading news posts…</div>
      <div v-else-if="hasLoaded && !posts.length" class="text-ink-500 text-sm">No news posts yet.</div>
      <ul v-else class="space-y-3">
        <li
          v-for="post in posts"
          :key="post.id"
          tabindex="0"
          role="button"
          :aria-label="`View news post: ${post.title}`"
          class="rounded-lg border border-ink-200 p-3 cursor-pointer hover:border-brand-300 hover:bg-brand-50/30 hover:ring-2 hover:ring-brand-500/10 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 group"
          @click="openNewsDetails(post)"
          @keydown.enter.prevent="openNewsDetails(post)"
          @keydown.space.prevent="openNewsDetails(post)"
        >
          <div class="flex justify-between gap-3">
            <div class="flex gap-3 min-w-0 pointer-events-none">
              <div class="relative w-16 h-16 shrink-0">
                <img
                  v-if="post.bannerUrl"
                  :src="post.bannerUrl"
                  :alt="`${post.title} banner preview`"
                  class="w-16 h-16 rounded-lg object-cover object-top border border-ink-200"
                />
                <div
                  v-else-if="post.hasVideo"
                  class="w-16 h-16 rounded-lg border border-dashed border-ink-200 bg-ink-50 flex items-center justify-center text-[10px] font-bold uppercase tracking-wide text-ink-500"
                >
                  Video
                </div>
                <div v-else class="w-16 h-16 rounded-lg border border-dashed border-ink-200 bg-ink-50 flex items-center justify-center text-[10px] font-bold text-ink-400">
                  No image
                </div>
                <span
                  v-if="post.hasVideo && post.bannerUrl"
                  class="absolute bottom-0.5 left-0.5 rounded bg-black/70 px-1 py-px text-[9px] font-bold uppercase tracking-wide text-white"
                  aria-label="This post includes a video"
                >
                  Video
                </span>
              </div>
              <div class="min-w-0">
                <div class="font-bold text-ink-900">{{ post.title }}</div>
                <div class="text-xs text-ink-500">
                  {{ post.category }} · {{ post.statusLabel }}
                  <span v-if="post.publishedDateShort"> · {{ post.publishedDateShort }}</span>
                </div>
                <p v-if="post.excerpt" class="text-xs text-ink-500 mt-1 line-clamp-2">{{ post.excerpt }}</p>
                <p class="text-xs text-brand-600 font-semibold mt-1 opacity-0 group-hover:opacity-100 transition-opacity">
                  Click to preview full post
                </p>
              </div>
            </div>
            <div class="flex flex-col gap-1 shrink-0" @click.stop>
              <button class="ml-btn-ghost text-sm" @click="edit(post)">Edit</button>
              <button class="ml-btn-ghost text-sm text-rose-600" :disabled="deletingId === post.id" @click="remove(post.id)">
                {{ deletingId === post.id ? 'Deleting…' : 'Delete' }}
              </button>
            </div>
          </div>
        </li>
      </ul>
    </section>

    <NewsDetailsModal v-model="showNewsModal" :post="selectedNews" show-status />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import { useToast } from 'vue-toastification';
import NewsDetailsModal from '../../../components/NewsDetailsModal.vue';
import MultiImageUploadField from '../../../components/MultiImageUploadField.vue';
import api from '../../../services/api';
import { mapApiNewsToCard } from '../../../utils/newsDisplay';
import { normalizeNews } from '../../../utils/imageUrl';
import { onSessionExpired } from '../../../utils/sessionExpiry';

const toast = useToast();
const posts = ref([]);
const loading = ref(false);
const hasLoaded = ref(false);
const saving = ref(false);
const deletingId = ref(null);
const editingId = ref(null);
const selectedNews = ref(null);
const showNewsModal = ref(false);
const imageField = ref(null);
const imageFiles = ref([]);
const removeImageIds = ref([]);
const editingImages = ref([]);
const legacyImagePath = ref('');
const videoInput = ref(null);
const videoFile = ref(null);
const videoPreviewUrl = ref('');
const existingVideoUrl = ref('');
const removeVideo = ref(false);
const draftRestoredNotice = ref(false);

const NEWS_DRAFT_KEY = 'cmart_news_form_draft';
const MAX_VIDEO_BYTES = 10 * 1024 * 1024;

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

const videoFileName = computed(() => videoFile.value?.name || '');
const videoFileSizeLabel = computed(() => {
  if (!videoFile.value) return '';
  const mb = videoFile.value.size / (1024 * 1024);
  return `${mb.toFixed(1)} MB`;
});

const clearNewsDraft = () => {
  try {
    sessionStorage.removeItem(NEWS_DRAFT_KEY);
  } catch {
    /* ignore */
  }
  draftRestoredNotice.value = false;
};

const persistNewsDraft = () => {
  const hasText = Boolean(
    editingId.value
    || form.title.trim()
    || form.excerpt.trim()
    || form.body.trim()
    || form.image_url.trim(),
  );
  if (!hasText) return;

  const payload = {
    editingId: editingId.value,
    title: form.title,
    excerpt: form.excerpt,
    body: form.body,
    category: form.category,
    image_url: form.image_url,
    published_at: form.published_at,
    is_published: form.is_published,
    hadFiles: imageFiles.value.length > 0 || Boolean(videoFile.value) || Boolean(existingVideoUrl.value && !removeVideo.value),
  };

  try {
    sessionStorage.setItem(NEWS_DRAFT_KEY, JSON.stringify(payload));
  } catch {
    /* ignore */
  }
};

const restoreNewsDraft = () => {
  let raw = null;
  try {
    raw = sessionStorage.getItem(NEWS_DRAFT_KEY);
  } catch {
    return;
  }
  if (!raw) return;

  try {
    const draft = JSON.parse(raw);
    if (!draft || typeof draft !== 'object') return;
    editingId.value = draft.editingId || null;
    form.title = draft.title || '';
    form.excerpt = draft.excerpt || '';
    form.body = draft.body || '';
    form.category = draft.category || 'Announcement';
    form.image_url = draft.image_url || '';
    form.published_at = draft.published_at || '';
    form.is_published = draft.is_published !== false;
    draftRestoredNotice.value = Boolean(draft.hadFiles);
  } catch {
    clearNewsDraft();
  }
};

const revokeVideoPreview = () => {
  if (videoPreviewUrl.value?.startsWith('blob:')) {
    URL.revokeObjectURL(videoPreviewUrl.value);
  }
  videoPreviewUrl.value = '';
};

const onVideoSelected = (event) => {
  const file = event.target.files?.[0];
  if (videoInput.value) videoInput.value.value = '';
  if (!file) return;

  const typeOk = file.type === 'video/mp4' || file.type === 'video/webm' || /\.(mp4|webm)$/i.test(file.name);
  if (!typeOk) {
    toast.error('Only MP4 or WebM video files are allowed.');
    return;
  }
  if (file.size > MAX_VIDEO_BYTES) {
    toast.error('Video must be 10 MB or smaller.');
    return;
  }

  revokeVideoPreview();
  videoFile.value = file;
  videoPreviewUrl.value = URL.createObjectURL(file);
  removeVideo.value = false;
};

const triggerVideoReplace = () => {
  videoInput.value?.click();
};

const removeSelectedVideo = () => {
  revokeVideoPreview();
  videoFile.value = null;
  if (existingVideoUrl.value) {
    removeVideo.value = true;
  }
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

  imageFiles.value.forEach((file) => {
    fd.append('images[]', file);
  });

  removeImageIds.value.forEach((id) => {
    fd.append('remove_image_ids[]', String(id));
  });

  if (imageField.value?.hasLegacyRemoval?.()) {
    fd.append('remove_banner', '1');
  }

  if (videoFile.value) {
    fd.append('video', videoFile.value);
  }

  if (removeVideo.value && !videoFile.value) {
    fd.append('remove_video', '1');
  }

  return fd;
};

const openNewsDetails = (post) => {
  selectedNews.value = post;
  showNewsModal.value = true;
};

const load = async () => {
  loading.value = true;
  try {
    const { data } = await api.get('/news-posts');
    posts.value = (Array.isArray(data) ? data : []).map(mapApiNewsToCard);
    hasLoaded.value = true;
    if (editingId.value) {
      const current = posts.value.find((post) => post.id === editingId.value);
      if (current) {
        const normalized = normalizeNews(current);
        editingImages.value = normalized.images?.filter((image) => image.id) || [];
        legacyImagePath.value = normalized.image_path || '';
        existingVideoUrl.value = normalized.videoUrl || normalized.video_url || '';
      }
    }
  } catch (error) {
    console.error('Failed to load news posts:', error);
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
  revokeVideoPreview();
  videoFile.value = null;
  existingVideoUrl.value = '';
  removeVideo.value = false;
  if (videoInput.value) videoInput.value.value = '';
  Object.assign(form, emptyForm());
  clearNewsDraft();
};

const edit = (post) => {
  const normalized = normalizeNews(post);
  editingId.value = normalized.id;
  form.title = normalized.title;
  form.excerpt = normalized.excerpt;
  form.body = normalized.body || '';
  form.category = normalized.category;
  form.image_url = normalized.external_image_url || '';
  form.published_at = toLocalInput(normalized.published_at);
  form.is_published = Boolean(normalized.is_published);
  editingImages.value = normalized.images?.filter((image) => image.id) || [];
  legacyImagePath.value = normalized.image_path || '';
  existingVideoUrl.value = normalized.video_url || normalized.videoUrl || '';
  imageFiles.value = [];
  removeImageIds.value = [];
  revokeVideoPreview();
  videoFile.value = null;
  removeVideo.value = false;
  if (videoInput.value) videoInput.value.value = '';
  imageField.value?.reset();
  draftRestoredNotice.value = false;
};

const save = async () => {
  if (!form.title.trim() || !form.excerpt.trim() || !form.category.trim()) {
    toast.error('Title, short summary, and category are required.');
    return;
  }

  saving.value = true;
  const usesMultipart = imageFiles.value.length > 0
    || removeImageIds.value.length > 0
    || imageField.value?.hasLegacyRemoval?.()
    || Boolean(videoFile.value)
    || removeVideo.value;

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
      const payload = {
        title: form.title.trim(),
        excerpt: form.excerpt.trim(),
        category: form.category.trim(),
        body: form.body || null,
        published_at: form.published_at || null,
        is_published: form.is_published,
      };
      if (form.image_url?.trim()) {
        payload.image_url = form.image_url.trim();
      }
      await api.put(`/news-posts/${editingId.value}`, payload);
      toast.success('News post updated.');
    } else {
      await api.post('/news-posts', {
        title: form.title.trim(),
        excerpt: form.excerpt.trim(),
        category: form.category.trim(),
        body: form.body || null,
        image_url: form.image_url?.trim() || null,
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

defineExpose({ load });

onMounted(() => {
  restoreNewsDraft();
});

const stopExpiryListener = onSessionExpired(() => {
  persistNewsDraft();
});

onUnmounted(() => {
  stopExpiryListener();
  revokeVideoPreview();
});
</script>
