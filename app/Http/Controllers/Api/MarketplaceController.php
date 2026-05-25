<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceItem;

class MarketplaceController extends Controller
{
    public function index()
    {
        return response()->json(
            MarketplaceItem::query()
                ->where('active', true)
                ->latest()
                ->get()
                ->map(fn (MarketplaceItem $item) => [
                    'id' => (string) $item->id,
                    'name' => $item->name,
                    'price' => rtrim(rtrim((string) $item->price, '0'), '.').' '.$this->currencySymbol($item->currency),
                    'image' => $item->image ?? '',
                ])
        );
    }

    private function currencySymbol(string $currency): string
    {
        return strtoupper($currency) === 'USD' ? '$' : $currency;
    }
}
