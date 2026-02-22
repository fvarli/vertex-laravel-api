<?php

return [

    // General
    'success' => 'Success',
    'error' => 'Error',
    'too_many_requests' => 'Too many requests. Please try again later.',
    'forbidden' => 'Forbidden.',
    'not_found' => 'Resource not found.',
    'method_not_allowed' => 'Method not allowed.',
    'unauthenticated' => 'Unauthenticated.',
    'validation_failed' => 'Validation failed.',
    'server_error' => 'Internal server error.',

    // Auth
    'auth' => [
        'registered' => 'User registered successfully.',
        'login_success' => 'Login successful.',
        'login_failed' => 'Invalid credentials.',
        'account_deactivated' => 'Your account has been deactivated.',
        'logged_out' => 'Logged out successfully.',
        'logged_out_all' => 'Logged out from all devices successfully.',
        'token_refreshed' => 'Token refreshed successfully.',
    ],

    // Password
    'password' => [
        'changed' => 'Password changed successfully.',
        'reset_link' => 'If your email is registered, you will receive a password reset link.',
        'reset_success' => 'Password has been reset successfully.',
        'reset_failed' => 'Invalid or expired reset token.',
    ],

    // Verification
    'verification' => [
        'verified' => 'Email verified successfully.',
        'already_verified' => 'Email is already verified.',
        'invalid_link' => 'Invalid verification link.',
        'link_sent' => 'Verification link sent.',
    ],

    // Profile
    'profile' => [
        'updated' => 'Profile updated successfully.',
        'password_changed' => 'Password changed successfully.',
        'account_deleted' => 'Account deleted successfully.',
        'avatar_uploaded' => 'Avatar uploaded successfully.',
        'avatar_deleted' => 'Avatar deleted successfully.',
    ],

    // Health
    'health' => [
        'ok' => 'All systems operational.',
        'degraded' => 'Some services are experiencing issues.',
    ],

    // Workspace
    'workspace' => [
        'created' => 'Workspace created successfully.',
        'created_pending_approval' => 'Workspace created and submitted for platform approval.',
        'switched' => 'Active workspace switched successfully.',
        'no_active_workspace' => 'No active workspace selected.',
        'membership_required' => 'You do not have access to this workspace.',
        'approval_required' => 'Workspace approval is required for this action.',
        'approval_updated' => 'Workspace approval status updated successfully.',
    ],

    // Student
    'student' => [
        'created' => 'Student created successfully.',
        'updated' => 'Student updated successfully.',
        'status_updated' => 'Student status updated successfully.',
    ],

    // Trainer
    'trainer' => [
        'created' => 'Trainer created successfully.',
    ],

    // Program
    'program' => [
        'created' => 'Program created successfully.',
        'updated' => 'Program updated successfully.',
        'status_updated' => 'Program status updated successfully.',
        'active_exists_for_week' => 'An active program already exists for this student and week.',
        'duplicate_day_order' => 'Program items cannot share the same day_of_week and order_no.',
        'template_created' => 'Program template created successfully.',
        'template_updated' => 'Program template updated successfully.',
        'template_deleted' => 'Program template deleted successfully.',
        'copied_week' => 'Program copied to target week successfully.',
        'copy_source_missing' => 'No source program found for the selected source week.',
    ],

    // Appointment
    'appointment' => [
        'created' => 'Appointment created successfully.',
        'updated' => 'Appointment updated successfully.',
        'status_updated' => 'Appointment status updated successfully.',
        'whatsapp_status_updated' => 'Appointment WhatsApp status updated successfully.',
        'series_created' => 'Appointment series created successfully.',
        'series_updated' => 'Appointment series updated successfully.',
        'series_status_updated' => 'Appointment series status updated successfully.',
        'conflict' => 'Appointment conflict detected for trainer or student.',
        'invalid_status_transition' => 'Status transition is not allowed.',
        'cannot_complete_future' => 'Future appointments cannot be marked as done or no_show.',
    ],

    'reminder' => [
        'opened' => 'Reminder marked as opened.',
        'marked_sent' => 'Reminder marked as sent.',
        'cancelled' => 'Reminder cancelled.',
        'requeued' => 'Reminder requeued successfully.',
        'bulk_applied' => 'Bulk reminder action applied successfully.',
    ],

    'notifications' => [
        'marked_read' => 'Notification marked as read.',
        'marked_all_read' => 'All notifications marked as read.',
    ],

    // Reports
    'reports' => [
        'trainer_performance' => 'Trainer performance report generated.',
        'student_retention' => 'Student retention report generated.',
        'export_csv' => 'CSV export generated.',
        'export_pdf' => 'PDF export generated.',
    ],

    // Device Tokens
    'device_token' => [
        'registered' => 'Device token registered successfully.',
        'deleted' => 'Device token deleted successfully.',
    ],

    // WhatsApp
    'whatsapp' => [
        'bulk_links_generated' => 'Bulk WhatsApp links generated.',
    ],

    // Message Templates
    'message_template' => [
        'created' => 'Message template created successfully.',
        'updated' => 'Message template updated successfully.',
        'deleted' => 'Message template deleted successfully.',
    ],

    // Web
    'web' => [
        'api_only' => 'This is an API-only application. No web access allowed.',
        'api_client_only' => 'API access only. Please use an API client.',
    ],

];
