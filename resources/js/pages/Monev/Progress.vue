<script setup lang="ts">
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pencil, Trash2, Plus, Eye, Paperclip, X, CheckCircle, XCircle, MessageCircle, CheckCheck } from 'lucide-vue-next';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

// ── Types ─────────────────────────────────────────────────────────────────────

type ProgressEntry = {
    id: number;
    uraian_progress: string | null;
    sumber: 'internal' | 'vendor';
    durasi_hari: number | null;
    tanggal_mulai: string | null;
    tanggal_akhir: string | null;
    file_url: string | null;
    file_name: string | null;
    created_by: string | null;
    last_update_by: string | null;
    status: 'draft' | 'approved' | 'rejected';
    reviewed_by: string | null;
    kabag_comment: string | null;
    comment_resolved: boolean;
};

type KontrakRow = {
    id: number;
    no_kontrak: string;
    uraian_pekerjaan: string | null;
    uraian_kegiatan: string | null;
    jenis_kegiatan: string | null;
    tanggal_kontrak: string | null;
    tanggal_mulai: string | null;
    tanggal_akhir: string | null;
    dokumen_url: string | null;
    dokumen_name: string | null;
    progress: ProgressEntry[];
};

type InstansiOption = { id: number; nama_instansi: string };
type UnitKerjaOption = { id: number; nama_unit_kerja: string };

