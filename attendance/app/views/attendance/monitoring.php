<?php
/**
 * Attendance Monitoring
 * Displays the computed official attendance summary for every employee / date.
 * All 6 official timestamps are shown: Time In, Break Out, Break In,
 * Time Out, OT In, OT Out.
 */

// Helper: format a datetime string as h:i A, or return '—'
function fmtT(?string $dt): string {
    if (!$dt) return '—';
    $ts = strtotime($dt);
    return $ts ? date('h:i A', $ts) : '—';
}

// Status badge map
// Status badge map — semantic colours only, no raw bright overrides
$statusBadge = [
    'present'    => 'text-bg-success',
    'absent'     => 'text-bg-danger',
    'half_day'   => 'text-bg-warning',
    'holiday'    => 'text-bg-secondary',
    'rest_day'   => 'text-bg-secondary',
    'leave'      => 'text-bg-info',
];
?>

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Attendance Monitoring</h1>
        <div class="text-muted small">Official computed attendance — today, weekly, monthly, by employee, department, or branch.</div>
    </div>
</div>

<!-- ── Filter bar ────────────────────────────────────────────────── -->
<form class="panel p-3 mb-3" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-2">
            <input class="form-control form-control-sm" name="q"
                   value="<?= e($_GET['q'] ?? '') ?>" placeholder="Search employee…">
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm" name="employee_id">
                <option value="">All employees</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?= e($emp['id']) ?>"
                        <?= ($_GET['employee_id'] ?? '') === $emp['id'] ? 'selected' : '' ?>>
                        <?= e($emp['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm" name="department_id">
                <option value="">All departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= e($dept['id']) ?>"
                        <?= ($_GET['department_id'] ?? '') === $dept['id'] ? 'selected' : '' ?>>
                        <?= e($dept['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm" name="branch_id">
                <option value="">All branches</option>
                <?php foreach ($branches as $branch): ?>
                    <option value="<?= e($branch['id']) ?>"
                        <?= ($_GET['branch_id'] ?? '') === $branch['id'] ? 'selected' : '' ?>>
                        <?= e($branch['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1">
            <input class="form-control form-control-sm" type="date" name="start_date"
                   value="<?= e($_GET['start_date'] ?? '') ?>">
        </div>
        <div class="col-md-1">
            <input class="form-control form-control-sm" type="date" name="end_date"
                   value="<?= e($_GET['end_date'] ?? '') ?>">
        </div>
        <div class="col-md-1">
            <select class="form-select form-select-sm" name="status">
                <option value="">All status</option>
                <?php foreach (['present','absent','half_day','holiday','rest_day','leave'] as $s): ?>
                    <option <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>
                            value="<?= $s ?>"><?= $s === 'leave' ? 'On Leave' : ucfirst(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1">
            <select class="form-select form-select-sm" name="leave_type">
                <option value="">All leave types</option>
                <?php foreach (\App\Services\LeaveService::TYPES as $type): ?>
                    <option <?= ($_GET['leave_type'] ?? '') === $type ? 'selected' : '' ?>
                            value="<?= $type ?>"><?= $type ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-auto d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm">
                <i class="bi bi-search"></i> Filter
            </button>
            <a href="<?= url('attendance-monitoring') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x-circle"></i> Reset
            </a>
        </div>
    </div>
</form>

<!-- ── Results ───────────────────────────────────────────────────── -->
<div class="panel">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Employee</th>
                    <th>Dept</th>
                    <th>Branch</th>
                    <th>Shift</th>
                    <th>Status</th>
                    <th>Leave Type</th>
                    <th>Leave Duration</th>
                    <th class="text-nowrap">Time In</th>
                    <th class="text-nowrap">Break Out</th>
                    <th class="text-nowrap">Break In</th>
                    <th class="text-nowrap">Time Out</th>
                    <th class="text-nowrap">OT In</th>
                    <th class="text-nowrap">OT Out</th>
                    <th class="text-end">Late<br><small class="text-muted fw-normal">min</small></th>
                    <th class="text-end">Break<br><small class="text-muted fw-normal">min</small></th>
                    <th class="text-end">OT<br><small class="text-muted fw-normal">min</small></th>
                    <th class="text-end">Undertime<br><small class="text-muted fw-normal">min</small></th>
                    <th class="text-end">Hours</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="17" class="text-center text-muted py-5">
                            <i class="bi bi-calendar-x fs-4 d-block mb-2"></i>
                            No attendance records found.
                        </td>
                    </tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr>
                        <td class="text-nowrap">
                            <small><?= e($row['display_date']) ?></small>
                        </td>
                        <td class="text-nowrap">
                            <div class="fw-semibold small"><?= e($row['employee_name']) ?></div>
                            <div class="text-muted" style="font-size:.72rem"><?= e($row['employee_number']) ?></div>
                        </td>
                        <td><small><?= e($row['department_name'] ?? '—') ?></small></td>
                        <td><small><?= e($row['branch_name']     ?? '—') ?></small></td>
                        <td><small><?= e($row['shift_name']      ?? '—') ?></small></td>
                        <td>
                            <?php
                            $sc = $statusBadge[$row['day_status']] ?? 'text-bg-secondary';
                            $isLate = !empty($row['is_late']);
                            $statusLabel = $row['day_status'] === 'leave' ? 'On Leave' : ucfirst(str_replace('_', ' ', $row['day_status']));
                            ?>
                            <span class="badge <?= $sc ?>">
                                <?= e($statusLabel) ?>
                            </span>
                            <?php if ($isLate && $row['day_status'] !== 'leave'): ?>
                                <span class="badge text-bg-warning ms-1" title="Late">L</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['day_status'] === 'leave' && !empty($row['leave_type'])): ?>
                                <small class="text-info fw-semibold"><?= e($row['leave_type']) ?></small>
                            <?php else: ?>
                                <small class="text-muted">—</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['day_status'] === 'leave' && !empty($row['leave_start_date']) && !empty($row['leave_end_date'])): ?>
                                <small class="text-muted"><?= e($row['leave_start_date']) ?> – <?= e($row['leave_end_date']) ?></small>
                                <?php if (!empty($row['leave_duration'])): ?>
                                    <br><small class="text-muted">(<?= (int) $row['leave_duration'] ?> day<?= (int) $row['leave_duration'] > 1 ? 's' : '' ?>)</small>
                                <?php endif; ?>
                            <?php else: ?>
                                <small class="text-muted">—</small>
                            <?php endif; ?>
                        </td>
                        <!-- Official timestamps -->
                        <td class="text-nowrap"><small><?= fmtT($row['time_in']    ?? null) ?></small></td>
                        <td class="text-nowrap"><small><?= fmtT($row['break_out']  ?? null) ?></small></td>
                        <td class="text-nowrap"><small><?= fmtT($row['break_in']   ?? null) ?></small></td>
                        <td class="text-nowrap"><small><?= fmtT($row['time_out']   ?? null) ?></small></td>
                        <td class="text-nowrap"><small><?= fmtT($row['overtime_in']  ?? null) ?></small></td>
                        <td class="text-nowrap"><small><?= fmtT($row['overtime_out'] ?? null) ?></small></td>
                        <!-- Metrics -->
                        <td class="text-end">
                            <?php if (!empty($row['late_minutes'])): ?>
                                <span class="text-danger fw-semibold small"><?= (int) $row['late_minutes'] ?></span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <small class="text-muted"><?= (int) ($row['break_minutes'] ?? 0) ?: '—' ?></small>
                        </td>
                        <td class="text-end">
                            <?php if (!empty($row['overtime_minutes'])): ?>
                                <span class="text-primary fw-semibold small"><?= (int) $row['overtime_minutes'] ?></span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if (!empty($row['undertime_minutes'])): ?>
                                <span class="text-warning fw-semibold small"><?= (int) $row['undertime_minutes'] ?></span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <small><?= $row['total_hours'] !== null ? number_format((float) $row['total_hours'], 2) : '—' ?></small>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Real-time monitoring data refresh
let refreshInterval = null;
const REFRESH_INTERVAL_MS = 30000; // 30 seconds

function refreshMonitoringData() {
    const url = new URL(window.location.href);
    const params = new URLSearchParams(url.search);
    
    // Preserve current filters
    const currentFilters = {
        q: params.get('q'),
        department_id: params.get('department_id'),
        branch_id: params.get('branch_id'),
        status: params.get('status'),
        leave_type: params.get('leave_type'),
        start_date: params.get('start_date'),
        end_date: params.get('end_date')
    };
    
    // Build API URL with current filters
    const apiUrl = '<?= url('attendance-monitoring/api/data') ?>?' + new URLSearchParams(currentFilters);
    
    fetch(apiUrl)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateMonitoringTable(data.data);
            }
        })
        .catch(error => {
            console.error('Failed to refresh monitoring data:', error);
        });
}

function updateMonitoringTable(rows) {
    const tbody = document.querySelector('tbody');
    if (!tbody) return;
    
    // Store current scroll position
    const scrollY = window.scrollY;
    
    // Rebuild table body
    tbody.innerHTML = '';
    
    if (rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-5">No attendance records found.</td></tr>';
        return;
    }
    
    rows.forEach(row => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><small>${escapeHtml(row.display_date)}</small></td>
            <td>
                <div class="fw-semibold small">${escapeHtml(row.employee_name)}</div>
                <div class="text-muted" style="font-size:.72rem">${escapeHtml(row.employee_number)}</div>
            </td>
            <td><small>${escapeHtml(row.department_name || '—')}</small></td>
            <td><small>${escapeHtml(row.branch_name || '—')}</small></td>
            <td><small>${escapeHtml(row.shift_name || '—')}</small></td>
            <td>
                <span class="badge ${getStatusBadgeClass(row.day_status)}">
                    ${getStatusLabel(row.day_status)}
                </span>
                ${row.is_late && row.day_status !== 'leave' ? '<span class="badge text-bg-warning ms-1" title="Late">L</span>' : ''}
            </td>
            <td>
                ${row.day_status === 'leave' && row.leave_type ? 
                    `<small class="text-info fw-semibold">${escapeHtml(row.leave_type)}</small>` : 
                    '<small class="text-muted">—</small>'}
            </td>
            <td><small>${row.day_status === 'leave' && row.leave_start_date && row.leave_end_date ? 
                `${escapeHtml(row.leave_start_date)}–${escapeHtml(row.leave_end_date)} (${row.leave_duration} days)` : '—'}</small></td>
            <td><small>${formatTime(row.time_in)}</small></td>
            <td><small>${formatTime(row.break_out)}</small></td>
            <td><small>${formatTime(row.break_in)}</small></td>
            <td><small>${formatTime(row.time_out)}</small></td>
            <td><small>${formatTime(row.overtime_in)}</small></td>
            <td><small>${formatTime(row.overtime_out)}</small></td>
            <td><small>${row.late_minutes ? row.late_minutes + ' min' : '—'}</small></td>
            <td><small>${row.undertime_minutes ? row.undertime_minutes + ' min' : '—'}</small></td>
            <td><small>${row.break_minutes ? row.break_minutes + ' min' : '—'}</small></td>
            <td><small>${row.overtime_minutes ? row.overtime_minutes + ' min' : '—'}</small></td>
            <td><small>${row.total_hours ? row.total_hours + ' hrs' : '—'}</small></td>
        `;
        tbody.appendChild(tr);
    });
    
    // Restore scroll position
    window.scrollTo(0, scrollY);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatTime(time) {
    if (!time) return '—';
    const date = new Date(time);
    return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
}

function getStatusBadgeClass(status) {
    const statusBadge = {
        'present': 'text-bg-success',
        'absent': 'text-bg-danger',
        'half_day': 'text-bg-warning',
        'holiday': 'text-bg-secondary',
        'rest_day': 'text-bg-secondary',
        'leave': 'text-bg-info',
    };
    return statusBadge[status] || 'text-bg-secondary';
}

function getStatusLabel(status) {
    if (status === 'leave') return 'On Leave';
    return status ? status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' ') : '—';
}

// Start polling when page loads
document.addEventListener('DOMContentLoaded', function() {
    refreshInterval = setInterval(refreshMonitoringData, REFRESH_INTERVAL_MS);
    
    // Trigger immediate refresh after leave approval/reject actions
    document.querySelectorAll('form[action*="/leaves/approve"], form[action*="/leaves/reject"]').forEach(form => {
        form.addEventListener('submit', function() {
            setTimeout(refreshMonitoringData, 1000); // Refresh 1 second after action
        });
    });
});

// Stop polling when page is hidden to save resources
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        clearInterval(refreshInterval);
    } else {
        refreshInterval = setInterval(refreshMonitoringData, REFRESH_INTERVAL_MS);
        refreshMonitoringData(); // Refresh immediately when page becomes visible
    }
});
</script>
