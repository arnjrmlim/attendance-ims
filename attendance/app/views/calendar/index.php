<div class="page-head"><div><h1 class="h3 mb-1">Attendance Calendar</h1><div class="text-muted">Attendance, leaves, holidays and correction context by date.</div></div><form><input class="form-control" type="month" name="month" value="<?= e($month) ?>" onchange="this.form.submit()"></form></div>
<?php
$start = new DateTimeImmutable($month . '-01');
$days = (int) $start->format('t');
$attendanceByDate = [];
foreach ($events['attendance'] as $event) { $attendanceByDate[$event['event_date']][] = $event; }
$holidaysByDate = [];
foreach ($events['holidays'] as $event) { $holidaysByDate[$event['event_date']][] = $event; }
?>
<div class="calendar-grid">
<?php for ($day = 1; $day <= $days; $day++): $date = $month . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT); ?>
    <button class="calendar-day text-start" type="button" data-bs-toggle="modal" data-bs-target="#day<?= $day ?>">
        <div class="fw-semibold"><?= e(date('M j', strtotime($date))) ?></div>
        <?php foreach ($attendanceByDate[$date] ?? [] as $event): ?><span class="badge text-bg-info me-1"><?= e($event['type'] . ': ' . $event['total']) ?></span><?php endforeach; ?>
        <?php foreach ($holidaysByDate[$date] ?? [] as $event): ?><span class="badge text-bg-warning me-1"><?= e($event['label']) ?></span><?php endforeach; ?>
    </button>
    <div class="modal fade" id="day<?= $day ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5"><?= e($date) ?></h2><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
        <?php if (!empty($attendanceByDate[$date])): ?>
            <h6 class="fw-semibold">Attendance</h6>
            <?php foreach ($attendanceByDate[$date] as $event): ?>
                <div class="mb-2"><span class="badge text-bg-info"><?= e($event['type']) ?></span>: <?= e($event['total']) ?> employee(s)</div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if (!empty($holidaysByDate[$date])): ?>
            <h6 class="fw-semibold mt-3">Holidays</h6>
            <?php foreach ($holidaysByDate[$date] as $event): ?>
                <div class="mb-2">
                    <div class="fw-semibold"><?= e($event['label']) ?></div>
                    <?php if (!empty($event['description'])): ?>
                        <div class="text-muted small"><?= e($event['description']) ?></div>
                    <?php endif; ?>
                    <div><span class="badge text-bg-secondary"><?= e($event['type']) ?></span></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if (empty($attendanceByDate[$date]) && empty($holidaysByDate[$date])): ?>
            <div class="text-muted">No events for this date.</div>
        <?php endif; ?>
    </div></div></div></div>
<?php endfor; ?>
</div>
