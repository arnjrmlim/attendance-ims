<?php
/**
 * Manual Attendance — Real-time AJAX index
 * Route: GET /manual-attendance
 */
$isAdminHr = $isAdminHr ?? has_role(['administrator', 'hr']);
$csrfToken = csrf_token();
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-semibold mb-0"><i class="bi bi-person-badge me-2"></i>Manual Attendance</h4>
        <small class="text-muted">
            <?= $isAdminHr ? 'Manage employee manual attendance requests and direct entries' : 'Your manual attendance records' ?>
        </small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($isAdminHr): ?>
            <button class="btn btn-primary btn-sm" id="btnOpenCreate">
                <i class="bi bi-plus-lg"></i> Create Manual Entry
            </button>
        <?php endif; ?>
        <button class="btn btn-outline-primary btn-sm" id="btnOpenRequest">
            <i class="bi bi-send"></i> Submit Attendance
        </button>
    </div>
</div>

<!-- Toast notification -->
<div id="toastWrap" class="position-fixed top-0 end-0 p-3" style="z-index:1090">
    <div id="liveToast" class="toast align-items-center border-0" role="alert" aria-live="assertive">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="toastMsg"></div>
            <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="panel p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-sm-3">
            <label class="form-label small mb-1">Date From</label>
            <input type="date" class="form-control form-control-sm" id="fDateFrom">
        </div>
        <div class="col-sm-3">
            <label class="form-label small mb-1">Date To</label>
            <input type="date" class="form-control form-control-sm" id="fDateTo">
        </div>
        <?php if ($isAdminHr): ?>
        <div class="col-sm-3">
            <label class="form-label small mb-1">Status</label>
            <select class="form-select form-select-sm" id="fStatus">
                <option value="">All Statuses</option>
                <option value="Pending">Pending</option>
                <option value="Approved">Approved</option>
                <option value="Rejected">Rejected</option>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-sm-auto d-flex gap-2">
            <button class="btn btn-sm btn-secondary" id="btnFilter">
                <i class="bi bi-search"></i> Filter
            </button>
            <button class="btn btn-sm btn-outline-secondary" id="btnReset">
                <i class="bi bi-x-circle"></i> Reset
            </button>
        </div>
    </div>
</div>

<!-- Requests table panel -->
<div class="panel mb-3">
    <div class="d-flex justify-content-between align-items-center p-3 border-bottom flex-wrap gap-2">
        <h6 class="mb-0 fw-semibold">Attendance Records</h6>
        <?php if ($isAdminHr): ?>
        <div class="d-flex gap-2 align-items-center" id="bulkBar" style="display:none!important">
            <span class="text-muted small" id="selectedCount">0 selected</span>
            <input type="text" class="form-control form-control-sm" id="bulkRemarks"
                   placeholder="Remarks (optional)" style="width:200px">
            <button class="btn btn-sm btn-success" id="btnBulkApprove">
                <i class="bi bi-check-all"></i> Approve
            </button>
            <button class="btn btn-sm btn-danger" id="btnBulkReject">
                <i class="bi bi-x-circle"></i> Reject
            </button>
        </div>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" id="requestsTable">
            <thead class="table-light">
                <tr>
                    <?php if ($isAdminHr): ?>
                    <th width="36"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                    <?php endif; ?>
                    <th>Employee</th>
                    <th>Dept</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Submitted</th>
                    <?php if ($isAdminHr): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody id="requestsTbody">
                <tr><td colspan="9" class="text-center py-4">
                    <span class="spinner-border spinner-border-sm me-2"></span>Loading…
                </td></tr>
            </tbody>
        </table>
    </div>

    <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2"
         id="requestsPagination"></div>
</div>

<!-- Admin direct entries panel (admin/hr only) -->
<?php if ($isAdminHr): ?>
<div class="panel" id="adminPanel">
    <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
        <h6 class="mb-0 fw-semibold">Direct Entries</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" id="adminTable">
            <thead class="table-light">
                <tr>
                    <th>Employee</th><th>Date</th>
                    <th>Time In</th><th>Break Out</th><th>Break In</th>
                    <th>Time Out</th><th>OT In</th><th>OT Out</th>
                    <th>Status</th><th>Method</th><th>By</th><th>Reason</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="adminTbody">
                <tr><td colspan="13" class="text-center py-4">
                    <span class="spinner-border spinner-border-sm me-2"></span>Loading…
                </td></tr>
            </tbody>
        </table>
    </div>
    <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2"
         id="adminPagination"></div>
</div>
<?php endif; ?>

