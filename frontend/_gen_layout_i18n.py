#!/usr/bin/env python3
"""Generate organizer.layout keys from LAYOUT_COPY and rewrite messages util to use tt()."""
from pathlib import Path
import re
import json

ROOT = Path(r"d:\Program Files\xampp\htdocs\cmart_ecosystem\frontend")
MSG = ROOT / "src/utils/organizerEventLayoutMessages.js"
MSG_SRC = ROOT / "_layout_copy_src.js"
EN = ROOT / "src/i18n/locales/en.js"
MS = ROOT / "src/i18n/locales/ms.js"

text = (MSG_SRC if MSG_SRC.exists() else MSG).read_text(encoding="utf-8")
if "export const LAYOUT_COPY = {" not in text:
    raise SystemExit("Need original LAYOUT_COPY object for parsing — place it in _layout_copy_src.js")

# --- Parse LAYOUT_COPY string properties (including concatenated multiline) ---
# Match key: '...' or key: '...\n  ...'
copy_m = re.search(r"export const LAYOUT_COPY = \{([\s\S]*?)\n\};", text)
body = copy_m.group(1)

# Remove function properties for separate handling
fn_props = {
    "physicalSitesOfTotal": ("{present} of {total} physical sites", "{present} daripada {total} tapak fizikal"),
    "sitesCountFallback": ("No sites | {count} site | {count} sites", "Tiada tapak | {count} tapak | {count} tapak"),
    "restoreSite": ("Restore {label}", "Pulihkan {label}"),
    "restoringSite": ("Restoring {label}…", "Memulihkan {label}…"),
    "siteRestoredNotOpen": ("{label} restored as NOT OPEN.", "{label} dipulihkan sebagai BELUM DIBUKA."),
    "sitesRestoredNotOpen": (
        "No physical sites restored | {count} physical site restored as NOT OPEN. | {count} physical sites restored as NOT OPEN.",
        "Tiada tapak fizikal dipulihkan | {count} tapak fizikal dipulihkan sebagai BELUM DIBUKA. | {count} tapak fizikal dipulihkan sebagai BELUM DIBUKA.",
    ),
    "generateStandardOpenCount": None,  # special: two variants
    "selectOpenSitesCount": ("No sites selected | {selected} site selected | {selected} sites selected", "Tiada tapak dipilih | {selected} tapak dipilih | {selected} tapak dipilih"),
    "confirmOpenSites": ("Confirm {count} Site & Open Booking | Confirm {count} Sites & Open Booking", "Sahkan {count} tapak & buka tempahan | Sahkan {count} tapak & buka tempahan"),
    "vendorBookingSitesConfigured": ("Vendor booking sites: {n} selected", "Tapak tempahan peniaga: {n} dipilih"),
    "vendorBookingOpenMessage": (
        "Vendors can now book from {n} selected site | Vendors can now book from {n} selected sites",
        "Peniaga kini boleh menempah daripada {n} tapak yang dipilih | Peniaga kini boleh menempah daripada {n} tapak yang dipilih",
    ),
    "loadingLayoutFor": ("Loading layout for {name}…", "Memuatkan susun atur untuk {name}…"),
    "physicalSitesSummary": ("{physical} physical · {active} open", "{physical} fizikal · {active} dibuka"),
    "physicalSitesSummaryWithLimit": ("{physical} physical · {active} open / {limit} allowed", "{physical} fizikal · {active} dibuka / {limit} dibenarkan"),
    "generateSitesAction": ("Generate {count} sites", "Jana {count} tapak"),
    "confirmReorderSites": (
        "Reorder sites in {label} using the current reverse order?\nYou can reorder again afterward.",
        "Susun semula tapak dalam {label} menggunakan susunan songsang semasa?\nAnda boleh menyusun semula lagi selepas itu.",
    ),
    "confirmGenerateSites": (
        "Generate {count} sites for {label}?\nExisting sites will not be deleted or replaced.",
        "Jana {count} tapak untuk {label}?\nTapak sedia ada tidak akan dipadam atau diganti.",
    ),
}

# Extract string literals with a simple state machine
strings = {}
i = 0
lines = body.split("\n")
buf_key = None
buf_val = []
for line in lines:
    # function skip
    if re.match(r"\s+[A-Za-z0-9_]+\s*:\s*\(", line):
        buf_key = None
        buf_val = []
        continue
    m = re.match(r"\s+([A-Za-z0-9_]+):\s*'(.*)',?\s*$", line)
    if m and "\\" not in m.group(2) or (m and True):
        # check if starts a multiline without closing on same... already single line if ends with '
        pass
    m = re.match(r"\s+([A-Za-z0-9_]+):\s*'(.*)',\s*$", line)
    if m:
        key, val = m.group(1), m.group(2)
        if key in fn_props:
            continue
        strings[key] = val.replace("\\'", "'").replace("\\n", "\n")
        continue
    m = re.match(r"\s+([A-Za-z0-9_]+):\s*'(.*)$", line)
    if m and not line.rstrip().endswith("',") and not line.rstrip().endswith("'"):
        buf_key = m.group(1)
        buf_val = [m.group(2)]
        continue
    if buf_key is not None:
        mm = re.match(r"\s+'(.*)',?\s*$", line)
        if mm:
            buf_val.append(mm.group(1))
            if line.rstrip().endswith("',") or line.rstrip().endswith("'"):
                # last piece may include trailing
                last = buf_val[-1]
                if last.endswith("',"):
                    last = last[:-2]
                elif last.endswith("'"):
                    last = last[:-1]
                buf_val[-1] = last
                strings[buf_key] = "".join(buf_val).replace("\\'", "'")
                buf_key = None
                buf_val = []
            continue

print(f"Parsed {len(strings)} string keys")

