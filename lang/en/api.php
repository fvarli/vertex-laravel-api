<?php

return [

    // General
    'success'            => 'Success',
    'error'              => 'Error',
    'too_many_requests'  => 'Too many requests. Please try again later.',
    'forbidden'          => 'Forbidden.',
    'not_found'          => 'Resource not found.',
    'method_not_allowed' => 'Method not allowed.',
    'unauthenticated'    => 'Unauthenticated.',
    'validation_failed'  => 'Validation failed.',
    'server_error'       => 'Internal server error.',

    // Auth
    'auth' => [
        'registered'          => 'User registered successfully.',
        'login_success'       => 'Login successful.',
        'login_failed'        => 'Invalid credentials.',
        'account_deactivated' => 'Your account has been deactivated.',
        'logged_out'          => 'Logged out successfully.',
        'logged_out_all'      => 'Logged out from all devices successfully.',
        'token_refreshed'     => 'Token refreshed successfully.',
    ],

    // Password
    'password' => [
        'changed'       => 'Password changed successfully.',
        'reset_link'    => 'If your email is registered, you will receive a password reset link.',
        'reset_success' => 'Password has been reset successfully.',
        'reset_failed'  => 'Invalid or expired reset token.',
    ],

    // Verification
    'verification' => [
        'verified'         => 'Email verified successfully.',
        'already_verified' => 'Email is already verified.',
        'invalid_link'     => 'Invalid verification link.',
        'link_sent'        => 'Verification link sent.',
    ],

    // Profile
    'profile' => [
        'updated'          => 'Profile updated successfully.',
        'password_changed' => 'Password changed successfully.',
    ],

    // Health
    'health' => [
        'ok'       => 'All systems operational.',
        'degraded' => 'Some services are experiencing issues.',
    ],

];
