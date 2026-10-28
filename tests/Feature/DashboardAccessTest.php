<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    public function test_user_can_access_dashboard_after_login(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Dashboard');
    }
}