# Malay translations for LAYOUT_COPY strings (manual map with fallbacks)
MS_MAP = {
    "pageTitle": "Susun atur tapak",
    "navLabel": "Pengurusan susun atur",
    "manageLayoutAction": "Pengurusan susun atur",
    "manageParkingLayout": "Urus susun atur parkir",
    "manageLayout": "Urus susun atur",
    "manageLayoutExit": "Selesai mengurus",
    "manageLayoutActive": "Mod pengurusan",
    "manageLayoutHint": "Pilih jubin tapak untuk membuka tindakan. Gunakan menu setiap baris untuk tindakan baris.",
    "editLayoutStructure": "Sunting struktur susun atur",
    "editLayoutStructureHelp": "Sunting baris, alih tapak dan urus susun atur fizikal.",
    "chooseBookingSitesMenuHelp": "Pilih tapak fizikal yang boleh ditempah peniaga.",
    "recommendedBadge": "Disyorkan",
    "advancedBadge": "Lanjutan",
    "rowMenuLabel": "Tindakan baris",
    "moveUpDisabled": "Sudah baris pertama.",
    "moveDownDisabled": "Sudah baris terakhir.",
    "generateSitesComplete": "Baris ini sudah mempunyai set tapak yang lengkap.",
    "reorderSitesDisabled": "Sekurang-kurangnya dua tapak diperlukan untuk menyusun semula.",
    "selectEvent": "Pilih acara",
    "refresh": "Muat semula susun atur",
    "backToEvents": "Kembali ke acara",
    "addRow": "Tambah baris",
    "editRow": "Sunting baris",
    "saveOrder": "Simpan susunan",
    "moveUp": "Naik",
    "moveDown": "Turun",
    "deleteRow": "Padam baris",
    "archiveRow": "Arkibkan baris",
    "unarchiveRow": "Nyaharkib baris",
    "addSite": "Tambah tapak",
    "generateSites": "Jana tapak",
    "editSite": "Sunting tapak",
    "moveSite": "Alih tapak",
    "disableSite": "Lumpuhkan",
    "enableSite": "Dayakan",
    "deleteSite": "Padam tapak",
    "restoreMissingSites": "Pulihkan tapak yang hilang",
    "restoreAllMissingSites": "Pulihkan semua tapak yang hilang",
    "restoringAllMissingSites": "Memulihkan tapak yang hilang…",
    "canonicalSiteDeleteForbidden": "Tapak parkir fizikal tidak boleh dipadam. Tetapkan tapak kepada BELUM DIBUKA atau Tidak tersedia.",
    "save": "Simpan",
    "cancel": "Batal",
    "tryAgain": "Cuba lagi",
    "locked": "Dikunci",
    "advanced": "Tetapan lanjutan",
    "unresolvedTitle": "Tapak belum ditugaskan",
    "emptyTitle": "Belum ada susun atur parkir dicipta.",
    "emptyBody": "Jana susun atur parkir standard 4×16, atau tambah baris fizikal yang belum digunakan. Kemudian pilih tapak tempahan daripada Urus susun atur parkir.",
    "generateStandardLayout": "Jana susun atur parkir standard",
    "generateStandardLayoutHelp": "Mencipta baris fizikal A–D dengan 64 tapak. Tapak bermula sebagai BELUM DIBUKA sehingga anda memilih yang boleh ditempah peniaga.",
    "generateStandardPreview": "Pratonton: A01–A16, B01–B16, C01–C16, D01–D16",
    "generateStandardConfirm": "Venue ini mempunyai 64 tapak parkir fizikal. Selepas janaan anda akan memilih tapak mana yang dibuka untuk tempahan peniaga.",
    "generateStandardLimitRequired": "Anda akan memilih tapak tempahan pada susun atur selepas janaan.",
    "standardLayoutGenerated": "Susun atur parkir standard dijana. Pilih tapak yang boleh ditempah peniaga.",
    "deleteParkingLayout": "Padam susun atur parkir",
    "confirmDeleteParkingLayout": "Padam seluruh susun atur parkir untuk acara ini? Semua baris dan tapak parkir akan dibuang. Tindakan ini tidak boleh dibuat asal.",
    "parkingLayoutDeleted": "Susun atur parkir dipadam.",
    "selectOpenSitesTitle": "Pilih tapak tempahan",
    "selectOpenSitesHelp": "Pilih tapak fizikal yang boleh ditempah peniaga. Sistem mengira pemilihan anda dan membuka tempahan apabila kesediaan susun atur lulus.",
    "selectOpenSitesMinimum": "Pilih sekurang-kurangnya satu tapak untuk tempahan peniaga.",
    "openSitesConfirmed": "Tapak tempahan berjaya disahkan.",
    "startSelectOpenSites": "Pilih tapak tempahan",
    "selectionModeBadge": "MOD PEMILIHAN",
    "selectAllSites": "Pilih semua",
    "clearAllSites": "Kosongkan semua",
    "selectRowSites": "Pilih baris",
    "clearRowSites": "Kosongkan baris",
    "protectedSiteHint": "Tapak ini dilindungi oleh tempahan atau tempahan barangan sedia ada.",
    "manageBookingSites": "Urus tapak tempahan",
    "vendorBookingSitesNotConfigured": "Tapak tempahan peniaga: Belum dikonfigurasi",
    "vendorBookingSetupRequired": "Persediaan tempahan peniaga diperlukan",
    "vendorBookingSetupMessage": "Pilih tapak fizikal yang boleh ditempah peniaga daripada Urus susun atur parkir di bawah.",
    "vendorBookingSelectingMessage": "Pilih tapak yang ingin anda tawarkan kepada peniaga.",
    "vendorBookingOpen": "Tempahan peniaga dibuka",
    "layoutExistsHint": "Susun atur sudah wujud. Gunakan kawalan tapak untuk mengemas kini tapak individu. Templat standard hanya boleh dijalankan pada susun atur kosong.",
    "missingEventDaysWarning": "Susun atur fizikal sudah sedia, tetapi hari acara mesti dikonfigurasi sebelum peniaga boleh menempah.",
    "setupNoticeTitle": "Kesediaan susun atur",
    "technicalDetails": "Butiran teknikal",
    "focusedSiteTitle": "Pengurusan tapak",
    "focusedRowTitle": "Tetapan baris",
    "rowActionsTitle": "Tindakan baris",
    "noSiteSelected": "Pilih tapak pada susun atur parkir untuk mengurusnya.",
    "closeSitePanel": "Tutup",
    "setActive": "Tetapkan aktif / dibuka",
    "setUnavailable": "Tetapkan tidak tersedia",
    "setDisabled": "Tetapkan BELUM DIBUKA",
    "updatingStatus": "Mengemas kini…",
    "siteCountsTitle": "Kiraan tapak",
    "advancedRowsTitle": "Alat baris lanjutan",
    "loadError": "Tidak dapat memuatkan susun atur.",
    "allPhysicalRowsInUse": "Semua baris fizikal untuk venue ini sudah digunakan.",
    "outsideVenueTemplateBadge": "Luar definisi venue",
    "outsideVenueTemplateHelp": "Baris ini di luar definisi fizikal venue semasa (A–D). Ia tidak dipadam secara automatik.",
    "vendorSitesToOpen": "Tapak tempahan peniaga",
    "vendorSitesToOpenHelp": "Dikonfigurasi dengan memilih tapak tempahan pada susun atur parkir.",
    "conflictRefreshHint": "Muat semula susun atur",
    "operationalReady": "Tempahan peniaga dibuka",
    "operationalNotReady": "Persediaan tempahan peniaga diperlukan",
    "publicReady": "Sedia untuk paparan awam",
    "publicNotReady": "Belum sedia untuk paparan awam",
    "rowCreated": "Baris ditambah dengan 16 tapak BELUM DIBUKA.",
    "rowUpdated": "Baris berjaya dikemas kini.",
    "rowDeleted": "Baris berjaya dipadam.",
    "rowArchived": "Baris berjaya diarkibkan.",
    "rowUnarchived": "Baris berjaya dinyaharkib.",
    "rowsReordered": "Susunan baris berjaya disimpan.",
    "siteCreated": "Tapak berjaya ditambah.",
    "sitesGenerated": "Tapak berjaya dijana.",
    "siteUpdated": "Tapak berjaya dikemas kini.",
    "sitesReordered": "Susunan tapak berjaya disimpan.",
    "siteDeleted": "Tapak berjaya dipadam.",
    "fallbackError": "Tindakan tidak dapat diselesaikan. Muat semula susun atur dan cuba lagi.",
    "renameLockedHint": "Nama baris ini tidak boleh ditukar kerana tapak dalam baris mempunyai sejarah tempahan.",
    "categoryLockedHint": "Kategori baris ini tidak boleh ditukar kerana tapak dalam baris mempunyai sejarah tempahan.",
    "structureLockedHint": "Struktur tapak ini dikunci kerana mempunyai sejarah tempahan.",
    "disableLockedHint": "Tapak ini tidak boleh ditutup semasa mempunyai tempahan aktif.",
    "archiveBlockedHint": "Baris ini tidak boleh diarkibkan semasa masih mempunyai tempahan aktif.",
    "unarchiveHint": "Baris akan diaktifkan semula, tetapi tapak di dalamnya tidak akan didayakan secara automatik.",
    "generateAtomicHint": "Semua tapak diminta dicipta dalam satu langkah, atau tiada yang dicipta. Tapak sedia ada tidak dipadam atau diganti.",
    "availabilityStatus": "Kesediaan susun atur",
    "availabilityStatusHelp": "Kesediaan tempahan dan keterlihatan awam dinilai secara berasingan.",
    "publicationTitle": "Penerbitan peta awam",
    "publicationHelp": "Peta pengunjung hanya boleh diterbitkan apabila kesediaan awam lengkap.",
    "published": "Diterbitkan",
    "notPublished": "Belum diterbitkan",
    "publishPublicMap": "Terbitkan peta awam",
    "unpublishPublicMap": "Nyahterbit peta awam",
    "entranceNoteLabel": "Panduan pintu masuk awam (pilihan)",
    "selectEventPrompt": "Pilih acara untuk mengurus susun aturnya",
    "selectEventOption": "— Pilih acara —",
    "loadingLayout": "Memuatkan susun atur…",
    "noSpace": "Tiada jenis ruang",
    "noCategory": "Tiada kategori",
    "physicalRowLabel": "Baris fizikal",
    "selectPhysicalRow": "Pilih baris fizikal yang belum digunakan",
    "siteGrid": "Grid tapak",
    "reorderSites": "Susun semula tapak",
    "noSitesInRow": "Belum ada tapak dalam baris ini.",
    "noReadinessBlockers": "Tiada halangan kesediaan dilaporkan buat masa ini.",
    "selectCategory": "Pilih kategori",
    "selectSpaceType": "Pilih jenis ruang",
    "rowCategoryA": "Kategori baris A",
    "rowCategoryB": "Kategori baris B",
    "rowCategoryC": "Kategori baris C",
    "rowCategoryD": "Kategori baris D",
    "targetRow": "Baris sasaran",
    "displayOrder": "Susunan paparan",
    "rowLabelPrefix": "Baris",
    "noPreview": "Tiada pratonton",
    "generating": "Menjana…",
    "available": "Tersedia",
    "reserved": "Ditempah",
    "confirmed": "Disahkan",
    "lockedLegend": "Dikunci",
    "delete": "Padam",
    "deleteLocked": "Padam dikunci",
    "renameLocked": "Namakan semula dikunci",
    "categoryLocked": "Kategori dikunci",
    "archiveLocked": "Arkib dikunci",
    "rowStillHasSites": "Baris ini masih mempunyai tapak.",
    "unresolvedHelp": "Tapak ini tidak dipautkan kepada baris susun atur. Pemetaan automatik tidak dilakukan.",
    "publicPublishedToast": "Susun atur awam diterbitkan.",
    "publicUnpublishedToast": "Susun atur awam dinyahterbit.",
    "confirmUnpublish": "Nyahterbit peta awam ini? Pengunjung tidak lagi dapat melihatnya.",
    "confirmDeleteRow": "Padam baris ini?\n\nBaris kosong ini akan dibuang secara kekal.",
    "confirmArchiveRow": "Arkibkan baris ini?\n\nBaris akan dinyahaktifkan dan disembunyikan daripada paparan awam. Tapak aktif dalam baris juga akan dilumpuhkan. Sejarah tempahan dikekalkan.",
    "confirmDeleteSite": "Padam tapak ini?\n\nTapak hanya boleh dipadam jika ia tidak pernah mempunyai sejarah tempahan.",
}

