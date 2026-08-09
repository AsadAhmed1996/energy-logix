<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'external_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'status',
        'address_street',
        'address_city',
        'address_state',
        'address_zip',
        'address_country',
    ];

    protected $appends = ['formatted_address'];

    /**
     * Get the formatted address.
     */
    public function getFormattedAddressAttribute(): string
    {
        return implode(', ', array_filter([
            $this->address_street,
            $this->address_city,
            $this->address_state,
            $this->address_zip,
            $this->address_country,
        ]));
    }

    /**
     * Get the customer's full name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Scope a query to only include active customers.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
