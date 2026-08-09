<?php

namespace Tests\Unit;

use App\Models\Customer;
use PHPUnit\Framework\TestCase;

class CustomerTest extends TestCase
{
    public function test_it_formats_only_available_address_parts(): void
    {
        $customer = new Customer([
            'address_street' => '1 High Street',
            'address_city' => null,
            'address_state' => 'London',
            'address_zip' => '',
            'address_country' => 'United Kingdom',
        ]);

        $this->assertSame('1 High Street, London, United Kingdom', $customer->formatted_address);
    }

    public function test_it_exposes_the_customers_full_name(): void
    {
        $customer = new Customer([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ]);

        $this->assertSame('Ada Lovelace', $customer->full_name);
    }
}