missing_ms = [k for k in strings if k not in MS_MAP]
if missing_ms:
    print("Missing MS for:", missing_ms)

# Build JS object fragments
def js_escape(s):
    return s.replace("\\", "\\\\").replace("'", "\\'").replace("\n", "\\n")

en_lines = ["    layout: {"]
ms_lines = ["    layout: {"]
for k, v in strings.items():
    en_lines.append(f"      {k}: '{js_escape(v)}',")
    ms_lines.append(f"      {k}: '{js_escape(MS_MAP.get(k, v))}',")

# function-backed keys
en_lines.append("      physicalSitesOfTotal: '{present} of {total} physical sites',")
ms_lines.append("      physicalSitesOfTotal: '{present} daripada {total} tapak fizikal',")
en_lines.append("      sitesCountFallback: 'No sites | {count} site | {count} sites',")
ms_lines.append("      sitesCountFallback: 'Tiada tapak | {count} tapak | {count} tapak',")
en_lines.append("      restoreSite: 'Restore {label}',")
ms_lines.append("      restoreSite: 'Pulihkan {label}',")
en_lines.append("      restoringSite: 'Restoring {label}…',")
ms_lines.append("      restoringSite: 'Memulihkan {label}…',")
en_lines.append("      siteRestoredNotOpen: '{label} restored as NOT OPEN.',")
ms_lines.append("      siteRestoredNotOpen: '{label} dipulihkan sebagai BELUM DIBUKA.',")
en_lines.append("      sitesRestoredNotOpen: 'No physical sites restored as NOT OPEN. | {count} physical site restored as NOT OPEN. | {count} physical sites restored as NOT OPEN.',")
ms_lines.append("      sitesRestoredNotOpen: 'Tiada tapak fizikal dipulihkan sebagai BELUM DIBUKA. | {count} tapak fizikal dipulihkan sebagai BELUM DIBUKA. | {count} tapak fizikal dipulihkan sebagai BELUM DIBUKA.',")
en_lines.append("      generateStandardOpenCountUnset: 'Vendor booking sites: Not configured yet. You will choose them after generation.',")
ms_lines.append("      generateStandardOpenCountUnset: 'Tapak tempahan peniaga: Belum dikonfigurasi. Anda akan memilihnya selepas janaan.',")
en_lines.append("      generateStandardOpenCountSet: 'Vendor booking sites currently configured: {n}',")
ms_lines.append("      generateStandardOpenCountSet: 'Tapak tempahan peniaga yang dikonfigurasi: {n}',")
en_lines.append("      selectOpenSitesCount: 'No sites selected | {selected} site selected | {selected} sites selected',")
ms_lines.append("      selectOpenSitesCount: 'Tiada tapak dipilih | {selected} tapak dipilih | {selected} tapak dipilih',")
en_lines.append("      confirmOpenSites: 'Confirm {count} Site & Open Booking | Confirm {count} Sites & Open Booking',")
ms_lines.append("      confirmOpenSites: 'Sahkan {count} tapak & buka tempahan | Sahkan {count} tapak & buka tempahan',")
en_lines.append("      vendorBookingSitesConfigured: 'Vendor booking sites: {n} selected',")
ms_lines.append("      vendorBookingSitesConfigured: 'Tapak tempahan peniaga: {n} dipilih',")
en_lines.append("      vendorBookingOpenMessage: 'Vendors can now book from {n} selected site | Vendors can now book from {n} selected sites',")
ms_lines.append("      vendorBookingOpenMessage: 'Peniaga kini boleh menempah daripada {n} tapak yang dipilih | Peniaga kini boleh menempah daripada {n} tapak yang dipilih',")
en_lines.append("      loadingLayoutFor: 'Loading layout for {name}…',")
ms_lines.append("      loadingLayoutFor: 'Memuatkan susun atur untuk {name}…',")
en_lines.append("      physicalSitesSummary: '{physical} physical · {active} open',")
ms_lines.append("      physicalSitesSummary: '{physical} fizikal · {active} dibuka',")
en_lines.append("      physicalSitesSummaryWithLimit: '{physical} physical · {active} open / {limit} allowed',")
ms_lines.append("      physicalSitesSummaryWithLimit: '{physical} fizikal · {active} dibuka / {limit} dibenarkan',")
en_lines.append("      generateSitesAction: 'Generate {count} sites',")
ms_lines.append("      generateSitesAction: 'Jana {count} tapak',")
en_lines.append("      confirmReorderSites: 'Reorder sites in {label} using the current reverse order?\\nYou can reorder again afterward.',")
ms_lines.append("      confirmReorderSites: 'Susun semula tapak dalam {label} menggunakan susunan songsang semasa?\\nAnda boleh menyusun semula lagi selepas itu.',")
en_lines.append("      confirmGenerateSites: 'Generate {count} sites for {label}?\\nExisting sites will not be deleted or replaced.',")
ms_lines.append("      confirmGenerateSites: 'Jana {count} tapak untuk {label}?\\nTapak sedia ada tidak akan dipadam atau diganti.',")