<!-- ── Approve modal ─────────────────────────────────────────────── -->
<?php if ($isAdminHr): ?>
<div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-success" id="approveModalLabel">
                    <i class="bi bi-check-circle me-2"></i>Approve Request
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Approve manual attendance request for
                    <strong id="approveEmpName"></strong> on <strong id="approveDate"></strong>?
                </p>
                <p class="text-muted small mb-2">
                    This will update the employee's attendance record immediately.
                </p>
                <label class="form-label">Remarks <span class="text-muted">(optional)</span></label>
                <textarea class="form-control" id="approveRemarks" rows="2"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success btn-sm" id="btnConfirmApprove">
                    <span class="spinner-border spinner-border-sm d-none me-1" id="approveSpinner"></span>
                    Approve
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Reject modal ──────────────────────────────────────────────── -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger" id="rejectModalLabel">
                    <i class="bi bi-x-circle me-2"></i>Reject Request
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Reject manual attendance request for
                    <strong id="rejectEmpName"></strong> on <strong id="rejectDate"></strong>?
                </p>
                <label class="form-label">Reason <span class="text-danger">*</span></label>
                <textarea class="form-control" id="rejectRemarks" rows="2" required></textarea>
                <div class="invalid-feedback d-none" id="rejectRemarksError">
                    Please enter a reason for rejection.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="btnConfirmReject">
                    <span class="spinner-border spinner-border-sm d-none me-1" id="rejectSpinner"></span>
                    Reject
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Create Entry modal (admin/hr) ──────────────────────────────── -->
<?php if ($isAdminHr): ?>
<div class="modal fade" id="createModal" tabindex="-1" aria-labelledby="createModalLabel" aria-modal="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createModalLabel">
                    <i class="bi bi-calendar-plus me-2"></i>
                    <span id="createModalTitle">Create Manual Attendance</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Inline warning banner -->
                <div class="alert alert-warning py-2 d-none" id="createWarning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <span id="createWarningMsg"></span>
                </div>

                <input type="hidden" id="createRecordId">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Employee <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="createEmployee" required>
                            <option value="">— Select Employee —</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control form-control-sm" id="createDate"
                               max="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select form-select-sm" id="createStatus">
                            <option value="present">Present</option>
                            <option value="half_day">Half Day</option>
                            <option value="absent">Absent</option>
                            <option value="leave">Leave</option>
                            <option value="holiday">Holiday</option>
                        </select>
                    </div>

                    <!-- ── Time fields: all 6 types ─────────────────── -->
                    <div class="col-12">
                        <hr class="my-1">
                        <small class="text-muted text-uppercase fw-semibold" style="letter-spacing:.06em;font-size:.7rem;">
                            Time Records — fill only the slots that apply
                        </small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">
                            Time In <span class="text-muted fw-normal">— earliest</span>
                        </label>
                        <input type="time" class="form-control form-control-sm" id="createTimeIn" name="time_in">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">
                            Break Out <span class="text-muted fw-normal">— earliest</span>
                        </label>
                        <input type="time" class="form-control form-control-sm" id="createBreakOut" name="break_out">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">
                            Break In <span class="text-muted fw-normal">— latest</span>
                        </label>
                        <input type="time" class="form-control form-control-sm" id="createBreakIn" name="break_in">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">
                            Time Out <span class="text-muted fw-normal">— latest</span>
                        </label>
                        <input type="time" class="form-control form-control-sm" id="createTimeOut" name="time_out">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">
                            OT In <span class="text-muted fw-normal">— earliest</span>
                        </label>
                        <input type="time" class="form-control form-control-sm" id="createOtIn" name="overtime_in">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">
                            OT Out <span class="text-muted fw-normal">— latest</span>
                        </label>
                        <input type="time" class="form-control form-control-sm" id="createOtOut" name="overtime_out">
                    </div>
                    <div class="col-12">
                        <div class="invalid-feedback d-none" id="timeOrderError"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Method</label>
                        <select class="form-select form-select-sm" id="createMethod">
                            <option value="Manual Entry">Manual Entry</option>
                            <option value="PIN">PIN</option>
                            <option value="QR Code">QR Code</option>
                            <option value="RFID">RFID</option>
                            <option value="System Generated">System Generated</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" id="createReason" rows="2"
                                  placeholder="Why is this manual entry needed?" required></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Admin Remarks</label>
                        <textarea class="form-control form-control-sm" id="createAdminRemarks" rows="2"
                                  placeholder="Internal notes (not shown to employee)"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnSaveEntry">
                    <span class="spinner-border spinner-border-sm d-none me-1" id="saveSpinner"></span>
                    <i class="bi bi-save me-1"></i><span id="btnSaveLabel">Save Entry</span>
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Submit Request modal (all roles) ──────────────────────────── -->
<div class="modal fade" id="requestModal" tabindex="-1" aria-labelledby="requestModalLabel" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="requestModalLabel">Submit Manual Attendance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">

                <p class="att-info-note mb-3">
                    <i class="bi bi-info-circle"></i>
                    Recorded at the current server time. No approval required.
                </p>

                <p class="form-label fw-semibold mb-2">Select Attendance Type</p>
                <div class="row g-2 mb-3">
                    <?php
                    $modalTypes = [
                        ['time_in',     'bi-box-arrow-in-right', 'Time In'],
                        ['break_out',   'bi-door-open',          'Break Out'],
                        ['break_in',    'bi-door-closed',        'Break In'],
                        ['time_out',    'bi-box-arrow-right',    'Time Out'],
                        ['overtime_in', 'bi-plus-circle',        'OT In'],
                        ['overtime_out','bi-dash-circle',        'OT Out'],
                    ];
                    foreach ($modalTypes as $i => [$val, $icon, $label]):
                    ?>
                    <div class="col-4">
                        <input type="radio" class="btn-check" name="reqType"
                               id="req_<?= $val ?>" value="<?= $val ?>"
                               <?= $i === 0 ? 'checked' : '' ?>>
                        <label class="att-type-btn" for="req_<?= $val ?>"
                               style="min-height:62px;padding:.5rem .4rem;font-size:.75rem;">
                            <i class="bi <?= $icon ?>" style="font-size:1.1rem;"></i>
                            <?= $label ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Selection indicator -->
                <div class="att-selection-bar mb-3" id="modalSelectionBar">
                    <i class="bi bi-check2-circle"></i>
                    <span>Selected: <strong id="modalSelectionLabel">Time In</strong></span>
                </div>

                <div class="d-flex gap-3 text-muted small">
                    <span><i class="bi bi-calendar3 me-1"></i><strong id="reqDate"><?= date('M j, Y') ?></strong></span>
                    <span><i class="bi bi-clock me-1"></i><strong id="reqTime"><?= date('h:i A') ?></strong></span>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnSubmitRequest">
                    <span class="spinner-border spinner-border-sm d-none me-1" id="reqSpinner"></span>
                    <i class="bi bi-send me-1"></i>Submit
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Delete confirmation modal (admin/hr) ───────────────────────── -->
<?php if ($isAdminHr): ?>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger" id="deleteModalLabel">
                    <i class="bi bi-trash me-2"></i>Delete Entry
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete the manual attendance entry for
                <strong id="deleteEmpName"></strong> on <strong id="deleteDate"></strong>?
                <br><small class="text-danger">This will also revert the attendance summary.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="btnConfirmDelete">
                    <span class="spinner-border spinner-border-sm d-none me-1" id="deleteSpinner"></span>
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Employee data island for create/edit modal -->
<?php if ($isAdminHr && !empty($employees)): ?>
<script>window.__MA_EMPLOYEES__ = <?= json_encode(array_values($employees), JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php endif; ?>

<script>
/* ================================================================
   Manual Attendance — Real-time AJAX controller
   ================================================================ */

const IS_ADMIN = <?= $isAdminHr ? 'true' : 'false' ?>;
const CSRF     = <?= json_encode($csrfToken) ?>;

const API = {
    stats:      <?= json_encode(url('manual-attendance/api/stats')) ?>,
    list:       <?= json_encode(url('manual-attendance/api/list')) ?>,
    adminList:  <?= json_encode(url('manual-attendance/api/admin-list')) ?>,
    store:      <?= json_encode(url('manual-attendance/api/store')) ?>,
    update:     <?= json_encode(url('manual-attendance/api/update')) ?>,
    del:        <?= json_encode(url('manual-attendance/api/delete')) ?>,
    request:    <?= json_encode(url('manual-attendance/api/request')) ?>,
    approve:    <?= json_encode(url('manual-attendance/api/approve')) ?>,
    reject:     <?= json_encode(url('manual-attendance/api/reject')) ?>,
    bulk:       <?= json_encode(url('manual-attendance/api/bulk-action')) ?>,
    employees:  <?= json_encode(url('manual-attendance/api/list')) ?>,
};

/* ── State ────────────────────────────────────────────────────── */
let reqPage  = 1, reqPerPage = 20;
let admPage  = 1, admPerPage = 20;
let activeApproveId  = null;
let activeRejectId   = null;
let activeDeleteId   = null;
let editMode         = false;

/* ── Toast ────────────────────────────────────────────────────── */
function showToast(msg, type = 'success') {
    const el  = document.getElementById('liveToast');
    const bod = document.getElementById('toastMsg');
    el.className = `toast align-items-center border-0 text-bg-${type}`;
    bod.textContent = msg;
    bootstrap.Toast.getOrCreateInstance(el, { delay: 4000 }).show();
}

/* ── Generic fetch helper ─────────────────────────────────────── */
async function api(method, url, body = null) {
    const opts = {
        method,
        headers: { 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
    };
    if (body) {
        const fd = new FormData();
        Object.entries(body).forEach(([k, v]) => {
            if (Array.isArray(v)) v.forEach(i => fd.append(k + '[]', i));
            else fd.append(k, v ?? '');
        });
        opts.body = fd;
    }
    const res  = await fetch(url, opts);
    const data = await res.json();
    if (!data.success) throw new Error(data.error || 'Server error.');
    return data;
}

/* ── Master refresh ───────────────────────────────────────────── */
function refreshAll(page) {
    loadRequests(page ?? reqPage);
    if (IS_ADMIN) loadAdminEntries(page ?? admPage);
}
function getFilters() {
    return {
        date_from: document.getElementById('fDateFrom')?.value  ?? '',
        date_to:   document.getElementById('fDateTo')?.value    ?? '',
        status:    document.getElementById('fStatus')?.value    ?? '',
    };
}

function statusBadge(s) {
    const map = { Pending: 'warning text-dark', Approved: 'success', Rejected: 'danger' };
    return `<span class="badge bg-${map[s] ?? 'secondary'}">${s}</span>`;
}

function typeBadge(t) {
    const map = {
        'time_in':     ['info',    'Time In'],
        'break_out':   ['warning', 'Break Out'],
        'break_in':    ['success', 'Break In'],
        'time_out':    ['danger',  'Time Out'],
        'overtime_in': ['primary', 'OT In'],
        'overtime_out':['dark',    'OT Out'],
        'lunch_out':   ['warning', 'Break Out'],
        'lunch_in':    ['success', 'Break In'],
    };
    if (!t) return '<span class="badge bg-secondary text-muted">—</span>';
    const [color, label] = map[t] ?? ['secondary', t];
    return `<span class="badge bg-${color}">${label}</span>`;
}

function esc(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
}

async function loadRequests(page = 1) {
    reqPage = page;
    const tbody = document.getElementById('requestsTbody');
    const cols  = IS_ADMIN ? 8 : 6;
    tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center py-4">
        <span class="spinner-border spinner-border-sm me-2"></span>Loading…</td></tr>`;

    try {
        const params = new URLSearchParams({ ...getFilters(), page, per_page: reqPerPage });
        const d = await api('GET', API.list + '?' + params);
        renderRequestRows(d.rows ?? []);
        renderPagination('requestsPagination', d.total, page, reqPerPage, loadRequests);
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center text-danger py-3">
            <i class="bi bi-exclamation-triangle me-1"></i>${esc(e.message)}</td></tr>`;
    }
}

function renderRequestRows(rows) {
    const tbody = document.getElementById('requestsTbody');
    const cols  = IS_ADMIN ? 8 : 6;
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center text-muted py-4">No records found.</td></tr>`;
        updateBulkBar();
        return;
    }
    tbody.innerHTML = rows.map(r => `
        <tr data-id="${esc(r.id)}">
            ${IS_ADMIN ? `<td><input type="checkbox" class="form-check-input row-check"
                data-id="${esc(r.id)}"></td>` : ''}
            <td>
                <div class="fw-semibold small">${esc(r.employee_name)}</div>
                <div class="text-muted" style="font-size:.75rem">${esc(r.employee_number)}</div>
            </td>
            <td><small>${esc(r.department_name ?? '—')}</small></td>
            <td>${typeBadge(r.request_type ?? '')}</td>
            <td><small>${esc(r.request_date)}</small></td>
            <td><small>${esc(r.requested_time ?? '—')}</small></td>
            <td><small>${esc((r.created_at ?? '').substring(0,16))}</small></td>
            ${IS_ADMIN ? `<td>
                ${r.status === 'Pending' ? `
                <button class="btn btn-xs btn-success me-1 btn-approve-req"
                        data-id="${esc(r.id)}"
                        data-name="${esc(r.employee_name)}"
                        data-date="${esc(r.request_date)}"
                        title="Approve">
                    <i class="bi bi-check-lg"></i>
                </button>
                <button class="btn btn-xs btn-danger btn-reject-req"
                        data-id="${esc(r.id)}"
                        data-name="${esc(r.employee_name)}"
                        data-date="${esc(r.request_date)}"
                        title="Reject">
                    <i class="bi bi-x-lg"></i>
                </button>` : ''}
            </td>` : ''}
        </tr>`).join('');

    // Bind row-level buttons
    tbody.querySelectorAll('.btn-approve-req').forEach(btn => btn.addEventListener('click', () => openApprove(btn)));
    tbody.querySelectorAll('.btn-reject-req').forEach(btn => btn.addEventListener('click', () => openReject(btn)));
    tbody.querySelectorAll('.row-check').forEach(cb => cb.addEventListener('change', updateBulkBar));
    updateBulkBar();
}

