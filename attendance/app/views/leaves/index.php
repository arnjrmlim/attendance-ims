<?php
/**
 * Leave Management
 * - Employees: see only their own requests; no employee/dept/branch filters
 * - HR / Admin: see all requests with full filter set and approve/reject controls
 */
$isAdminHr = $isAdminHr ?? has_role(['administrator', 'hr']);
$ownOnly   = $ownOnly   ?? !$isAdminHr;
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Leave Management</h1>
        <div class="text-muted small">
            <?= $isAdminHr
                ? 'View, approve, reject and manage all employee leave requests.'
                : 'Submit and track your leave requests.' ?>
        </div>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#leaveForm">
        <i class="bi bi-plus-lg me-1"></i>New Leave Request
    </button>
</div>

<!-- ── New leave request form ────────────────────────────────────── -->
<div id="leaveForm" class="collapse panel p-4 mb-3">
    <h6 class="fw-semibold mb-3">Submit Leave Request</h6>
    <form method="post" action="<?= url('leaves') ?>" enctype="multipart/form-data" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-md-3">
            <label class="form-label">Leave Type <span class="text-danger">*</span></label>
            <select class="form-select form-select-sm" name="leave_type" required>
                <?php foreach ($types as $type): ?>
                    <option><?= e($type) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Start Date <span class="text-danger">*</span></label>
            <input class="form-control form-control-sm" type="date" name="start_date" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">End Date <span class="text-danger">*</span></label>
            <input class="form-control form-control-sm" type="date" name="end_date" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Attachment</label>
            <input class="form-control form-control-sm" type="file" name="attachment"
                   accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">
        </div>
        <div class="col-12">
            <label class="form-label">Reason</label>
            <textarea class="form-control form-control-sm" name="reason" rows="2"></textarea>
        </div>
        <div class="col-12">
            <button class="btn btn-success btn-sm">
                <i class="bi bi-send me-1"></i>Submit Request
            </button>
        </div>
    </form>
</div>

<!-- ── Filters ────────────────────────────────────────────────────── -->
<form class="panel p-3 mb-3" method="get" action="<?= url('leaves') ?>">
    <div class="row g-2 align-items-end">

        <?php if ($isAdminHr): ?>
        <!-- Admin/HR: full filter set including employee search -->
        <div class="col-md-3">
            <label class="form-label small mb-1">Search</label>
            <input class="form-control form-control-sm" name="q"
                   value="<?= e($_GET['q'] ?? '') ?>"
                   placeholder="Employee name, number or reason">
        </div>
        <?php else: ?>
        <!-- Employee: search own records by reason only -->
        <div class="col-md-3">
            <label class="form-label small mb-1">Search</label>
            <input class="form-control form-control-sm" name="q"
                   value="<?= e($_GET['q'] ?? '') ?>"
                   placeholder="Search by reason">
        </div>
        <?php endif; ?>

        <div class="col-md-2">
            <label class="form-label small mb-1">Leave Type</label>
            <select class="form-select form-select-sm" name="leave_type">
                <option value="">All Types</option>
                <?php foreach ($types as $type): ?>
                    <option <?= ($_GET['leave_type'] ?? '') === $type ? 'selected' : '' ?>>
                        <?= e($type) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label small mb-1">Status</label>
            <select class="form-select form-select-sm" name="status">
                <option value="">All Statuses</option>
                <?php foreach (['Pending','Approved','Rejected','Cancelled'] as $status): ?>
                    <option <?= ($_GET['status'] ?? '') === $status ? 'selected' : '' ?>>
                        <?= e($status) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label small mb-1">From</label>
            <input class="form-control form-control-sm" type="date" name="start_date"
                   value="<?= e($_GET['start_date'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">To</label>
            <input class="form-control form-control-sm" type="date" name="end_date"
                   value="<?= e($_GET['end_date'] ?? '') ?>">
        </div>

        <div class="col-md-auto d-flex gap-2">
            <button class="btn btn-sm btn-secondary">
                <i class="bi bi-search"></i> Filter
            </button>
            <a href="<?= url('leaves') ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x-circle"></i> Reset
            </a>
            <button type="button" class="btn btn-sm btn-primary" onclick="printBulk()">
                <i class="bi bi-printer"></i> Print
            </button>
        </div>
    </div>
</form>

