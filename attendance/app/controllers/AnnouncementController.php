<?php

/**
 * AnnouncementController
 *
 * Routes:
 *   GET  /announcements           — List announcements
 *   GET  /announcements/create    — Create form
 *   POST /announcements           — Store
 *   GET  /announcements/edit      — Edit form
 *   POST /announcements/update    — Update
 *   POST /announcements/archive   — Archive
 *   POST /announcements/publish   — Set status to published
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AnnouncementService;
use App\Services\DirectoryService;

final class AnnouncementController extends BaseController
{
    private AnnouncementService $svc;

    public function __construct()
    {
        $this->svc = new AnnouncementService();
    }

    public function index(): void
    {
        require_login();

        $filters = [
            'status' => $_GET['status'] ?? '',
            'q'      => $_GET['q']      ?? '',
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        // Employees only see published announcements
        if (has_role('employee')) {
            $filters['status'] = 'published';
        }

        $data = $this->svc->list($filters, $page);

        $this->render('announcements/index', [
            'title'   => 'Announcements',
            'rows'    => $data['rows'],
            'total'   => $data['total'],
            'page'    => $page,
            'perPage' => 20,
            'filters' => $filters,
        ]);
    }

    public function create(): void
    {
        require_role(['administrator', 'hr']);
        $dir = new DirectoryService();
        $this->render('announcements/form', [
            'title'       => 'New Announcement',
            'announcement'=> null,
            'departments' => $dir->departments(),
            'employees'   => $dir->employees(),
            'branches'    => $dir->branches(),
        ]);
    }

    public function store(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();

        $data = $this->sanitize($_POST);
        if (empty($data['title']) || empty($data['body'])) {
            flash('error', 'Title and body are required.');
            redirect('announcements/create');
        }

        $id = $this->svc->create($data, current_user()['id']);
        flash('success', 'Announcement created.');
        redirect('announcements');
    }

    public function edit(): void
    {
        require_role(['administrator', 'hr']);

        $id           = trim($_GET['id'] ?? '');
        $announcement = $this->svc->find($id);
        if (!$announcement) {
            flash('error', 'Announcement not found.');
            redirect('announcements');
        }

        $dir = new DirectoryService();
        $this->render('announcements/form', [
            'title'        => 'Edit Announcement',
            'announcement' => $announcement,
            'departments'  => $dir->departments(),
            'employees'    => $dir->employees(),
            'branches'     => $dir->branches(),
        ]);
    }

    public function update(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();

        $id   = trim($_POST['id'] ?? '');
        $data = $this->sanitize($_POST);

        if (empty($data['title']) || empty($data['body'])) {
            flash('error', 'Title and body are required.');
            redirect('announcements/edit?id=' . urlencode($id));
        }

        $this->svc->update($id, $data);
        flash('success', 'Announcement updated.');
        redirect('announcements');
    }

    public function archive(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();

        $id = trim($_POST['id'] ?? '');
        $this->svc->archive($id);
        flash('success', 'Announcement archived.');
        redirect('announcements');
    }

    public function publish(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();

        $id = trim($_POST['id'] ?? '');
        $this->svc->update($id, ['status' => 'published', 'publish_at' => date('Y-m-d H:i:s')]);
        flash('success', 'Announcement published.');
        redirect('announcements');
    }

    private function sanitize(array $post): array
    {
        return [
            'title'        => trim($post['title']        ?? ''),
            'body'         => trim($post['body']         ?? ''),
            'status'       => in_array($post['status'] ?? '', ['draft','published','archived'], true) ? $post['status'] : 'draft',
            'target_type'  => in_array($post['target_type'] ?? '', ['all','department','employee'], true) ? $post['target_type'] : 'all',
            'target_id'    => !empty($post['target_id'])    ? $post['target_id']    : null,
            'branch_id'    => !empty($post['branch_id'])    ? $post['branch_id']    : null,
            'pinned'       => isset($post['pinned'])         ? 1                    : 0,
            'publish_at'   => !empty($post['publish_at'])   ? $post['publish_at']   : null,
            'expire_at'    => !empty($post['expire_at'])    ? $post['expire_at']    : null,
            'scheduled_at' => !empty($post['scheduled_at']) ? $post['scheduled_at'] : null,
        ];
    }
}