# readiness + errors - keep codes as keys under organizer.layout.readiness / errors
# Parse READINESS and LAYOUT_ERROR
ready_m = re.search(r"export const READINESS_BLOCKER_MESSAGES = \{([\s\S]*?)\n\};", text)
err_m = re.search(r"export const LAYOUT_ERROR_MESSAGES = \{([\s\S]*?)\n\};", text)

def parse_code_map(block):
    out = {}
    for m in re.finditer(r"([A-Z0-9_]+):\s*'((?:\\'|[^'])*)'", block):
        out[m.group(1)] = m.group(2).replace("\\'", "'")
    return out

readiness = parse_code_map(ready_m.group(1))
errors = parse_code_map(err_m.group(1))
print(f"readiness {len(readiness)} errors {len(errors)}")

# BM for readiness/errors - use approximate natural BM
def rough_ms(en):
    # For coded messages we'll provide reasonable BM in a dict; fallback keep EN marked
    return en  # filled below

READY_MS = {
    "VENDOR_SITE_OPEN_LIMIT_NOT_SET": "Pilih tapak fizikal yang boleh ditempah peniaga.",
    "ACTIVE_SITE_COUNT_BELOW_VENDOR_LIMIT": "Pilih semula tapak tempahan. Kiraan tapak dibuka tidak lagi sepadan dengan pemilihan yang dikonfigurasi.",
    "ACTIVE_SITE_COUNT_EXCEEDS_VENDOR_LIMIT": "Pilih semula tapak tempahan. Kiraan tapak dibuka tidak lagi sepadan dengan pemilihan yang dikonfigurasi.",
    "ROW_OUTSIDE_VENUE_TEMPLATE": "Satu atau lebih baris di luar definisi fizikal venue ini (A–D). Selesaikan atau arkibkannya.",
    "NO_ACTIVE_EVENT_DAYS": "Tiada hari acara aktif dikonfigurasi.",
    "NO_ACTIVE_LAYOUT_ROWS": "Tiada baris susun atur aktif dikonfigurasi.",
    "ACTIVE_ROW_MISSING_CATEGORY": "Satu atau lebih baris tidak mempunyai kategori.",
    "ROW_CATEGORY_INACTIVE": "Satu atau lebih baris menggunakan kategori yang tidak lagi aktif.",
    "ACTIVE_ROW_HAS_NO_ACTIVE_SITES": "Satu atau lebih baris aktif tidak mempunyai tapak fizikal.",
    "ACTIVE_SITE_MISSING_ROW": "Tapak aktif tidak dipautkan kepada baris.",
    "SITE_EVENT_ROW_MISMATCH": "Tapak tidak sepadan dengan acara barisnya.",
    "ACTIVE_SITE_MISSING_SPACE": "Tapak aktif tiada jenis ruang yang sah.",
    "ACTIVE_SITE_INVALID_LABEL": "Tapak aktif mempunyai label tidak sah.",
    "UNRESOLVED_ACTIVE_SITES": "Tapak legasi masih perlu ditugaskan kepada baris.",
    "DUPLICATE_ACTIVE_SITE_IDENTITY": "Tapak aktif berkongsi identiti pendua.",
    "NO_PUBLIC_ROWS": "Tiada baris dikonfigurasi untuk paparan awam.",
    "PUBLIC_ROW_CATEGORY_NOT_PUBLIC": "Kategori baris awam tidak dibenarkan untuk paparan awam.",
    "PUBLIC_ROW_HAS_NO_VISIBLE_SITES": "Baris awam tiada tapak yang kelihatan.",
    "EMPTY_PUBLIC_LAYOUT": "Susun atur awam masih kosong.",
    "INVALID_PUBLIC_ROW_ORDER": "Susunan baris awam tidak sah.",
}

