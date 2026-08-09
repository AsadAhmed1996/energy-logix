<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCrudTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = \App\Models\User::factory()->create([
            'email_verified_at' => now(),
        ]);
    }

    public function test_guests_cannot_access_customers_endpoints(): void
    {
        $this->get(route('customers.index'))->assertRedirect(route('login'));
        $this->post(route('customers.store'), [])->assertRedirect(route('login'));

        $customer = \App\Models\Customer::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'status' => 'active',
        ]);

        $this->put(route('customers.update', $customer), [])->assertRedirect(route('login'));
        $this->delete(route('customers.destroy', $customer))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_list_customers(): void
    {
        $customer = \App\Models\Customer::create([
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@example.com',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->get(route('customers.index'));
        $response->assertStatus(200);
        $response->assertSee('Alice');
    }

    public function test_authorized_user_can_access_create_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('customers.create'));
        $response->assertStatus(200);
    }

    public function test_authorized_user_can_access_edit_page(): void
    {
        $customer = \App\Models\Customer::create([
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@example.com',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->get(route('customers.edit', $customer));
        $response->assertStatus(200);
    }

    public function test_authorized_user_can_create_customer(): void
    {
        $customerData = [
            'first_name' => 'Bob',
            'last_name' => 'Jones',
            'email' => 'bob@example.com',
            'phone' => '1234567890',
            'status' => 'active',
            'address_street' => '123 Street',
            'address_city' => 'Cityville',
            'address_state' => 'State',
            'address_zip' => '12345',
            'address_country' => 'Country',
        ];

        $response = $this->actingAs($this->user)->post(route('customers.store'), $customerData);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', [
            'first_name' => 'Bob',
            'email' => 'bob@example.com',
            'address_street' => '123 Street',
            'address_city' => 'Cityville',
        ]);
    }

    public function test_create_customer_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)->post(route('customers.store'), []);

        $response->assertSessionHasErrors([
            'first_name', 'last_name', 'email', 'phone', 'status',
            'address_street', 'address_city', 'address_state', 'address_zip', 'address_country',
        ]);
    }

    public function test_create_customer_validates_email_uniqueness(): void
    {
        \App\Models\Customer::create([
            'first_name' => 'Existing',
            'last_name' => 'Customer',
            'email' => 'duplicate@example.com',
            'phone' => '1234567890',
            'status' => 'active',
            'address_street' => 'Test Address',
        ]);

        $response = $this->actingAs($this->user)->post(route('customers.store'), [
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => 'duplicate@example.com',
            'phone' => '1234567890',
            'status' => 'active',
            'address_street' => 'Test Address',
            'address_city' => 'Cityville',
            'address_state' => 'State',
            'address_zip' => '12345',
            'address_country' => 'Country',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_authorized_user_can_update_customer(): void
    {
        $customer = \App\Models\Customer::create([
            'first_name' => 'Old',
            'last_name' => 'Name',
            'email' => 'old@example.com',
            'phone' => '1234567890',
            'status' => 'active',
            'address_street' => 'Test Address',
        ]);

        $updateData = [
            'first_name' => 'New',
            'last_name' => 'Name',
            'email' => 'new@example.com',
            'phone' => '0987654321',
            'status' => 'inactive',
            'address_street' => 'New Address',
            'address_city' => 'Cityville',
            'address_state' => 'State',
            'address_zip' => '12345',
            'address_country' => 'Country',
        ];

        $response = $this->actingAs($this->user)->put(route('customers.update', $customer), $updateData);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'first_name' => 'New',
            'email' => 'new@example.com',
            'status' => 'inactive',
            'address_street' => 'New Address',
        ]);
    }

    public function test_update_customer_ignores_own_email_uniqueness(): void
    {
        $customer = \App\Models\Customer::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'status' => 'active',
            'address_street' => 'Test Address',
        ]);

        $updateData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com', // same email
            'phone' => '1234567890',
            'status' => 'active',
            'address_street' => 'Test Address',
            'address_city' => 'Cityville',
            'address_state' => 'State',
            'address_zip' => '12345',
            'address_country' => 'Country',
        ];

        $response = $this->actingAs($this->user)->put(route('customers.update', $customer), $updateData);

        $response->assertRedirect(route('customers.index'));
        $response->assertSessionHasNoErrors();
    }

    public function test_customer_listing_applies_search_status_and_name_sorting(): void
    {
        \App\Models\Customer::create([
            'first_name' => 'Zed',
            'last_name' => 'Customer',
            'email' => 'zed.customer@example.com',
            'status' => 'active',
        ]);
        \App\Models\Customer::create([
            'first_name' => 'Alpha',
            'last_name' => 'Customer',
            'email' => 'alpha.customer@example.com',
            'status' => 'active',
        ]);
        \App\Models\Customer::create([
            'first_name' => 'Inactive',
            'last_name' => 'Customer',
            'email' => 'inactive.customer@example.com',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($this->user)->get(route('customers.index', [
            'search' => 'customer',
            'status' => 'active',
            'sort_field' => 'name',
            'sort_order' => 'asc',
        ]));

        $response->assertOk()
            ->assertSeeInOrder(['Alpha', 'Zed'])
            ->assertDontSee('Inactive');
    }

    public function test_authorized_user_can_delete_customer(): void
    {
        $customer = \App\Models\Customer::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->delete(route('customers.destroy', $customer));

        $response->assertRedirect(route('customers.index'));
        $this->assertSoftDeleted('customers', [
            'id' => $customer->id,
        ]);
    }
}
