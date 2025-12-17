<?php
namespace App\Jobs;

use App\Events\OrderbookUpdated;
use App\Events\OrderMatched;
use App\Models\Asset;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class MatchOrderJob implements ShouldQueue
{
    use Queueable;

    const FEE_RATE = '0.015'; // 1.5% commission

    /**
     * Create a new job instance.
     * @param Order $order
     */
    public function __construct(public Order $order)
    {}

    /**
     * Execute the job.
     * @return void
     */
    public function handle(): void
    {
        DB::transaction(function () {
            $order = Order::lockForUpdate()->find($this->order->id);

            if (! $order || $order->status !== Order::OPEN || bccomp($order->remaining_amount, '0', 8) <= 0) {
                return;
            }

            // Find a matching counter order
            $counter = Order::where('symbol', $order->symbol)
                ->where('user_id', '!=', $order->user_id)
                ->where('side', $order->side === 'buy' ? 'sell' : 'buy')
                ->where('status', Order::OPEN)
                ->where('remaining_amount', '>', 0)
                ->when(
                    $order->side === 'buy',
                    fn($q) => $q->where('price', '<=', $order->price)->orderBy('price')->orderBy('created_at')
                )
                ->when(
                    $order->side === 'sell',
                    fn($q) => $q->where('price', '>=', $order->price)->orderByDesc('price')->orderBy('created_at')
                )
                ->lockForUpdate()
                ->first();

            if (! $counter) {
                return;
            }

            $this->executeTrade($order, $counter);
        });
    }

    /**
     * Execute the trade.
     * @param Order $incomingOrder
     * @param Order $counterOrder
     * @return void
     */
    private function executeTrade(Order $incomingOrder, Order $counterOrder): void
    {
        $buyOrder  = $incomingOrder->side === 'buy' ? $incomingOrder : $counterOrder;
        $sellOrder = $incomingOrder->side === 'sell' ? $incomingOrder : $counterOrder;

        $matchedAmount = bccomp($buyOrder->remaining_amount, $sellOrder->remaining_amount, 8) <= 0
            ? $buyOrder->remaining_amount
            : $sellOrder->remaining_amount;

        $executionPrice = $sellOrder->price;

        $usdValue = bcmul($matchedAmount, $executionPrice, 8);
        $fee      = bcmul($usdValue, self::FEE_RATE, 8);

        $buyerAsset = Asset::firstOrCreate(
            ['user_id' => $buyOrder->user_id, 'symbol' => $buyOrder->symbol],
            ['amount' => '0', 'locked_amount' => '0']
        );
        $buyerAsset->increment('amount', $matchedAmount);

        $buyerPaidPerUnit = $buyOrder->price;
        $buyerPaidTotal   = bcmul($matchedAmount, $buyerPaidPerUnit, 8);
        $actualCost       = $usdValue;
        $buyerRefund      = bcsub($buyerPaidTotal, $actualCost, 8);

        if (bccomp($buyerRefund, '0', 8) > 0) {
            $buyOrder->user->increment('balance', $buyerRefund);
        }

        $sellerReceives = bcsub($usdValue, $fee, 8);
        $sellOrder->user->increment('balance', $sellerReceives);

        $sellerAsset = Asset::where('user_id', $sellOrder->user_id)
            ->where('symbol', $sellOrder->symbol)
            ->lockForUpdate()
            ->first();

        if ($sellerAsset) {
            $sellerAsset->decrement('locked_amount', $matchedAmount);
        }

        $buyRemainingNew  = bcsub($buyOrder->remaining_amount, $matchedAmount, 8);
        $sellRemainingNew = bcsub($sellOrder->remaining_amount, $matchedAmount, 8);

        $buyOrder->remaining_amount = $buyRemainingNew;
        $buyOrder->status           = bccomp($buyRemainingNew, '0', 8) <= 0 ? Order::FILLED : Order::OPEN;
        $buyOrder->save();

        $sellOrder->remaining_amount = $sellRemainingNew;
        $sellOrder->status           = bccomp($sellRemainingNew, '0', 8) <= 0 ? Order::FILLED : Order::OPEN;
        $sellOrder->save();

        $buyOrder->refresh();
        $sellOrder->refresh();

        event(new OrderMatched($buyOrder, $sellOrder, (float) $executionPrice, (float) $matchedAmount));
        event(new OrderbookUpdated($buyOrder->symbol));

        if ($incomingOrder->status === Order::OPEN && bccomp($incomingOrder->remaining_amount, '0', 8) > 0) {
            dispatch(new self($incomingOrder));
        }
    }
}
