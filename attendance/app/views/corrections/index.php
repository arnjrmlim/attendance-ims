<?php $isAdminHr = !$ownOnly; ?>

<div class="page-head">
    <div><h1 class="h3 mb-1">Attendance Corrections</h1><div class="text-muted">Forgot time in/out, incorrect attendance and method correction workflow.</div></div>
    <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#correctionForm"><i class="bi bi-plus-lg"></i> New Correction</button>
</div>
<div id="correctionForm" class="collapse panel p-3 mb-3">
    <form method="post" action="<?= url('corrections') ?>" enctype="multipart/form-data" class="row g-3">
        <?= csrf_field() ?>
        <?php if (!$ownOnly): ?><div class="col-md-3"><label class="form-label">Employee</label><select class="form-select" name="employee_id" required><?php foreach ($employees as $employee): ?><option value="<?= e($employee['id']) ?>"><?= e($employee['employee_number'] . ' - ' . $employee['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
        <div class="col-md-3"><label class="form-label">Type</label><select class="form-select" name="correction_type" id="correctionType" required><?php foreach ($types as $type): ?><option><?= e($type) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label">Date</label><input class="form-control" type="date" name="attendance_date" required></div>
        <div class="col-md-2" id="requestedTimeInGroup"><label class="form-label">Requested In</label><input class="form-control" type="datetime-local" name="requested_time_in" id="requestedTimeIn"></div>
        <div class="col-md-2" id="requestedTimeOutGroup"><label class="form-label">Requested Out</label><input class="form-control" type="datetime-local" name="requested_time_out" id="requestedTimeOut"></div>
        <div class="col-md-3"><label class="form-label">Attachment</label><input class="form-control" type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"></div>
        <div class="col-12"><label class="form-label">Reason</label><textarea class="form-control" name="reason" required></textarea></div>
        <div class="col-12"><button class="btn btn-success">Submit Correction</button></div>
    </form>
</div>
<form class="panel p-3 mb-3" method="get" action="<?= url('corrections') ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small mb-1">Search</label><input class="form-control form-control-sm" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="<?= $ownOnly ? 'Search by reason' : 'Employee name, number or reason' ?>"></div>
        <div class="col-md-2"><label class="form-label small mb-1">Type</label><select class="form-select form-select-sm" name="correction_type"><option value="">All Types</option><?php foreach ($types as $type): ?><option value="<?= e($type) ?>" <?= ($_GET['correction_type'] ?? '') === $type ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label small mb-1">Status</label><select class="form-select form-select-sm" name="status"><option value="">All Statuses</option><?php foreach (['Pending','Approved','Rejected','Cancelled'] as $status): ?><option value="<?= e($status) ?>" <?= ($_GET['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label small mb-1">From</label><input class="form-control form-control-sm" type="date" name="start_date" value="<?= e($_GET['start_date'] ?? '') ?>"></div>
        <div class="col-md-2"><label class="form-label small mb-1">To</label><input class="form-control form-control-sm" type="date" name="end_date" value="<?= e($_GET['end_date'] ?? '') ?>"></div>
        <div class="col-md-auto d-flex gap-2"><button class="btn btn-sm btn-secondary"><i class="bi bi-search"></i> Filter</button><a href="<?= url('corrections') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i> Reset</a><button type="button" class="btn btn-sm btn-primary" onclick="printBulk()"><i class="bi bi-printer"></i> Print</button></div>
    </div>
</form>
<div class="panel">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <?php if ($isAdminHr): ?><th>Employee</th><?php endif; ?>
                    <th>Date</th><th>Type</th><th>Requested</th><th>Status</th><th>Reason</th><th>Attachment</th><th>Remarks</th>
                    <th class="text-nowrap" style="width:1%">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="<?= $isAdminHr ? 9 : 8 ?>" class="text-center text-muted py-5"><i class="bi bi-calendar-x fs-4 d-block mb-2"></i>No correction requests found.</td></tr>
            <?php else: foreach ($rows as $row):
                $statusClass = match($row['status']) {
                    'Approved' => 'text-bg-success', 'Rejected' => 'text-bg-danger',
                    'Cancelled' => 'text-bg-secondary', default => 'text-bg-warning',
                };
                $requestedTimes = implode(' / ', array_filter([$row['requested_time_in'] ?? '', $row['requested_time_out'] ?? ''])) ?: '—';
            ?>
                <tr>
                    <?php if ($isAdminHr): ?><td><div class="fw-semibold small"><?= e($row['employee_name'] ?? '—') ?></div><div class="text-muted" style="font-size:.75rem"><?= e($row['employee_number'] ?? '') ?></div></td><?php endif; ?>
                    <td><small><?= e($row['attendance_date']) ?></small></td>
                    <td><small><?= e($row['correction_type']) ?></small></td>
                    <td><small><?= e($requestedTimes) ?></small></td>
                    <td><span class="badge <?= $statusClass ?>"><?= e($row['status']) ?></span></td>
                    <td><small title="<?= e($row['reason']) ?>"><?= e(mb_strimwidth($row['reason'], 0, 60, '…')) ?></small></td>
                    <td><?php if (!empty($row['attachment'])): ?><button class="btn btn-xs btn-outline-primary" data-attachment-id="<?= e($row['id']) ?>" data-attachment-filename="<?= e($row['attachment']) ?>" title="View attachment"><i class="bi bi-paperclip"></i> View</button><?php else: ?><small class="text-muted">No file</small><?php endif; ?></td>
                    <?php if (current_user()): ?>
                        <td><small class="text-muted"><?= e($row['admin_remarks'] ?? '—') ?></small></td>
                    <?php endif; ?>
                    <td class="text-nowrap" style="width:1%">
                        <?php if ($row['status'] === 'Pending' && $isAdminHr): ?>
                            <form class="d-inline" method="post" action="<?= url('corrections/approve') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($row['id']) ?>"><input type="hidden" name="admin_remarks" value="Approved"><button class="btn btn-xs btn-success" title="Approve"><i class="bi bi-check-lg"></i></button></form>
                            <button class="btn btn-xs btn-danger ms-1" data-bs-toggle="modal" data-bs-target="#rejectCorrectionModal" data-id="<?= e($row['id']) ?>" data-name="<?= e($row['employee_name'] ?? '') ?>" title="Reject"><i class="bi bi-x-lg"></i></button>
                        <?php endif; ?>
                        <?php if ($row['status'] === 'Pending'): ?><form class="d-inline ms-1" method="post" action="<?= url('corrections/cancel') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($row['id']) ?>"><button class="btn btn-xs btn-outline-secondary" data-confirm="Cancel this correction request?" title="Cancel"><i class="bi bi-slash-circle"></i></button></form><?php endif; ?>
                        <button class="btn btn-xs btn-outline-secondary ms-1" onclick="printRow('<?= rawurlencode($row['id']) ?>')" title="Print"><i class="bi bi-printer"></i></button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($isAdminHr): ?>
<div class="modal fade" id="rejectCorrectionModal" tabindex="-1" aria-labelledby="rejectCorrectionModalLabel" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title text-danger" id="rejectCorrectionModalLabel"><i class="bi bi-x-circle me-2"></i>Reject Correction Request</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="post" action="<?= url('corrections/reject') ?>">
            <?= csrf_field() ?><input type="hidden" name="id" id="rejectCorrectionId">
            <div class="modal-body"><p class="mb-2">Reject correction request for <strong id="rejectCorrectionName"></strong>?</p><label class="form-label">Reason for rejection <span class="text-danger">*</span></label><textarea class="form-control" name="admin_remarks" rows="2" required placeholder="Provide a reason…"></textarea></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger btn-sm">Reject</button></div>
        </form>
    </div></div>
</div>
<script>
document.querySelectorAll('[data-bs-target="#rejectCorrectionModal"]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('rejectCorrectionId').value = btn.dataset.id;
        document.getElementById('rejectCorrectionName').textContent = btn.dataset.name;
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
    const type = document.getElementById('correctionType');
    const inGroup = document.getElementById('requestedTimeInGroup');
    const outGroup = document.getElementById('requestedTimeOutGroup');
    const inInput = document.getElementById('requestedTimeIn');
    const outInput = document.getElementById('requestedTimeOut');

    const updateRequestedTimes = () => {
        const forgotTimeIn = type.value === 'Forgot Time In';
        const forgotTimeOut = type.value === 'Forgot Time Out';

        inGroup.classList.toggle('d-none', forgotTimeOut);
        outGroup.classList.toggle('d-none', forgotTimeIn);
        inInput.required = forgotTimeIn;
        outInput.required = forgotTimeOut;

        if (forgotTimeOut) inInput.value = '';
        if (forgotTimeIn) outInput.value = '';
    };

    type.addEventListener('change', updateRequestedTimes);
    updateRequestedTimes();

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
                    attachmentPreview.innerHTML = `<iframe src="<?= url('corrections/attachment') ?>?id=${id}" style="width: 100%; height: 500px; border: none;"></iframe>`;
                } else if (isImage) {
                    // Show image
                    attachmentPreview.innerHTML = `<img src="<?= url('corrections/attachment') ?>?id=${id}" style="max-width: 100%; max-height: 500px; object-fit: contain;" alt="${filename}">`;
                } else {
                    attachmentPreview.innerHTML = '<p class="text-muted">Preview not available for this file type.</p>';
                }
                
                downloadAttachmentBtn.href = `<?= url('corrections/download') ?>?id=${id}`;
                downloadAttachmentBtn.download = filename;
                attachmentFilename.textContent = filename;
                
                attachmentModal.show();
            });
        });
    }
});