ERR_MS = {
    "LAYOUT_ALREADY_EXISTS": "Susun atur sudah wujud untuk acara ini. Templat parkir standard hanya boleh dijana pada susun atur kosong.",
    "PUBLIC_LAYOUT_PUBLISHED": "Nyahterbit susun atur awam sebelum mengubah susun atur parkir.",
    "ALLOCATION_HISTORY_PRESENT": "Acara ini mempunyai sejarah peruntukan tempahan dan tidak boleh menerima perubahan susun atur yang merosakkan.",
    "EVENT_HAS_BOOKINGS": "Susun atur parkir ini tidak boleh dipadam kerana acara sudah mempunyai sejarah tempahan.",
    "LAYOUT_NOT_FOUND": "Tiada susun atur parkir untuk acara ini.",
    "INVALID_STANDARD_TEMPLATE": "Permintaan templat parkir standard tidak sah.",
    "INVALID_SPACE": "Jenis ruang yang dipilih tidak sah.",
    "VENDOR_SITE_OPEN_LIMIT_NOT_SET": "Pilih tapak tempahan sebelum meneruskan.",
    "VENDOR_SITE_OPEN_LIMIT_BELOW_PROTECTED": "Tidak boleh mengurangkan tapak tempahan di bawah tapak yang dilindungi (ditempah atau dibook).",
    "OPEN_SITE_SELECTION_COUNT_MISMATCH": "Susun atur berubah semasa mengesahkan tapak tempahan. Muat semula dan cuba lagi.",
    "ACTIVE_ALLOCATIONS_PRESENT": "Tindakan ini tidak dibenarkan semasa tempahan atau peruntukan aktif wujud.",
    "INVALID_SITE": "Satu atau lebih tapak yang dipilih tidak sah untuk acara ini.",
    "NO_PHYSICAL_SITES": "Tiada tapak fizikal untuk acara ini.",
    "CANONICAL_SITE_DELETE_FORBIDDEN": "Tapak parkir fizikal tidak boleh dipadam. Tetapkan kepada BELUM DIBUKA atau Tidak tersedia.",
    "CANONICAL_SITE_ALREADY_EXISTS": "Tapak fizikal itu sudah wujud. Muat semula susun atur dan cuba lagi.",
    "CANONICAL_SITE_POSITION_CONFLICT": "Kedudukan parkir itu sudah diduduki. Muat semula susun atur dan cuba lagi.",
    "CANONICAL_ROW_COMPLETE": "Baris ini sudah mempunyai semua 16 tapak fizikal.",
    "VENUE_TEMPLATE_ROWS_EXHAUSTED": "Semua baris fizikal untuk venue ini sudah digunakan.",
    "ROW_OUTSIDE_VENUE_TEMPLATE": "Identiti baris fizikal mestilah A, B, C, atau D untuk venue ini.",
    "ACTIVE_SITE_COUNT_EXCEEDS_VENDOR_LIMIT": "Membuka tapak ini akan melebihi tapak peniaga untuk dibuka.",
    "ROW_LABEL_LOCKED": "Nama baris ini tidak boleh ditukar kerana mempunyai sejarah tempahan.",
    "ROW_CATEGORY_LOCKED": "Kategori baris ini tidak boleh ditukar kerana mempunyai sejarah tempahan.",
    "ROW_NOT_EMPTY": "Baris ini masih mempunyai tapak dan tidak boleh dipadam.",
    "SITE_STRUCTURE_LOCKED": "Struktur tapak ini dikunci kerana mempunyai sejarah tempahan.",
    "SITE_HAS_ALLOCATION_HISTORY": "Tapak ini mempunyai sejarah tempahan dan tidak boleh dipadam.",
    "SITE_LABEL_CONFLICT": "Label tapak ini sudah digunakan.",
    "SITE_POSITION_CONFLICT": "Kedudukan tapak ini bertindih dengan tapak lain.",
    "ROW_LABEL_CONFLICT": "Nama baris ini sudah digunakan untuk acara yang sama.",
    "LAYOUT_GENERATION_CONFLICT": "Tapak tidak dapat dijana kerana konflik susun atur.",
    "CATEGORY_INACTIVE": "Kategori ini tidak aktif dan tidak boleh digunakan.",
    "INVALID_LAYOUT_ROW": "Baris susun atur tidak sah.",
    "INVALID_VENDOR_CATEGORY": "Kategori peniaga tidak sah.",
    "INVALID_SITE_COUNT": "Kiraan tapak tidak sah.",
    "INVALID_SITE_LABEL": "Label tapak tidak sah.",
    "INVALID_DISPLAY_ORDER": "Susunan paparan tidak sah.",
    "INVALID_SITE_STATUS": "Status tapak tidak sah.",
    "ACTIVE_ROW_HAS_NO_ACTIVE_SITES": "Baris aktif tidak boleh disimpan tanpa tapak fizikal.",
}

en_lines.append("      readiness: {")
ms_lines.append("      readiness: {")
for k, v in readiness.items():
    en_lines.append(f"        {k}: '{js_escape(v)}',")
    ms_lines.append(f"        {k}: '{js_escape(READY_MS.get(k, v))}',")
