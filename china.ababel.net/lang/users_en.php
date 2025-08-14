<?php
/**
 * English translations for User Management
 * 
 * @author System Improvement Update
 * @date 2025-01-10
 * @description User management interface translations
 */

return [
    // User Management
    'users' => [
        'title' => 'User Management',
        'add_new' => 'Add New User',
        'edit' => 'Edit User',
        'delete' => 'Delete User',
        'view' => 'View User',
        'list' => 'Users List',
        
        // Fields
        'id' => 'ID',
        'username' => 'Username',
        'email' => 'Email',
        'password' => 'Password',
        'confirm_password' => 'Confirm Password',
        'full_name' => 'Full Name',
        'name' => 'Name',
        'role' => 'Role',
        'status' => 'Status',
        'last_login' => 'Last Login',
        'transactions' => 'Transactions',
        'clients' => 'Clients',
        'actions' => 'Actions',
        'permissions' => 'Permissions',
        'activity_log' => 'Activity Log',
        
        // Status
        'status_active' => 'Active',
        'status_inactive' => 'Inactive',
        'status_deleted' => 'Deleted',
        'toggle_status' => 'Toggle Status',
        
        // Roles
        'role_admin' => 'Administrator',
        'role_accountant' => 'Accountant',
        'role_manager' => 'Manager',
        'role_user' => 'User',
        
        // Role Descriptions
        'role_admin_desc' => 'Full system access',
        'role_accountant_desc' => 'Manage accounting and financial reports',
        'role_manager_desc' => 'Manage operations and clients',
        'role_user_desc' => 'Limited view permissions',
        
        // Role Permissions
        'role_permissions' => 'Role Permissions',
        'select_role_to_see_permissions' => 'Select a role to see available permissions',
        'role_admin_permissions' => 'All permissions: Manage users, system, transactions, clients, cashbox, reports, settings',
        'role_accountant_permissions' => 'Accounting: Manage transactions, clients, cashbox, reports',
        'role_manager_permissions' => 'Management: Transactions, clients, reports',
        'role_user_permissions' => 'View: View transactions, clients, reports',
        
        // Messages
        'created_successfully' => 'User created successfully',
        'updated_successfully' => 'User updated successfully',
        'deleted_successfully' => 'User deleted successfully',
        'status_updated' => 'User status updated',
        'permissions_updated' => 'Permissions updated successfully',
        'password_reset_success' => 'Password reset successfully',
        
        // Errors
        'not_found' => 'User not found',
        'username_exists' => 'Username or email already exists',
        'email_exists' => 'Email already in use',
        'cannot_delete_self' => 'You cannot delete your own account',
        'cannot_deactivate_self' => 'You cannot deactivate your own account',
        'error_loading' => 'Error loading users',
        
        // Validation
        'username_hint' => 'Letters and numbers only (3-50 characters)',
        'password_hint' => 'Must be at least 8 characters',
        'passwords_not_match' => 'Passwords do not match',
        'password_too_short' => 'Password is too short',
        
        // Confirmations
        'confirm_delete' => 'Confirm Delete',
        'delete_confirmation' => 'Are you sure you want to delete this user?',
        
        // Other
        'never' => 'Never',
    ],
    
    // Common
    'common' => [
        'select' => 'Select',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'delete' => 'Delete',
        'edit' => 'Edit',
        'view' => 'View',
        'back' => 'Back',
        'yes' => 'Yes',
        'no' => 'No',
    ],
    
    // Errors
    'error' => [
        'csrf_invalid' => 'Session expired, please refresh the page',
        'general' => 'An error occurred, please try again',
    ],
];