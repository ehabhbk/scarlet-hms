<?php

namespace Tests\Feature\Api;

use App\Domains\Patients\Models\Patient;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\QueueTicket;

class AppointmentQueueTest extends ApiTestCase
{
    public function test_booking_check_in_and_queue_lifecycle(): void
    {
        $patient = Patient::factory()->create();
        $this->actingAsRole('receptionist');

        $booking = $this->postJson('/api/v1/appointments', [
            'patient_id' => $patient->id,
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
            'scheduled_at' => now()->addHour()->toDateTimeString(),
            'type' => 'new',
            'reason' => 'صداع',
        ])->assertCreated()
            ->assertJsonPath('data.status', Appointment::STATUS_BOOKED);

        $appointmentId = $booking->json('data.id');

        $checkIn = $this->postJson('/api/v1/appointments/'.$appointmentId.'/check-in')
            ->assertOk()
            ->assertJsonPath('data.status', Appointment::STATUS_CHECKED_IN)
            ->assertJsonPath('data.queue_ticket.status', QueueTicket::STATUS_WAITING);

        $ticketId = $checkIn->json('data.queue_ticket.id');
        $displayNumber = $checkIn->json('data.queue_ticket.display_number');

        $this->assertMatchesRegularExpression('/^Q\d{3}$/', $displayNumber);

        $this->postJson('/api/v1/appointments/'.$appointmentId.'/check-in')
            ->assertOk()
            ->assertJsonPath('data.queue_ticket.id', $ticketId);

        $this->assertSame(1, QueueTicket::query()->count());

        $called = $this->postJson('/api/v1/queues/next', [
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
        ])->assertOk()
            ->assertJsonPath('data.status', QueueTicket::STATUS_CALLED);

        $this->assertSame($ticketId, $called->json('data.id'));

        $started = $this->postJson('/api/v1/queues/'.$ticketId.'/start')
            ->assertOk()
            ->assertJsonPath('data.status', QueueTicket::STATUS_IN_PROGRESS)
            ->assertJsonPath('data.encounter.status', 'in_progress');

        $this->assertNotNull($started->json('data.encounter_id'));

        $this->postJson('/api/v1/queues/'.$ticketId.'/complete')
            ->assertOk()
            ->assertJsonPath('data.status', QueueTicket::STATUS_COMPLETED);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'status' => Appointment::STATUS_COMPLETED,
        ]);
    }

    public function test_cancelled_appointment_cannot_check_in(): void
    {
        $patient = Patient::factory()->create();
        $this->actingAsRole('receptionist');

        $booking = $this->postJson('/api/v1/appointments', [
            'patient_id' => $patient->id,
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
            'scheduled_at' => now()->addHours(2)->toDateTimeString(),
        ])->assertCreated();

        $appointmentId = $booking->json('data.id');

        $this->postJson('/api/v1/appointments/'.$appointmentId.'/cancel', [
            'reason' => 'ظرف طارئ',
        ])->assertOk()->assertJsonPath('data.status', Appointment::STATUS_CANCELLED);

        $this->postJson('/api/v1/appointments/'.$appointmentId.'/check-in')
            ->assertStatus(422);
    }

    public function test_walk_in_queue_ticket_can_be_issued(): void
    {
        $patient = Patient::factory()->create();
        $this->actingAsRole('receptionist');

        $this->postJson('/api/v1/queues', [
            'patient_id' => $patient->id,
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
            'priority' => 'urgent',
        ])->assertCreated()
            ->assertJsonPath('data.status', QueueTicket::STATUS_WAITING)
            ->assertJsonPath('data.priority', 'urgent');
    }

    public function test_public_queue_board_hides_patient_identifiers(): void
    {
        $patient = Patient::factory()->create(['first_name_ar' => 'سرية', 'last_name_ar' => 'تامة']);
        $this->actingAsRole('receptionist');

        $this->postJson('/api/v1/queues', [
            'patient_id' => $patient->id,
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
        ])->assertCreated();

        $response = $this->getJson('/api/v1/public/queues/'.$this->branch->id)->assertOk();

        $payload = json_encode($response->json('data'), JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('سرية', $payload);
        $this->assertStringNotContainsString('تامة', $payload);
        $this->assertStringContainsString('Q001', $payload);
    }
}
