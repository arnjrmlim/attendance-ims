<?php /** Employee: Self-service manual attendance request */ ?>

<div class="page-head">
    <div>
        <h4 class="fw-semibold mb-0"><i class="bi bi-send me-2"></i>Manual Attendance</h4>
        <small class="text-muted">Submit a manual time-in or time-out — recorded immediately</small>
    </div>
    <a href="<?= url('manual-attendance') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> My Records
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="alert alert-info py-2 mb-3">
            <i class="bi bi-info-circle me-2"></i>
            Manual attendance is the primary attendance method. Your entry is recorded using the
            current date and time and applied to your attendance record right away — no admin
            approval required.
        </div>

        <form method="post" action="<?= url('manual-attendance/request') ?>">
            <?= csrf_field() ?>
            <div class="panel p-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Request Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="request_type" required>
                            <option value="">— Select —</option>
                            <option value="time_in">Manual Time In</option>
                            <option value="time_out">Manual Time Out</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date</label>
                        <input type="text" class="form-control" value="<?= date('Y-m-d') ?>" disabled readonly>
                        <small class="text-muted">Set automatically</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Time</label>
                        <input type="text" class="form-control" value="<?= date('h:i A') ?>" disabled readonly>
                        <small class="text-muted">Set automatically</small>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i>Submit Request
                    </button>
                    <a href="<?= url('manual-attendance') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
