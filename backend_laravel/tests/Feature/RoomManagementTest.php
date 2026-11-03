<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\Booking;

class RoomManagementTest extends TestCase
{
    public function test_categories_api_returns_all_room_types(): void
    {
        $response = $this->getJson('/api/v1/categories');
        $response->assertStatus(200)
                 ->assertJsonStructure(['categories' => [
                     '*' => ['id', 'name', 'slug', 'base_price_etb', 'base_price_usd', 'max_capacity']
                 ]]);
    }

    public function test_amenities_api_returns_available_amenities(): void
    {
        $response = $this->getJson('/api/v1/amenities');
        $response->assertStatus(200)
                 ->assertJsonStructure(['amenities' => [
                     '*' => ['id', 'name', 'icon_name']
                 ]]);
    }

    public function test_room_creation_validation_prevents_duplicate_number(): void
    {
        $existing = Room::first();
        if (!$existing) return;

        $response = $this->postJson('/api/v1/rooms', [
            'room_number' => $existing->room_number,
            'category_id' => 'cat-deluxe',
            'title' => 'Duplicate Room Test',
            'floor' => 1,
            'capacity' => 2,
            'price_etb' => 2500,
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['room_number']);
    }

    public function test_room_creation_validation_prevents_zero_price(): void
    {
        $response = $this->postJson('/api/v1/rooms', [
            'room_number' => 'TEST-' . rand(1000, 9999),
            'category_id' => 'cat-deluxe',
            'title' => 'Invalid Price Room',
            'floor' => 1,
            'capacity' => 2,
            'price_etb' => 0,
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['price_etb']);
    }

    public function test_delete_occupied_room_is_rejected(): void
    {
        $occupied = Room::where('status', 'occupied')->first();
        if (!$occupied) return;

        $response = $this->deleteJson("/api/v1/rooms/{$occupied->id}");
        $response->assertStatus(422);
    }
}