/* ── Admin entries table ──────────────────────────────────────── */
async function loadAdminEntries(page = 1) {
    if (!IS_ADMIN) return;
    admPage = page;
    const tbody = document.getElementById('adminTbody');
    tbody.innerHTML = `<tr><td colspan="13" class="text-center py-4">
        <span class="spinner-border spinner-border-sm me-2"></span>Loading…</td></tr>`;
    try {
        const filters = getFilters();
        const params  = new URLSearchParams({
            date_from: filters.date_from,
            date_to:   filters.date_to,
            page, per_page: admPerPage,
        });
        const d = await api('GET', API.adminList + '?' + params);
        renderAdminRows(d.rows ?? []);
        renderPagination('adminPagination', d.total, page, admPerPage, loadAdminEntries);
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-3">
            <i class="bi bi-exclamation-triangle me-1"></i>${esc(e.message)}</td></tr>`;
    }
}

function renderAdminRows(rows) {
    const tbody = document.getElementById('adminTbody');
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="13" class="text-center text-muted py-4">No direct entries found.</td></tr>`;
        return;
    }
    tbody.innerHTML = rows.map(r => `
        <tr data-id="${esc(r.id)}">
            <td>
                <div class="fw-semibold small">${esc(r.employee_name)}</div>
                <div class="text-muted" style="font-size:.75rem">${esc(r.employee_number)}</div>
            </td>
            <td><small>${esc(r.attendance_date)}</small></td>
            <td><small>${r.time_in    ? esc(fmtTime(r.time_in))    : '—'}</small></td>
            <td><small>${r.break_out  ? esc(fmtTime(r.break_out))  : '—'}</small></td>
            <td><small>${r.break_in   ? esc(fmtTime(r.break_in))   : '—'}</small></td>
            <td><small>${r.time_out   ? esc(fmtTime(r.time_out))   : '—'}</small></td>
            <td><small>${r.overtime_in  ? esc(fmtTime(r.overtime_in))  : '—'}</small></td>
            <td><small>${r.overtime_out ? esc(fmtTime(r.overtime_out)) : '—'}</small></td>
            <td><span class="badge bg-secondary">${esc(r.attendance_status)}</span></td>
            <td><small>${esc(r.method)}</small></td>
            <td><small>${esc(r.created_by_name ?? '—')}</small></td>
            <td><small title="${esc(r.admin_remarks ?? '')}">${esc(truncate(r.reason, 30))}</small></td>
            <td class="text-nowrap">
                <button class="btn btn-xs btn-outline-primary me-1 btn-edit-admin"
                        data-row='${JSON.stringify(r).replace(/'/g, "&#39;")}'
                        title="Edit"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-xs btn-outline-danger btn-delete-admin"
                        data-id="${esc(r.id)}"
                        data-name="${esc(r.employee_name)}"
                        data-date="${esc(r.attendance_date)}"
                        title="Delete"><i class="bi bi-trash"></i></button>
            </td>
        </tr>`).join('');

    tbody.querySelectorAll('.btn-edit-admin').forEach(btn => btn.addEventListener('click', () => {
        openEditEntry(JSON.parse(btn.dataset.row));
    }));
    tbody.querySelectorAll('.btn-delete-admin').forEach(btn => btn.addEventListener('click', () => openDelete(btn)));
}

