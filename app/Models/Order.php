<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    /**
     * The attributes that are mass assignable.
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'symbol',
        'side',
        'price',
        'amount',
        'remaining_amount',
        'status',
    ];

    /**
     * The constants for the status of the order.
     */
    const OPEN      = 1;
    const FILLED    = 2;
    const CANCELLED = 3;

    /**
     * Get the user that owns the order.
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
