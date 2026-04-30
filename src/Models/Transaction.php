<?php

namespace Amzad\ApplePayKnet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Transaction extends Model
{
    protected $table = 'apple_pay_transactions';

    protected $fillable = [
        'order_id',
        'amount',
        'currency',
        'apple_transaction_id',
        'knet_transaction_id',
        'status',
        'response_code',
        'auth_code',
        'raw_response',
    ];

    protected $casts = [
        'amount'       => 'integer',
        'raw_response' => 'array',
    ];

    /**
     * Scope: successfully authorized or captured transactions.
     *
     * @param  Builder $query
     * @return Builder
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->whereIn('status', ['authorized', 'captured']);
    }

    /**
     * Scope: failed transactions.
     *
     * @param  Builder $query
     * @return Builder
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Return the amount formatted as a decimal KWD value.
     */
    public function getAmountInKwdAttribute(): float
    {
        return $this->amount / 1000;
    }
}
