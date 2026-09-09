<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;
use App\Services\Exceptions\AccountLockedException;
use App\Services\Exceptions\AuthenticationException;

/**
 * Authentication Controller
 * Manages administrative login presentation, authentication processing, and logout.
 */
class AuthController extends Controller
{
    private AuthService $authService;

    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
    }

    /**
     * Show the administrative login view.
     */
    public function showLogin(Request $request): Response
    {
        return $this->render('auth/login', [
            'title' => 'Admin Login',
        ], 'layouts/public');
    }

    /**
     * Process login form submission.
     */
    public function login(Request $request): Response
    {
        $email = (string) $request->post('email', '');
        $password = (string) $request->post('password', '');

        // Basic format check
        if (empty(trim($email)) || empty($password)) {
            Session::flash('error', 'Please provide both your email address and password.');
            return $this->redirect('/login');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Invalid email address format.');
            return $this->redirect('/login');
        }

        try {
            $this->authService->authenticate(
                $email,
                $password,
                $request->ip(),
                $request->userAgent()
            );

            // Check for safe intended URL
            $intended = Session::get('_intended_url');
            Session::remove('_intended_url');

            if (is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//')) {
                return $this->redirect($intended);
            }

            return $this->redirect('/admin');
        } catch (AccountLockedException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/login');
        } catch (AuthenticationException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/login');
        } catch (\Throwable $e) {
            Logger::error('Unexpected authentication error: ' . $e->getMessage());
            Session::flash('error', 'An unexpected error occurred. Please try again.');
            return $this->redirect('/login');
        }
    }

    /**
     * Process logout request.
     */
    public function logout(Request $request): Response
    {
        $this->authService->logout($request->ip(), $request->userAgent());
        Session::flash('success', 'You have been securely logged out.');
        return $this->redirect('/login');
    }
}
