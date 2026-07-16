<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ProfileService;
use Throwable;

/**
 * ProfileController
 *
 * Handles the authenticated user's own profile settings:
 *   GET  /profile          → show My Profile page
 *   POST /profile/password → change password
 *   POST /profile/picture  → upload / replace profile picture
 *   POST /profile/picture/remove → remove profile picture
 */
final class ProfileController extends BaseController
{
    private ProfileService $service;

    public function __construct()
    {
        $this->service = new ProfileService();
    }

    // ----------------------------------------------------------------
    // Show profile page
    // ----------------------------------------------------------------

    public function index(): void
    {
        require_login();

        $user   = current_user();
        $profile = $this->service->findByUserId($user['id']);

        if (!$profile) {
            flash('error', 'Your account could not be loaded.');
            redirect('dashboard');
        }

        $this->render('profile/index', [
            'title'   => 'My Profile',
            'profile' => $profile,
        ]);
    }

    // ----------------------------------------------------------------
    // Change password
    // ----------------------------------------------------------------

    public function changePassword(): void
    {
        require_login();
        verify_csrf();

        $user = current_user();

        try {
            $this->service->changePassword($user['id'], $_POST);

            // Clear the must_change_password flag from the live session
            $_SESSION['user']['must_change_password'] = 0;

            flash('success', 'Password changed successfully.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        redirect('profile');
    }

    // ----------------------------------------------------------------
    // Upload / replace profile picture
    // ----------------------------------------------------------------

    public function uploadPicture(): void
    {
        require_login();
        verify_csrf();

        $user = current_user();

        try {
            $path = $this->service->uploadProfilePicture($user['id']);

            // Keep the session avatar in sync
            $_SESSION['user']['profile_picture'] = $path;

            flash('success', 'Profile picture updated successfully.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        redirect('profile');
    }

    // ----------------------------------------------------------------
    // Remove profile picture
    // ----------------------------------------------------------------

    public function removePicture(): void
    {
        require_login();
        verify_csrf();

        $user = current_user();

        try {
            $this->service->removeProfilePicture($user['id']);

            // Clear avatar from session
            $_SESSION['user']['profile_picture'] = null;

            flash('success', 'Profile picture removed.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        redirect('profile');
    }
}
