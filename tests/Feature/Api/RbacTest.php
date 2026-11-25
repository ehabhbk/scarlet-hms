<?php

namespace Tests\Feature\Api;

class RbacTest extends ApiTestCase
{
    public function test_super_admin_can_access_administrative_endpoints(): void
    {
        $this->actingAsRole('super_admin');

        $this->getJson('/api/v1/settings')->assertOk();
        $this->getJson('/api/v1/audit-logs')->assertOk();
        $this->getJson('/api/v1/users')->assertOk();
        $this->getJson('/api/v1/roles')->assertOk();
    }

    public function test_receptionist_is_denied_audit_and_settings(): void
    {
        $this->actingAsRole('receptionist');

        $this->getJson('/api/v1/audit-logs')->assertForbidden();
        $this->getJson('/api/v1/settings')->assertForbidden();
        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_doctor_is_denied_administration_but_can_view_dashboard(): void
    {
        $this->actingAsRole('doctor');

        $this->getJson('/api/v1/dashboard/summary')->assertOk();
        $this->getJson('/api/v1/settings')->assertForbidden();
        $this->getJson('/api/v1/audit-logs')->assertForbidden();
    }

    public function test_laboratory_technician_only_sees_laboratory_scope(): void
    {
        $this->actingAsRole('laboratory_technician');

        $this->getJson('/api/v1/patients')->assertOk();
        $this->getJson('/api/v1/queues')->assertForbidden();
        $this->getJson('/api/v1/appointments')->assertForbidden();
    }
}
