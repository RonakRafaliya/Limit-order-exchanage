<?php
namespace App\Http\Controllers\V1;

use App\Events\OrderbookUpdated;
use App\Http\Controllers\Controller;
use App\Jobs\MatchOrderJob;
use App\Models\Asset;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Get orders by symbol or user orders.
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $symbol     = $request->query('symbol');
        $userOrders = $request->query('user_orders', false);

        if ($userOrders) {
            $orders = $request->user()
                ->orders()
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($orders);
        }

        $orders = Order::where('symbol', $symbol)
            ->where('status', Order::OPEN)
            ->orderBy('price')
            ->get();

        return response()->json($orders);
    }

    /**
     * Store a new order.
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'symbol' => 'required|in:BTC,ETH',
            'side'   => 'required|in:buy,sell',
            'price'  => 'required|numeric|min:0.0001',
            'amount' => 'required|numeric|min:0.0001',
        ]);

        return DB::transaction(function () use ($request, $data) {
            $user = $request->user();

            // BUY ORDER
            if ($data['side'] === 'buy') {
                $total = bcmul($data['price'], $data['amount'], 8);

                if (bccomp($user->balance, $total, 8) < 0) {
                    abort(400, 'Insufficient USD balance');
                }

                $user->decrement('balance', $total);
            }

            // SELL ORDER
            if ($data['side'] === 'sell') {
                $asset = Asset::firstOrCreate(
                    ['user_id' => $user->id, 'symbol' => $data['symbol']],
                    ['amount' => 0, 'locked_amount' => 0]
                );

                if (bccomp($asset->amount, $data['amount'], 8) < 0) {
                    abort(400, 'Insufficient asset balance');
                }

                $asset->decrement('amount', $data['amount']);
                $asset->increment('locked_amount', $data['amount']);
            }

            $order = Order::create([
                'user_id'          => $user->id,
                'symbol'           => $data['symbol'],
                'side'             => $data['side'],
                'price'            => $data['price'],
                'amount'           => $data['amount'],
                'remaining_amount' => $data['amount'],
                'status'           => Order::OPEN,
            ]);

            dispatch(new MatchOrderJob($order));
            event(new OrderbookUpdated($data['symbol']));

            return response()->json($order);
        });
    }

    /**
     * Cancel an order.
     * @param Order $order
     * @return JsonResponse
     */
    public function cancel(Order $order)
    {
        if ($order->status !== Order::OPEN) {
            abort(400, 'Order not open');
        }

        DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->find($order->id);

            if ($order->side === 'buy') {
                $refund = bcmul($order->price, $order->remaining_amount, 8);
                $order->user->increment('balance', $refund);
            } else {
                $asset = Asset::where('user_id', $order->user_id)
                    ->where('symbol', $order->symbol)
                    ->lockForUpdate()
                    ->first();

                if ($asset) {
                    $asset->increment('amount', $order->remaining_amount);
                    $asset->decrement('locked_amount', $order->remaining_amount);
                }
            }

            $order->update(['status' => Order::CANCELLED]);
            event(new OrderbookUpdated($order->symbol));
        });

        return response()->json(['status' => 'cancelled']);
    }
}