/* ── Pagination renderer ──────────────────────────────────────── */
function renderPagination(containerId, total, page, perPage, loadFn) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const pages = Math.max(1, Math.ceil(total / perPage));
    const from  = Math.min((page - 1) * perPage + 1, total);
    const to    = Math.min(page * perPage, total);

    let pagerHtml = '';
    if (pages > 1) {
        pagerHtml = '<nav><ul class="pagination pagination-sm mb-0">';
        const start = Math.max(1, page - 2);
        const end   = Math.min(pages, page + 2);
        if (start > 1)  pagerHtml += `<li class="page-item"><a class="page-link" data-page="1">1</a></li>
                                       ${start > 2 ? '<li class="page-item disabled"><span class="page-link">…</span></li>' : ''}`;
        for (let p = start; p <= end; p++) {
            pagerHtml += `<li class="page-item ${p === page ? 'active' : ''}">
                <a class="page-link" data-page="${p}">${p}</a></li>`;
        }
        if (end < pages) pagerHtml += `${end < pages - 1 ? '<li class="page-item disabled"><span class="page-link">…</span></li>' : ''}
                                        <li class="page-item"><a class="page-link" data-page="${pages}">${pages}</a></li>`;
        pagerHtml += '</ul></nav>';
    }

    container.innerHTML = `
        <small class="text-muted">
            ${total === 0 ? 'No records' : `Showing ${from}–${to} of ${total}`}
        </small>
        ${pagerHtml}`;

    container.querySelectorAll('[data-page]').forEach(a => {
        a.style.cursor = 'pointer';
        a.addEventListener('click', () => loadFn(parseInt(a.dataset.page)));
    });
}

