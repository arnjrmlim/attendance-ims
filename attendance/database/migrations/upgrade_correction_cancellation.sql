-- Add the cancellation state required by attendance correction requests.
ALTER TABLE attendance_corrections
    MODIFY COLUMN status ENUM('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
    MODIFY COLUMN correction_type ENUM('Forgot Time In','Forgot Time Out','Incorrect Attendance','Wrong Attendance Method','Official Business') NOT NULL;