en_lines.append("      },")
ms_lines.append("      },")
en_lines.append("      errors: {")
ms_lines.append("      errors: {")
for k, v in errors.items():
    en_lines.append(f"        {k}: '{js_escape(v)}',")
    ms_lines.append(f"        {k}: '{js_escape(ERR_MS.get(k, v))}',")
en_lines.append("      },")
ms_lines.append("      },")
en_lines.append("      occupancy: {")
ms_lines.append("      occupancy: {")
en_lines.append("        available: 'Available',")
ms_lines.append("        available: 'Tersedia',")
en_lines.append("        reserved: 'Booked',")
ms_lines.append("        reserved: 'Ditempah',")
en_lines.append("        confirmed: 'Confirmed',")
ms_lines.append("        confirmed: 'Disahkan',")
en_lines.append("        'released-history': 'Released history',")
ms_lines.append("        'released-history': 'Sejarah dilepaskan',")
en_lines.append("      },")
ms_lines.append("      },")
en_lines.append("      siteStatus: {")
ms_lines.append("      siteStatus: {")
en_lines.append("        active: 'Open',")
ms_lines.append("        active: 'Dibuka',")
en_lines.append("        unavailable: 'Unavailable',")
ms_lines.append("        unavailable: 'Tidak tersedia',")
en_lines.append("        disabled: 'NOT OPEN',")
ms_lines.append("        disabled: 'BELUM DIBUKA',")
en_lines.append("      },")
ms_lines.append("      },")

en_lines.append("    },")
ms_lines.append("    },")

fragment_en = "\n".join(en_lines) + "\n"
fragment_ms = "\n".join(ms_lines) + "\n"

# Insert layout as first child of top-level organizer: { ... }
for path, frag in [(EN, fragment_en), (MS, fragment_ms)]:
    src = path.read_text(encoding="utf-8")
    marker = "\n  organizer: {\n"
    if "organizer: {\n    layout: {" in src or "\n    layout: {\n      pageTitle:" in src:
        # already has layout under organizer?
        if "organizer.layout" in src or "pageTitle: 'Site Layout'" in src or "pageTitle: 'Susun atur tapak'" in src:
            print(path.name, "already has organizer.layout — skip insert")
            continue
    idx = src.find(marker)
    if idx < 0:
        raise SystemExit(f"no top-level organizer in {path}")
    insert_at = idx + len(marker)
    src = src[:insert_at] + frag + src[insert_at:]
    path.write_text(src, encoding="utf-8")
    print("inserted layout into", path.name)

# Do not rewrite util if already converted
if "getLayoutCopy" in MSG.read_text(encoding="utf-8"):
    print("organizerEventLayoutMessages.js already uses getLayoutCopy — skip util rewrite")