/* ── Approve / Reject modal logic ────────────────────────────── */
function openApprove(btn) {
    activeApproveId = btn.dataset.id;
    document.getElementById('approveEmpName').textContent = btn.dataset.name;
    document.getElementById('approveDate').textContent    = btn.dataset.date;
    document.getElementById('approveRemarks').value       = '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('approveModal')).show();
}

function openReject(btn) {
    activeRejectId = btn.dataset.id;
    document.getElementById('rejectEmpName').textContent = btn.dataset.name;
    document.getElementById('rejectDate').textContent    = btn.dataset.date;
    document.getElementById('rejectRemarks').value       = '';
    document.getElementById('rejectRemarks').classList.remove('is-invalid');
    document.getElementById('rejectRemarksError').classList.add('d-none');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('rejectModal')).show();
}

document.getElementById('btnConfirmApprove')?.addEventListener('click', async () => {
    const btn = document.getElementById('btnConfirmApprove');
    const sp  = document.getElementById('approveSpinner');
    btn.disabled = true; sp.classList.remove('d-none');
    try {
        const res = await api('POST', API.approve, {
            id:            activeApproveId,
            admin_remarks: document.getElementById('approveRemarks').value,
        });
        bootstrap.Modal.getInstance(document.getElementById('approveModal')).hide();
        showToast(res.message, 'success');
        refreshAll();
    } catch (e) { showToast(e.message || 'An unexpected error occurred.', 'danger'); }
    finally { btn.disabled = false; sp.classList.add('d-none'); }
});

