<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_name',
        'nic',
        'address',
        'bank_name',
        'bank_branch',
        'bank_account_number',
        'bank_account_name',
        'kyc_status',
        'kyc_notes',
        'commission_rate_override',
        'wallet_balance',
        'reserved_balance',
        'outstanding_clawback',
        'total_earned',
        'total_withdrawn',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate_override' => 'decimal:2',
            'wallet_balance' => 'decimal:2',
            'reserved_balance' => 'decimal:2',
            'outstanding_clawback' => 'decimal:2',
            'total_earned' => 'decimal:2',
            'total_withdrawn' => 'decimal:2',
        ];
    }

    /**
     * Compute available spendable wallet balance (gross wallet balance minus reserved payout balance).
     * Decimal-safe string arithmetic using BCMath.
     */
    public function getAvailableBalanceAttribute(): string
    {
        $gross = (string) ($this->wallet_balance ?? '0.00');
        $reserved = (string) ($this->reserved_balance ?? '0.00');

        $available = bcsub($gross, $reserved, 2);
        return bccomp($available, '0', 2) > 0 ? $available : '0.00';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

