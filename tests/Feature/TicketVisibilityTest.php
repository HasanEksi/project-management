<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_project_member_cannot_view_unowned_unassigned_ticket(): void
    {
        $user = $this->userWithTicketPermission();
        $owner = User::factory()->create();
        $project = $this->projectWithMember($owner, $user);
        $ticket = $this->ticketFor($project, $owner);

        $this->actingAs($user);

        $this->assertFalse($user->can('view', $ticket));
    }

    public function test_non_admin_can_view_owned_or_assigned_ticket(): void
    {
        $user = $this->userWithTicketPermission();
        $otherUser = User::factory()->create();
        $project = $this->projectWithMember($otherUser, $user);
        $ownedTicket = $this->ticketFor($project, $user);
        $assignedTicket = $this->ticketFor($project, $otherUser, $user);

        $this->actingAs($user);

        $this->assertTrue($user->can('view', $ownedTicket));
        $this->assertTrue($user->can('view', $assignedTicket));
    }

    public function test_admin_can_view_any_ticket(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->create();
        $project = $this->projectWithMember($owner);
        $ticket = $this->ticketFor($project, $owner);

        $this->actingAs($admin);

        $this->assertTrue($admin->can('view', $ticket));
    }

    private function userWithTicketPermission(): User
    {
        Permission::firstOrCreate(['name' => 'View ticket']);

        $user = User::factory()->create();
        $user->givePermissionTo('View ticket');

        return $user;
    }

    private function adminUser(): User
    {
        Role::firstOrCreate(['name' => 'Admin']);

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        return $admin;
    }

    private function projectWithMember(User $owner, ?User $member = null): Project
    {
        $project = Project::create([
            'name' => 'Test Project',
            'description' => 'Test project',
            'status_id' => ProjectStatus::create([
                'name' => 'Active',
                'color' => '#000000',
                'is_default' => true,
            ])->id,
            'owner_id' => $owner->id,
            'ticket_prefix' => 'TEST',
            'status_type' => 'default',
            'type' => 'kanban',
        ]);

        if ($member) {
            $project->users()->attach($member->id, ['role' => 'employee']);
        }

        return $project;
    }

    private function ticketFor(Project $project, User $owner, ?User $responsible = null): Ticket
    {
        return Ticket::create([
            'name' => 'Test ticket',
            'content' => 'Test content',
            'owner_id' => $owner->id,
            'responsible_id' => $responsible?->id,
            'status_id' => TicketStatus::create([
                'name' => 'Open',
                'color' => '#000000',
                'is_default' => true,
                'order' => 1,
                'project_id' => null,
            ])->id,
            'project_id' => $project->id,
            'type_id' => TicketType::create([
                'name' => 'Task',
                'icon' => 'heroicon-o-ticket',
                'color' => '#000000',
                'is_default' => true,
            ])->id,
            'priority_id' => TicketPriority::create([
                'name' => 'Normal',
                'color' => '#000000',
                'is_default' => true,
            ])->id,
        ]);
    }
}
