<?php

namespace Tests\Feature;

use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\Warehouse;
use App\Services\InvoiceService;
use App\Services\StockDocumentService;
use App\Services\VariantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VariantTest extends TestCase
{
    use RefreshDatabase;

    private function shirt(): array
    {
        $size = ProductAttribute::create(['name' => 'Size', 'sort' => 1]);
        $color = ProductAttribute::create(['name' => 'Colour', 'sort' => 0]);
        $s = $size->values()->create(['value' => 'S']);
        $m = $size->values()->create(['value' => 'M']);
        $red = $color->values()->create(['value' => 'Red']);
        $p = $this->product(['name' => 'T-Shirt', 'sku' => 'TSHIRT', 'has_variants' => true, 'sale_price' => 500]);
        app(VariantService::class)->sync($p, [
            ['value_ids' => [$s->id, $red->id]],
            ['value_ids' => [$m->id, $red->id], 'sale_price' => 550],
        ]);

        return [$p->fresh(), ...$p->variants()->with('product', 'values')->get()->all()];
    }

    private function line($p, ?ProductVariant $v, float $qty, float $price, array $extra = []): array
    {
        return ['product_id' => $p->id, 'variant_id' => $v?->id, 'unit_id' => $p->unit_id, 'quantity' => $qty, 'price' => $price] + $extra;
    }

    private function onHand(ProductVariant $v, ?int $wh = null): float
    {
        return (float) StockBalance::where('variant_id', $v->id)->when($wh, fn ($q) => $q->where('warehouse_id', $wh))->sum('quantity');
    }

    public function test_variations_get_names_skus_and_barcodes(): void
    {
        $this->setUpBusiness('wholesale', ['variants' => true]);
        [$p, $small, $medium] = $this->shirt();

        $this->assertSame('Red / S', $small->name);               // colour first (attribute sort), then size
        $this->assertSame('TSHIRT-RED-S', $small->sku);
        $this->assertSame(13, strlen($small->barcode));
        $this->assertSame((int) substr($small->barcode, -1), \App\Services\SequenceService::ean13Check(substr($small->barcode, 0, 12)));
        $this->assertNotSame($small->barcode, $medium->barcode);
        $this->assertEquals(500, $small->salePrice());          // blank → product price
        $this->assertEquals(550, $medium->salePrice());

        // The same combination twice is refused.
        $this->expectException(ValidationException::class);
        app(VariantService::class)->sync($p, [['value_ids' => $small->values->pluck('id')->all()], ['value_ids' => $small->values->pluck('id')->all()]]);
    }

    public function test_stock_and_cost_are_kept_per_variation(): void
    {
        $this->setUpBusiness('wholesale', ['variants' => true]);
        [$p, $small, $medium] = $this->shirt();
        $supplier = $this->party('supplier');
        $customer = $this->party('customer');
        $inv = app(InvoiceService::class);
        $date = now()->toDateString();

        $inv->save('purchase', ['date' => $date, 'party_id' => $supplier->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [$this->line($p, $small, 10, 200), $this->line($p, $medium, 5, 260)]]);
        $this->assertEquals(10, $this->onHand($small));
        $this->assertEquals(5, $this->onHand($medium));
        $this->assertEquals(200, $small->fresh()->avg_cost);
        $this->assertEquals(260, $medium->fresh()->avg_cost);
        $this->assertEquals(220, $p->fresh()->avg_cost);          // product shows the stock-weighted average
        $this->assertEquals(260, $medium->fresh()->purchase_price); // last purchase price kept on the variation

        $sale = $inv->save('sale', ['date' => $date, 'party_id' => $customer->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [$this->line($p, $medium, 2, 550)]]);
        $this->assertEquals(3, $this->onHand($medium));
        $this->assertEquals(10, $this->onHand($small));
        $this->assertEquals(260, (float) $sale->items->first()->unit_cost);   // COGS at the variation's own cost

        // Returns go back to the same variation.
        $inv->save('sale_return', ['date' => $date, 'party_id' => $customer->id, 'warehouse_id' => $this->warehouse->id, 'return_of_id' => $sale->id,
            'items' => [$this->line($p, $medium, 1, 550, ['source_item_id' => $sale->items->first()->id])]]);
        $this->assertEquals(4, $this->onHand($medium));

        // Not enough of one variation even though the product has plenty overall.
        try {
            $inv->save('sale', ['date' => $date, 'party_id' => $customer->id, 'warehouse_id' => $this->warehouse->id, 'items' => [$this->line($p, $medium, 6, 550)]]);
            $this->fail('Expected a stock error');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('T-Shirt — Red / M', json_encode($e->errors(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        // A product with variations needs one on every line.
        try {
            $inv->save('sale', ['date' => $date, 'party_id' => $customer->id, 'warehouse_id' => $this->warehouse->id, 'items' => [$this->line($p, null, 1, 500)]]);
            $this->fail('Expected a variation error');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0.variant_id', $e->errors());
        }

        // Editing reposts per variation.
        $second = $inv->save('sale', ['date' => $date, 'party_id' => $customer->id, 'warehouse_id' => $this->warehouse->id, 'items' => [$this->line($p, $medium, 1, 550)]]);
        $this->assertEquals(3, $this->onHand($medium));
        $inv->save('sale', ['date' => $date, 'party_id' => $customer->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [$this->line($p, $small, 4, 500)]], $second->fresh());
        $this->assertEquals(6, $this->onHand($small));
        $this->assertEquals(4, $this->onHand($medium));
    }

    public function test_counts_and_transfers_per_variation(): void
    {
        $this->setUpBusiness('wholesale', ['variants' => true, 'multi_warehouse' => true]);
        [$p, $small, $medium] = $this->shirt();
        $docs = app(StockDocumentService::class);
        $date = now()->toDateString();

        $docs->adjust(['type' => 'opening', 'warehouse_id' => $this->warehouse->id, 'date' => $date, 'items' => [
            ['product_id' => $p->id, 'variant_id' => $small->id, 'quantity' => 8, 'unit_cost' => 150],
            ['product_id' => $p->id, 'variant_id' => $medium->id, 'quantity' => 4, 'unit_cost' => 170],
        ]]);
        $docs->adjust(['type' => 'count', 'warehouse_id' => $this->warehouse->id, 'date' => $date, 'items' => [
            ['product_id' => $p->id, 'variant_id' => $small->id, 'counted' => 7],
        ]]);
        $this->assertEquals(7, $this->onHand($small));
        $this->assertEquals(4, $this->onHand($medium));

        $godown = Warehouse::create(['branch_id' => $this->warehouse->branch_id, 'name' => 'Godown']);
        $t = $docs->transfer(['from_warehouse_id' => $this->warehouse->id, 'to_warehouse_id' => $godown->id, 'date' => $date,
            'items' => [['product_id' => $p->id, 'variant_id' => $medium->id, 'unit_id' => $p->unit_id, 'quantity' => 3]]]);
        $this->assertEquals(3, $this->onHand($medium, $godown->id));
        $this->assertEquals(1, $this->onHand($medium, $this->warehouse->id));
        $this->assertEquals(7, $this->onHand($small, $this->warehouse->id));

        $docs->deleteTransfer($t);
        $this->assertEquals(4, $this->onHand($medium, $this->warehouse->id));

        // A used variation is switched off rather than deleted when removed from the product.
        app(VariantService::class)->sync($p, [['id' => $small->id, 'value_ids' => $small->values->pluck('id')->all()]]);
        $this->assertFalse($medium->fresh()->is_active);
    }

    public function test_screens_create_and_sell_variations_over_http(): void
    {
        $this->setUpBusiness('retail', ['variants' => true, 'pos_shifts' => false]);
        $this->actingAs($this->owner);

        // Attributes screen
        $this->post('/attributes', ['name' => 'Size', 'values' => 'S, M, L=LG', 'sort' => 1])->assertRedirect();
        $this->post('/attributes', ['name' => 'Colour', 'values' => 'Black', 'sort' => 0])->assertRedirect();
        $size = ProductAttribute::where('name', 'Size')->with('values')->first();
        $black = ProductAttribute::where('name', 'Colour')->first()->values()->first();
        $this->assertSame(['S', 'M', 'L'], $size->values->pluck('value')->all());
        $this->assertSame('LG', $size->values[2]->code);

        // Product with two variations and opening stock per variation
        $this->post('/products', [
            'name' => 'Polo', 'sku' => 'POLO', 'unit_id' => $this->product()->unit_id, 'sale_price' => 900, 'purchase_price' => 500,
            'track_stock' => 1, 'is_active' => 1, 'has_variants_shown' => 1, 'has_variants' => 1,
            'variants' => [
                ['value_ids' => [$size->values[0]->id, $black->id], 'is_active' => 1, 'opening_qty' => 6],
                ['value_ids' => [$size->values[2]->id, $black->id], 'is_active' => 1, 'sale_price' => 950, 'barcode' => '4000000000017', 'opening_qty' => 2],
            ],
        ])->assertRedirect();
        $polo = \App\Models\Product::where('name', 'Polo')->firstOrFail();
        $this->assertTrue($polo->has_variants);
        [$small, $large] = $polo->variants()->get()->all();
        $this->assertSame('POLO-BLACK-LG', $large->sku);
        $this->assertEquals(6, $this->onHand($small));
        $this->assertEquals(2, $this->onHand($large));
        $this->get("/products/{$polo->id}")->assertOk()->assertSee('Black / L')->assertSee('POLO-BLACK-S');
        $this->get("/products/{$polo->id}/edit")->assertOk()->assertSee('variant-builder', false);

        // Lookup: a variation's barcode finds just that variation, with its own price and stock.
        $hit = $this->getJson('/products/lookup?barcode=1&q=4000000000017&warehouse_id='.$this->warehouse->id)->json('results');
        $this->assertCount(1, $hit);
        $this->assertSame([$polo->id, $large->id, 950.0, 2.0], [$hit[0]['product_id'], $hit[0]['variant_id'], (float) $hit[0]['sale_price'], (float) $hit[0]['stock']]);
        $this->assertCount(2, $this->getJson('/products/lookup?q=Polo')->json('results'));

        // POS sells a variation; the catalog lists each variation.
        $catalog = collect($this->getJson('/pos/catalog?warehouse_id='.$this->warehouse->id)->json('products'))->where('product_id', $polo->id);
        $this->assertCount(2, $catalog);
        $walkIn = \App\Models\Party::where('is_walk_in', true)->first();
        $this->postJson('/pos', ['party_id' => $walkIn->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $polo->id, 'variant_id' => $large->id, 'unit_id' => $polo->unit_id, 'quantity' => 1, 'price' => 950]],
            'payments' => [['account_id' => \App\Models\Account::system('cash')->id, 'amount' => 950]]])->assertOk();
        $this->assertEquals(1, $this->onHand($large));
        $invoice = \App\Models\Invoice::latest('id')->first();
        $this->get("/sales/{$invoice->id}")->assertOk()->assertSee('Polo — Black / L');
        $this->get("/sales/{$invoice->id}/print?layout=thermal")->assertOk()->assertSee('Polo — Black / L');

        // Reports and labels
        $this->get('/reports/stock')->assertOk()->assertSee('Polo — Black / S')->assertSee('Polo — Black / L');
        $this->get("/reports/stock-movement?product_id={$polo->id}-{$large->id}")->assertOk()->assertSee('Black / L');
        $this->get('/reports/product-sales')->assertOk()->assertSee('Polo — Black / L');
        $this->get('/attributes')->assertOk()->assertSee('LG');
        $this->get("/labels?product_id={$polo->id}")->assertOk()->assertSee($small->barcode);
        $this->post('/labels/print', ['size' => 'roll-38x25', 'show_price' => 1, 'show_name' => 1,
            'items' => [['product_id' => $polo->id, 'variant_id' => $large->id, 'copies' => 3]]])
            ->assertOk()->assertSee('4000000000017')->assertSee('<svg', false);

        // A value in use can't be removed from its attribute.
        $this->put("/attributes/{$size->id}", ['name' => 'Size', 'values' => 'S, M'])->assertSessionHasErrors('values');
    }
}
