<?php

namespace App\Support;

class Permissions
{
    public const DASHBOARD_VIEW = 'dashboard.view';

    public const PATIENTS_VIEW = 'patients.view';

    public const PATIENTS_CREATE = 'patients.create';

    public const PATIENTS_UPDATE = 'patients.update';

    public const PATIENTS_MERGE = 'patients.merge';

    public const APPOINTMENTS_VIEW = 'appointments.view';

    public const APPOINTMENTS_CREATE = 'appointments.create';

    public const APPOINTMENTS_MANAGE = 'appointments.manage';

    public const QUEUES_VIEW = 'queues.view';

    public const QUEUES_MANAGE = 'queues.manage';

    public const ENCOUNTERS_VIEW = 'encounters.view';

    public const ENCOUNTERS_CREATE = 'encounters.create';

    public const ENCOUNTERS_UPDATE = 'encounters.update';

    public const ENCOUNTERS_SIGN = 'encounters.sign';

    public const VITALS_CREATE = 'vitals.create';

    public const DIAGNOSES_CREATE = 'diagnoses.create';

    public const CLINICAL_NOTES_CREATE = 'clinical_notes.create';

    public const PRESCRIPTIONS_VIEW = 'prescriptions.view';

    public const PRESCRIPTIONS_CREATE = 'prescriptions.create';

    public const PRESCRIPTIONS_DISPENSE = 'prescriptions.dispense';

    public const LABORATORY_VIEW = 'laboratory.view';

    public const LABORATORY_MANAGE = 'laboratory.manage';

    public const LABORATORY_APPROVE = 'laboratory.approve';

    public const RADIOLOGY_VIEW = 'radiology.view';

    public const RADIOLOGY_MANAGE = 'radiology.manage';

    public const BILLING_VIEW = 'billing.view';

    public const PAYMENTS_COLLECT = 'payments.collect';

    public const REFUNDS_APPROVE = 'refunds.approve';

    public const INSURANCE_VIEW = 'insurance.view';

    public const INSURANCE_MANAGE = 'insurance.manage';

    public const REPORTS_VIEW = 'reports.view';

    public const REPORTS_EXPORT = 'reports.export';

    public const SETTINGS_MANAGE = 'settings.manage';

    public const USERS_MANAGE = 'users.manage';

    public const ROLES_MANAGE = 'roles.manage';

    public const BRANCHES_MANAGE = 'branches.manage';

    public const AUDIT_VIEW = 'audit.view';

    public const ACCOUNTING_VIEW = 'accounting.view';

    public const ACCOUNTING_MANAGE = 'accounting.manage';

