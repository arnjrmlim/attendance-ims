<div class="page-head">
    <div>
        <h1 class="h3 mb-1">
            <?= e($shift['name']) ?>
            <?php if ((int)($shift['is_default'] ?? 0)): ?>
                <span class="badge bg-warning text-dark ms-2"><i class="bi bi-star-fill me-1"></i>Default</span>
            <?php endif; ?>
            <?php if ((int)($shift['overnight'] ?? 0)): ?>
                <span class="badge bg-info text-dark ms-2">Overnight</span>
            <?php endif; ?>
        </h1>
        <div class="text-muted"><?= e($shift['description'] ?? 'No description provided.') ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('shifts/edit?id=' . $shift['id']) ?>" class="btn btn-primary">
            <i class="bi bi-pencil me-1"></i> Edit Shift
        </a>
        <a href="<?= url('shifts') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="row g-4">

    <!-- Shift Details -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-clock me-2"></i>Schedule Details</h6>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted small">Type</dt>
                    <dd class="col-7">
                        <?php
                        $typeColors = ['regular' => 'primary', 'night' => 'dark', 'flexible' => 'info'];
                        $tc = $typeColors[$shift['type']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?= $tc ?> bg-opacity-10 text-<?= $tc ?> border border-<?= $tc ?> border-opacity-25">
                            <?= ucfirst(e($shift['type'])) ?>
                        </span>
                    </dd>

                    <dt class="col-5 text-muted small">Time In</dt>
                    <dd class="col-7 fw-semibold"><?= date('h:i A', strtotime($shift['time_in'])) ?></dd>

                    <dt class="col-5 text-muted small">Time Out</dt>
                    <dd class="col-7 fw-semibold"><?= date('h:i A', strtotime($shift['time_out'])) ?></dd>

                    <dt class="col-5 text-muted small">Break Start</dt>
                    <dd class="col-7">
                        <?= $shift['lunch_break_start'] ? date('h:i A', strtotime($shift['lunch_break_start'])) : '—' ?>
                    </dd>

                    <dt class="col-5 text-muted small">Break End</dt>
                    <dd class="col-7">
                        <?= $shift['lunch_break_end'] ? date('h:i A', strtotime($shift['lunch_break_end'])) : '—' ?>
                    </dd>

                    <dt class="col-5 text-muted small">Break Duration</dt>
                    <dd class="col-7"><?= (int)$shift['lunch_break_minutes'] ?> min</dd>

                    <dt class="col-5 text-muted small">Working Hours</dt>
                    <dd class="col-7 fw-semibold text-primary">
                        <?= number_format((float)$shift['required_hours'], 1) ?> hrs
                    </dd>

                    <dt class="col-5 text-muted small">Grace Period</dt>
                    <dd class="col-7"><?= (int)$shift['grace_period_minutes'] ?> min</dd>

                    <dt class="col-5 text-muted small">Overnight</dt>
                    <dd class="col-7">
                        <?php if ((int)($shift['overnight'] ?? 0)): ?>
                            <span class="badge bg-info text-dark">Yes</span>
                        <?php else: ?>
                            <span class="text-muted">No</span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-5 text-muted small">Status</dt>
                    <dd class="col-7">
                        <?php if ($shift['status'] === 'active'): ?>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Inactive</span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-5 text-muted small">Default</dt>
                    <dd class="col-7">
                        <?php if ((int)($shift['is_default'] ?? 0)): ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-star-fill me-1"></i>Yes</span>
                        <?php else: ?>
                            <span class="text-muted">No</span>
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>
            <div class="card-footer bg-transparent d-flex gap-2 flex-wrap">
                <?php if (!(int)($shift['is_default'] ?? 0)): ?>
                <form method="post" action="<?= url('shifts/set-default') ?>"
                      onsubmit="return confirm('Set this as the default shift for new employees?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($shift['id']) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-warning">
                        <i class="bi bi-star me-1"></i>Set as Default
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($shift['status'] === 'active'): ?>
                <form method="post" action="<?= url('shifts/deactivate') ?>"
                      onsubmit="return confirm('Deactivate this shift?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($shift['id']) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pause-circle me-1"></i>Deactivate
                    </button>
                </form>
                <?php else: ?>
                <form method="post" action="<?= url('shifts/activate') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($shift['id']) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-play-circle me-1"></i>Activate
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Assigned Employees -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0">
                    <i class="bi bi-people me-2"></i>Assigned Employees
                    <span class="badge bg-primary ms-2"><?= (int)$shift['employee_count'] ?></span>
                </h6>
                <a href="<?= url('employees?shift_id=' . $shift['id']) ?>"
                   class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-box-arrow-up-right me-1"></i>View All
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($employees)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-people fs-2 d-block mb-2"></i>
                        No employees are currently assigned to this shift.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Employee #</th>
                                    <th>Name</th>
                                    <th>Position</th>
                                    <th>Department</th>
                                    <th>Branch</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($employees as $emp): ?>
                                <tr>
                                    <td class="text-muted small"><?= e($emp['employee_number']) ?></td>
                                    <td>
                                        <a href="<?= url('employees/show?id=' . $emp['id']) ?>">
                                            <?= e($emp['full_name']) ?>
                                        </a>
                                    </td>
                                    <td class="text-muted small"><?= e($emp['position'] ?? '—') ?></td>
                                    <td class="text-muted small"><?= e($emp['department_name'] ?? '—') ?></td>
                                    <td class="text-muted small"><?= e($emp['branch_name'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (($empMeta['pages'] ?? 1) > 1): ?>
            <div class="card-footer">
                <nav>
                    <ul class="pagination pagination-sm mb-0 justify-content-center">
                        <?php for ($p = 1; $p <= $empMeta['pages']; $p++): ?>
                            <li class="page-item <?= $p === ($empMeta['page'] ?? 1) ? 'active' : '' ?>">
                                <a class="page-link"
                                   href="<?= url('shifts/show?id=' . $shift['id'] . '&page=' . $p) ?>">
                                    <?= $p ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <div class="text-center text-muted small mt-1">
                    Showing <?= count($employees) ?> of <?= $empMeta['total'] ?> employees
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>