<!-- ── Leave requests table ───────────────────────────────────────── -->
<div class="panel">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <?php if ($isAdminHr): ?>
                        <th>Employee</th>
                    <?php endif; ?>
                    <th>Type</th>
                    <th>Dates</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th>Reason</th>
                    <th>Attachment</th>
                    <th>Remarks</th>
                    <th class="text-nowrap" style="width:1%">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="<?= $isAdminHr ? 10 : 9 ?>"
                        class="text-center text-muted py-5">
                        <i class="bi bi-calendar-x fs-4 d-block mb-2"></i>
                        No leave requests found.
                    </td>
                </tr>
            <?php else: foreach ($rows as $row):
                $statusClass = match($row['status']) {
                    'Approved'  => 'text-bg-success',
                    'Rejected'  => 'text-bg-danger',
                    'Cancelled' => 'text-bg-secondary',
                    default     => 'text-bg-warning',
                };
            ?>
                <tr>
                    <td><small><?= e(date('Y-m-d', strtotime($row['created_at']))) ?></small></td>
                    <?php if ($isAdminHr): ?>
                    <td>
                            <div class="fw-semibold small"><?= e($row['employee_name'] ?? '—') ?></div>
                            <div class="text-muted" style="font-size:.75rem"><?= e($row['employee_number'] ?? '') ?></div>
                        </td>
                    <?php endif; ?>
                    <td><small><?= e($row['leave_type']) ?></small></td>
                    <td class="text-nowrap">
                        <small><?= e($row['start_date']) ?></small><br>
                        <small class="text-muted">to <?= e($row['end_date']) ?></small>
                    </td>
                    <td><small><?= e($row['number_of_days']) ?></small></td>
                    <td>
                        <span class="badge <?= $statusClass ?>">
                            <?= e($row['status']) ?>
                        </span>
                    </td>
                    <td>
                        <small title="<?= e($row['reason']) ?>">
                            <?= e(mb_strimwidth($row['reason'], 0, 60, '…')) ?>
                        </small>
                    </td>
                    <td>
                            <?php if (!empty($row['attachment'])): ?>
                                <button class="btn btn-xs btn-outline-primary"
                                   data-attachment-id="<?= e($row['id']) ?>"
                                   data-attachment-filename="<?= e($row['attachment']) ?>"
                                   title="View attachment">
                                    <i class="bi bi-paperclip"></i> View
                                </button>
                            <?php else: ?>
                                <small class="text-muted">No file</small>
                            <?php endif; ?>
                    </td>
                    <td>
                            <small class="text-muted">
                                <?= e($row['admin_remarks'] ?? '—') ?>
                            </small>
                    </td>
                    <td class="text-nowrap" style="width:1%">
                        <?php if ($row['status'] === 'Pending' && $isAdminHr): ?>
                            <!-- Approve -->
                            <form class="d-inline" method="post"
                                  action="<?= url('leaves/approve') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                <input type="hidden" name="admin_remarks" value="">
                                <button class="btn btn-xs btn-success" title="Approve">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </form>
                            <!-- Reject with inline remarks -->
                            <button class="btn btn-xs btn-danger ms-1"
                                    data-bs-toggle="modal"
                                    data-bs-target="#rejectModal"
                                    data-id="<?= e($row['id']) ?>"
                                    data-name="<?= e($row['employee_name'] ?? '') ?>"
                                    title="Reject">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        <?php endif; ?>

                        <?php if ($row['status'] === 'Pending'): ?>
                            <!-- Cancel — available to the owner and to admin/hr -->
                            <form class="d-inline ms-1" method="post"
                                  action="<?= url('leaves/cancel') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                <button class="btn btn-xs btn-outline-secondary"
                                        data-confirm="Cancel this leave request?"
                                        title="Cancel">
                                    <i class="bi bi-slash-circle"></i>
                                </button>
                            </form>
                        <?php endif; ?>

                        <button class="btn btn-xs btn-outline-secondary ms-1"
                                onclick="printRow('<?= rawurlencode($row['id']) ?>')"
                                title="Print">
                            <i class="bi bi-printer"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Reject modal ───────────────────────────────────────────────── -->
<?php if ($isAdminHr): ?>
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger" id="rejectModalLabel">
                    <i class="bi bi-x-circle me-2"></i>Reject Leave Request
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="<?= url('leaves/reject') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="rejectLeaveId">
                <div class="modal-body">
                    <p class="mb-2">Reject leave request for
                        <strong id="rejectLeaveName"></strong>?
                    </p>
                    <label class="form-label">
                        Reason for rejection <span class="text-danger">*</span>
                    </label>
                    <textarea class="form-control" name="admin_remarks" rows="2"
                              required placeholder="Provide a reason…"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        Reject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-bs-target="#rejectModal"]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('rejectLeaveId').value   = btn.dataset.id;
            document.getElementById('rejectLeaveName').textContent = btn.dataset.name;
        });
    });
});
</script>
<?php endif; ?>