document.getElementById('btnConfirmReject')?.addEventListener('click', async () => {
    const remarks = document.getElementById('rejectRemarks').value.trim();
    if (!remarks) {
        document.getElementById('rejectRemarks').classList.add('is-invalid');
        document.getElementById('rejectRemarksError').classList.remove('d-none');
        return;
    }
    const btn = document.getElementById('btnConfirmReject');
    const sp  = document.getElementById('rejectSpinner');
    btn.disabled = true; sp.classList.remove('d-none');
    try {
        const res = await api('POST', API.reject, {
            id:            activeRejectId,
            admin_remarks: remarks,
        });
        bootstrap.Modal.getInstance(document.getElementById('rejectModal')).hide();
        showToast(res.message, 'success');
        refreshAll();
    } catch (e) { showToast(e.message || 'An unexpected error occurred.', 'danger'); }
    finally { btn.disabled = false; sp.classList.add('d-none'); }
});

/* ── Delete modal logic ───────────────────────────────────────── */
function openDelete(btn) {
    activeDeleteId = btn.dataset.id;
    document.getElementById('deleteEmpName').textContent = btn.dataset.name;
    document.getElementById('deleteDate').textContent    = btn.dataset.date;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteModal')).show();
}

document.getElementById('btnConfirmDelete')?.addEventListener('click', async () => {
    const btn = document.getElementById('btnConfirmDelete');
    const sp  = document.getElementById('deleteSpinner');
    btn.disabled = true; sp.classList.remove('d-none');
    try {
        const res = await api('POST', API.del, { id: activeDeleteId });
        bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
        showToast(res.message, 'success');
        refreshAll();
    } catch (e) { showToast(e.message || 'An unexpected error occurred.', 'danger'); }
    finally { btn.disabled = false; sp.classList.add('d-none'); }
});

/* ── Create / Edit entry modal ────────────────────────────────── */
// Employee list injected server-side — see data island above the <script> tag
const EMPLOYEES = window.__MA_EMPLOYEES__ ?? [];

function populateEmployeeSelect() {
    if (!IS_ADMIN) return;
    const sel = document.getElementById('createEmployee');
    if (!sel || sel.options.length > 1) return; // already populated
    EMPLOYEES.forEach(e => {
        const opt = document.createElement('option');
        opt.value = e.id;
        opt.textContent = `${e.employee_number} — ${e.name}`;
        sel.appendChild(opt);
    });
}

function openCreateEntry() {
    editMode = false;
    document.getElementById('createModalTitle').textContent = 'Create Manual Attendance';
    document.getElementById('btnSaveLabel').textContent     = 'Save Entry';
    document.getElementById('createRecordId').value         = '';
    document.getElementById('createDate').value             = new Date().toISOString().slice(0, 10);
    document.getElementById('createEmployee').value         = '';
    document.getElementById('createEmployee').disabled      = false;
    document.getElementById('createStatus').value           = 'present';
    ['createTimeIn','createBreakOut','createBreakIn','createTimeOut','createOtIn','createOtOut'].forEach(id => {
        document.getElementById(id).value = '';
    });
    document.getElementById('createMethod').value           = 'Manual Entry';
    document.getElementById('createReason').value           = '';
    document.getElementById('createAdminRemarks').value     = '';
    document.getElementById('createWarning').classList.add('d-none');
    document.getElementById('timeOrderError').classList.add('d-none');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('createModal')).show();
}

