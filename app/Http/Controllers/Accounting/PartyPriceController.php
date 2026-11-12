<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\PartyPrice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Special prices for one customer. */
class PartyPriceController extends Controller
{
    public function store(Request $request, Party $party): RedirectResponse
    {
        $this->allow('parties.edit');
        $data = $request->validate([
            'product_id' => ['required', $this->owned('products')],
            'price' => ['required', 'numeric', 'min:0'],
        ]);
        PartyPrice::updateOrCreate(['party_id' => $party->id, 'product_id' => $data['product_id']], ['price' => $data['price']]);

        return back()->with('success', __('Special price saved.'));
    }

    public function destroy(Party $party, PartyPrice $price): RedirectResponse
    {
        $this->allow('parties.edit');
        abort_unless($price->party_id === $party->id, 404);
        $price->delete();

        return back()->with('success', __('Special price removed.'));
    }
}