    /**
     * Role => permissions. Super administrator receives every permission at runtime.
     *
     * @return array<string, array{ar: string, en: string, permissions: array<int, string>}>
     */
    public static function roles(): array
    {
        $clinical = [
            self::DASHBOARD_VIEW,
            self::PATIENTS_VIEW,
            self::APPOINTMENTS_VIEW,
            self::QUEUES_VIEW,
            self::ENCOUNTERS_VIEW,
        ];

        return [
            'super_admin' => [
                'ar' => 'مدير النظام',
                'en' => 'Super Administrator',
                'permissions' => self::all(),
            ],
            'hospital_admin' => [
                'ar' => 'مدير المستشفى',
                'en' => 'Hospital Administrator',
                'permissions' => array_merge($clinical, [
                    self::PATIENTS_CREATE,
                    self::PATIENTS_UPDATE,
                    self::PATIENTS_MERGE,
                    self::APPOINTMENTS_CREATE,
                    self::APPOINTMENTS_MANAGE,
                    self::QUEUES_MANAGE,
                    self::BILLING_VIEW,
                    self::REPORTS_VIEW,
                    self::REPORTS_EXPORT,
                    self::INSURANCE_VIEW,
                    self::USERS_MANAGE,
                    self::AUDIT_VIEW,
                    self::SETTINGS_MANAGE,
                    self::ACCOUNTING_VIEW,
                    self::ACCOUNTING_MANAGE,
                ]),
            ],
            'medical_director' => [
                'ar' => 'المدير الطبي',
                'en' => 'Medical Director',
                'permissions' => array_merge($clinical, [
                    self::ENCOUNTERS_CREATE,
                    self::ENCOUNTERS_UPDATE,
                    self::ENCOUNTERS_SIGN,
                    self::VITALS_CREATE,
                    self::DIAGNOSES_CREATE,
                    self::CLINICAL_NOTES_CREATE,
                    self::PRESCRIPTIONS_VIEW,
                    self::PRESCRIPTIONS_CREATE,
                    self::LABORATORY_VIEW,
                    self::LABORATORY_APPROVE,
                    self::RADIOLOGY_VIEW,
                    self::REPORTS_VIEW,
                    self::REPORTS_EXPORT,
                ]),
            ],
            'doctor' => [
                'ar' => 'طبيب',
                'en' => 'Doctor',
                'permissions' => array_merge($clinical, [
                    self::ENCOUNTERS_CREATE,
                    self::ENCOUNTERS_UPDATE,
                    self::ENCOUNTERS_SIGN,
                    self::VITALS_CREATE,
                    self::DIAGNOSES_CREATE,
                    self::CLINICAL_NOTES_CREATE,
                    self::PRESCRIPTIONS_VIEW,
                    self::PRESCRIPTIONS_CREATE,
                    self::LABORATORY_VIEW,
                    self::RADIOLOGY_VIEW,
                ]),
            ],
            'nurse' => [
                'ar' => 'ممرض',
                'en' => 'Nurse',
                'permissions' => array_merge($clinical, [
                    self::ENCOUNTERS_UPDATE,
                    self::VITALS_CREATE,
                    self::CLINICAL_NOTES_CREATE,
                    self::PRESCRIPTIONS_VIEW,
                    self::LABORATORY_VIEW,
                ]),
            ],
            'receptionist' => [
                'ar' => 'موظف استقبال',
                'en' => 'Receptionist',
                'permissions' => [
                    self::DASHBOARD_VIEW,
                    self::PATIENTS_VIEW,
                    self::PATIENTS_CREATE,
                    self::PATIENTS_UPDATE,
                    self::APPOINTMENTS_VIEW,
                    self::APPOINTMENTS_CREATE,
                    self::APPOINTMENTS_MANAGE,
                    self::QUEUES_VIEW,
                    self::QUEUES_MANAGE,
                    self::BILLING_VIEW,
                    self::PAYMENTS_COLLECT,
                ],
            ],
            'cashier' => [
                'ar' => 'أمين صندوق',
                'en' => 'Cashier',
                'permissions' => [
                    self::DASHBOARD_VIEW,
                    self::PATIENTS_VIEW,
                    self::APPOINTMENTS_VIEW,
                    self::QUEUES_VIEW,
                    self::BILLING_VIEW,
                    self::PAYMENTS_COLLECT,
                ],
            ],
            'laboratory_technician' => [
                'ar' => 'فني مختبر',
                'en' => 'Laboratory Technician',
                'permissions' => [
                    self::DASHBOARD_VIEW,
                    self::PATIENTS_VIEW,
                    self::ENCOUNTERS_VIEW,
                    self::LABORATORY_VIEW,
                    self::LABORATORY_MANAGE,
                ],
            ],
            'laboratory_supervisor' => [
                'ar' => 'مشرف مختبر',
                'en' => 'Laboratory Supervisor',
                'permissions' => [
                    self::DASHBOARD_VIEW,
                    self::PATIENTS_VIEW,
                    self::ENCOUNTERS_VIEW,
                    self::LABORATORY_VIEW,
                    self::LABORATORY_MANAGE,
                    self::LABORATORY_APPROVE,
                    self::REPORTS_VIEW,
                ],
            ],
            'radiologist' => [
                'ar' => 'أخصائي أشعة',
                'en' => 'Radiologist',
                'permissions' => [
                    self::DASHBOARD_VIEW,
                    self::PATIENTS_VIEW,
                    self::ENCOUNTERS_VIEW,
                    self::RADIOLOGY_VIEW,
                    self::RADIOLOGY_MANAGE,
                    self::REPORTS_VIEW,
                ],
            ],
            'pharmacist' => [
                'ar' => 'صيدلي',
                'en' => 'Pharmacist',
                'permissions' => [
                    self::DASHBOARD_VIEW,
                    self::PATIENTS_VIEW,
                    self::PRESCRIPTIONS_VIEW,
                    self::PRESCRIPTIONS_DISPENSE,
                    self::BILLING_VIEW,
                    self::PAYMENTS_COLLECT,
                ],
            ],
            'accountant' => [
                'ar' => 'محاسب',
                'en' => 'Accountant',
                'permissions' => [
                    self::DASHBOARD_VIEW,
                    self::BILLING_VIEW,
                    self::PAYMENTS_COLLECT,
                    self::REFUNDS_APPROVE,
                    self::REPORTS_VIEW,
                    self::REPORTS_EXPORT,
                    self::ACCOUNTING_VIEW,
                    self::ACCOUNTING_MANAGE,
                ],
            ],
            'insurance_officer' => [
                'ar' => 'مسؤول تأمين',
                'en' => 'Insurance Officer',
                'permissions' => [
                    self::DASHBOARD_VIEW,
                    self::PATIENTS_VIEW,
                    self::BILLING_VIEW,
                    self::INSURANCE_VIEW,
                    self::INSURANCE_MANAGE,
                ],
            ],
            'hr_manager' => [
                'ar' => 'مدير موارد بشرية',
                'en' => 'HR Manager',
                'permissions' => [
                    self::DASHBOARD_VIEW,
                    self::USERS_MANAGE,
                    self::REPORTS_VIEW,
                ],
            ],
            'metron' => [
                'ar' => 'ممرضة رئيسة',
                'en' => 'Head Nurse (Metron)',
                'permissions' => array_merge($clinical, [
                    self::VITALS_CREATE,
                    self::CLINICAL_NOTES_CREATE,
                    self::QUEUES_MANAGE,
                    self::LABORATORY_VIEW,
                ]),
            ],
            'sample_collector' => [
                'ar' => 'محضر عينات',
                'en' => 'Sample Collector',
                'permissions' => [
                    self::DASHBOARD_VIEW,
                    self::PATIENTS_VIEW,
                    self::LABORATORY_VIEW,
                    self::LABORATORY_MANAGE,
                ],
            ],
            'auditor' => [
                'ar' => 'مراجع',
                'en' => 'Auditor',
                'permissions' => [
                    self::DASHBOARD_VIEW,
                    self::PATIENTS_VIEW,
                    self::ENCOUNTERS_VIEW,
                    self::BILLING_VIEW,
                    self::REPORTS_VIEW,
                    self::AUDIT_VIEW,
                ],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::DASHBOARD_VIEW,
            self::PATIENTS_VIEW,
            self::PATIENTS_CREATE,
            self::PATIENTS_UPDATE,
            self::PATIENTS_MERGE,
            self::APPOINTMENTS_VIEW,
            self::APPOINTMENTS_CREATE,
            self::APPOINTMENTS_MANAGE,
            self::QUEUES_VIEW,
            self::QUEUES_MANAGE,
            self::ENCOUNTERS_VIEW,
            self::ENCOUNTERS_CREATE,
            self::ENCOUNTERS_UPDATE,
            self::ENCOUNTERS_SIGN,
            self::VITALS_CREATE,
            self::DIAGNOSES_CREATE,
            self::CLINICAL_NOTES_CREATE,
            self::PRESCRIPTIONS_VIEW,
            self::PRESCRIPTIONS_CREATE,
            self::PRESCRIPTIONS_DISPENSE,
            self::LABORATORY_VIEW,
            self::LABORATORY_MANAGE,
            self::LABORATORY_APPROVE,
            self::RADIOLOGY_VIEW,
            self::RADIOLOGY_MANAGE,
            self::BILLING_VIEW,
            self::PAYMENTS_COLLECT,
            self::REFUNDS_APPROVE,
            self::INSURANCE_VIEW,
            self::INSURANCE_MANAGE,
            self::REPORTS_VIEW,
            self::REPORTS_EXPORT,
            self::SETTINGS_MANAGE,
            self::USERS_MANAGE,
            self::ROLES_MANAGE,
            self::BRANCHES_MANAGE,
            self::AUDIT_VIEW,
            self::ACCOUNTING_VIEW,
            self::ACCOUNTING_MANAGE,
        ];
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function labels(): array
    {
        return [
            self::DASHBOARD_VIEW => ['ar' => 'عرض لوحة التحكم', 'en' => 'View dashboard'],
            self::PATIENTS_VIEW => ['ar' => 'عرض المرضى', 'en' => 'View patients'],
            self::PATIENTS_CREATE => ['ar' => 'تسجيل مريض', 'en' => 'Register patient'],
            self::PATIENTS_UPDATE => ['ar' => 'تعديل بيانات المرضى', 'en' => 'Update patients'],
            self::PATIENTS_MERGE => ['ar' => 'دمج الملفات المكررة', 'en' => 'Merge duplicate records'],
            self::APPOINTMENTS_VIEW => ['ar' => 'عرض المواعيد', 'en' => 'View appointments'],
            self::APPOINTMENTS_CREATE => ['ar' => 'حجز موعد', 'en' => 'Book appointment'],
            self::APPOINTMENTS_MANAGE => ['ar' => 'إدارة المواعيد', 'en' => 'Manage appointments'],
            self::QUEUES_VIEW => ['ar' => 'عرض الطوابير', 'en' => 'View queues'],
            self::QUEUES_MANAGE => ['ar' => 'إدارة الطوابير', 'en' => 'Manage queues'],
            self::ENCOUNTERS_VIEW => ['ar' => 'عرض الزيارات', 'en' => 'View encounters'],
            self::ENCOUNTERS_CREATE => ['ar' => 'بدء زيارة', 'en' => 'Start encounter'],
            self::ENCOUNTERS_UPDATE => ['ar' => 'تعديل الزيارة', 'en' => 'Update encounter'],
            self::ENCOUNTERS_SIGN => ['ar' => 'توقيع الزيارة', 'en' => 'Sign encounter'],
            self::VITALS_CREATE => ['ar' => 'تسجيل العلامات الحيوية', 'en' => 'Record vital signs'],
            self::DIAGNOSES_CREATE => ['ar' => 'إضافة تشخيص', 'en' => 'Add diagnosis'],
            self::CLINICAL_NOTES_CREATE => ['ar' => 'إضافة ملاحظات سريرية', 'en' => 'Add clinical notes'],
            self::PRESCRIPTIONS_VIEW => ['ar' => 'عرض الوصفات', 'en' => 'View prescriptions'],
            self::PRESCRIPTIONS_CREATE => ['ar' => 'إنشاء وصفة', 'en' => 'Create prescription'],
            self::PRESCRIPTIONS_DISPENSE => ['ar' => 'صرف الأدوية', 'en' => 'Dispense medication'],
            self::LABORATORY_VIEW => ['ar' => 'عرض المختبر', 'en' => 'View laboratory'],
            self::LABORATORY_MANAGE => ['ar' => 'إدارة المختبر', 'en' => 'Manage laboratory'],
            self::LABORATORY_APPROVE => ['ar' => 'اعتماد نتائج المختبر', 'en' => 'Approve lab results'],
            self::RADIOLOGY_VIEW => ['ar' => 'عرض الأشعة', 'en' => 'View radiology'],
            self::RADIOLOGY_MANAGE => ['ar' => 'إدارة الأشعة', 'en' => 'Manage radiology'],
            self::BILLING_VIEW => ['ar' => 'عرض الفواتير', 'en' => 'View billing'],
            self::PAYMENTS_COLLECT => ['ar' => 'تحصيل المدفوعات', 'en' => 'Collect payments'],
            self::REFUNDS_APPROVE => ['ar' => 'اعتماد المبالغ المستردة', 'en' => 'Approve refunds'],
            self::INSURANCE_VIEW => ['ar' => 'عرض التأمين', 'en' => 'View insurance'],
            self::INSURANCE_MANAGE => ['ar' => 'إدارة التأمين', 'en' => 'Manage insurance'],
            self::REPORTS_VIEW => ['ar' => 'عرض التقارير', 'en' => 'View reports'],
            self::REPORTS_EXPORT => ['ar' => 'تصدير التقارير', 'en' => 'Export reports'],
            self::SETTINGS_MANAGE => ['ar' => 'إدارة الإعدادات', 'en' => 'Manage settings'],
            self::USERS_MANAGE => ['ar' => 'إدارة المستخدمين', 'en' => 'Manage users'],
            self::ROLES_MANAGE => ['ar' => 'إدارة الأدوار', 'en' => 'Manage roles'],
            self::BRANCHES_MANAGE => ['ar' => 'إدارة الفروع', 'en' => 'Manage branches'],
            self::AUDIT_VIEW => ['ar' => 'عرض سجل التدقيق', 'en' => 'View audit log'],
            self::ACCOUNTING_VIEW => ['ar' => 'عرض المحاسبة', 'en' => 'View accounting'],
            self::ACCOUNTING_MANAGE => ['ar' => 'إدارة المحاسبة', 'en' => 'Manage accounting'],
        ];
    }
}
