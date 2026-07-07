<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    /*
    |--------------------------------------------------------------------------
    | This App's Own Auth Screens (backend-API-driven)
    |--------------------------------------------------------------------------
    |
    | UI text for the Splash/Onboarding/Login/Register/Forgot-Password/
    | Reset-Password/Logout/Profile/Change-Password screens — see
    | docs/CUSTOMER_APP_AUTH.md. Field-level validation errors from a 422
    | response are rendered as returned by restaurant-backend (already
    | bilingual there), not from this file.
    |
    */

    'onboarding' => [
        'title' => 'Welcome',
        'description' => 'Browse the menu, place orders, and track them in real time — all from your phone.',
        'get_started' => 'Get started',
    ],

    'login' => [
        'title' => 'Log in',
        'description' => 'Enter your email and password to continue',
        'email' => 'Email address',
        'password' => 'Password',
        'submit' => 'Log in',
        'no_account' => 'Don\'t have an account?',
        'sign_up' => 'Sign up',
        'forgot_password' => 'Forgot your password?',
    ],

    'register' => [
        'title' => 'Create an account',
        'description' => 'Enter your details below to create your account',
        'name' => 'Name',
        'email' => 'Email address',
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'submit' => 'Create account',
        'has_account' => 'Already have an account?',
        'log_in' => 'Log in',
    ],

    'forgot_password' => [
        'title' => 'Forgot password',
        'description' => 'Enter your email and we\'ll send you a password reset code if an account exists',
        'email' => 'Email address',
        'submit' => 'Email password reset code',
        'sent' => 'If an account exists for this email, a password reset code has been sent to it.',
        'back_to_login' => 'Back to log in',
        'have_code' => 'Already have a reset code?',
    ],

    'reset_password' => [
        'title' => 'Reset password',
        'description' => 'Enter the code from your email along with your new password',
        'email' => 'Email address',
        'token' => 'Reset code',
        'token_help' => 'Copy this from the password reset email we sent you.',
        'password' => 'New password',
        'password_confirmation' => 'Confirm new password',
        'submit' => 'Reset password',
        'success' => 'Your password has been reset. Every device has been signed out — please log in again.',
    ],

    'logout' => [
        'title' => 'Log out',
        'description' => 'Are you sure you want to log out of this device?',
        'confirm' => 'Log out',
        'cancel' => 'Cancel',
    ],

    'profile' => [
        'title' => 'Profile',
        'name' => 'Name',
        'email' => 'Email address',
        'phone' => 'Phone number',
        'save' => 'Save changes',
        'saved' => 'Your profile has been updated.',
        'change_password' => 'Change password',
        'log_out' => 'Log out',
    ],

    'change_password' => [
        'title' => 'Change password',
        'current_password' => 'Current password',
        'password' => 'New password',
        'password_confirmation' => 'Confirm new password',
        'submit' => 'Change password',
        'saved' => 'Your password has been changed.',
    ],

];
