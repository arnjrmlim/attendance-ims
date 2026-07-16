<div class="page-head">
    <div>
        <h1 class="h3 mb-1">Import Employees</h1>
        <div class="text-muted">Bulk import employees from Excel file.</div>
    </div>
    <div>
        <a href="<?= url('employees') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to List</a>
    </div>
</div>

<?php if (flash('warning')): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <?= e(flash('warning')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="panel p-3">
            <h5 class="mb-3"><i class="bi bi-upload"></i> Upload Excel File</h5>
            <form method="post" action="<?= url('employees/import') ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Select Excel File (.xlsx)</label>
                    <input type="file" class="form-control" name="import_file" accept=".xlsx" required>
                    <small class="text-muted">Maximum file size: 5MB</small>
                </div>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> 
                    <strong>Note:</strong> Employee import feature requires a library like PhpSpreadsheet. 
                    This feature will be fully implemented in a future update.
                </div>
                <button type="submit" class="btn btn-success" disabled>
                    <i class="bi bi-upload"></i> Import Employees
                </button>
            </form>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="panel p-3">
            <h5 class="mb-3"><i class="bi bi-file-earmark-excel"></i> Template Format</h5>
            <p class="text-muted small">Your Excel file should contain the following columns:</p>
            <ul class="small">
                <li><strong>employee_number</strong> (Required)</li>
                <li><strong>first_name</strong> (Required)</li>
                <li><strong>middle_name</strong></li>
                <li><strong>last_name</strong> (Required)</li>
                <li><strong>suffix</strong></li>
                <li><strong>gender</strong></li>
                <li><strong>date_of_birth</strong></li>
                <li><strong>civil_status</strong></li>
                <li><strong>nationality</strong></li>
                <li><strong>contact_number</strong> (Required)</li>
                <li><strong>alternate_mobile</strong></li>
                <li><strong>email</strong> (Required)</li>
                <li><strong>home_address</strong></li>
                <li><strong>emergency_contact_name</strong> (Required)</li>
                <li><strong>emergency_contact_number</strong> (Required)</li>
                <li><strong>emergency_contact_relationship</strong> (Required)</li>
                <li><strong>department_id</strong> (Required)</li>
                <li><strong>branch_id</strong> (Required)</li>
                <li><strong>position</strong> (Required)</li>
                <li><strong>employment_status</strong> (Required)</li>
                <li><strong>employment_type</strong> (Required)</li>
                <li><strong>date_hired</strong> (Required)</li>
                <li><strong>shift_id</strong> (Required)</li>
                <li><strong>pin</strong> (Required)</li>
                <li><strong>rfid_value</strong></li>
            </ul>
            <hr>
            <p class="text-muted small mb-2"><strong>Validation Rules:</strong></p>
            <ul class="small">
                <li>Employee numbers must be unique</li>
                <li>Email addresses must be unique</li>
                <li>PINs must be unique</li>
                <li>Department, Branch, and Shift IDs must exist</li>
                <li>Required fields cannot be empty</li>
            </ul>
        </div>
        
        <div class="panel p-3 mt-3">
            <h5 class="mb-3"><i class="bi bi-download"></i> Download Template</h5>
            <p class="text-muted small">Download a sample Excel template to get started.</p>
            <button class="btn btn-outline-primary w-100" disabled>
                <i class="bi bi-download"></i> Download Template
            </button>
        </div>
    </div>
</div>