function printRow(id) {
    fetch('<?= url('corrections/print-row') ?>?id=' + id + '&json=1')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('print-container');
            const cfg = { companyLogo: '<?= asset_url((new \App\Services\SettingsService())->getCompanyLogo()) ?>', companyName: '<?= e((new \App\Services\SettingsService())->getCompanyName()) ?>' };
            
            container.innerHTML = generateCorrectionRowPrintHtml(data.request, data.isAdminHr, cfg);
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
    
    fetch('<?= url('corrections/print-bulk') ?>?' + params.toString() + '&json=1')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('print-container');
            container.innerHTML = generateCorrectionBulkPrintHtml(data.rows, data.filters, data.isAdminHr, data.companyLogo, data.companyName);
            window.print();
        })
        .catch(error => console.error('Error fetching print data:', error));
}

function generateCorrectionRowPrintHtml(request, isAdminHr, cfg) {
    let requestedTimesHtml = '';
    if (request.requested_time_in) requestedTimesHtml += `<div style="margin-bottom: 8px;"><strong>Requested Time In:</strong> ${request.requested_time_in}</div>`;
    if (request.requested_time_out) requestedTimesHtml += `<div style="margin-bottom: 8px;"><strong>Requested Time Out:</strong> ${request.requested_time_out}</div>`;
    
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
                <div class="text-end text-muted">Attendance Correction</div>
            </div>
            <div style="margin-bottom: 20px;">
                <div style="margin-bottom: 8px;"><strong>Employee:</strong> ${request.employee_name || '—'}</div>
                <div style="margin-bottom: 8px;"><strong>Attendance Date:</strong> ${request.attendance_date || '—'}</div>
                <div style="margin-bottom: 8px;"><strong>Correction Type:</strong> ${request.correction_type || '—'}</div>
                ${request.original_time_in ? `<div style="margin-bottom: 8px;"><strong>Original Time In:</strong> ${request.original_time_in}</div>` : ''}
                ${request.original_time_out ? `<div style="margin-bottom: 8px;"><strong>Original Time Out:</strong> ${request.original_time_out}</div>` : ''}
                ${requestedTimesHtml}
                <div style="margin-bottom: 8px;"><strong>Status:</strong> ${request.status || '—'}</div>
                <div style="margin-bottom: 8px;"><strong>Reason:</strong> ${request.reason || '—'}</div>
                ${isAdminHr ? `<div style="margin-bottom: 8px;"><strong>Admin Remarks:</strong> ${request.admin_remarks || '—'}</div>` : ''}
                <div style="margin-bottom: 8px;"><strong>Submitted Date:</strong> ${request.created_at ? request.created_at.slice(0, 10) : '—'}</div>
            </div>
        </div>
    `;
}

function generateCorrectionBulkPrintHtml(rows, filters, isAdminHr, companyLogo, companyName) {
    let filterHtml = '';
    if (filters && Object.keys(filters).length > 0) {
        filterHtml = '<div style="margin-bottom: 20px; padding: 10px; background-color: #f9f9f9; border: 1px solid #ddd;"><h3>Applied Filters</h3>';
        if (filters.status) filterHtml += `<div style="margin-bottom: 5px;"><strong>Status:</strong> ${filters.status}</div>`;
        if (filters.correction_type) filterHtml += `<div style="margin-bottom: 5px;"><strong>Correction Type:</strong> ${filters.correction_type}</div>`;
        if (filters.start_date) filterHtml += `<div style="margin-bottom: 5px;"><strong>Start Date:</strong> ${filters.start_date}</div>`;
        if (filters.end_date) filterHtml += `<div style="margin-bottom: 5px;"><strong>End Date:</strong> ${filters.end_date}</div>`;
        if (filters.q) filterHtml += `<div style="margin-bottom: 5px;"><strong>Search:</strong> ${filters.q}</div>`;
        filterHtml += '</div>';
    }

    let tableHtml = '';
    if (rows.length === 0) {
        tableHtml = '<p>No attendance corrections found matching the current filters.</p>';
    } else {
        tableHtml = `
            <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                <thead>
                    <tr>
                        ${isAdminHr ? '<th style="border: 1px solid #ddd; padding: 8px; text-align: left; background-color: #f5f5f5; font-weight: bold;">Employee</th>' : ''}
                        <th style="border: 1px solid #ddd; padding: 8px; text-align: left; background-color: #f5f5f5; font-weight: bold;">Date</th>
                        <th style="border: 1px solid #ddd; padding: 8px; text-align: left; background-color: #f5f5f5; font-weight: bold;">Type</th>
                        <th style="border: 1px solid #ddd; padding: 8px; text-align: left; background-color: #f5f5f5; font-weight: bold;">Requested Times</th>
                        <th style="border: 1px solid #ddd; padding: 8px; text-align: left; background-color: #f5f5f5; font-weight: bold;">Status</th>
                        <th style="border: 1px solid #ddd; padding: 8px; text-align: left; background-color: #f5f5f5; font-weight: bold;">Reason</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows.map(row => {
                        const requestedTimes = [];
                        if (row.requested_time_in) requestedTimes.push(row.requested_time_in);
                        if (row.requested_time_out) requestedTimes.push(row.requested_time_out);
                        const requestedTimesStr = requestedTimes.join(' / ') || '—';
                        return `
                        <tr>
                            ${isAdminHr ? `<td style="border: 1px solid #ddd; padding: 8px; text-align: left;">${row.employee_name || '—'}</td>` : ''}
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: left;">${row.attendance_date || '—'}</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: left;">${row.correction_type || '—'}</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: left;">${requestedTimesStr}</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: left; font-weight: bold; color: ${getStatusColor(row.status)}">${row.status || '—'}</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: left;">${row.reason || '—'}</td>
                        </tr>
                    `}).join('')}
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
                <div class="text-end text-muted">Attendance Corrections Report</div>
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
