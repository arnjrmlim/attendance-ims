<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DirectoryService;
use App\Services\HolidayService;
use Throwable;

final class HolidayController extends BaseController
{
    public function index(): void
    {
        require_role(['administrator', 'hr']);
        $this->render('holidays/index', [
            'title' => 'Holiday Management',
            'rows' => (new HolidayService())->list($_GET),
            'branches' => (new DirectoryService())->branches(),
        ]);
    }

    public function store(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();
        try {
            (new HolidayService())->store($_POST);
            flash('success', 'Holiday saved.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('holidays');
    }

    public function update(): void
    {
        $this->store();
    }

    public function deactivate(): void
    {
        require_role(['administrator', 'hr']);
        verify_csrf();
        (new HolidayService())->deactivate((string) ($_POST['id'] ?? ''));
        flash('success', 'Holiday deactivated.');
        redirect('holidays');
    }
}
