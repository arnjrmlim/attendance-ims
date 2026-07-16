<?php

/**
 * AnnouncementService
 *
 * Manages announcements: create, edit, schedule, publish, archive.
 * Targets: everyone, department, or individual employee.
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class AnnouncementService
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    /* ── List / Read ─────────────────────────────────────────── */

    public function list(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'a.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where[]  = '(a.title LIKE ? OR a.body LIKE ?)';
            $params[] = '%' . $filters['q'] . '%';
            $params[] = '%' . $filters['q'] . '%';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = (int) Database::connection()
            ->prepare("SELECT COUNT(*) FROM announcements a {$whereClause}")
            ->execute($params) ? Database::connection()
            ->prepare("SELECT COUNT(*) FROM announcements a {$whereClause}")
            ->execute($params) : 0;

        // Correct count
        $cntStmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM announcements a {$whereClause}"
        );
        $cntStmt->execute($params);
        $total = (int) $cntStmt->fetchColumn();

        $offset   = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = Database::connection()->prepare(
            "SELECT a.*, u.username AS author_name
             FROM announcements a
             LEFT JOIN users u ON u.id = a.author_id
             {$whereClause}
             ORDER BY a.pinned DESC, a.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute($params);

        return ['total' => $total, 'rows' => $stmt->fetchAll()];
    }

    /**
     * Get announcements visible to a specific user/employee (for dashboard).
     */
    public function visible(string $userId, ?string $employeeId, ?string $departmentId): array
    {
        $now = date('Y-m-d H:i:s');
        $stmt = Database::connection()->prepare(
            "SELECT a.*, u.username AS author_name
             FROM announcements a
             LEFT JOIN users u ON u.id = a.author_id
             WHERE a.status = 'published'
               AND (a.publish_at IS NULL OR a.publish_at <= ?)
               AND (a.expire_at  IS NULL OR a.expire_at  >= ?)
               AND (
                     a.target_type = 'all'
                  OR (a.target_type = 'department' AND a.target_id = ?)
                  OR (a.target_type = 'employee'   AND a.target_id = ?)
               )
             ORDER BY a.pinned DESC, a.created_at DESC
             LIMIT 10"
        );
        $stmt->execute([$now, $now, $departmentId, $employeeId]);
        return $stmt->fetchAll();
    }

    public function find(string $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT a.*, u.username AS author_name FROM announcements a LEFT JOIN users u ON u.id = a.author_id WHERE a.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /* ── Write ───────────────────────────────────────────────── */

    public function create(array $data, string $authorId): string
    {
        $id = uuid_v4();
        Database::connection()->prepare(
            "INSERT INTO announcements
             (id, title, body, author_id, branch_id, target_type, target_id, pinned, publish_at, expire_at, scheduled_at, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $id,
            $data['title']        ?? '',
            $data['body']         ?? '',
            $authorId,
            $data['branch_id']    ?? null,
            $data['target_type']  ?? 'all',
            $data['target_id']    ?? null,
            (int) ($data['pinned'] ?? 0),
            $data['publish_at']   ?? null,
            $data['expire_at']    ?? null,
            $data['scheduled_at'] ?? null,
            $data['status']       ?? 'draft',
        ]);

        $this->audit->log('ANNOUNCEMENT_CREATED', 'announcements', $id, null, $data);

        return $id;
    }

    public function update(string $id, array $data): bool
    {
        $old = $this->find($id);
        Database::connection()->prepare(
            "UPDATE announcements
             SET title = ?, body = ?, branch_id = ?, target_type = ?, target_id = ?,
                 pinned = ?, publish_at = ?, expire_at = ?, scheduled_at = ?, status = ?,
                 updated_at = NOW()
             WHERE id = ?"
        )->execute([
            $data['title']        ?? $old['title'],
            $data['body']         ?? $old['body'],
            $data['branch_id']    ?? $old['branch_id'],
            $data['target_type']  ?? $old['target_type'],
            $data['target_id']    ?? $old['target_id'],
            (int) ($data['pinned'] ?? $old['pinned']),
            $data['publish_at']   ?? $old['publish_at'],
            $data['expire_at']    ?? $old['expire_at'],
            $data['scheduled_at'] ?? $old['scheduled_at'],
            $data['status']       ?? $old['status'],
            $id,
        ]);

        $this->audit->log('ANNOUNCEMENT_UPDATED', 'announcements', $id, $old, $data);
        return true;
    }

    public function archive(string $id): bool
    {
        $old = $this->find($id);
        Database::connection()->prepare(
            "UPDATE announcements SET status = 'archived', updated_at = NOW() WHERE id = ?"
        )->execute([$id]);
        $this->audit->log('ANNOUNCEMENT_ARCHIVED', 'announcements', $id, $old, null);
        return true;
    }

    /**
     * Publish any scheduled announcements whose publish_at has arrived.
     */
    public function publishScheduled(): int
    {
        $stmt = Database::connection()->prepare(
            "UPDATE announcements
             SET status = 'published', updated_at = NOW()
             WHERE status = 'draft'
               AND publish_at IS NOT NULL
               AND publish_at <= NOW()"
        );
        $stmt->execute();
        return $stmt->rowCount();
    }
}
