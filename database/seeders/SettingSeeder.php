<?php

namespace Database\Seeders;

use App\Domains\Settings\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['hospital_name_ar', 'مستشفى سكارلت العام', 'hospital', true],
            ['hospital_name_en', 'Scarlet General Hospital', 'hospital', true],
            ['hospital_phone', '+249 123 456 789', 'hospital', true],
            ['hospital_email', 'info@scarlet.care', 'hospital', true],
            ['hospital_address_ar', 'شارع النيل، الخرطوم، السودان', 'hospital', true],
            ['hospital_address_en', 'Nile Street, Khartoum, Sudan', 'hospital', true],
            ['brand_tagline_ar', 'رعاية رقمية متكاملة', 'branding', true],
            ['brand_tagline_en', 'Integrated digital care', 'branding', true],
            ['queue_display_notice_ar', 'يرجى الانتظار حتى يتم نداء رقمك', 'queues', true],
            ['queue_display_notice_en', 'Please wait until your number is called', 'queues', true],
            ['appointment_slot_minutes', 20, 'scheduling', true],
            ['allow_walk_ins', true, 'scheduling', false],
            ['queue_auto_call', false, 'queues', false],
            ['currency', config('scarlet.currency', 'SDG'), 'finance', true],
            ['timezone', config('scarlet.timezone', 'Africa/Khartoum'), 'general', true],
            ['mrn_prefix', config('scarlet.mrn_prefix', 'SC'), 'patients', false],
            ['duplicate_score_threshold', 50, 'patients', false],
            ['audit_retention_days', 3650, 'audit', false],
            ['session_timeout_minutes', 60, 'security', false],
            ['support_email', 'support@scarlet.care', 'general', true],
            ['service_default_price', 0, 'services', true],
            ['lab_auto_approve', false, 'laboratory', false],
            ['lab_device_sync_interval', 60, 'laboratory', false],
            ['lab_allow_empty_results', false, 'laboratory', false],
            ['whatsapp_enabled', false, 'whatsapp', false],
            ['whatsapp_api_url', '', 'whatsapp', false],
            ['whatsapp_token', '', 'whatsapp', false],
            ['display_refresh_seconds', 5, 'display', true],
            ['display_show_doctor', true, 'display', true],
            ['clinic_default_duration', 20, 'clinics', true],
            ['clinic_allow_overbooking', false, 'clinics', false],
        ];

        foreach ($settings as [$key, $value, $group, $isPublic]) {
            Setting::put($key, $value, $group, $isPublic);
        }

        $this->command?->info('Settings seeded: '.count($settings).' entries.');
    }
}
