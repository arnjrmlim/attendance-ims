<div class="page-head">
    <div>
        <h1 class="h3 mb-1">Shift Management</h1>
        <div class="text-muted">Define and manage work schedules for all employees.</div>
    </div>
    <div>
        <a href="<?= url('shifts/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Add Shift
        </a>
    </div>
</div>

<!-- Statistics row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-primary bg-opacity-10 h-100">
            <div class="card-body py-3">
                <div class="fw-bold fs-4 text-primary"><?= (int) ($statistics['total'] ?? 0) ?></div>
                <div class="text-muted small">Total Shifts</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-success bg-opacity-10 h-100">
            <div class="card-body py-3">
                <div class="fw-bold fs-4 text-success"><?= (int) ($statistics['active'] ?? 0) ?></div>
                <div class="text-muted small">Active</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-secondary bg-opacity-10 h-100">
            <div class="card-body py-3">
                <div class="fw-bold fs-4 text-secondary"><?= (int) ($statistics['inactive'] ?? 0) ?></div>
                <div class="text-muted small">Inactive</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-warning bg-opacity-10 h-100">
            <div class="card-body py-3">
                <div class="fw-bold fs-4 text-warning"><?= (int) ($statistics['unassigned_employees'] ?? 0) ?></div>
                <div class="text-muted small">Unassigned Employees</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="<?= url('shifts') ?>" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted">Search</label>
                <input type="text" name="q" class="form-control"
                       placeholder="Search by name or description…"
                       value="<?= e($filters['q'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="active"   <?= ($filters['status'] ?? '') === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Type</label>
                <select name="type" class="form-select">
                    <option value="">All Types</option>
                    <option value="regular"  <?= ($filters['type'] ?? '') === 'regular'  ? 'selected' : '' ?>>Regular</option>
                    <option value="night"    <?= ($filters['type'] ?? '') === 'night'    ? 'selected' : '' ?>>Night</option>
                    <option value="flexible" <?= ($filters['type'] ?? '') === 'flexible' ? 'selected' : '' ?>>Flexible</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Search
                </button>
                <a href="<?= url('shifts') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Shift List -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width:200px">Shift Name</th>
                        <th>Type</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Break</th>
                        <th>Hours</th>
                        <th>Grace</th>
                        <th>Status</th>
                        <th>Employees</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($shifts)): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">
                            <i class="bi bi-clock-history fs-2 d-block mb-2"></i>
                            No shifts found. <a href="<?= url('shifts/create') ?>">Add the first shift</a>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($shifts as $shift): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div>
                                    <div class="fw-semibold">
                                        <?= e($shift['name']) ?>
                                        <?php if ((int)($shift['is_default'] ?? 0)): ?>
                                            <span class="badge bg-warning text-dark ms-1" title="Default shift for new employees">
                                                <i class="bi bi-star-fill me-1"></i>Default
                                            </span>
                                        <?php endif; ?>
                                        <?php if ((int)($shift['overnight'] ?? 0)): ?>
                                            <span class="badge bg-info text-dark ms-1">Overnight</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($shift['description'])): ?>
                                        <div class="text-muted small"><?= e($shift['description']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php
                            $typeColors = ['regular' => 'primary', 'night' => 'dark', 'flexible' => 'info'];
                            $typeColor  = $typeColors[$shift['type']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $typeColor ?> bg-opacity-10 text-<?= $typeColor ?> border border-<?= $typeColor ?> border-opacity-25">
                                <?= ucfirst(e($shift['type'])) ?>
                            </span>
                        </td>
                        <td class="text-nowrap">
                            <i class="bi bi-clock text-muted me-1"></i>
                            <?= date('h:i A', strtotime($shift['time_in'])) ?>
                        </td>
                        <td class="text-nowrap">
                            <i class="bi bi-clock text-muted me-1"></i>
                            <?= date('h:i A', strtotime($shift['time_out'])) ?>
                        </td>
                        <td class="text-nowrap text-muted small">
                            <?php if ($shift['lunch_break_start'] && $shift['lunch_break_end']): ?>
                                <?= date('h:i A', strtotime($shift['lunch_break_start'])) ?>
                                – <?= date('h:i A', strtotime($shift['lunch_break_end'])) ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="fw-semibold"><?= number_format((float)$shift['required_hours'], 1) ?>h</span>
                        </td>
                        <td class="text-muted small">
                            <?= (int)$shift['grace_period_minutes'] ?> min
                        </td>
                        <td>
                            <?php if ($shift['status'] === 'active'): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button"
                                    class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold"
                                    data-bs-toggle="modal"
                                    data-bs-target="#employeesModal"
                                    data-shift-id="<?= e($shift['id']) ?>"
                                    data-shift-name="<?= e($shift['name']) ?>"
                                    title="View assigned employees">
                                <i class="bi bi-people me-1"></i><?= (int)$shift['employee_count'] ?>
                            </button>
                        </td>
                        <td class="text-end text-nowrap">
                            <!-- Set Default -->
                            <?php if (!(int)($shift['is_default'] ?? 0)): ?>
                            <form method="post" action="<?= url('shifts/set-default') ?>" class="d-inline"
                                  onsubmit="return confirm('Set «<?= e(addslashes($shift['name'])) ?>» as the default shift for new employees?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($shift['id']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-warning" title="Set as default">
                                    <i class="bi bi-star"></i>
                                </button>
                            </form>
                            <?php endif; ?>

                            <!-- Edit -->
                            <a href="<?= url('shifts/edit?id=' . $shift['id']) ?>"
                               class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>

                            <!-- Activate / Deactivate -->
                            <?php if ($shift['status'] === 'active'): ?>
                            <form method="post" action="<?= url('shifts/deactivate') ?>" class="d-inline"
                                  onsubmit="return confirm('Deactivate this shift?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($shift['id']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Deactivate">
                                    <i class="bi bi-pause-circle"></i>
                                </button>
                            </form>
                            <?php else: ?>
                            <form method="post" action="<?= url('shifts/activate') ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($shift['id']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-success" title="Activate">
                                    <i class="bi bi-play-circle"></i>
                                </button>
                            </form>
                            <?php endif; ?>

                            <!-- Delete -->
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    title="Delete"
                                    data-bs-toggle="modal"
                                    data-bs-target="#deleteModal"
                                    data-shift-id="<?= e($shift['id']) ?>"
                                    data-shift-name="<?= e($shift['name']) ?>"
                                    data-employee-count="<?= (int)$shift['employee_count'] ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (($meta['pages'] ?? 1) > 1): ?>
    <div class="card-footer">
        <nav aria-label="Shifts pagination">
            <ul class="pagination pagination-sm mb-0 justify-content-center">
                <?php for ($p = 1; $p <= $meta['pages']; $p++): ?>
                    <li class="page-item <?= $p === ($meta['page'] ?? 1) ? 'active' : '' ?>">
                        <a class="page-link" href="<?= url('shifts?' . http_build_query(array_merge($filters, ['page' => $p]))) ?>">
                            <?= $p ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <div class="text-center text-muted small mt-1">
            Showing <?= count($shifts) ?> of <?= $meta['total'] ?> shifts
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ============================================================
     Assigned Employees Modal
     ============================================================ -->
<div class="modal fade" id="employeesModal" tabindex="-1" aria-labelledby="employeesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="employeesModalLabel">
                    <i class="bi bi-people me-2"></i>Employees on <span id="modalShiftName"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="employeesModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <span class="ms-2 text-muted">Loading…</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     Delete Shift Modal
     ============================================================ -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">
                    <i class="bi bi-trash me-2"></i>Delete Shift
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="<?= url('shifts/delete') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="deleteShiftId">

                <div class="modal-body">
                    <p>You are about to delete <strong id="deleteShiftName"></strong>.</p>

                    <!-- Shown only when employees are assigned -->
                    <div id="reassignSection" class="d-none">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong id="reassignCount"></strong> employee(s) are assigned to this shift.
                            Select a replacement shift to reassign them, or cancel.
                        </div>
                        <label class="form-label">Replacement Shift <span class="text-danger">*</span></label>
                        <select name="replacement_shift_id" id="replacementShiftId" class="form-select">
                            <option value="">— Select replacement shift —</option>
                            <?php foreach ($shifts as $s): ?>
                                <option value="<?= e($s['id']) ?>"><?= e($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="noAssignSection">
                        <p class="text-muted">This action cannot be undone.</p>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="deleteSubmitBtn">
                        <i class="bi bi-trash me-1"></i>Delete Shift
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    // ── Employees Modal ──────────────────────────────────────────────
    const empModal = document.getElementById('employeesModal');
    if (empModal) {
        empModal.addEventListener('show.bs.modal', function (e) {
            const btn       = e.relatedTarget;
            const shiftId   = btn.dataset.shiftId;
            const shiftName = btn.dataset.shiftName;

            document.getElementById('modalShiftName').textContent = shiftName;
            document.getElementById('employeesModalBody').innerHTML =
                '<div class="text-center py-4">' +
                '<div class="spinner-border spinner-border-sm text-primary" role="status"></div>' +
                '<span class="ms-2 text-muted">Loading…</span></div>';

            fetch('<?= url('shifts/employees') ?>?shift_id=' + encodeURIComponent(shiftId))
                .then(r => r.json())
                .then(data => {
                    const employees = data.employees || [];
                    if (employees.length === 0) {
                        document.getElementById('employeesModalBody').innerHTML =
                            '<p class="text-center text-muted py-3">No employees assigned to this shift.</p>';
                        return;
                    }

                    let html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0">' +
                        '<thead class="table-light"><tr>' +
                        '<th>#</th><th>Employee</th><th>Position</th><th>Department</th><th>Branch</th>' +
                        '</tr></thead><tbody>';

                    employees.forEach((emp, i) => {
                        html += '<tr>' +
                            '<td class="text-muted small">' + escHtml(emp.employee_number) + '</td>' +
                            '<td><a href="<?= url('employees/show') ?>?id=' + encodeURIComponent(emp.id) + '">' +
                            escHtml(emp.full_name) + '</a></td>' +
                            '<td class="text-muted small">' + escHtml(emp.position || '—') + '</td>' +
                            '<td class="text-muted small">' + escHtml(emp.department_name || '—') + '</td>' +
                            '<td class="text-muted small">' + escHtml(emp.branch_name || '—') + '</td>' +
                            '</tr>';
                    });

                    html += '</tbody></table></div>';
                    if (data.meta && data.meta.total > employees.length) {
                        html += '<p class="text-muted small text-end mt-2">Showing ' +
                            employees.length + ' of ' + data.meta.total + ' employees.</p>';
                    }
                    document.getElementById('employeesModalBody').innerHTML = html;
                })
                .catch(() => {
                    document.getElementById('employeesModalBody').innerHTML =
                        '<div class="alert alert-danger">Failed to load employees.</div>';
                });
        });
    }

    // ── Delete Modal ─────────────────────────────────────────────────
    const delModal = document.getElementById('deleteModal');
    if (delModal) {
        delModal.addEventListener('show.bs.modal', function (e) {
            const btn   = e.relatedTarget;
            const id    = btn.dataset.shiftId;
            const name  = btn.dataset.shiftName;
            const count = parseInt(btn.dataset.employeeCount, 10) || 0;

            document.getElementById('deleteShiftId').value       = id;
            document.getElementById('deleteShiftName').textContent = name;

            const reassign  = document.getElementById('reassignSection');
            const noAssign  = document.getElementById('noAssignSection');
            const submitBtn = document.getElementById('deleteSubmitBtn');

            // Filter replacement options — remove the shift being deleted
            const sel = document.getElementById('replacementShiftId');
            Array.from(sel.options).forEach(opt => {
                opt.hidden = opt.value === id;
            });

            if (count > 0) {
                reassign.classList.remove('d-none');
                noAssign.classList.add('d-none');
                document.getElementById('reassignCount').textContent = count;
                submitBtn.disabled = false;
            } else {
                reassign.classList.add('d-none');
                noAssign.classList.remove('d-none');
                submitBtn.disabled = false;
            }
        });

        // Validate replacement selected when employees exist
        delModal.querySelector('form').addEventListener('submit', function (e) {
            const reassign = document.getElementById('reassignSection');
            if (!reassign.classList.contains('d-none')) {
                const sel = document.getElementById('replacementShiftId');
                if (!sel.value) {
                    e.preventDefault();
                    sel.classList.add('is-invalid');
                    if (!sel.nextElementSibling?.classList.contains('invalid-feedback')) {
                        const msg = document.createElement('div');
                        msg.className = 'invalid-feedback';
                        msg.textContent = 'Please select a replacement shift.';
                        sel.after(msg);
                    }
                }
            }
        });
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
})();
</script>