<!-- Attachment Preview Modal -->
<div class="modal fade" id="attachmentModal" tabindex="-1" aria-labelledby="attachmentModalLabel" aria-modal="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="attachmentModalLabel">Attachment Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="min-height: 400px; display: flex; align-items: center; justify-content: center; background: #f5f5f5;">
                <div id="attachmentPreview" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                    <!-- Attachment content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <span id="attachmentFilename" class="me-auto text-muted small"></span>
                <a id="downloadAttachmentBtn" href="#" class="btn btn-primary" download>
                    <i class="bi bi-download me-1"></i>Download
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Attachment validation
    const attachmentInput = document.querySelector('input[name="attachment"]');
    if (attachmentInput) {
        attachmentInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
                const allowedExtensions = ['.jpg', '.jpeg', '.png', '.webp', '.pdf'];
                const fileExt = '.' + file.name.split('.').pop().toLowerCase();
                
                if (!allowedTypes.includes(file.type) || !allowedExtensions.includes(fileExt)) {
                    alert('Invalid file type. Only JPG, JPEG, PNG, WEBP, and PDF files are allowed.');
                    e.target.value = '';
                }
            }
        });
    }

    // Attachment preview modal
    const attachmentModalEl = document.getElementById('attachmentModal');
    if (attachmentModalEl) {
        const attachmentModal = new bootstrap.Modal(attachmentModalEl);
        const attachmentPreview = document.getElementById('attachmentPreview');
        const downloadAttachmentBtn = document.getElementById('downloadAttachmentBtn');
        const attachmentFilename = document.getElementById('attachmentFilename');

        document.querySelectorAll('[data-attachment-id]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const id = this.dataset.attachmentId;
                const filename = this.dataset.attachmentFilename || 'attachment';
                
                // Determine file type from extension
                const ext = filename.split('.').pop().toLowerCase();
                const isPdf = ext === 'pdf';
                const isImage = ['jpg', 'jpeg', 'png', 'webp'].includes(ext);
                
                if (isPdf) {
                    // Show PDF in iframe
                    attachmentPreview.innerHTML = `<iframe src="<?= url('leaves/attachment') ?>?id=${id}" style="width: 100%; height: 500px; border: none;"></iframe>`;
                } else if (isImage) {
                    // Show image
                    attachmentPreview.innerHTML = `<img src="<?= url('leaves/attachment') ?>?id=${id}" style="max-width: 100%; max-height: 500px; object-fit: contain;" alt="${filename}">`;
                } else {
                    attachmentPreview.innerHTML = '<p class="text-muted">Preview not available for this file type.</p>';
                }
                
                downloadAttachmentBtn.href = `<?= url('leaves/download') ?>?id=${id}`;
                downloadAttachmentBtn.download = filename;
                attachmentFilename.textContent = filename;
                
                attachmentModal.show();
            });
        });
    }
});

function printRow(id) {
    fetch('<?= url('leaves/print-row') ?>?id=' + id + '&json=1')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('print-container');
            const cfg = { companyLogo: '<?= asset_url((new \App\Services\SettingsService())->getCompanyLogo()) ?>', companyName: '<?= e((new \App\Services\SettingsService())->getCompanyName()) ?>' };
            
            container.innerHTML = generateLeaveRowPrintHtml(data.request, data.isAdminHr, cfg);
            window.print();
        })
        .catch(error => console.error('Error fetching print data:', error));
}

function printBulk() {
    const form = document.querySelector('form[method="get"]');
    const params = new URLSearchParams();
    
    // Collect all filter values
    const inputs = form.querySelectorAll('input, select');
    inputs.forEach(input => {
        if (input.name && input.value) {
            params.append(input.name, input.value);
        }
    });
    
    fetch('<?= url('leaves/print-bulk') ?>?' + params.toString() + '&json=1')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('print-container');
            container.innerHTML = generateLeaveBulkPrintHtml(data.rows, data.filters, data.isAdminHr, data.companyLogo, data.companyName);
            window.print();
        })
        .catch(error => console.error('Error fetching print data:', error));
}

function generateLeaveRowPrintHtml(request, isAdminHr, cfg) {
    return `
        <div style="font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto;">
            <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
                <div class="d-flex align-items-center gap-3">
                    <img src="${cfg.companyLogo}" width="52" height="52" alt="IMS">
                    <div>
                        <h2 class="h4 mb-0">${cfg.companyName}</h2>
                        <div class="text-muted">Generated ${new Date().toISOString().slice(0, 16).replace('T', ' ')}</div>
                    </div>
                </div>
                <div class="text-end text-muted">Leave Request</div>
            </div>
            <div style="margin-bottom: 20px;">
                <div style="margin-bottom: 8px;"><strong>Employee:</strong> ${request.employee_name || '—'}</div>
                <div style="margin-bottom: 8px;"><strong>Leave Type:</strong> ${request.leave_type || '—'}</div>
                <div style="margin-bottom: 8px;"><strong>Start Date:</strong> ${request.start_date || '—'}</div>
                <div style="margin-bottom: 8px;"><strong>End Date:</strong> ${request.end_date || '—'}</div>
                <div style="margin-bottom: 8px;"><strong>Number of Days:</strong> ${request.number_of_days || '—'}</div>
                <div style="margin-bottom: 8px;"><strong>Status:</strong> ${request.status || '—'}</div>
                <div style="margin-bottom: 8px;"><strong>Reason:</strong> ${request.reason || '—'}</div>
                ${isAdminHr ? `<div style="margin-bottom: 8px;"><strong>Admin Remarks:</strong> ${request.admin_remarks || '—'}</div>` : ''}
                <div style="margin-bottom: 8px;"><strong>Submitted Date:</strong> ${request.created_at ? request.created_at.slice(0, 10) : '—'}</div>
            </div>
        </div>
    `;
}