const props = defineProps<{
    kontrak: KontrakRow[];
    instansiList: InstansiOption[];
    unitKerjaList: UnitKerjaOption[];
    selectedInstansi: number | null;
    selectedUnit: number | null;
    selectedUnitName: string | null;
    canApprove: boolean;
    canStore: boolean;
    canDelete: boolean;
    userRole: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Progress', href: '#' }];

// ── Role helpers ──────────────────────────────────────────────────────────────
const page       = usePage();
const teamSlug   = computed(() => (page.props as any).currentTeam?.slug ?? '');
function monevRoute(path: string) { return `/${teamSlug.value}/monev${path}`; }

const isAdmin     = computed(() => props.canApprove);
const isFullAdmin = computed(() => props.userRole === 'admin_mutu');
const isPimpinan  = computed(() => false);
const isStaff     = computed(() => props.userRole === 'staf');

// Hanya admin_mutu yg perlu pilih unit kerja dulu; kepala_unit & staf sudah auto-resolve
const needsUnitFilter = computed(() => isFullAdmin.value && !filterUnit.value);

// ── Filter (admin) ────────────────────────────────────────────────────────────
const filterInstansi = ref<number | null>(props.selectedInstansi);
const filterUnit     = ref<number | null>(props.selectedUnit);

watch(filterInstansi, (val) => {
    filterUnit.value = null;
    const params = val ? { instansi_id: val } : {};
    router.get(monevRoute('/progress'), params, { preserveState: true, replace: true });
});

watch(filterUnit, (val) => {
    if (val) {
        router.get(monevRoute('/progress'), { instansi_id: filterInstansi.value, unit_kerja_id: val }, { preserveState: true, replace: true });
    } else if (filterInstansi.value) {
        router.get(monevRoute('/progress'), { instansi_id: filterInstansi.value }, { preserveState: true, replace: true });
    }
});

// ── Modal State ───────────────────────────────────────────────────────────────

// Lihat (detail) modal – for the full progress list of selected kontrak
const lihatKontrakId = ref<number | null>(null);
const lihatKontrak   = computed(() => props.kontrak.find(k => k.id === lihatKontrakId.value) ?? null);

// Add (Tambah) modal
const tambahKontrakId = ref<number | null>(null);
const tambahKontrak   = computed(() => props.kontrak.find(k => k.id === tambahKontrakId.value) ?? null);

// Edit modal
const editEntry     = ref<ProgressEntry | null>(null);
const editKontrakId = ref<number | null>(null);
const editKontrak   = computed(() => editKontrakId.value ? props.kontrak.find(k => k.id === editKontrakId.value) ?? null : null);

// View (read-only) states
const viewKontrakOpen   = ref(false);
const viewProgressEntry = ref<ProgressEntry | null>(null);

// Delete confirm
const deleteEntryId    = ref<number | null>(null);
const deleteEntryLabel = ref('');

// Reject modal
const rejectDialogOpen = ref(false);
const rejectEntry      = ref<ProgressEntry | null>(null);
const rejectForm       = useForm({ kabag_comment: '' });

// Bulk reject modal
const bulkRejectOpen      = ref(false);
const bulkRejectKontrakId = ref<number | null>(null);
const bulkRejectComment   = ref('');

// ── Helpers ───────────────────────────────────────────────────────────────────

function fmtDate(d: string | null): string {
    if (!d) return '-';
    const dt = new Date(d + 'T00:00:00');
    return dt.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function calcPersen(progressAkhir: string | null, kontrakMulai: string | null, kontrakAkhir: string | null): number | null {
    if (!progressAkhir || !kontrakMulai || !kontrakAkhir) return null;
    const end    = new Date(progressAkhir + 'T00:00:00');
    const kStart = new Date(kontrakMulai  + 'T00:00:00');
    const kEnd   = new Date(kontrakAkhir  + 'T00:00:00');
    const total  = kEnd.getTime() - kStart.getTime();
    if (total <= 0) return 100;
    const elapsed = end.getTime() - kStart.getTime();
    return Math.round((elapsed / total) * 100);
}

type StatusInfo = { text: string; badge: string };

function calcStatus(akhir: string | null, persen: number | null): StatusInfo {
    if (!akhir || persen === null) {
        return { text: '-', badge: 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' };
    }
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const end   = new Date(akhir + 'T00:00:00');
    if (end > today) {
        return { text: 'Berjalan', badge: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' };
    }
    if (persen > 100) {
        return { text: 'Terlambat', badge: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' };
    }
    return { text: 'Tepat Waktu', badge: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' };
}

function persenColor(persen: number | null): string {
    if (persen === null) return 'text-muted-foreground';
    if (persen > 100) return 'font-semibold text-red-500 dark:text-red-400';
    return 'font-semibold text-green-600 dark:text-green-400';
}

type DelayStatus = { label: string; badge: string; warning: string | null };

function calcDelayStatus(entry: ProgressEntry): DelayStatus | null {
    if (!entry.tanggal_mulai || !entry.durasi_hari) return null;
    const today   = new Date(); today.setHours(0, 0, 0, 0);
    const start   = new Date(entry.tanggal_mulai + 'T00:00:00');
    const planned = new Date(start); planned.setDate(planned.getDate() + entry.durasi_hari - 1);
    const actual  = entry.tanggal_akhir ? new Date(entry.tanggal_akhir + 'T00:00:00') : null;
    if (actual && actual < today) {
        const actualDays = Math.round((actual.getTime() - start.getTime()) / 86400000) + 1;
        if (actualDays <= entry.durasi_hari) {
            return { label: 'Tepat Waktu', badge: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400', warning: null };
        }
        return { label: 'Terlambat', badge: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400', warning: `Melebihi rencana ${actualDays - entry.durasi_hari} hari` };
    }
    if (start > today) {
        return { label: 'Belum Mulai', badge: 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400', warning: null };
    }
    const daysElapsed = Math.round((today.getTime() - start.getTime()) / 86400000) + 1;
    if (daysElapsed > entry.durasi_hari) {
        const over = daysElapsed - entry.durasi_hari;
        return { label: 'Berpotensi Terlambat', badge: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400', warning: `Sudah melewati rencana ${over} hari` };
    }
    if (daysElapsed >= entry.durasi_hari - 2) {
        return { label: 'Mendekati Batas', badge: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400', warning: `Sisa ${entry.durasi_hari - daysElapsed} hari dari rencana` };
    }
    return { label: 'On Progress', badge: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', warning: null };
}

// Progress belum bisa di-approve/reject jika tanggal_akhir masih di atas hari ini
function isProgressFuture(p: ProgressEntry): boolean {
    if (!p.tanggal_akhir) return false;
    const today = new Date(); today.setHours(0, 0, 0, 0);
    return new Date(p.tanggal_akhir + 'T00:00:00') > today;
}

// ── Calendar constants ─────────────────────────────────────────────────────────

const MONTH_NAMES    = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
const DAY_NAMES      = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
const DAY_NAMES_FULL = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];

// ── Indonesian National Holidays ─────────────────────────────────────────────
const NATIONAL_HOLIDAYS = new Set<string>([
    // 2025
    '2025-01-01','2025-01-27','2025-01-28','2025-01-29',
    '2025-03-28','2025-03-29','2025-03-31',
    '2025-04-01','2025-04-02','2025-04-03','2025-04-07','2025-04-18',
    '2025-05-01','2025-05-12','2025-05-29',
    '2025-06-01','2025-06-06','2025-06-07','2025-06-27',
    '2025-08-17','2025-08-18','2025-09-05',
    '2025-12-25','2025-12-26',
    // 2026
    '2026-01-01','2026-01-16','2026-02-17',
    '2026-03-09','2026-03-19','2026-03-20','2026-03-21',
    '2026-04-03',
    '2026-05-01','2026-05-14','2026-05-22','2026-05-27',
    '2026-06-01','2026-06-17',
    '2026-08-17','2026-08-26',
    '2026-12-25',
]);

function isHoliday(y: number, m: number, d: number): boolean {
    return NATIONAL_HOLIDAYS.has(dateStrCal(y, m, d));
}

function isRedDay(y: number, m: number, d: number): boolean {
    return new Date(y, m, d).getDay() === 0 || isHoliday(y, m, d);
}

function isSaturday(y: number, m: number, d: number): boolean {
    return new Date(y, m, d).getDay() === 6;
}

// ── Form calendar (Tambah + Edit) ─────────────────────────────────────────────

const tambahCalYear  = ref(new Date().getFullYear());
const tambahCalMonth = ref(new Date().getMonth());
const editCalYear    = ref(new Date().getFullYear());
const editCalMonth   = ref(new Date().getMonth());

function buildCalGrid(year: number, month: number): (number | null)[] {
    const firstDay    = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const cells: (number | null)[] = [];
    for (let i = 0; i < firstDay; i++) cells.push(null);
    for (let d = 1; d <= daysInMonth; d++) cells.push(d);
    while (cells.length % 7 !== 0) cells.push(null);
    return cells;
}

function dateStrCal(y: number, m: number, d: number): string {
    return `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
}

function tambahPrevMonth() {
    if (tambahCalMonth.value === 0) { tambahCalYear.value--; tambahCalMonth.value = 11; }
    else tambahCalMonth.value--;
}
function tambahNextMonth() {
    if (tambahCalMonth.value === 11) { tambahCalYear.value++; tambahCalMonth.value = 0; }
    else tambahCalMonth.value++;
}
function editPrevMonth() {
    if (editCalMonth.value === 0) { editCalYear.value--; editCalMonth.value = 11; }
    else editCalMonth.value--;
}
function editNextMonth() {
    if (editCalMonth.value === 11) { editCalYear.value++; editCalMonth.value = 0; }
    else editCalMonth.value++;
}

function calDayOutOfContract(kontrak: KontrakRow | null, y: number, m: number, d: number): boolean {
    if (!kontrak?.tanggal_mulai) return false;
    const ds = dateStrCal(y, m, d);
    if (ds < kontrak.tanggal_mulai) return true;
    if (kontrak.tanggal_akhir && ds > kontrak.tanggal_akhir) return true;
    return false;
}

function calDayToday(y: number, m: number, d: number): boolean {
    const t = new Date();
    return y === t.getFullYear() && m === t.getMonth() && d === t.getDate();
}

function calDayIsStart(mulai: string, y: number, m: number, d: number): boolean {
    return dateStrCal(y, m, d) === mulai;
}
function calDayIsEnd(akhir: string, y: number, m: number, d: number): boolean {
    return dateStrCal(y, m, d) === akhir;
}
function calDayInRange(mulai: string, akhir: string, y: number, m: number, d: number): boolean {
    if (!mulai || !akhir) return false;
    const ds = dateStrCal(y, m, d);
    return ds > mulai && ds < akhir;
}

function progressCountOnDay(kontrak: KontrakRow | null, y: number, m: number, d: number): number {
    if (!kontrak) return 0;
    const ds = dateStrCal(y, m, d);
    return kontrak.progress.filter(p => p.tanggal_mulai && p.tanggal_akhir && p.tanggal_mulai <= ds && p.tanggal_akhir >= ds).length;
}

function tambahCalClick(y: number, m: number, d: number) {
    if (calDayOutOfContract(tambahKontrak.value, y, m, d)) return;
    const ds = dateStrCal(y, m, d);
    if (!addForm.tanggal_mulai || (addForm.tanggal_mulai && addForm.tanggal_akhir)) {
        addForm.tanggal_mulai = ds;
        addForm.tanggal_akhir = '';
    } else {
        if (ds >= addForm.tanggal_mulai) {
            addForm.tanggal_akhir = ds;
        } else {
            addForm.tanggal_akhir = addForm.tanggal_mulai;
            addForm.tanggal_mulai = ds;
        }
    }
}

function editCalClick(y: number, m: number, d: number) {
    if (calDayOutOfContract(editKontrak.value, y, m, d)) return;
    const ds = dateStrCal(y, m, d);
    if (!editForm.tanggal_mulai || (editForm.tanggal_mulai && editForm.tanggal_akhir)) {
        editForm.tanggal_mulai = ds;
        editForm.tanggal_akhir = '';
    } else {
        if (ds >= editForm.tanggal_mulai) {
            editForm.tanggal_akhir = ds;
        } else {
            editForm.tanggal_akhir = editForm.tanggal_mulai;
            editForm.tanggal_mulai = ds;
        }
    }
}

// ── Main page calendar view ───────────────────────────────────────────────────

const calKontrakId = ref<number | null>(null);
const calKontrak   = computed(() => props.kontrak.find(k => k.id === calKontrakId.value) ?? null);

// Reset kontrak selection when the list changes (e.g. after unit kerja filter)
watch(() => props.kontrak, (newList) => {
    const stillValid = newList.find(k => k.id === calKontrakId.value);
    if (!stillValid) {
        calKontrakId.value = null;
    }
});

const pageCalYear  = ref(new Date().getFullYear());
const pageCalMonth = ref(new Date().getMonth());

function pageCalPrevMonth() {
    if (pageCalMonth.value === 0) { pageCalYear.value--; pageCalMonth.value = 11; }
    else pageCalMonth.value--;
}
function pageCalNextMonth() {
    if (pageCalMonth.value === 11) { pageCalYear.value++; pageCalMonth.value = 0; }
    else pageCalMonth.value++;
}
function pageCalToday() {
    pageCalYear.value  = new Date().getFullYear();
    pageCalMonth.value = new Date().getMonth();
}

function getProgressOnDay(y: number, m: number, d: number): ProgressEntry[] {
    if (!calKontrak.value) return [];
    const ds = dateStrCal(y, m, d);
    return calKontrak.value.progress.filter(p =>
        p.tanggal_mulai && p.tanggal_akhir &&
        p.tanggal_mulai <= ds && p.tanggal_akhir >= ds
    );
}

// Day panel
const selectedDay = ref<string | null>(null);

const selectedDayProgress = computed<ProgressEntry[]>(() => {
    if (!calKontrak.value || !selectedDay.value) return [];
    const ds = selectedDay.value;
    return calKontrak.value.progress.filter(p =>
        p.tanggal_mulai && p.tanggal_akhir &&
        p.tanggal_mulai <= ds && p.tanggal_akhir >= ds
    );
});

function openDayPanel(y: number, m: number, d: number) {
    selectedDay.value = dateStrCal(y, m, d);
}

function openTambahFromDay() {
    if (!calKontrak.value) return;
    const day = selectedDay.value;
    selectedDay.value = null;
    openTambah(calKontrak.value);
    if (day) {
        addForm.tanggal_mulai = day;
        const d = new Date(day + 'T00:00:00');
        tambahCalYear.value  = d.getFullYear();
        tambahCalMonth.value = d.getMonth();
    }
}

// Calendar stats for current month (all entries active in current month view)
const calStats = computed(() => {
    if (!calKontrak.value) return { total: 0, draft: 0, approved: 0, rejected: 0 };
    const y           = pageCalYear.value;
    const m           = pageCalMonth.value;
    const daysInMonth = new Date(y, m + 1, 0).getDate();
    const monthStart  = `${y}-${String(m + 1).padStart(2, '0')}-01`;
    const monthEnd    = `${y}-${String(m + 1).padStart(2, '0')}-${String(daysInMonth).padStart(2, '0')}`;
    const entries = calKontrak.value.progress.filter(p =>
        p.tanggal_mulai && p.tanggal_akhir &&
        p.tanggal_mulai <= monthEnd && p.tanggal_akhir >= monthStart
    );
    return {
        total:    entries.length,
        draft:    entries.filter(p => p.status === 'draft').length,
        approved: entries.filter(p => p.status === 'approved').length,
        rejected: entries.filter(p => p.status === 'rejected').length,
    };
});

// All-time stats for selected kontrak
const calStatsAll = computed(() => {
    if (!calKontrak.value) return { total: 0, draft: 0, approved: 0, rejected: 0 };
    const entries = calKontrak.value.progress;
    return {
        total:    entries.length,
        draft:    entries.filter(p => p.status === 'draft').length,
        approved: entries.filter(p => p.status === 'approved').length,
        rejected: entries.filter(p => p.status === 'rejected').length,
    };
});

// Is the currently selected day panel date outside the active contract's range?
const selectedDayOutOfRange = computed(() => {
    if (!calKontrak.value || !selectedDay.value) return false;
    const k = calKontrak.value;
    if (!k.tanggal_mulai) return false;
    if (selectedDay.value < k.tanggal_mulai) return true;
    if (k.tanggal_akhir && selectedDay.value > k.tanggal_akhir) return true;
    return false;
});

// ── Tambah Form ───────────────────────────────────────────────────────────────

const addForm = useForm({
    kontrak_id:      '' as string | number,
    uraian_progress: '',
    sumber:          'vendor' as 'internal' | 'vendor',
    durasi_hari:     '' as string | number,
    tanggal_mulai:   '',
    tanggal_akhir:   '',
    file:            null as File | null,
});

function openTambah(k: KontrakRow) {
    tambahKontrakId.value = k.id;
    addForm.reset();
    addForm.kontrak_id    = k.id;
    const today         = new Date();
    const contractStart = k.tanggal_mulai ? new Date(k.tanggal_mulai + 'T00:00:00') : today;
    const initDate      = contractStart > today ? contractStart : today;
    tambahCalYear.value  = initDate.getFullYear();
    tambahCalMonth.value = initDate.getMonth();
}

function closeTambah() {
    tambahKontrakId.value = null;
    addForm.reset();
}

function submitTambah() {
    addForm.post(monevRoute('/progress'), { onSuccess: closeTambah });
}

// ── Edit Form ─────────────────────────────────────────────────────────────────

const editForm = useForm({
    uraian_progress: '',
    sumber:          'vendor' as 'internal' | 'vendor',
    durasi_hari:     '' as string | number,
    tanggal_mulai:   '',
    tanggal_akhir:   '',
    file:            null as File | null,
    remove_file:     false,
});

watch([() => addForm.durasi_hari, () => addForm.tanggal_mulai], ([dur, mulai]) => {
    if (dur && mulai) {
        const d = new Date(mulai + 'T00:00:00');
        d.setDate(d.getDate() + Number(dur) - 1);
        if (!addForm.tanggal_akhir) {
            addForm.tanggal_akhir = d.toISOString().slice(0, 10);
        }
    }
});

watch([() => editForm.durasi_hari, () => editForm.tanggal_mulai], ([dur, mulai]) => {
    if (dur && mulai && !editForm.tanggal_akhir) {
        const d = new Date(mulai + 'T00:00:00');
        d.setDate(d.getDate() + Number(dur) - 1);
        editForm.tanggal_akhir = d.toISOString().slice(0, 10);
    }
});

function openEdit(entry: ProgressEntry, kontrak: KontrakRow) {
    editEntry.value            = entry;
    editKontrakId.value        = kontrak.id;
    editForm.uraian_progress   = entry.uraian_progress ?? '';
    editForm.sumber            = entry.sumber ?? 'vendor';
    editForm.durasi_hari       = entry.durasi_hari ?? '';
    editForm.tanggal_mulai     = entry.tanggal_mulai ?? '';
    editForm.tanggal_akhir     = entry.tanggal_akhir ?? '';
    if (entry.tanggal_mulai) {
        const d = new Date(entry.tanggal_mulai + 'T00:00:00');
        editCalYear.value  = d.getFullYear();
        editCalMonth.value = d.getMonth();
    } else {
        editCalYear.value  = new Date().getFullYear();
        editCalMonth.value = new Date().getMonth();
    }
}

function closeEdit() {
    editEntry.value     = null;
    editKontrakId.value = null;
    editForm.reset();
}

function submitEdit() {
    if (!editEntry.value) return;
    editForm
        .transform((data) => ({ ...data, _method: 'PUT' }))
        .post(monevRoute(`/progress/${editEntry.value.id}`), { onSuccess: closeEdit });
}

// ── Delete ────────────────────────────────────────────────────────────────────

function openDelete(entry: ProgressEntry) {
    deleteEntryId.value    = entry.id;
    deleteEntryLabel.value = entry.uraian_progress || 'entri ini';
}

function confirmDelete() {
    if (!deleteEntryId.value) return;
    router.delete(monevRoute(`/progress/${deleteEntryId.value}`), {
        onSuccess: () => { deleteEntryId.value = null; },
    });
}

// ── Approve ────────────────────────────────────────────────────────────────────
function submitApprove(progressId: number) {
    router.post(monevRoute(`/progress/${progressId}/approve`), {}, { preserveScroll: true });
}

// ── Reject ─────────────────────────────────────────────────────────────────────
function openReject(entry: ProgressEntry) {
    rejectEntry.value = entry;
    rejectForm.kabag_comment = '';
    rejectDialogOpen.value = true;
}

function submitReject() {
    if (!rejectEntry.value) return;
    rejectForm.post(monevRoute(`/progress/${rejectEntry.value.id}/reject`), {
        onSuccess: () => {
            rejectDialogOpen.value = false;
            rejectEntry.value = null;
            rejectForm.reset();
        },
    });
}

// ── Resolve comment ────────────────────────────────────────────────────────────
function submitResolve(progressId: number) {
    router.post(monevRoute(`/progress/${progressId}/resolve`), {}, { preserveScroll: true });
}

// ── Bulk approve ───────────────────────────────────────────────────────────────
function submitBulkApprove(kontrakId: number) {
    router.post(monevRoute('/progress/bulk-approve'), { kontrak_id: kontrakId }, { preserveScroll: true });
}

// ── Bulk reject ────────────────────────────────────────────────────────────────
function openBulkReject(kontrakId: number) {
    bulkRejectKontrakId.value = kontrakId;
    bulkRejectComment.value   = '';
    bulkRejectOpen.value      = true;
}

function submitBulkReject() {
    if (!bulkRejectKontrakId.value) return;
    router.post(monevRoute('/progress/bulk-reject'), {
        kontrak_id:    bulkRejectKontrakId.value,
        kabag_comment: bulkRejectComment.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            bulkRejectOpen.value      = false;
            bulkRejectKontrakId.value = null;
            bulkRejectComment.value   = '';
        },
    });
}
</script>

<template>
    <Head title="Progress" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Calendar Page layout -->
        <div class="flex flex-col">

            <!-- ── Top control bar ─────────────────────────────────────────── -->
            <div class="flex flex-wrap items-center gap-2 px-4 py-2.5">

                <!-- Admin mutu: filter instansi + unit kerja -->
                <template v-if="isFullAdmin">
                    <select
                        v-model="filterInstansi"
                        class="h-8 rounded-md border border-input bg-background px-2 text-xs focus:outline-none focus:ring-1 focus:ring-ring"
                    >
                        <option :value="null">— Pilih Instansi —</option>
                        <option v-for="ins in instansiList" :key="ins.id" :value="ins.id">{{ ins.nama_instansi }}</option>
                    </select>
                    <select
                        v-model="filterUnit"
                        :disabled="!filterInstansi"
                        class="h-8 rounded-md border border-input bg-background px-2 text-xs focus:outline-none focus:ring-1 focus:ring-ring disabled:opacity-50"
                    >
                        <option :value="null">— Pilih Unit Kerja —</option>
                        <option v-for="uk in unitKerjaList" :key="uk.id" :value="uk.id">{{ uk.nama_unit_kerja }}</option>
                    </select>
                    <button
                        v-if="filterInstansi"
                        class="h-8 rounded-md border border-input px-2 text-xs text-muted-foreground hover:bg-muted"
                        @click="filterInstansi = null"
                    >Reset</button>
                    <div class="h-5 w-px bg-border"></div>
                </template>
                <!-- Kepala unit / staf: tampilkan nama unit kerja (read-only) -->
                <template v-else-if="props.selectedUnitName">
                    <span class="flex h-8 items-center gap-1.5 rounded-md border border-input bg-background px-2.5 text-xs text-muted-foreground">
                        Unit:
                        <span class="font-medium text-foreground">{{ props.selectedUnitName }}</span>
                    </span>
                    <div class="h-5 w-px bg-border"></div>
                </template>

                <!-- Kontrak selector -->
                <div class="flex items-center gap-1.5">
                    <span class="shrink-0 text-xs text-muted-foreground">Kontrak:</span>
                    <select
                        v-model="calKontrakId"
                        :disabled="needsUnitFilter"
                        class="h-8 max-w-[320px] rounded-md border border-input bg-background px-2 text-xs focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option :value="null">{{ needsUnitFilter ? '— Pilih unit kerja dulu —' : '— Pilih Kontrak —' }}</option>
                        <option v-for="k in props.kontrak" :key="k.id" :value="k.id">
                            {{ k.no_kontrak }}{{ k.uraian_kegiatan ? ' — ' + k.uraian_kegiatan.slice(0, 35) : '' }}
                        </option>
                    </select>
                </div>

                <!-- Month navigator (right-aligned) -->
                <div class="ml-auto flex items-center gap-1.5">
                    <button
                        class="rounded-lg border p-1.5 hover:bg-muted transition-colors"
                        title="Bulan sebelumnya"
                        @click="pageCalPrevMonth"
                    >
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <span class="min-w-[148px] text-center text-sm font-semibold">
                        {{ MONTH_NAMES[pageCalMonth] }} {{ pageCalYear }}
                    </span>
                    <button
                        class="rounded-lg border p-1.5 hover:bg-muted transition-colors"
                        title="Bulan berikutnya"
                        @click="pageCalNextMonth"
                    >
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                    <button
                        class="h-8 rounded-lg border px-3 text-xs text-muted-foreground hover:bg-muted transition-colors"
                        @click="pageCalToday"
                    >Hari Ini</button>
                </div>
            </div>

            <!-- ── Main content: calendar + stats sidebar ─────────────────── -->
            <div class="flex items-start">

                <!-- Calendar grid -->
                <div class="flex-1 min-w-0">

                    <!-- Placeholder: belum pilih unit kerja (admin) -->
                    <div
                        v-if="needsUnitFilter"
                        class="flex h-80 flex-col items-center justify-center gap-3 text-muted-foreground"
                    >
                        <svg class="size-14 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        <p class="text-sm font-medium">Pilih unit kerja terlebih dahulu</p>
                        <p class="text-xs">lalu pilih kontrak untuk melihat kalender progress</p>
                    </div>

                    <!-- Placeholder: belum pilih kontrak -->
                    <div
                        v-else-if="!calKontrakId"
                        class="flex h-80 flex-col items-center justify-center gap-3 text-muted-foreground"
                    >
                        <svg class="size-14 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p class="text-sm font-medium">Pilih kontrak terlebih dahulu</p>
                        <p class="text-xs">untuk melihat kalender progress</p>
                    </div>

                    <!-- Calendar (shown only when kontrak selected) -->
                    <template v-else>

                        <!-- Day-name header -->
                        <div class="grid grid-cols-7 border-b bg-muted/30">
                            <div
                                v-for="(dn, di) in DAY_NAMES_FULL"
                                :key="dn"
                                class="border-r py-2 text-center text-[11px] font-medium last:border-r-0"
                                :class="di === 0 ? 'text-red-500 dark:text-red-400' : di === 6 ? 'text-blue-500 dark:text-blue-400' : 'text-muted-foreground'"
                            >{{ dn }}</div>
                        </div>

                        <!-- Day cells -->
                        <div class="grid grid-cols-7 border-l border-t">
                            <template v-for="(day, ci) in buildCalGrid(pageCalYear, pageCalMonth)" :key="ci">
                                <div
                                    class="min-h-[90px] border-r border-b p-1.5 transition-colors"
                                    :class="[
                                        !day ? 'bg-muted/5' : '',
                                        day && calDayToday(pageCalYear, pageCalMonth, day) ? 'bg-primary/5' : '',
                                        day && !calDayToday(pageCalYear, pageCalMonth, day) && isRedDay(pageCalYear, pageCalMonth, day) && !calDayOutOfContract(calKontrak, pageCalYear, pageCalMonth, day) ? 'bg-red-50/50 dark:bg-red-950/20' : '',
                                        day && !calDayToday(pageCalYear, pageCalMonth, day) && isSaturday(pageCalYear, pageCalMonth, day) && !calDayOutOfContract(calKontrak, pageCalYear, pageCalMonth, day) ? 'bg-sky-50/50 dark:bg-sky-950/20' : '',
                                        day && calDayOutOfContract(calKontrak, pageCalYear, pageCalMonth, day) ? 'opacity-35 bg-muted/30' : '',
                                        day && !calDayOutOfContract(calKontrak, pageCalYear, pageCalMonth, day) ? 'cursor-pointer hover:bg-muted/20' : 'cursor-default',
                                    ]"
                                    @click="day && !calDayOutOfContract(calKontrak, pageCalYear, pageCalMonth, day) && openDayPanel(pageCalYear, pageCalMonth, day)"
                                >
                                    <template v-if="day">
                                        <!-- Day number -->
                                        <span
                                            class="mb-1 inline-flex size-6 items-center justify-center rounded-full text-[11px]"
                                            :class="calDayToday(pageCalYear, pageCalMonth, day)
                                                ? 'bg-primary text-primary-foreground font-bold'
                                                : isRedDay(pageCalYear, pageCalMonth, day)
                                                    ? 'font-semibold text-red-500 dark:text-red-400'
                                                    : isSaturday(pageCalYear, pageCalMonth, day)
                                                        ? 'font-semibold text-blue-500 dark:text-blue-400'
                                                        : 'font-medium text-foreground'"
                                        >{{ day }}</span>

                                        <!-- Progress bars per entry -->
                                        <div class="flex flex-col gap-0.5">
                                            <div
                                                v-for="p in getProgressOnDay(pageCalYear, pageCalMonth, day)"
                                                :key="p.id"
                                                class="truncate rounded px-1 py-0.5 text-[9px] leading-tight"
                                                :class="{
                                                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300': p.status === 'draft',
                                                    'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300': p.status === 'approved',
                                                    'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300': p.status === 'rejected',
                                                }"
                                                :title="p.uraian_progress ?? ''"
                                            >
                                                {{ p.uraian_progress?.slice(0, 18) ?? '—' }}
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

                        <!-- Legend -->
                        <div class="flex flex-wrap items-center gap-4 border-t px-4 py-2">
                            <p class="text-[10px] text-muted-foreground font-medium">Keterangan:</p>
                            <span class="inline-flex items-center gap-1 text-[10px]">
                                <span class="inline-block size-2.5 rounded bg-red-200 dark:bg-red-800/50"></span>
                                <span class="text-muted-foreground">Minggu / Libur</span>
                            </span>
                            <span class="inline-flex items-center gap-1 text-[10px]">
                                <span class="inline-block size-2.5 rounded bg-sky-200 dark:bg-sky-800/50"></span>
                                <span class="text-muted-foreground">Sabtu</span>
                            </span>
                            <span class="inline-flex items-center gap-1 text-[10px]">
                                <span class="inline-block size-2.5 rounded bg-yellow-200"></span>
                                <span class="text-muted-foreground">Belum Divalidasi</span>
                            </span>
                            <span class="inline-flex items-center gap-1 text-[10px]">
                                <span class="inline-block size-2.5 rounded bg-green-200"></span>
                                <span class="text-muted-foreground">Disetujui</span>
                            </span>
                            <span class="inline-flex items-center gap-1 text-[10px]">
                                <span class="inline-block size-2.5 rounded bg-red-300"></span>
                                <span class="text-muted-foreground">Ditolak</span>
                            </span>
                            <span class="ml-auto text-[10px] text-muted-foreground italic">Klik hari untuk melihat / tambah progress</span>
                        </div>

                    </template>
                </div>

                <!-- ── Stats sidebar (right) ──────────────────────────────── -->
                <div class="w-64 shrink-0 border-l sticky top-0">
                    <div class="flex flex-col gap-3 p-4">

                        <!-- Kontrak info card -->
                        <div v-if="calKontrak" class="rounded-xl bg-primary/5 px-3 py-2.5">
                            <p class="font-mono text-xs font-bold text-primary">{{ calKontrak.no_kontrak }}</p>
                            <p class="mt-0.5 line-clamp-2 text-[11px] text-muted-foreground">
                                {{ calKontrak.uraian_kegiatan ?? calKontrak.uraian_pekerjaan ?? '—' }}
                            </p>
                            <p v-if="calKontrak.tanggal_mulai" class="mt-1 text-[10px] text-muted-foreground">
                                {{ fmtDate(calKontrak.tanggal_mulai) }} – {{ fmtDate(calKontrak.tanggal_akhir) }}
                            </p>
                        </div>
                        <div v-else class="rounded-xl border border-dashed py-5 text-center text-xs text-muted-foreground">
                            Pilih kontrak untuk<br>melihat progress
                        </div>

                        <!-- Month stats heading -->
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">
                            Statistik {{ MONTH_NAMES[pageCalMonth] }}
                        </p>

                        <!-- Total -->
                        <div class="rounded-xl border p-3">
                            <p class="text-[11px] text-muted-foreground">Total Progress Bulan Ini</p>
                            <p class="text-3xl font-bold leading-tight">{{ calStats.total }}</p>
                            <p class="text-[10px] text-muted-foreground">entri aktif</p>
                        </div>

                        <!-- Belum Divalidasi -->
                        <div class="rounded-xl border border-yellow-200 bg-yellow-50/60 p-3 dark:border-yellow-900/40 dark:bg-yellow-900/10">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-[11px] font-semibold text-yellow-700 dark:text-yellow-400">Belum Divalidasi</p>
                                    <p class="text-[10px] text-yellow-600/70 dark:text-yellow-500/60">Draft</p>
                                </div>
                                <span class="text-2xl font-bold text-yellow-700 dark:text-yellow-400">{{ calStats.draft }}</span>
                            </div>
                        </div>

                        <!-- Disetujui -->
                        <div class="rounded-xl border border-green-200 bg-green-50/60 p-3 dark:border-green-900/40 dark:bg-green-900/10">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-[11px] font-semibold text-green-700 dark:text-green-400">Disetujui</p>
                                    <p class="text-[10px] text-green-600/70 dark:text-green-500/60">Sudah divalidasi</p>
                                </div>
                                <span class="text-2xl font-bold text-green-700 dark:text-green-400">{{ calStats.approved }}</span>
                            </div>
                        </div>

                        <!-- Ditolak -->
                        <div class="rounded-xl border border-red-200 bg-red-50/60 p-3 dark:border-red-900/40 dark:bg-red-900/10">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-[11px] font-semibold text-red-700 dark:text-red-400">Ditolak</p>
                                    <p class="text-[10px] text-red-600/70 dark:text-red-500/60">Perlu perbaikan</p>
                                </div>
                                <span class="text-2xl font-bold text-red-700 dark:text-red-400">{{ calStats.rejected }}</span>
                            </div>
                        </div>

                        <!-- All-time stats (if different from month stats) -->
                        <div v-if="calKontrak && calStatsAll.total !== calStats.total" class="rounded-xl bg-muted/30 p-3">
                            <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Total Keseluruhan</p>
                            <div class="grid grid-cols-3 gap-1 text-center">
                                <div>
                                    <p class="text-xs font-bold text-yellow-600">{{ calStatsAll.draft }}</p>
                                    <p class="text-[9px] text-muted-foreground">Draft</p>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-green-600">{{ calStatsAll.approved }}</p>
                                    <p class="text-[9px] text-muted-foreground">Disetujui</p>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-red-600">{{ calStatsAll.rejected }}</p>
                                    <p class="text-[9px] text-muted-foreground">Ditolak</p>
                                </div>
                            </div>
                        </div>

                        <!-- Action buttons -->
                        <div class="flex flex-col gap-2 border-t pt-3">
                            <Button
                                v-if="props.canStore && calKontrak"
                                size="sm"
                                class="w-full gap-1.5"
                                @click="openTambah(calKontrak)"
                            >
                                <Plus class="size-3.5" />
                                Tambah Progress
                            </Button>
                            <Button
                                v-if="calKontrak"
                                size="sm"
                                variant="outline"
                                class="w-full gap-1.5"
                                @click="lihatKontrakId = calKontrakId"
                            >
                                <Eye class="size-3.5" />
                                Lihat Semua Progress
                            </Button>
                            <Button
                                v-if="canApprove && calKontrak?.progress.some(p => p.status === 'draft')"
                                size="sm"
                                class="w-full gap-1.5 bg-green-600 hover:bg-green-700 text-white"
                                @click="submitBulkApprove(calKontrakId!)"
                            >
                                <CheckCircle class="size-3.5" />
                                Setujui Semua Draft
                            </Button>
                            <Button
                                v-if="canApprove && calKontrak?.progress.some(p => p.status === 'draft')"
                                size="sm"
                                variant="destructive"
                                class="w-full gap-1.5"
                                @click="openBulkReject(calKontrakId!)"
                            >
                                <XCircle class="size-3.5" />
                                Tolak Semua Draft
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- ── Day Panel Dialog ───────────────────────────────────────────────────── -->
    <Dialog :open="selectedDay !== null" @update:open="(v) => { if (!v) selectedDay = null; }">
        <DialogContent class="max-w-md max-h-[88vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>
                    <span>{{ selectedDay ? fmtDate(selectedDay) : '' }}</span>
                    <span v-if="calKontrak" class="ml-1 font-mono text-sm font-normal text-muted-foreground">
                        — {{ calKontrak.no_kontrak }}
                    </span>
                </DialogTitle>
            </DialogHeader>

            <!-- No kontrak selected -->
            <div v-if="!calKontrak" class="py-6 text-center text-sm text-muted-foreground">
                Pilih kontrak terlebih dahulu untuk melihat progress.
            </div>

            <!-- Progress entries for this day -->
            <div v-else class="flex flex-col gap-3">
                <div
                    v-if="selectedDayProgress.length === 0"
                    class="rounded-xl border border-dashed py-6 text-center text-sm text-muted-foreground"
                >
                    Tidak ada progress pada tanggal ini.<br>
                    <span class="text-xs">Klik "Tambah Progress" untuk menambahkan.</span>
                </div>

                <div
                    v-for="p in selectedDayProgress"
                    :key="p.id"
                    class="flex flex-col gap-1.5 rounded-xl border p-3"
                >
                    <!-- Status + sumber badges -->
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium"
                            :class="{
                                'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300': p.status === 'draft',
                                'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300': p.status === 'approved',
                                'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300': p.status === 'rejected',
                            }"
                        >{{ p.status === 'approved' ? '✓ Disetujui' : p.status === 'rejected' ? '✗ Ditolak' : '○ Draft' }}</span>
                        <span class="inline-flex items-center rounded-full bg-muted px-2 py-0.5 text-[10px] text-muted-foreground">
                            {{ p.sumber === 'vendor' ? 'Vendor' : 'Internal' }}
                        </span>
                        <span v-if="p.reviewed_by" class="ml-auto text-[10px] text-muted-foreground">oleh {{ p.reviewed_by }}</span>
                    </div>

                    <!-- Description -->
                    <p class="text-sm leading-relaxed">{{ p.uraian_progress || '-' }}</p>

                    <!-- Date range -->
                    <p class="text-xs text-muted-foreground">{{ fmtDate(p.tanggal_mulai) }} – {{ fmtDate(p.tanggal_akhir) }}</p>

                    <!-- File -->
                    <div v-if="p.file_url" class="flex items-center gap-1">
                        <Paperclip class="size-3 shrink-0 text-muted-foreground" />
                        <a :href="p.file_url" target="_blank" class="max-w-[220px] truncate text-xs text-primary hover:underline">
                            {{ p.file_name ?? 'Berkas' }}
                        </a>
                    </div>

                    <!-- Kabag comment -->
                    <p
                        v-if="p.kabag_comment && !p.comment_resolved"
                        class="rounded-md bg-red-50 px-2 py-1.5 text-[10px] italic text-red-600 dark:bg-red-900/20 dark:text-red-300"
                    >
                        💬 {{ p.kabag_comment }}
                    </p>

                    <!-- Action row -->
                    <div class="flex items-center gap-0.5 border-t pt-1.5">
                        <!-- Approve -->
                        <button
                            v-if="canApprove && p.status !== 'approved'"
                            class="relative rounded p-1 transition-colors"
                            :class="isProgressFuture(p) ? 'cursor-not-allowed opacity-40' : 'hover:bg-muted'"
                            :title="isProgressFuture(p) ? 'Belum bisa disetujui — pekerjaan belum selesai' : (p.sumber === 'vendor' ? 'Setujui (Penilaian Vendor)' : 'Setujui')"
                            :disabled="isProgressFuture(p)"
                            @click="!isProgressFuture(p) && submitApprove(p.id)"
                        >
                            <CheckCircle class="size-4 text-green-500" />
                            <span v-if="p.sumber === 'vendor' && !isProgressFuture(p)" class="absolute -right-0.5 -top-0.5 size-1.5 rounded-full bg-blue-400"></span>
                        </button>
                        <!-- Reject -->
                        <button
                            v-if="canApprove && p.status !== 'rejected'"
                            class="rounded p-1 transition-colors"
                            :class="isProgressFuture(p) ? 'cursor-not-allowed opacity-40' : 'hover:bg-muted'"
                            :title="isProgressFuture(p) ? 'Belum bisa ditolak — pekerjaan belum selesai' : 'Tolak & Beri Komentar'"
                            :disabled="isProgressFuture(p)"
                            @click="!isProgressFuture(p) && openReject(p)"
                        >
                            <XCircle class="size-4 text-red-400" />
                        </button>
                        <!-- Komentar icon (staff) -->
                        <button
                            v-if="isStaff && p.status === 'rejected' && !p.comment_resolved && p.kabag_comment"
                            class="rounded p-1 hover:bg-muted"
                            :title="'Komentar: ' + p.kabag_comment"
                            @click="viewProgressEntry = p"
                        >
                            <MessageCircle class="size-4 text-orange-500" />
                        </button>
                        <!-- Sudah Diperbaiki (staff) -->
                        <button
                            v-if="isStaff && p.status === 'rejected' && !p.comment_resolved"
                            class="rounded p-1 hover:bg-muted"
                            title="Sudah Diperbaiki"
                            @click="submitResolve(p.id)"
                        >
                            <CheckCheck class="size-4 text-blue-500" />
                        </button>
                        <!-- Edit -->
                        <button
                            v-if="props.canStore && (canApprove || p.status === 'draft')"
                            class="rounded p-1 hover:bg-muted"
                            title="Edit"
                            @click="openEdit(p, calKontrak!); selectedDay = null"
                        >
                            <Pencil class="size-4 text-muted-foreground" />
                        </button>
                        <!-- Hapus -->
                        <button
                            v-if="canDelete"
                            class="rounded p-1 hover:bg-muted"
                            title="Hapus"
                            @click="openDelete(p)"
                        >
                            <Trash2 class="size-4 text-red-400" />
                        </button>
                        <!-- View detail -->
                        <button
                            class="ml-auto rounded p-1 hover:bg-muted"
                            title="Lihat Detail"
                            @click="viewProgressEntry = p"
                        >
                            <Eye class="size-4 text-muted-foreground" />
                        </button>
                    </div>
                </div>
            </div>

            <DialogFooter class="border-t pt-3">
                <Button
                    v-if="props.canStore && calKontrak && !selectedDayOutOfRange"
                    size="sm"
                    variant="outline"
                    class="mr-auto gap-1"
                    @click="openTambahFromDay"
                >
                    <Plus class="size-3" />
                    Tambah Progress
                </Button>
                <p
                    v-if="selectedDayOutOfRange"
                    class="mr-auto text-[11px] text-muted-foreground italic"
                >Di luar periode kontrak</p>
                <Button variant="outline" size="sm" @click="selectedDay = null">Tutup</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- ── Tambah Progress Modal ─────────────────────────────────────────────── -->
    <Dialog :open="tambahKontrakId !== null" @update:open="(v) => { if (!v) closeTambah(); }">
        <DialogContent class="max-w-lg max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>
                    Tambah Progress
                    <span v-if="tambahKontrak" class="ml-1 font-mono text-sm font-normal text-muted-foreground">
                        — {{ tambahKontrak.no_kontrak }}
                    </span>
                </DialogTitle>
                <p v-if="tambahKontrak?.uraian_kegiatan" class="text-xs text-muted-foreground">
                    {{ tambahKontrak.uraian_kegiatan }}
                </p>
            </DialogHeader>

            <form class="flex flex-col gap-4" @submit.prevent="submitTambah">

                <input type="hidden" :value="addForm.kontrak_id" />

                <!-- Sumber Progress -->
                <div class="flex flex-col gap-1.5">
                    <Label>Sumber Progress <span class="text-red-500">*</span></Label>
                    <div class="flex gap-4">
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="radio" value="vendor" v-model="addForm.sumber" class="accent-primary" />
                            <span class="font-medium">Vendor</span>
                            <span class="text-xs text-muted-foreground">(masuk penilaian)</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="radio" value="internal" v-model="addForm.sumber" class="accent-primary" />
                            <span class="font-medium">Internal</span>
                            <span class="text-xs text-muted-foreground">(tidak masuk penilaian)</span>
                        </label>
                    </div>
                </div>

                <!-- Uraian Progress -->
                <div class="flex flex-col gap-1.5">
                    <Label>Uraian Progress <span class="text-red-500">*</span></Label>
                    <textarea
                        v-model="addForm.uraian_progress"
                        rows="3"
                        placeholder="Deskripsikan pekerjaan yang dilakukan…"
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                    />
                    <p v-if="addForm.errors.uraian_progress" class="text-xs text-red-500">{{ addForm.errors.uraian_progress }}</p>
                </div>

                <!-- Durasi Rencana -->
                <div class="flex flex-col gap-1.5">
                    <Label>Durasi Rencana (hari)</Label>
                    <Input
                        v-model="addForm.durasi_hari"
                        type="number"
                        min="1"
                        max="9999"
                        placeholder="Contoh: 30"
                        class="w-36"
                    />
                    <p class="text-[11px] text-muted-foreground">Isi untuk menampilkan status ketepatan waktu. Tanggal selesai akan otomatis terisi.</p>
                    <p v-if="addForm.errors.durasi_hari" class="text-xs text-red-500">{{ addForm.errors.durasi_hari }}</p>
                </div>

                <!-- Kalender Pilih Tanggal -->
                <div class="flex flex-col gap-2">
                    <Label>Tanggal Pelaksanaan <span class="text-red-500">*</span></Label>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="rounded-md border px-3 py-1.5 text-xs">
                            <span class="text-muted-foreground">Awal:</span>
                            <span class="ml-1 font-semibold">{{ addForm.tanggal_mulai ? fmtDate(addForm.tanggal_mulai) : '—' }}</span>
                        </div>
                        <div class="rounded-md border px-3 py-1.5 text-xs">
                            <span class="text-muted-foreground">Selesai:</span>
                            <span class="ml-1 font-semibold">{{ addForm.tanggal_akhir ? fmtDate(addForm.tanggal_akhir) : '—' }}</span>
                        </div>
                    </div>

                    <div class="rounded-lg border p-3">
                        <div class="mb-2 flex items-center justify-between">
                            <button type="button" class="rounded p-1 hover:bg-muted" @click="tambahPrevMonth">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <span class="text-sm font-semibold">{{ MONTH_NAMES[tambahCalMonth] }} {{ tambahCalYear }}</span>
                            <button type="button" class="rounded p-1 hover:bg-muted" @click="tambahNextMonth">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                        <div class="grid grid-cols-7 mb-1">
                            <div v-for="dn in DAY_NAMES" :key="dn" class="py-0.5 text-center text-[10px] font-medium text-muted-foreground">{{ dn }}</div>
                        </div>
                        <div class="grid grid-cols-7 gap-y-0.5">
                            <template v-for="(day, ci) in buildCalGrid(tambahCalYear, tambahCalMonth)" :key="ci">
                                <div v-if="!day" class="py-1"></div>
                                <button
                                    v-else
                                    type="button"
                                    :disabled="calDayOutOfContract(tambahKontrak, tambahCalYear, tambahCalMonth, day)"
                                    class="relative mx-auto flex size-7 flex-col items-center justify-center rounded text-xs transition-colors"
                                    :class="[
                                        calDayOutOfContract(tambahKontrak, tambahCalYear, tambahCalMonth, day)
                                            ? 'text-muted-foreground/25 cursor-not-allowed'
                                            : (calDayIsStart(addForm.tanggal_mulai, tambahCalYear, tambahCalMonth, day) || calDayIsEnd(addForm.tanggal_akhir, tambahCalYear, tambahCalMonth, day))
                                                ? 'bg-primary text-primary-foreground font-bold'
                                                : calDayInRange(addForm.tanggal_mulai, addForm.tanggal_akhir, tambahCalYear, tambahCalMonth, day)
                                                    ? 'bg-primary/20 text-primary'
                                                    : calDayToday(tambahCalYear, tambahCalMonth, day)
                                                        ? 'ring-1 ring-primary/50 hover:bg-muted'
                                                        : 'hover:bg-muted'
                                    ]"
                                    @click="tambahCalClick(tambahCalYear, tambahCalMonth, day)"
                                >
                                    {{ day }}
                                    <span
                                        v-if="progressCountOnDay(tambahKontrak, tambahCalYear, tambahCalMonth, day) > 0"
                                        class="absolute bottom-0.5 size-1 rounded-full bg-blue-400"
                                    ></span>
                                </button>
                            </template>
                        </div>
                        <p class="mt-2 text-center text-[10px] text-muted-foreground">
                            <span v-if="!addForm.tanggal_mulai">Klik tanggal awal</span>
                            <span v-else-if="!addForm.tanggal_akhir">Klik tanggal selesai</span>
                            <span v-else>Klik lagi untuk reset & pilih ulang • <span class="text-blue-400">●</span> ada progress</span>
                        </p>
                    </div>

                    <p v-if="tambahKontrak?.tanggal_mulai" class="text-[11px] text-muted-foreground">
                        Periode kontrak: {{ fmtDate(tambahKontrak.tanggal_mulai) }} – {{ fmtDate(tambahKontrak.tanggal_akhir) }}
                    </p>
                    <p v-if="addForm.errors.tanggal_mulai" class="text-xs text-red-500">{{ addForm.errors.tanggal_mulai }}</p>
                    <p v-if="addForm.errors.tanggal_akhir" class="text-xs text-red-500">{{ addForm.errors.tanggal_akhir }}</p>
                </div>

                <!-- Upload Berkas -->
                <div class="flex flex-col gap-1.5">
                    <Label>Berkas (opsional)</Label>
                    <input
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.doc,.docx"
                        class="block w-full text-sm text-muted-foreground file:mr-3 file:rounded file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-foreground hover:file:bg-muted/80"
                        @change="(e) => addForm.file = (e.target as HTMLInputElement).files?.[0] ?? null"
                    />
                    <p class="text-[11px] text-muted-foreground">PDF, gambar, Excel, atau Word – maks 10 MB</p>
                    <p v-if="addForm.errors.file" class="text-xs text-red-500">{{ addForm.errors.file }}</p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="closeTambah">Batal</Button>
                    <Button type="submit" :disabled="addForm.processing">Simpan</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- ── Lihat Progress Modal ──────────────────────────────────────────────── -->
    <Dialog
        :open="lihatKontrakId !== null && editEntry === null"
        @update:open="(v) => { if (!v) lihatKontrakId = null; }"
    >
        <DialogContent class="max-w-5xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>
                    Detail Progress
                    <span v-if="lihatKontrak" class="ml-1 font-mono text-sm font-normal text-muted-foreground">
                        — {{ lihatKontrak.no_kontrak }}
                    </span>
                </DialogTitle>
                <p v-if="lihatKontrak?.uraian_kegiatan" class="text-xs text-muted-foreground">
                    {{ lihatKontrak.uraian_kegiatan }}
                    <span v-if="lihatKontrak.tanggal_kontrak || lihatKontrak.tanggal_akhir" class="ml-2 text-[10px]">
                        ({{ fmtDate(lihatKontrak.tanggal_kontrak) }} – {{ fmtDate(lihatKontrak.tanggal_akhir) }})
                    </span>
                </p>
            </DialogHeader>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/40 text-xs text-muted-foreground">
                            <th class="px-3 py-2.5 text-left font-medium">No</th>
                            <th class="px-3 py-2.5 text-left font-medium">Uraian Progress</th>
                            <th class="px-3 py-2.5 text-left font-medium whitespace-nowrap">Tanggal Mulai</th>
                            <th class="px-3 py-2.5 text-left font-medium whitespace-nowrap">Tanggal Selesai</th>
                            <th class="px-3 py-2.5 text-right font-medium">Persentase</th>
                            <th class="px-3 py-2.5 text-center font-medium">Status</th>
                            <th class="px-3 py-2.5 text-left font-medium whitespace-nowrap">Diinput / Diubah</th>
                            <th class="px-3 py-2.5 text-center font-medium">Berkas</th>
                            <th class="px-3 py-2.5 text-center font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">

                        <!-- Baris kontrak -->
                        <tr v-if="lihatKontrak" class="bg-muted/20">
                            <td class="px-3 py-2.5 text-center">
                                <span class="inline-block rounded bg-primary/10 px-1.5 py-0.5 text-[9px] font-bold uppercase text-primary">K</span>
                            </td>
                            <td class="max-w-[240px] px-3 py-2.5">
                                <p class="text-xs font-medium">Kontrak Kerja</p>
                                <p class="font-mono text-[10px] text-muted-foreground">{{ lihatKontrak.no_kontrak }}</p>
                            </td>
                            <td class="px-3 py-2.5 text-xs whitespace-nowrap">{{ fmtDate(lihatKontrak.tanggal_mulai) }}</td>
                            <td class="px-3 py-2.5 text-xs whitespace-nowrap">{{ fmtDate(lihatKontrak.tanggal_akhir) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-xs">
                                <span class="font-semibold text-green-600 dark:text-green-400">100%</span>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                    On Time
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-xs text-muted-foreground">—</td>
                            <td class="px-3 py-2.5 text-center">
                                <a
                                    v-if="lihatKontrak.dokumen_url"
                                    :href="lihatKontrak.dokumen_url"
                                    target="_blank"
                                    class="inline-flex items-center gap-1 text-[10px] text-primary hover:underline"
                                >
                                    <Paperclip class="size-3" />
                                    <span class="max-w-[80px] truncate">{{ lihatKontrak.dokumen_name ?? 'Dokumen' }}</span>
                                </a>
                                <span v-else class="text-[10px] text-muted-foreground">—</span>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <button
                                    class="rounded p-1 hover:bg-muted"
                                    title="Lihat Detail Kontrak"
                                    @click="viewKontrakOpen = true"
                                >
                                    <Eye class="size-3.5 text-muted-foreground" />
                                </button>
                            </td>
                        </tr>

                        <!-- Progress rows -->
                        <tr
                            v-for="(p, i) in lihatKontrak?.progress ?? []"
                            :key="p.id"
                            class="hover:bg-muted/20 transition-colors"
                        >
                            <td class="px-3 py-2.5 text-muted-foreground">{{ i + 1 }}</td>
                            <td class="max-w-[240px] px-3 py-2.5">
                                <p class="text-xs">{{ p.uraian_progress || '-' }}</p>
                                <span
                                    class="mt-0.5 inline-flex items-center rounded-full px-1.5 py-0.5 text-[9px] font-medium"
                                    :class="p.sumber === 'internal'
                                        ? 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'
                                        : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'"
                                >
                                    {{ p.sumber === 'internal' ? 'Internal' : 'Vendor' }}
                                </span>
                                <span
                                    class="ml-0.5 inline-flex items-center rounded-full px-1.5 py-0.5 text-[9px] font-medium"
                                    :class="{
                                        'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300': !p.status || p.status === 'draft',
                                        'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300': p.status === 'approved',
                                        'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300': p.status === 'rejected',
                                    }"
                                >
                                    {{ p.status === 'approved' ? '✓ Disetujui' : p.status === 'rejected' ? '✗ Ditolak' : '○ Draft' }}
                                </span>
                                <p
                                    v-if="p.status === 'rejected' && p.kabag_comment && !p.comment_resolved"
                                    class="mt-1 max-w-[220px] rounded bg-red-50 px-2 py-1 text-[9px] italic text-red-600 dark:bg-red-900/20 dark:text-red-300"
                                >
                                    💬 {{ p.kabag_comment }}
                                </p>
                            </td>
                            <td class="px-3 py-2.5 text-xs whitespace-nowrap">{{ fmtDate(p.tanggal_mulai) }}</td>
                            <td class="px-3 py-2.5 text-xs whitespace-nowrap">{{ fmtDate(p.tanggal_akhir) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-xs">
                                <span :class="persenColor(calcPersen(p.tanggal_akhir, lihatKontrak?.tanggal_mulai, lihatKontrak?.tanggal_akhir))">
                                    {{ calcPersen(p.tanggal_akhir, lihatKontrak?.tanggal_mulai, lihatKontrak?.tanggal_akhir) !== null
                                        ? calcPersen(p.tanggal_akhir, lihatKontrak?.tanggal_mulai, lihatKontrak?.tanggal_akhir) + '%'
                                        : '-' }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <template v-if="calcDelayStatus(p)">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium"
                                        :class="calcDelayStatus(p)!.badge"
                                    >{{ calcDelayStatus(p)!.label }}</span>
                                    <p v-if="calcDelayStatus(p)!.warning" class="mt-0.5 text-[9px] text-orange-600 dark:text-orange-400">
                                        ⚠ {{ calcDelayStatus(p)!.warning }}
                                    </p>
                                    <p class="mt-0.5 text-[9px] text-muted-foreground">Rencana: {{ p.durasi_hari }} hari</p>
                                </template>
                                <span v-else
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium"
                                    :class="calcStatus(p.tanggal_akhir, calcPersen(p.tanggal_akhir, lihatKontrak?.tanggal_mulai, lihatKontrak?.tanggal_akhir)).badge"
                                >
                                    {{ calcStatus(p.tanggal_akhir, calcPersen(p.tanggal_akhir, lihatKontrak?.tanggal_mulai, lihatKontrak?.tanggal_akhir)).text }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-xs">
                                <p v-if="p.created_by" class="text-muted-foreground">
                                    <span class="text-[9px] uppercase font-semibold text-slate-400">Input:</span>
                                    {{ p.created_by }}
                                </p>
                                <p v-if="p.last_update_by" class="mt-0.5 text-muted-foreground">
                                    <span class="text-[9px] uppercase font-semibold text-slate-400">Edit:</span>
                                    {{ p.last_update_by }}
                                </p>
                                <span v-if="!p.created_by && !p.last_update_by" class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <a
                                    v-if="p.file_url"
                                    :href="p.file_url"
                                    target="_blank"
                                    class="inline-flex items-center gap-1 text-[10px] text-primary hover:underline"
                                >
                                    <Paperclip class="size-3" />
                                    <span class="max-w-[80px] truncate">{{ p.file_name ?? 'Berkas' }}</span>
                                </a>
                                <span v-else class="text-[10px] text-muted-foreground">—</span>
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="flex items-center justify-center gap-1">
                                    <button class="rounded p-1 hover:bg-muted" title="Lihat Detail" @click="viewProgressEntry = p">
                                        <Eye class="size-3.5 text-muted-foreground" />
                                    </button>
                                    <button
                                        v-if="canApprove && p.status !== 'approved'"
                                        class="relative rounded p-1 transition-colors"
                                        :class="isProgressFuture(p) ? 'opacity-40 cursor-not-allowed' : 'hover:bg-muted'"
                                        :title="isProgressFuture(p) ? 'Belum bisa disetujui' : (p.sumber === 'vendor' ? 'Setujui (Penilaian Vendor)' : 'Setujui')"
                                        :disabled="isProgressFuture(p)"
                                        @click="!isProgressFuture(p) && submitApprove(p.id)"
                                    >
                                        <CheckCircle class="size-3.5 text-green-500" />
                                        <span v-if="p.sumber === 'vendor' && !isProgressFuture(p)" class="absolute -top-0.5 -right-0.5 size-1.5 rounded-full bg-blue-400"></span>
                                    </button>
                                    <button
                                        v-if="canApprove && p.status !== 'rejected'"
                                        class="rounded p-1 transition-colors"
                                        :class="isProgressFuture(p) ? 'opacity-40 cursor-not-allowed' : 'hover:bg-muted'"
                                        :disabled="isProgressFuture(p)"
                                        title="Tolak & Beri Komentar"
                                        @click="!isProgressFuture(p) && openReject(p)"
                                    >
                                        <XCircle class="size-3.5 text-red-400" />
                                    </button>
                                    <button
                                        v-if="isStaff && p.status === 'rejected' && !p.comment_resolved && p.kabag_comment"
                                        class="rounded p-1 hover:bg-muted"
                                        :title="'Komentar Kabag: ' + p.kabag_comment"
                                        @click="viewProgressEntry = p"
                                    >
                                        <MessageCircle class="size-3.5 text-orange-500" />
                                    </button>
                                    <button
                                        v-if="isStaff && p.status === 'rejected' && !p.comment_resolved"
                                        class="rounded p-1 hover:bg-muted"
                                        title="Sudah Diperbaiki"
                                        @click="submitResolve(p.id)"
                                    >
                                        <CheckCheck class="size-3.5 text-blue-500" />
                                    </button>
                                    <button
                                        v-if="props.canStore && (canApprove || p.status === 'draft')"
                                        class="rounded p-1 hover:bg-muted"
                                        title="Edit"
                                        @click="openEdit(p, lihatKontrak!)"
                                    >
                                        <Pencil class="size-3.5 text-muted-foreground" />
                                    </button>
                                    <button
                                        v-if="canDelete"
                                        class="rounded p-1 hover:bg-muted"
                                        title="Hapus"
                                        @click="openDelete(p)"
                                    >
                                        <Trash2 class="size-3.5 text-red-400" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!lihatKontrak?.progress.length">
                            <td colspan="9" class="px-3 py-4 text-center text-xs text-muted-foreground italic">
                                Belum ada progress tambahan.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between border-t pt-3">
                <div class="flex gap-2">
                    <Button
                        v-if="props.canStore"
                        size="sm"
                        variant="outline"
                        class="gap-1 text-xs"
                        @click="() => { const k = lihatKontrak; lihatKontrakId = null; if (k) openTambah(k); }"
                    >
                        <Plus class="size-3" />
                        Tambah Progress
                    </Button>
                    <Button
                        v-if="canApprove && lihatKontrak?.progress.some(p => p.status === 'draft')"
                        size="sm"
                        class="gap-1 text-xs bg-green-600 hover:bg-green-700 text-white"
                        @click="submitBulkApprove(lihatKontrakId!)"
                    >
                        <CheckCircle class="size-3" />
                        Setujui Semua Draft
                    </Button>
                    <Button
                        v-if="canApprove && lihatKontrak?.progress.some(p => p.status === 'draft')"
                        size="sm"
                        variant="destructive"
                        class="gap-1 text-xs"
                        @click="openBulkReject(lihatKontrakId!)"
                    >
                        <XCircle class="size-3" />
                        Tolak Semua Draft
                    </Button>
                </div>
                <Button variant="outline" size="sm" @click="lihatKontrakId = null">Tutup</Button>
            </div>
        </DialogContent>
    </Dialog>

    <!-- ── Edit Progress Modal ───────────────────────────────────────────────── -->
    <Dialog :open="editEntry !== null" @update:open="(v) => { if (!v) closeEdit(); }">
        <DialogContent class="max-w-lg max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Edit Progress</DialogTitle>
                <p v-if="editKontrakId" class="font-mono text-xs text-muted-foreground">
                    {{ props.kontrak.find(k => k.id === editKontrakId)?.no_kontrak }}
                </p>
            </DialogHeader>

            <form class="flex flex-col gap-4" @submit.prevent="submitEdit">

                <!-- Sumber Progress -->
                <div class="flex flex-col gap-1.5">
                    <Label>Sumber Progress <span class="text-red-500">*</span></Label>
                    <div class="flex gap-4">
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="radio" value="vendor" v-model="editForm.sumber" class="accent-primary" />
                            <span class="font-medium">Vendor</span>
                            <span class="text-xs text-muted-foreground">(masuk penilaian)</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="radio" value="internal" v-model="editForm.sumber" class="accent-primary" />
                            <span class="font-medium">Internal</span>
                            <span class="text-xs text-muted-foreground">(tidak masuk penilaian)</span>
                        </label>
                    </div>
                </div>

                <!-- Uraian Progress -->
                <div class="flex flex-col gap-1.5">
                    <Label>Uraian Progress <span class="text-red-500">*</span></Label>
                    <textarea
                        v-model="editForm.uraian_progress"
                        rows="3"
                        placeholder="Deskripsikan pekerjaan yang dilakukan…"
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                    />
                    <p v-if="editForm.errors.uraian_progress" class="text-xs text-red-500">{{ editForm.errors.uraian_progress }}</p>
                </div>

                <!-- Durasi Rencana -->
                <div class="flex flex-col gap-1.5">
                    <Label>Durasi Rencana (hari)</Label>
                    <Input
                        v-model="editForm.durasi_hari"
                        type="number"
                        min="1"
                        max="9999"
                        placeholder="Contoh: 30"
                        class="w-36"
                    />
                    <p v-if="editForm.errors.durasi_hari" class="text-xs text-red-500">{{ editForm.errors.durasi_hari }}</p>
                </div>

                <!-- Kalender Pilih Tanggal -->
                <div class="flex flex-col gap-2">
                    <Label>Tanggal Pelaksanaan <span class="text-red-500">*</span></Label>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="rounded-md border px-3 py-1.5 text-xs">
                            <span class="text-muted-foreground">Awal:</span>
                            <span class="ml-1 font-semibold">{{ editForm.tanggal_mulai ? fmtDate(editForm.tanggal_mulai) : '—' }}</span>
                        </div>
                        <div class="rounded-md border px-3 py-1.5 text-xs">
                            <span class="text-muted-foreground">Selesai:</span>
                            <span class="ml-1 font-semibold">{{ editForm.tanggal_akhir ? fmtDate(editForm.tanggal_akhir) : '—' }}</span>
                        </div>
                    </div>

                    <div class="rounded-lg border p-3">
                        <div class="mb-2 flex items-center justify-between">
                            <button type="button" class="rounded p-1 hover:bg-muted" @click="editPrevMonth">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <span class="text-sm font-semibold">{{ MONTH_NAMES[editCalMonth] }} {{ editCalYear }}</span>
                            <button type="button" class="rounded p-1 hover:bg-muted" @click="editNextMonth">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                        <div class="grid grid-cols-7 mb-1">
                            <div v-for="dn in DAY_NAMES" :key="dn" class="py-0.5 text-center text-[10px] font-medium text-muted-foreground">{{ dn }}</div>
                        </div>
                        <div class="grid grid-cols-7 gap-y-0.5">
                            <template v-for="(day, ci) in buildCalGrid(editCalYear, editCalMonth)" :key="ci">
                                <div v-if="!day" class="py-1"></div>
                                <button
                                    v-else
                                    type="button"
                                    :disabled="calDayOutOfContract(editKontrak, editCalYear, editCalMonth, day)"
                                    class="relative mx-auto flex size-7 flex-col items-center justify-center rounded text-xs transition-colors"
                                    :class="[
                                        calDayOutOfContract(editKontrak, editCalYear, editCalMonth, day)
                                            ? 'text-muted-foreground/25 cursor-not-allowed'
                                            : (calDayIsStart(editForm.tanggal_mulai, editCalYear, editCalMonth, day) || calDayIsEnd(editForm.tanggal_akhir, editCalYear, editCalMonth, day))
                                                ? 'bg-primary text-primary-foreground font-bold'
                                                : calDayInRange(editForm.tanggal_mulai, editForm.tanggal_akhir, editCalYear, editCalMonth, day)
                                                    ? 'bg-primary/20 text-primary'
                                                    : calDayToday(editCalYear, editCalMonth, day)
                                                        ? 'ring-1 ring-primary/50 hover:bg-muted'
                                                        : 'hover:bg-muted'
                                    ]"
                                    @click="editCalClick(editCalYear, editCalMonth, day)"
                                >
                                    {{ day }}
                                    <span
                                        v-if="progressCountOnDay(editKontrak, editCalYear, editCalMonth, day) > 0"
                                        class="absolute bottom-0.5 size-1 rounded-full bg-blue-400"
                                    ></span>
                                </button>
                            </template>
                        </div>
                        <p class="mt-2 text-center text-[10px] text-muted-foreground">
                            <span v-if="!editForm.tanggal_mulai">Klik tanggal awal</span>
                            <span v-else-if="!editForm.tanggal_akhir">Klik tanggal selesai</span>
                            <span v-else>Klik lagi untuk reset & pilih ulang • <span class="text-blue-400">●</span> ada progress</span>
                        </p>
                    </div>

                    <p v-if="editKontrak?.tanggal_mulai" class="text-[11px] text-muted-foreground">
                        Periode kontrak: {{ fmtDate(editKontrak.tanggal_mulai) }} – {{ fmtDate(editKontrak.tanggal_akhir) }}
                    </p>
                    <p v-if="editForm.errors.tanggal_mulai" class="text-xs text-red-500">{{ editForm.errors.tanggal_mulai }}</p>
                    <p v-if="editForm.errors.tanggal_akhir" class="text-xs text-red-500">{{ editForm.errors.tanggal_akhir }}</p>
                </div>

                <!-- Berkas yang sudah ada -->
                <div v-if="editEntry?.file_url && !editForm.remove_file" class="flex flex-col gap-1.5">
                    <Label>Berkas Saat Ini</Label>
                    <div class="flex items-center gap-2 rounded-md border px-3 py-2 text-xs">
                        <Paperclip class="size-3.5 shrink-0 text-muted-foreground" />
                        <a
                            :href="editEntry.file_url"
                            target="_blank"
                            class="flex-1 truncate text-primary hover:underline"
                        >{{ editEntry.file_name ?? 'Berkas' }}</a>
                        <button
                            type="button"
                            class="shrink-0 rounded p-0.5 hover:bg-muted"
                            title="Hapus berkas"
                            @click="editForm.remove_file = true"
                        >
                            <X class="size-3.5 text-red-400" />
                        </button>
                    </div>
                </div>
                <div v-else-if="editEntry?.file_url && editForm.remove_file" class="rounded-md border border-dashed border-red-300 px-3 py-2 text-xs text-red-400">
                    Berkas akan dihapus saat disimpan.
                    <button type="button" class="ml-2 underline" @click="editForm.remove_file = false">Batalkan</button>
                </div>

                <!-- Upload Berkas Baru -->
                <div class="flex flex-col gap-1.5">
                    <Label>{{ editEntry?.file_url ? 'Ganti Berkas (opsional)' : 'Upload Berkas (opsional)' }}</Label>
                    <input
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.doc,.docx"
                        class="block w-full text-sm text-muted-foreground file:mr-3 file:rounded file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-foreground hover:file:bg-muted/80"
                        @change="(e) => {
                            editForm.file = (e.target as HTMLInputElement).files?.[0] ?? null;
                            if (editForm.file) editForm.remove_file = false;
                        }"
                    />
                    <p class="text-[11px] text-muted-foreground">PDF, gambar, Excel, atau Word – maks 10 MB</p>
                    <p v-if="editForm.errors.file" class="text-xs text-red-500">{{ editForm.errors.file }}</p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="closeEdit">Batal</Button>
                    <Button type="submit" :disabled="editForm.processing">Update</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- ── View Kontrak Modal ────────────────────────────────────────────────── -->
    <Dialog :open="viewKontrakOpen" @update:open="(v) => { if (!v) viewKontrakOpen = false; }">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Detail Kontrak</DialogTitle>
            </DialogHeader>
            <div v-if="lihatKontrak" class="flex flex-col gap-3 text-sm">
                <div>
                    <p class="text-xs text-muted-foreground">No. Kontrak</p>
                    <p class="font-mono font-semibold">{{ lihatKontrak.no_kontrak }}</p>
                </div>
                <div v-if="lihatKontrak.uraian_kegiatan">
                    <p class="text-xs text-muted-foreground">Uraian Kegiatan</p>
                    <p>{{ lihatKontrak.uraian_kegiatan }}</p>
                </div>
                <div v-if="lihatKontrak.uraian_pekerjaan">
                    <p class="text-xs text-muted-foreground">Uraian Pekerjaan</p>
                    <p>{{ lihatKontrak.uraian_pekerjaan }}</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <p class="text-xs text-muted-foreground">Tanggal Mulai</p>
                        <p>{{ fmtDate(lihatKontrak.tanggal_mulai) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Tanggal Selesai</p>
                        <p>{{ fmtDate(lihatKontrak.tanggal_akhir) }}</p>
                    </div>
                </div>
                <div v-if="lihatKontrak.dokumen_url">
                    <p class="text-xs text-muted-foreground">Dokumen Kontrak</p>
                    <a :href="lihatKontrak.dokumen_url" target="_blank" class="inline-flex items-center gap-1 text-sm text-primary hover:underline">
                        <Paperclip class="size-3.5" />
                        {{ lihatKontrak.dokumen_name ?? 'Lihat Dokumen' }}
                    </a>
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="viewKontrakOpen = false">Tutup</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- ── View Progress Entry Modal ────────────────────────────────────────── -->
    <Dialog :open="viewProgressEntry !== null" @update:open="(v) => { if (!v) viewProgressEntry = null; }">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Detail Progress</DialogTitle>
            </DialogHeader>
            <div v-if="viewProgressEntry" class="flex flex-col gap-3 text-sm">
                <div>
                    <p class="text-xs text-muted-foreground">Uraian Progress</p>
                    <p class="whitespace-pre-wrap">{{ viewProgressEntry.uraian_progress || '-' }}</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <p class="text-xs text-muted-foreground">Tanggal Mulai</p>
                        <p>{{ fmtDate(viewProgressEntry.tanggal_mulai) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Tanggal Selesai</p>
                        <p>{{ fmtDate(viewProgressEntry.tanggal_akhir) }}</p>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Persentase</p>
                    <span :class="persenColor(calcPersen(viewProgressEntry.tanggal_akhir, lihatKontrak?.tanggal_mulai, lihatKontrak?.tanggal_akhir))">
                        {{ calcPersen(viewProgressEntry.tanggal_akhir, lihatKontrak?.tanggal_mulai, lihatKontrak?.tanggal_akhir) !== null
                            ? calcPersen(viewProgressEntry.tanggal_akhir, lihatKontrak?.tanggal_mulai, lihatKontrak?.tanggal_akhir) + '%'
                            : '-' }}
                    </span>
                </div>
                <div v-if="viewProgressEntry.file_url">
                    <p class="text-xs text-muted-foreground">Berkas</p>
                    <a :href="viewProgressEntry.file_url" target="_blank" class="inline-flex items-center gap-1 text-sm text-primary hover:underline">
                        <Paperclip class="size-3.5" />
                        {{ viewProgressEntry.file_name ?? 'Lihat Berkas' }}
                    </a>
                </div>
                <div v-else>
                    <p class="text-xs text-muted-foreground">Berkas</p>
                    <p class="text-muted-foreground">—</p>
                </div>
                <div v-if="viewProgressEntry.status && viewProgressEntry.status !== 'draft'">
                    <p class="text-xs text-muted-foreground">Status Persetujuan</p>
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="viewProgressEntry.status === 'approved'
                                ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'"
                        >
                            {{ viewProgressEntry.status === 'approved' ? 'Disetujui' : 'Ditolak' }}
                        </span>
                        <span v-if="viewProgressEntry.reviewed_by" class="text-xs text-muted-foreground">oleh {{ viewProgressEntry.reviewed_by }}</span>
                    </div>
                </div>
                <div v-if="viewProgressEntry.kabag_comment">
                    <p class="text-xs text-muted-foreground">Komentar Kabag</p>
                    <p class="whitespace-pre-wrap rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">{{ viewProgressEntry.kabag_comment }}</p>
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="viewProgressEntry = null">Tutup</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- ── Reject Progress Modal ────────────────────────────────────────────── -->
    <Dialog :open="rejectDialogOpen" @update:open="(v) => { if (!v) { rejectDialogOpen = false; rejectEntry = null; } }">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Tolak Progress</DialogTitle>
                <p class="text-xs text-muted-foreground">Berikan catatan perbaikan yang harus dilakukan oleh staff</p>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="submitReject">
                <div v-if="rejectEntry" class="rounded-md bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
                    {{ rejectEntry.uraian_progress?.slice(0, 100) }}
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label>Komentar / Catatan Perbaikan <span class="text-red-500">*</span></Label>
                    <textarea
                        v-model="rejectForm.kabag_comment"
                        rows="4"
                        placeholder="Tuliskan catatan apa yang perlu diperbaiki..."
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                    />
                    <p v-if="rejectForm.errors.kabag_comment" class="text-xs text-red-500">{{ rejectForm.errors.kabag_comment }}</p>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="rejectDialogOpen = false; rejectEntry = null;">Batal</Button>
                    <Button type="submit" variant="destructive" :disabled="rejectForm.processing">Kirim Penolakan</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- ── Bulk Reject Modal ────────────────────────────────────────────────── -->
    <Dialog :open="bulkRejectOpen" @update:open="(v) => { if (!v) bulkRejectOpen = false; }">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Tolak Semua Draft</DialogTitle>
                <p class="text-xs text-muted-foreground">Semua progress berstatus Draft pada kontrak ini akan ditolak sekaligus</p>
            </DialogHeader>
            <div class="flex flex-col gap-3">
                <div class="flex flex-col gap-1.5">
                    <Label>Catatan Penolakan <span class="text-red-500">*</span></Label>
                    <textarea
                        v-model="bulkRejectComment"
                        rows="4"
                        placeholder="Tuliskan catatan apa yang perlu diperbaiki..."
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                    />
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="bulkRejectOpen = false">Batal</Button>
                <Button
                    variant="destructive"
                    :disabled="!bulkRejectComment.trim()"
                    @click="submitBulkReject"
                >Kirim Penolakan</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- ── Delete Confirm ───────────────────────────────────────────────────── -->
    <Dialog :open="deleteEntryId !== null" @update:open="(v) => { if (!v) deleteEntryId = null; }">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Hapus Progress?</DialogTitle>
            </DialogHeader>
            <p class="text-sm text-muted-foreground">
                Progress "<strong>{{ deleteEntryLabel }}</strong>" akan dihapus dari tampilan (data tetap tersimpan di database untuk keperluan pelacakan).
            </p>
            <DialogFooter>
                <Button variant="outline" @click="deleteEntryId = null">Batal</Button>
                <Button variant="destructive" @click="confirmDelete">Hapus</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