else:
    # Write new messages util
    new_util = r'''/**
 * Organizer event-layout operational copy (vue-i18n).
 * Proxy re-reads tt() on each access so locale switches update labels without remounting.
 */

import { tt } from '../i18n';

function buildLayoutCopy(t = tt) {
  return {
    pageTitle: t('organizer.layout.pageTitle'),
    navLabel: t('organizer.layout.navLabel'),
    manageLayoutAction: t('organizer.layout.manageLayoutAction'),
    manageParkingLayout: t('organizer.layout.manageParkingLayout'),
    manageLayout: t('organizer.layout.manageLayout'),
    manageLayoutExit: t('organizer.layout.manageLayoutExit'),
    manageLayoutActive: t('organizer.layout.manageLayoutActive'),
    manageLayoutHint: t('organizer.layout.manageLayoutHint'),
    editLayoutStructure: t('organizer.layout.editLayoutStructure'),
    editLayoutStructureHelp: t('organizer.layout.editLayoutStructureHelp'),
    chooseBookingSitesMenuHelp: t('organizer.layout.chooseBookingSitesMenuHelp'),
    recommendedBadge: t('organizer.layout.recommendedBadge'),
    advancedBadge: t('organizer.layout.advancedBadge'),
    rowMenuLabel: t('organizer.layout.rowMenuLabel'),
    moveUpDisabled: t('organizer.layout.moveUpDisabled'),
    moveDownDisabled: t('organizer.layout.moveDownDisabled'),
    generateSitesComplete: t('organizer.layout.generateSitesComplete'),
    reorderSitesDisabled: t('organizer.layout.reorderSitesDisabled'),
    selectEvent: t('organizer.layout.selectEvent'),
    refresh: t('organizer.layout.refresh'),
    backToEvents: t('organizer.layout.backToEvents'),
    addRow: t('organizer.layout.addRow'),
    editRow: t('organizer.layout.editRow'),
    saveOrder: t('organizer.layout.saveOrder'),
    moveUp: t('organizer.layout.moveUp'),
    moveDown: t('organizer.layout.moveDown'),
    deleteRow: t('organizer.layout.deleteRow'),
    archiveRow: t('organizer.layout.archiveRow'),
    unarchiveRow: t('organizer.layout.unarchiveRow'),
    addSite: t('organizer.layout.addSite'),
    generateSites: t('organizer.layout.generateSites'),
    editSite: t('organizer.layout.editSite'),
    moveSite: t('organizer.layout.moveSite'),
    disableSite: t('organizer.layout.disableSite'),
    enableSite: t('organizer.layout.enableSite'),
    deleteSite: t('organizer.layout.deleteSite'),
    physicalSitesOfTotal: (present, total) =>
      t('organizer.layout.physicalSitesOfTotal', { present, total }),
    sitesCountFallback: (count) => t('organizer.layout.sitesCountFallback', { count }),
    restoreSite: (label) => t('organizer.layout.restoreSite', { label }),
    restoreMissingSites: t('organizer.layout.restoreMissingSites'),
    restoreAllMissingSites: t('organizer.layout.restoreAllMissingSites'),
    restoringSite: (label) => t('organizer.layout.restoringSite', { label }),
    restoringAllMissingSites: t('organizer.layout.restoringAllMissingSites'),
    siteRestoredNotOpen: (label) => t('organizer.layout.siteRestoredNotOpen', { label }),
    sitesRestoredNotOpen: (count) => t('organizer.layout.sitesRestoredNotOpen', { count }),
    canonicalSiteDeleteForbidden: t('organizer.layout.canonicalSiteDeleteForbidden'),
    save: t('organizer.layout.save'),
    cancel: t('organizer.layout.cancel'),
    tryAgain: t('organizer.layout.tryAgain'),
    locked: t('organizer.layout.locked'),
    advanced: t('organizer.layout.advanced'),
    unresolvedTitle: t('organizer.layout.unresolvedTitle'),
    emptyTitle: t('organizer.layout.emptyTitle'),
    emptyBody: t('organizer.layout.emptyBody'),
    generateStandardLayout: t('organizer.layout.generateStandardLayout'),
    generateStandardLayoutHelp: t('organizer.layout.generateStandardLayoutHelp'),
    generateStandardPreview: t('organizer.layout.generateStandardPreview'),
    generateStandardConfirm: t('organizer.layout.generateStandardConfirm'),
    generateStandardOpenCount: (n) =>
      (n == null
        ? t('organizer.layout.generateStandardOpenCountUnset')
        : t('organizer.layout.generateStandardOpenCountSet', { n })),
    generateStandardLimitRequired: t('organizer.layout.generateStandardLimitRequired'),
    standardLayoutGenerated: t('organizer.layout.standardLayoutGenerated'),
    deleteParkingLayout: t('organizer.layout.deleteParkingLayout'),
    confirmDeleteParkingLayout: t('organizer.layout.confirmDeleteParkingLayout'),
    parkingLayoutDeleted: t('organizer.layout.parkingLayoutDeleted'),
    selectOpenSitesTitle: t('organizer.layout.selectOpenSitesTitle'),
    selectOpenSitesHelp: t('organizer.layout.selectOpenSitesHelp'),
    selectOpenSitesCount: (selected) =>
      t('organizer.layout.selectOpenSitesCount', { selected, n: selected, count: selected }),
    selectOpenSitesMinimum: t('organizer.layout.selectOpenSitesMinimum'),
    confirmOpenSites: (count) => t('organizer.layout.confirmOpenSites', { count, n: count }),
    openSitesConfirmed: t('organizer.layout.openSitesConfirmed'),
    startSelectOpenSites: t('organizer.layout.startSelectOpenSites'),
    selectionModeBadge: t('organizer.layout.selectionModeBadge'),
    selectAllSites: t('organizer.layout.selectAllSites'),
    clearAllSites: t('organizer.layout.clearAllSites'),
    selectRowSites: t('organizer.layout.selectRowSites'),
    clearRowSites: t('organizer.layout.clearRowSites'),
    protectedSiteHint: t('organizer.layout.protectedSiteHint'),
    manageBookingSites: t('organizer.layout.manageBookingSites'),
    vendorBookingSitesConfigured: (n) =>
      t('organizer.layout.vendorBookingSitesConfigured', { n }),
    vendorBookingSitesNotConfigured: t('organizer.layout.vendorBookingSitesNotConfigured'),
    vendorBookingSetupRequired: t('organizer.layout.vendorBookingSetupRequired'),
    vendorBookingSetupMessage: t('organizer.layout.vendorBookingSetupMessage'),
    vendorBookingSelectingMessage: t('organizer.layout.vendorBookingSelectingMessage'),
    vendorBookingOpen: t('organizer.layout.vendorBookingOpen'),
    vendorBookingOpenMessage: (n) => t('organizer.layout.vendorBookingOpenMessage', { n }),
    layoutExistsHint: t('organizer.layout.layoutExistsHint'),
    missingEventDaysWarning: t('organizer.layout.missingEventDaysWarning'),
    setupNoticeTitle: t('organizer.layout.setupNoticeTitle'),
    technicalDetails: t('organizer.layout.technicalDetails'),
    focusedSiteTitle: t('organizer.layout.focusedSiteTitle'),
    focusedRowTitle: t('organizer.layout.focusedRowTitle'),
    rowActionsTitle: t('organizer.layout.rowActionsTitle'),
    noSiteSelected: t('organizer.layout.noSiteSelected'),
    closeSitePanel: t('organizer.layout.closeSitePanel'),
    setActive: t('organizer.layout.setActive'),
    setUnavailable: t('organizer.layout.setUnavailable'),
    setDisabled: t('organizer.layout.setDisabled'),
    updatingStatus: t('organizer.layout.updatingStatus'),
    siteCountsTitle: t('organizer.layout.siteCountsTitle'),
    advancedRowsTitle: t('organizer.layout.advancedRowsTitle'),
    loadError: t('organizer.layout.loadError'),
    loadingLayoutFor: (name) => t('organizer.layout.loadingLayoutFor', { name }),
    allPhysicalRowsInUse: t('organizer.layout.allPhysicalRowsInUse'),
    outsideVenueTemplateBadge: t('organizer.layout.outsideVenueTemplateBadge'),
    outsideVenueTemplateHelp: t('organizer.layout.outsideVenueTemplateHelp'),
    vendorSitesToOpen: t('organizer.layout.vendorSitesToOpen'),
    vendorSitesToOpenHelp: t('organizer.layout.vendorSitesToOpenHelp'),
    physicalSitesSummary: (physical, active, limit) =>
      (limit != null
        ? t('organizer.layout.physicalSitesSummaryWithLimit', { physical, active, limit })
        : t('organizer.layout.physicalSitesSummary', { physical, active })),
    conflictRefreshHint: t('organizer.layout.conflictRefreshHint'),
    operationalReady: t('organizer.layout.operationalReady'),
    operationalNotReady: t('organizer.layout.operationalNotReady'),
    publicReady: t('organizer.layout.publicReady'),
    publicNotReady: t('organizer.layout.publicNotReady'),
    rowCreated: t('organizer.layout.rowCreated'),
    rowUpdated: t('organizer.layout.rowUpdated'),
    rowDeleted: t('organizer.layout.rowDeleted'),
    rowArchived: t('organizer.layout.rowArchived'),
    rowUnarchived: t('organizer.layout.rowUnarchived'),
    rowsReordered: t('organizer.layout.rowsReordered'),
    siteCreated: t('organizer.layout.siteCreated'),
    sitesGenerated: t('organizer.layout.sitesGenerated'),
    siteUpdated: t('organizer.layout.siteUpdated'),
    sitesReordered: t('organizer.layout.sitesReordered'),
    siteDeleted: t('organizer.layout.siteDeleted'),
    fallbackError: t('organizer.layout.fallbackError'),
    renameLockedHint: t('organizer.layout.renameLockedHint'),
    categoryLockedHint: t('organizer.layout.categoryLockedHint'),
    structureLockedHint: t('organizer.layout.structureLockedHint'),
    disableLockedHint: t('organizer.layout.disableLockedHint'),
    archiveBlockedHint: t('organizer.layout.archiveBlockedHint'),
    unarchiveHint: t('organizer.layout.unarchiveHint'),
    generateAtomicHint: t('organizer.layout.generateAtomicHint'),
    availabilityStatus: t('organizer.layout.availabilityStatus'),
    availabilityStatusHelp: t('organizer.layout.availabilityStatusHelp'),
    publicationTitle: t('organizer.layout.publicationTitle'),
    publicationHelp: t('organizer.layout.publicationHelp'),
    published: t('organizer.layout.published'),
    notPublished: t('organizer.layout.notPublished'),
    publishPublicMap: t('organizer.layout.publishPublicMap'),
    unpublishPublicMap: t('organizer.layout.unpublishPublicMap'),
    entranceNoteLabel: t('organizer.layout.entranceNoteLabel'),
    selectEventPrompt: t('organizer.layout.selectEventPrompt'),
    selectEventOption: t('organizer.layout.selectEventOption'),
    loadingLayout: t('organizer.layout.loadingLayout'),
    noSpace: t('organizer.layout.noSpace'),
    noCategory: t('organizer.layout.noCategory'),
    physicalRowLabel: t('organizer.layout.physicalRowLabel'),
    selectPhysicalRow: t('organizer.layout.selectPhysicalRow'),
    siteGrid: t('organizer.layout.siteGrid'),
    reorderSites: t('organizer.layout.reorderSites'),
    noSitesInRow: t('organizer.layout.noSitesInRow'),
    noReadinessBlockers: t('organizer.layout.noReadinessBlockers'),
    selectCategory: t('organizer.layout.selectCategory'),
    selectSpaceType: t('organizer.layout.selectSpaceType'),
    rowCategoryA: t('organizer.layout.rowCategoryA'),
    rowCategoryB: t('organizer.layout.rowCategoryB'),
    rowCategoryC: t('organizer.layout.rowCategoryC'),
    rowCategoryD: t('organizer.layout.rowCategoryD'),
    targetRow: t('organizer.layout.targetRow'),
    displayOrder: t('organizer.layout.displayOrder'),
    rowLabelPrefix: t('organizer.layout.rowLabelPrefix'),
    noPreview: t('organizer.layout.noPreview'),
    generateSitesAction: (count) => t('organizer.layout.generateSitesAction', { count }),
    generating: t('organizer.layout.generating'),
    available: t('organizer.layout.available'),
    reserved: t('organizer.layout.reserved'),
    confirmed: t('organizer.layout.confirmed'),
    lockedLegend: t('organizer.layout.lockedLegend'),
    delete: t('organizer.layout.delete'),
    deleteLocked: t('organizer.layout.deleteLocked'),
    renameLocked: t('organizer.layout.renameLocked'),
    categoryLocked: t('organizer.layout.categoryLocked'),
    archiveLocked: t('organizer.layout.archiveLocked'),
    rowStillHasSites: t('organizer.layout.rowStillHasSites'),
    unresolvedHelp: t('organizer.layout.unresolvedHelp'),
    publicPublishedToast: t('organizer.layout.publicPublishedToast'),
    publicUnpublishedToast: t('organizer.layout.publicUnpublishedToast'),
    confirmUnpublish: t('organizer.layout.confirmUnpublish'),
    confirmDeleteRow: t('organizer.layout.confirmDeleteRow'),
    confirmArchiveRow: t('organizer.layout.confirmArchiveRow'),
    confirmDeleteSite: t('organizer.layout.confirmDeleteSite'),
    confirmReorderSites: (label) => t('organizer.layout.confirmReorderSites', { label }),
    confirmGenerateSites: (count, label) =>
      t('organizer.layout.confirmGenerateSites', { count, label }),
  };
}

/** Prefer in components: `computed(() => getLayoutCopy(t))` with useI18n(). */
export function getLayoutCopy(t = tt) {
  return buildLayoutCopy(t);
}

/** Proxy re-reads tt() on each access (helpers / non-setup call sites). */
export const LAYOUT_COPY = new Proxy(
  {},
  {
    get(_target, prop) {
      if (typeof prop === 'symbol') return undefined;
      const copy = buildLayoutCopy();
      return copy[prop];
    },
  },
);

function translateCodedMap(prefix, code, t = tt) {
  if (!code) return code;
  const key = `${prefix}.${code}`;
  const translated = t(key);
  return translated === key ? code : translated;
}

export function readinessMessage(code, t = tt) {
  return translateCodedMap('organizer.layout.readiness', code, t);
}

export function layoutErrorMessage(error, t = tt) {
  const code = error?.response?.data?.error;
  if (code) {
    const key = `organizer.layout.errors.${code}`;
    const translated = t(key);
    if (translated !== key) return translated;
  }
  const message = error?.response?.data?.message;
  if (typeof message === 'string' && message.trim() !== '') {
    return message.replace(/^\d{3}\s+[A-Za-z ]+:\s*/, '');
  }
  return t('organizer.layout.fallbackError');
}

export function occupancyLabel(status, t = tt) {
  return translateCodedMap('organizer.layout.occupancy', status, t);
}

export function siteStatusLabel(status, t = tt) {
  return translateCodedMap('organizer.layout.siteStatus', status, t);
}

/** Resolves through tt on each access. */
export const READINESS_BLOCKER_MESSAGES = new Proxy(
  {},
  {
    get(_target, prop) {
      if (typeof prop === 'symbol') return undefined;
      return readinessMessage(prop);
    },
  },
);

/** Resolves through tt on each access. */
export const LAYOUT_ERROR_MESSAGES = new Proxy(
  {},
  {
    get(_target, prop) {
      if (typeof prop === 'symbol') return undefined;
      return translateCodedMap('organizer.layout.errors', prop);
    },
  },
);

export const OCCUPANCY_LABELS = new Proxy(
  {},
  {
    get(_target, prop) {
      if (typeof prop === 'symbol') return undefined;
      return occupancyLabel(prop);
    },
  },
);

export const SITE_STATUS_LABELS = new Proxy(
  {},
  {
    get(_target, prop) {
      if (typeof prop === 'symbol') return undefined;
      return siteStatusLabel(prop);
    },
  },
);
'''

    MSG.write_text(new_util, encoding="utf-8")
    print("rewrote organizerEventLayoutMessages.js")