function openEditEntry(row) {
    editMode = true;
    document.getElementById('createModalTitle').textContent = 'Edit Manual Attendance';
    document.getElementById('btnSaveLabel').textContent     = 'Update Entry';
    document.getElementById('createRecordId').value         = row.id;
    document.getElementById('createDate').value             = row.attendance_date ?? '';
    document.getElementById('createEmployee').value         = row.employee_id ?? '';
    document.getElementById('createEmployee').disabled      = true;
    document.getElementById('createStatus').value           = row.attendance_status ?? 'present';
    document.getElementById('createTimeIn').value           = timeVal(row.time_in);
    document.getElementById('createBreakOut').value         = timeVal(row.break_out);
    document.getElementById('createBreakIn').value          = timeVal(row.break_in);
    document.getElementById('createTimeOut').value          = timeVal(row.time_out);
    document.getElementById('createOtIn').value             = timeVal(row.overtime_in);
    document.getElementById('createOtOut').value            = timeVal(row.overtime_out);
    document.getElementById('createMethod').value           = row.method ?? 'Manual Entry';
    document.getElementById('createReason').value           = row.reason ?? '';
    document.getElementById('createAdminRemarks').value     = row.admin_remarks ?? '';
    document.getElementById('createWarning').classList.add('d-none');
    document.getElementById('timeOrderError').classList.add('d-none');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('createModal')).show();
}

// Extract HH:MM from a "Y-m-d H:i:s" datetime string (or return '')
function timeVal(dt) {
    if (!dt) return '';
    const parts = dt.split(' ');
    return parts[1] ? parts[1].substring(0, 5) : '';
}

// Re-enable employee on modal hidden
document.getElementById('createModal')?.addEventListener('hidden.bs.modal', () => {
    document.getElementById('createEmployee').disabled = false;
});
document.getElementById('createModal')?.addEventListener('show.bs.modal', () => {
    populateEmployeeSelect();
});

document.getElementById('btnSaveEntry')?.addEventListener('click', async () => {
    const empId   = document.getElementById('createEmployee').value;
    const date    = document.getElementById('createDate').value;
    const reason  = document.getElementById('createReason').value.trim();
    const errEl   = document.getElementById('timeOrderError');

    // Read all 6 time fields
    const timeIn   = document.getElementById('createTimeIn').value;
    const breakOut = document.getElementById('createBreakOut').value;
    const breakIn  = document.getElementById('createBreakIn').value;
    const timeOut  = document.getElementById('createTimeOut').value;
    const otIn     = document.getElementById('createOtIn').value;
    const otOut    = document.getElementById('createOtOut').value;

    // Client-side sequence validation
    const seq = [
        ['Time In',    timeIn],
        ['Break Out',  breakOut],
        ['Break In',   breakIn],
        ['Time Out',   timeOut],
        ['OT In',      otIn],
        ['OT Out',     otOut],
    ].filter(([, v]) => v);

    let seqErr = null;
    for (let i = 1; i < seq.length; i++) {
        if (seq[i][1] < seq[i-1][1]) {
            seqErr = `${seq[i][0]} must be after ${seq[i-1][0]}.`;
            break;
        }
    }

    if (!empId || !date || !reason) {
        showToast('Employee, date, and reason are required.', 'warning');
        return;
    }
    if (seqErr) {
        errEl.textContent = seqErr;
        errEl.classList.remove('d-none');
        return;
    }
    errEl.classList.add('d-none');

    const btn = document.getElementById('btnSaveEntry');
    const sp  = document.getElementById('saveSpinner');
    btn.disabled = true; sp.classList.remove('d-none');

    const body = {
        employee_id:       empId,
        attendance_date:   date,
        attendance_status: document.getElementById('createStatus').value,
        time_in:           timeIn,
        break_out:         breakOut,
        break_in:          breakIn,
        time_out:          timeOut,
        overtime_in:       otIn,
        overtime_out:      otOut,
        method:            document.getElementById('createMethod').value,
        reason:            reason,
        admin_remarks:     document.getElementById('createAdminRemarks').value,
    };

    try {
        let res;
        if (editMode) {
            body.id = document.getElementById('createRecordId').value;
            res = await api('POST', API.update, body);
        } else {
            res = await api('POST', API.store, body);
        }

        if (res.warnings?.length) {
            document.getElementById('createWarningMsg').textContent = res.warnings.join(' · ');
            document.getElementById('createWarning').classList.remove('d-none');
            await new Promise(r => setTimeout(r, 1800));
        }

        bootstrap.Modal.getInstance(document.getElementById('createModal')).hide();
        showToast(res.message, 'success');
        refreshAll();
    } catch (e) {
        const friendly = e.message?.startsWith('SQLSTATE') || e.message?.includes('Unknown column')
            ? 'The record could not be saved. Please contact the system administrator.'
            : (e.message || 'An unexpected error occurred.');
        showToast(friendly, 'danger');
    } finally {
        btn.disabled = false; sp.classList.add('d-none');
    }
});

/* ── Submit Request modal ─────────────────────────────────────── */
/* Keep selection bar label in sync with chosen type */
const MODAL_LABELS = {
    time_in:'Time In', break_out:'Break Out', break_in:'Break In',
    time_out:'Time Out', overtime_in:'Overtime In', overtime_out:'Overtime Out',
};
function syncModalBar() {
    const checked = document.querySelector('input[name="reqType"]:checked');
    const lbl     = document.getElementById('modalSelectionLabel');
    if (checked && lbl) lbl.textContent = MODAL_LABELS[checked.value] ?? checked.value;
}
document.querySelectorAll('input[name="reqType"]').forEach(r => r.addEventListener('change', syncModalBar));

