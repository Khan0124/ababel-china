<?php
/**
 * Arabic translations for User Management
 * 
 * @author System Improvement Update
 * @date 2025-01-10
 * @description User management interface translations
 */

return [
    // User Management
    'users' => [
        'title' => 'إدارة المستخدمين',
        'add_new' => 'إضافة مستخدم جديد',
        'edit' => 'تعديل المستخدم',
        'delete' => 'حذف المستخدم',
        'view' => 'عرض المستخدم',
        'list' => 'قائمة المستخدمين',
        
        // Fields
        'id' => 'المعرف',
        'username' => 'اسم المستخدم',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'confirm_password' => 'تأكيد كلمة المرور',
        'full_name' => 'الاسم الكامل',
        'name' => 'الاسم',
        'role' => 'الصلاحية',
        'status' => 'الحالة',
        'last_login' => 'آخر دخول',
        'transactions' => 'المعاملات',
        'clients' => 'العملاء',
        'actions' => 'الإجراءات',
        'permissions' => 'الصلاحيات',
        'activity_log' => 'سجل النشاط',
        
        // Status
        'status_active' => 'نشط',
        'status_inactive' => 'غير نشط',
        'status_deleted' => 'محذوف',
        'toggle_status' => 'تبديل الحالة',
        
        // Roles
        'role_admin' => 'مدير النظام',
        'role_accountant' => 'محاسب',
        'role_manager' => 'مدير',
        'role_user' => 'مستخدم',
        
        // Role Descriptions
        'role_admin_desc' => 'صلاحيات كاملة على النظام',
        'role_accountant_desc' => 'إدارة المحاسبة والتقارير المالية',
        'role_manager_desc' => 'إدارة العمليات والعملاء',
        'role_user_desc' => 'صلاحيات عرض محدودة',
        
        // Role Permissions
        'role_permissions' => 'صلاحيات الدور',
        'select_role_to_see_permissions' => 'اختر دوراً لعرض الصلاحيات المتاحة',
        'role_admin_permissions' => 'جميع الصلاحيات: إدارة المستخدمين، النظام، المعاملات، العملاء، الصندوق، التقارير، الإعدادات',
        'role_accountant_permissions' => 'المحاسبة: إدارة المعاملات، العملاء، الصندوق، التقارير',
        'role_manager_permissions' => 'الإدارة: المعاملات، العملاء، التقارير',
        'role_user_permissions' => 'العرض: عرض المعاملات، العملاء، التقارير',
        
        // Messages
        'created_successfully' => 'تم إنشاء المستخدم بنجاح',
        'updated_successfully' => 'تم تحديث المستخدم بنجاح',
        'deleted_successfully' => 'تم حذف المستخدم بنجاح',
        'status_updated' => 'تم تحديث حالة المستخدم',
        'permissions_updated' => 'تم تحديث الصلاحيات بنجاح',
        'password_reset_success' => 'تم إعادة تعيين كلمة المرور بنجاح',
        
        // Errors
        'not_found' => 'المستخدم غير موجود',
        'username_exists' => 'اسم المستخدم أو البريد الإلكتروني موجود مسبقاً',
        'email_exists' => 'البريد الإلكتروني مستخدم مسبقاً',
        'cannot_delete_self' => 'لا يمكنك حذف حسابك الخاص',
        'cannot_deactivate_self' => 'لا يمكنك تعطيل حسابك الخاص',
        'error_loading' => 'خطأ في تحميل المستخدمين',
        
        // Validation
        'username_hint' => 'أحرف إنجليزية وأرقام فقط (3-50 حرف)',
        'password_hint' => 'يجب أن تكون 8 أحرف على الأقل',
        'passwords_not_match' => 'كلمات المرور غير متطابقة',
        'password_too_short' => 'كلمة المرور قصيرة جداً',
        
        // Confirmations
        'confirm_delete' => 'تأكيد الحذف',
        'delete_confirmation' => 'هل أنت متأكد من حذف هذا المستخدم؟',
        
        // Other
        'never' => 'أبداً',
    ],
    
    // Common
    'common' => [
        'select' => 'اختر',
        'save' => 'حفظ',
        'cancel' => 'إلغاء',
        'delete' => 'حذف',
        'edit' => 'تعديل',
        'view' => 'عرض',
        'back' => 'رجوع',
        'yes' => 'نعم',
        'no' => 'لا',
    ],
    
    // Errors
    'error' => [
        'csrf_invalid' => 'انتهت صلاحية الجلسة، يرجى تحديث الصفحة',
        'general' => 'حدث خطأ، يرجى المحاولة مرة أخرى',
    ],
];