function generateLeaveBulkPrintHtml(rows, filters, isAdminHr, companyLogo, companyName) {
    let filterHtml = '';
    if (filters && Object.keys(filters).length > 0) {
        filterHtml = '<div style="margin-bottom: 20px; padding: 10px; background-color: #f9f9f9; border: 1px solid #ddd;"><h3>Applied Filters</h3>';
        if (filters.status) filterHtml += `<div style="margin-bottom: 5px;"><strong>Status:</strong> ${filters.status}</div>`;
        if (filters.leave_type) filterHtml += `<div style="margin-bottom: 5px;"><strong>Leave Type:</strong> ${filters.leave_type}</div>`;
        if (filters.start_date) filterHtml += `<div style="margin-bottom: 5px;"><strong>Start Date:</strong> ${filters.start_date}</div>`;
        if (filters.end_date) filterHtml += `<div style="margin-bottom: 5px;"><strong>End Date:</strong> ${filters.end_date}</div>`;
        if (filters.q) filterHtml += `<div style="margin-bottom: 5px;"><strong>Search:</strong> ${filters.q}</div>`;
        filterHtml += '</div>';
    }

    let tableHtml = '';
    if (rows.length === 0) {
        tableHtml = '<p>No leave requests found matching the current filters.</p>';
    } else {
        tableHtml = `
            <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                <thead>
                    <tr>
                        <th style="border: 1px solid #ddd; padding: 8px; text-align: left; background-color: #f5f5f5; font-weight: bold;">Date</th>
                        ${isAdminHr ? '<th style="border: 1px solid #ddd; padding: 8px; text-align: left; background-color: #f5f5f5; font-weight: bold;">Employee</th>' : ''}
                        <th style="border: 1px solid #ddd; padding: 8px; text-align: left; background-color: #f5f5f5; font-weight: bold;">Type</th>
                        <th style="border: 1px solid #ddd; padding: 8px; text-align: left; background-color: #f5f5f5; font-weight: bold;">Dates</th>
                        <th style="border: 1px solid #ddd; padding: 8px; text-align: left; background-color: #f5f5f5; font-weight: bold;">Days</th>
                        <th style="border: 1px solid #ddd; padding: 8px; text-align: left; background-color: #f5f5f5; font-weight: bold;">Status</th>
                        <th style="border: 1px solid #ddd; padding: 8px; text-align: left; background-color: #f5f5f5; font-weight: bold;">Reason</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows.map(row => `
                        <tr>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: left;">${row.created_at ? row.created_at.slice(0, 10) : '—'}</td>
                            ${isAdminHr ? `<td style="border: 1px solid #ddd; padding: 8px; text-align: left;">${row.employee_name || '—'}</td>` : ''}
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: left;">${row.leave_type || '—'}</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: left;">${row.start_date || '—'} to ${row.end_date || '—'}</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: left;">${row.number_of_days || '—'}</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: left; font-weight: bold; color: ${getStatusColor(row.status)}">${row.status || '—'}</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: left;">${row.reason || '—'}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            <p style="margin-top: 20px;"><strong>Total Records:</strong> ${rows.length}</p>
        `;
    }

    return `
        <div style="font-family: Arial, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto;">
            <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
                <div class="d-flex align-items-center gap-3">
                    <img src="${companyLogo}" width="52" height="52" alt="IMS">
                    <div>
                        <h2 class="h4 mb-0">${companyName}</h2>
                        <div class="text-muted">Generated ${new Date().toISOString().slice(0, 16).replace('T', ' ')}</div>
                    </div>
                </div>
                <div class="text-end text-muted">Leave Requests Report</div>
            </div>
            ${filterHtml}
            ${tableHtml}
        </div>
    `;
}

function getStatusColor(status) {
    const colors = {
        'Approved': 'green',
        'Rejected': 'red',
        'Cancelled': 'gray',
        'Pending': 'orange'
    };
    return colors[status] || 'black';
}

window.addEventListener('afterprint', function() {
    const container = document.getElementById('print-container');
    if (container) {
        container.innerHTML = '';
    }
});
</script>
