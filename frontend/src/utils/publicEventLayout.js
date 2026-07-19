function numberOrZero(value) {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

function normalizeCategory(category = {}) {
  return {
    id: numberOrZero(category.id),
    slug: String(category.slug || ''),
    label: String(category.label || ''),
    description: category.description ? String(category.description) : null,
    display_order: numberOrZero(category.display_order),
    row_count: numberOrZero(category.row_count),
    site_count: numberOrZero(category.site_count),
  };
}

export function normalizePublicLayout(payload = {}) {
  const rows = (Array.isArray(payload.rows) ? payload.rows : [])
    .map((row) => ({
      id: numberOrZero(row.id),
      label: String(row.label || ''),
      description: row.description ? String(row.description) : null,
      display_order: numberOrZero(row.display_order),
      site_count: numberOrZero(row.site_count),
      category: normalizeCategory(row.category),
      sites: (Array.isArray(row.sites) ? row.sites : [])
        .map((site) => ({
          id: numberOrZero(site.id),
          label: String(site.label || ''),
          display_order: numberOrZero(site.display_order),
          position_number: numberOrZero(site.position_number),
          grid_row: site.grid_row == null ? null : numberOrZero(site.grid_row),
          grid_column: site.grid_column == null ? null : numberOrZero(site.grid_column),
          space: site.space?.name ? { name: String(site.space.name) } : null,
        }))
        .filter((site) => site.label)
        .sort((left, right) =>
          left.display_order - right.display_order
          || left.position_number - right.position_number
          || left.id - right.id),
    }))
    .filter((row) => row.label && row.category.label)
    .sort((left, right) =>
      left.display_order - right.display_order || left.id - right.id);

  const categories = (Array.isArray(payload.categories) ? payload.categories : [])
    .map(normalizeCategory)
    .filter((category) => category.id && category.label)
    .sort((left, right) =>
      left.display_order - right.display_order || left.id - right.id);

  return {
    layout_available: payload.layout_available === true,
    historical: payload.historical === true,
    entrance_note: payload.entrance_note ? String(payload.entrance_note) : null,
    event: {
      id: numberOrZero(payload.event?.id),
      name: String(payload.event?.name || ''),
      status: String(payload.event?.status || ''),
    },
    categories,
    rows,
  };
}

export function filterPublicLayoutRows(rows = [], categoryId = 'all') {
  if (categoryId === 'all') return [...rows];
  return rows.filter((row) => String(row.category?.id) === String(categoryId));
}

export function publicLayoutFilterAnnouncement(categoryLabel, rowCount) {
  if (categoryLabel === 'All Categories') {
    return `${rowCount} layout rows shown for all categories.`;
  }
  return `${rowCount} layout rows shown for ${categoryLabel}.`;
}
