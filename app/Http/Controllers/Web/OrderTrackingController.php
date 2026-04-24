<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Order\Models\Order;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class OrderTrackingController extends Controller
{
    /** @var array<string, string> */
    private const CARRIER_TRACKING_URLS = [
        'usps' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels=%s',
        'ups' => 'https://www.ups.com/track?tracknum=%s',
        'fedex' => 'https://www.fedex.com/fedextrack/?trknbr=%s',
        'dhl' => 'https://www.dhl.com/en/express/tracking.html?AWB=%s',
        'auspost' => 'https://auspost.com.au/mypost/track/#/search?id=%s',
        'royalmail' => 'https://www.royalmail.com/track-your-item#/tracking-results/%s',
    ];

    public function show(Request $request): Response
    {
        $result = null;

        if ($request->isMethod('POST')) {
            $request->validate([
                'order_number' => ['required', 'string'],
                'email' => ['required', 'email'],
            ]);

            $order = Order::query()
                ->where('order_number', mb_strtoupper(mb_trim($request->string('order_number')->value())))
                ->with(['items.product', 'user:id,email'])
                ->first();

            $orderEmail = $order?->guest_email ?? $order?->user?->email ?? '';
            if ($order && mb_strtolower($orderEmail) === mb_strtolower($request->string('email')->value())) {
                $trackingUrl = null;
                if ($order->tracking_number && $order->carrier) {
                    $carrierKey = mb_strtolower((string) $order->carrier);
                    if (isset(self::CARRIER_TRACKING_URLS[$carrierKey])) {
                        $trackingUrl = sprintf(self::CARRIER_TRACKING_URLS[$carrierKey], urlencode((string) $order->tracking_number));
                    }
                }

                $result = [
                    'found' => true,
                    'order_number' => $order->order_number,
                    'status' => $order->status->value,
                    'carrier' => $order->carrier,
                    'tracking_number' => $order->tracking_number,
                    'tracking_url' => $trackingUrl,
                    'shipped_at' => $order->shipped_at?->toDateString(),
                    'items' => $order->items->map(fn ($item) => [
                        'name' => $item->product?->name ?? $item->bundle_name_snapshot ?? 'Item',
                        'quantity' => $item->quantity,
                    ]),
                ];
            } else {
                $result = ['found' => false];
            }
        }

        return Inertia::render('Orders/Track', [
            'result' => $result,
        ]);
    }
}