function updateReqClock() {
    const now = new Date();
    const dateStr = now.toISOString().slice(0, 10);
    const h   = now.getHours() % 12 || 12;
    const m   = String(now.getMinutes()).padStart(2, '0');
    const ampm = now.getHours() >= 12 ? 'PM' : 'AM';
    const el = document.getElementById('reqTime');
    const de = document.getElementById('reqDate');
    if (el) el.textContent = `${h}:${m} ${ampm}`;
    if (de) de.textContent = dateStr;
}
// Update clock every 30 s while modal is open
let clockInterval = null;
document.getElementById('requestModal')?.addEventListener('shown.bs.modal', () => {
    updateReqClock();
    syncModalBar();
    clockInterval = setInterval(updateReqClock, 30000);
});
document.getElementById('requestModal')?.addEventListener('hidden.bs.modal', () => {
    clearInterval(clockInterval);
});

document.getElementById('btnSubmitRequest')?.addEventListener('click', async () => {
    const type = document.querySelector('input[name="reqType"]:checked')?.value;
    if (!type) { showToast('Please select a request type.', 'warning'); return; }

    const btn = document.getElementById('btnSubmitRequest');
    const sp  = document.getElementById('reqSpinner');
    btn.disabled = true; sp.classList.remove('d-none');

    try {
        const res = await api('POST', API.request, { request_type: type });
        bootstrap.Modal.getInstance(document.getElementById('requestModal')).hide();
        let msg = res.message;
        if (res.warnings?.length) msg += ' Note: ' + res.warnings.join(' | ');
        showToast(msg, 'success');
        refreshAll();
    } catch (e) {
        const friendly = e.message?.startsWith('SQLSTATE') || e.message?.includes('Unknown column')
            ? 'Attendance could not be processed. Please contact the system administrator.'
            : (e.message || 'An unexpected error occurred.');
        showToast(friendly, 'danger');
    }
    finally { btn.disabled = false; sp.classList.add('d-none'); }
});

/* ── Bulk actions ─────────────────────────────────────────────── */
function updateBulkBar() {
    const bar = document.getElementById('bulkBar');
    if (!bar) return;
    const checked = document.querySelectorAll('.row-check:checked').length;
    document.getElementById('selectedCount').textContent = `${checked} selected`;
    bar.style.display = checked > 0 ? 'flex' : 'none';
    bar.style.removeProperty('display'); // undo the !important trick
    if (checked > 0) bar.classList.remove('d-none');
    else             bar.classList.add('d-none');
}

// Select-all checkbox
document.getElementById('selectAll')?.addEventListener('change', function () {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
    updateBulkBar();
});

async function doBulk(action) {
    const ids     = [...document.querySelectorAll('.row-check:checked')].map(cb => cb.dataset.id);
    const remarks = document.getElementById('bulkRemarks')?.value.trim() ?? '';
    if (!ids.length) { showToast('No records selected.', 'warning'); return; }
    if (action === 'reject' && !remarks) {
        showToast('Please enter a reason for rejection.', 'warning');
        document.getElementById('bulkRemarks')?.focus();
        return;
    }
    try {
        const res = await api('POST', API.bulk, { ids, action, admin_remarks: remarks });
        showToast(res.message, 'success');
        document.getElementById('selectAll').checked = false;
        if (document.getElementById('bulkRemarks')) document.getElementById('bulkRemarks').value = '';
        refreshAll();
    } catch (e) { showToast(e.message || 'An unexpected error occurred.', 'danger'); }
}

document.getElementById('btnBulkApprove')?.addEventListener('click', () => doBulk('approve'));
document.getElementById('btnBulkReject')?.addEventListener('click',  () => doBulk('reject'));

/* ── Filter bar ───────────────────────────────────────────────── */
document.getElementById('btnFilter')?.addEventListener('click', () => refreshAll(1));
document.getElementById('btnReset')?.addEventListener('click', () => {
    ['fDateFrom','fDateTo','fStatus'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    refreshAll(1);
});

/* ── Open modal buttons ───────────────────────────────────────── */
document.getElementById('btnOpenCreate')?.addEventListener('click', openCreateEntry);
document.getElementById('btnOpenRequest')?.addEventListener('click', () => {
    updateReqClock();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('requestModal')).show();
});

/* ── Utility helpers ──────────────────────────────────────────── */
function fmtTime(dt) {
    if (!dt) return '—';
    const d = new Date(dt.replace(' ', 'T'));
    if (isNaN(d)) return dt;
    return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
}
function truncate(s, n) {
    return s && s.length > n ? s.slice(0, n) + '…' : (s ?? '');
}

/* ── Initial load ─────────────────────────────────────────────── */
populateEmployeeSelect();
refreshAll(1);
</script>
