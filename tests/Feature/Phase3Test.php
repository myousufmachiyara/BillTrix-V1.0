<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\JournalLine;
use App\Models\Party;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase3Test extends TestCase
{
    use RefreshDatabase;

    private function balance(string $key): float
    {
        $id = Account::system($key)->id;

        return round((float) JournalLine::where('account_id', $id)->sum('debit') - (float) JournalLine::where('account_id', $id)->sum('credit'), 2);
    }

    private function sell($party, $product, float $qty, float $price, array $extra = []): Invoice
    {
        return app(InvoiceService::class)->save('sale', $extra + ['date' => now()->toDateString(), 'party_id' => $party->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $product->id, 'unit_id' => $product->unit_id, 'quantity' => $qty, 'price' => $price]]]);
    }

    private function balanced(): void
    {
        $this->assertEqualsWithDelta((float) JournalLine::sum('debit'), (float) JournalLine::sum('credit'), 0.001);
    }

    public function test_withholding_tax_on_sales_and_purchases(): void
    {
        $this->setUpBusiness('wholesale', ['wht' => true, 'wht_sales_filer' => 0.5, 'wht_sales_nonfiler' => 1, 'wht_purchase_filer' => 1, 'wht_purchase_nonfiler' => 2]);
        $p = $this->product(['track_stock' => false]);
        $nonFiler = $this->party('customer', ['is_filer' => false]);
        $exempt = $this->party('customer', ['wht_exempt' => true]);
        $supplier = $this->party('supplier');

        $sale = $this->sell($nonFiler, $p, 10, 100);
        $this->assertEquals(10, (float) $sale->wht_amount);
        $this->assertEquals(1010, (float) $sale->total);
        $this->assertEquals(1010, $nonFiler->balance());
        $this->assertEquals(-10, $this->balance('wht_payable'));
        $this->assertEquals(1000, (float) $sale->total - (float) $sale->wht_amount);

        $this->assertEquals(0, (float) $this->sell($exempt, $p, 1, 100)->wht_amount);
        $this->assertEquals(3, (float) $this->sell($nonFiler, $p, 1, 100, ['wht_rate' => 3])->wht_amount); // manual override

        $pur = app(InvoiceService::class)->save('purchase', ['date' => now()->toDateString(), 'party_id' => $supplier->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 10, 'price' => 100]]]);
        $this->assertEquals(10, (float) $pur->wht_amount);
        $this->assertEquals(990, (float) $pur->total);
        $this->assertEquals(-990, $supplier->balance());
        $this->assertEquals(-10 - 0.5 * 0 - 3 - 10, $this->balance('wht_payable'));
        $this->balanced();

        $this->get('/reports/withholding')->assertOk()->assertSee($nonFiler->name)->assertSee($supplier->name);
    }

    public function test_fbr_pos_reports_sale_adds_fee_and_locks_invoice(): void
    {
        Http::fake(['*esp.fbr.gov.pk*' => Http::response(['InvoiceNumber' => '123456SI2611110001', 'Code' => '100', 'Response' => 'Invoice received successfully'])]);
        $this->setUpBusiness('retail', ['tax' => true, 'fbr_mode' => 'pos', 'fbr_pos_id' => '123456', 'fbr_token' => Crypt::encryptString('secret-token')]);
        $p = $this->product(['track_stock' => false, 'hs_code' => '3401.1100']);
        $walkIn = Party::where('is_walk_in', true)->first();

        $sale = $this->sell($walkIn, $p, 2, 100, ['paid_amount' => 999, 'payment_mode' => 'cash', 'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 2, 'price' => 100, 'tax_rate' => 18]]]);
        $this->assertEquals(1, (float) $sale->service_fee);
        $this->assertEquals(237, (float) $sale->total); // 200 + 36 tax + Re 1 fee
        $this->assertSame('synced', $sale->fbr_status);
        $this->assertSame('123456SI2611110001', $sale->fbr_invoice_number);
        $this->assertEquals(-1, $this->balance('pos_fee_payable'));

        Http::assertSent(function ($req) {
            $b = $req->data();

            return $req->hasHeader('Authorization', 'Bearer secret-token') && $b['POSID'] === 123456 && $b['InvoiceType'] === 1
                && $b['TotalBillAmount'] == 237 && $b['Items'][0]['PCTCode'] === '3401.1100' && $b['PaymentMode'] === 1;
        });

        $this->get("/sales/{$sale->id}/print?layout=thermal")->assertOk()->assertSee('123456SI2611110001');

        $this->expectException(ValidationException::class);
        app(InvoiceService::class)->delete($sale);
    }

    public function test_fbr_failure_does_not_block_sale_and_is_retried(): void
    {
        Http::fakeSequence('*esp.fbr.gov.pk*')
            ->push(['Code' => '401', 'Response' => 'Invalid POS'], 400)
            ->push(['InvoiceNumber' => 'FBR-OK-1', 'Code' => '100', 'Response' => 'ok']);
        $this->setUpBusiness('retail', ['fbr_mode' => 'pos', 'fbr_pos_id' => '1', 'fbr_token' => 't']);
        $p = $this->product(['track_stock' => false]);
        $c = $this->party('customer');

        $sale = $this->sell($c, $p, 1, 100);
        $this->assertSame('failed', $sale->fbr_status);
        $this->assertNotNull($sale->id);

        $this->artisan('billtrix:fbr-sync')->assertSuccessful();
        $this->assertSame('synced', $sale->fresh()->fbr_status);
        $this->assertSame('FBR-OK-1', $sale->fresh()->fbr_invoice_number);
        $this->get('/fbr?status=synced')->assertOk()->assertSee('FBR-OK-1');
    }

    public function test_fbr_digital_invoicing_payload(): void
    {
        Http::fake(['*gw.fbr.gov.pk/di_data/*' => Http::response(['invoiceNumber' => '7000007DI1747119701593', 'validationResponse' => ['statusCode' => '00', 'status' => 'Valid']])]);
        $this->setUpBusiness('wholesale', ['tax' => true, 'fbr_mode' => 'di', 'fbr_token' => 't']);
        $this->owner->business->update(['ntn' => '1234567', 'province' => 'Sindh']);
        \App\Support\Tenant::set($this->owner->business->fresh());
        $p = $this->product(['track_stock' => false, 'hs_code' => '0101.2100']);
        $c = $this->party('customer', ['ntn' => '7654321', 'province' => 'Punjab']);

        $sale = $this->sell($c, $p, 5, 1000, ['items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 5, 'price' => 1000, 'tax_rate' => 18]]]);
        $this->assertSame('synced', $sale->fbr_status);
        $this->assertEquals(0, (float) $sale->service_fee); // POS fee only applies to Tier-1 POS
        Http::assertSent(fn ($req) => str_ends_with($req->url(), 'postinvoicedata_sb')
            && $req['sellerNTNCNIC'] === '1234567' && $req['buyerRegistrationType'] === 'Registered'
            && $req['items'][0]['rate'] === '18%' && $req['items'][0]['valueSalesExcludingST'] == 5000 && $req['items'][0]['salesTaxApplicable'] == 900);
    }

    public function test_batches_fefo_skip_expired_and_returns(): void
    {
        $this->setUpBusiness('wholesale', ['batches' => true]);
        $p = $this->product(['track_batches' => true]);
        $s = $this->party('supplier');
        $c = $this->party('customer');
        $buy = fn ($batch, $exp, $qty) => app(InvoiceService::class)->save('purchase', ['date' => now()->toDateString(), 'party_id' => $s->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => $qty, 'price' => 50, 'batch_no' => $batch, 'expiry_date' => $exp]]]);

        try {
            $buy('', null, 5);
            $this->fail('batch number should be required');
        } catch (ValidationException) {
        }

        $buy('OLD', now()->subDay()->toDateString(), 5);       // already expired
        $buy('B1', now()->addDays(10)->toDateString(), 5);
        $buy('B2', now()->addDays(90)->toDateString(), 10);

        $sale = $this->sell($c, $p, 7, 100);                    // FEFO: 5 from B1, 2 from B2, none from expired
        $stock = app(\App\Services\StockService::class)->batchStock($p->id)->pluck('quantity', 'batch_no')->map(fn ($q) => (float) $q);
        $this->assertEquals(['OLD' => 5.0, 'B2' => 8.0], $stock->all());

        try {
            $this->sell($c, $p, 9, 100);                        // 13 on hand but only 8 unexpired
            $this->fail('expired stock was sold');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('unexpired', json_encode($e->errors()));
        }

        // Return goes back to the batch it was sold from.
        app(InvoiceService::class)->save('sale_return', ['date' => now()->toDateString(), 'party_id' => $c->id, 'warehouse_id' => $this->warehouse->id, 'return_of_id' => $sale->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 1, 'price' => 100, 'source_item_id' => $sale->items[0]->id]]]);
        $this->assertEquals(9.0, (float) app(\App\Services\StockService::class)->batchStock($p->id)->firstWhere('batch_no', 'B2')->quantity);

        $this->get('/reports/expiry')->assertOk()->assertSee('OLD')->assertDontSee('>B2<', false);
        $this->post('/stock-adjustments', ['type' => 'adjustment', 'reason' => 'expired', 'warehouse_id' => $this->warehouse->id, 'date' => now()->toDateString(),
            'items' => [['product_id' => $p->id, 'quantity' => -5, 'batch_no' => 'OLD']]])->assertSessionHasNoErrors();
        $this->assertNull(app(\App\Services\StockService::class)->batchStock($p->id)->firstWhere('batch_no', 'OLD'));
        $this->assertEquals(9.0, app(\App\Services\StockService::class)->onHand($p->id));

        // Deleting the sale puts stock back into the same batches.
        app(InvoiceService::class)->delete(Invoice::where('type', 'sale_return')->first());
        app(InvoiceService::class)->delete($sale->fresh());
        $this->assertEquals(['B1' => 5.0, 'B2' => 10.0], app(\App\Services\StockService::class)->batchStock($p->id)->pluck('quantity', 'batch_no')->map(fn ($q) => (float) $q)->all());
    }

    public function test_import_products_and_parties_then_export(): void
    {
        $this->setUpBusiness('wholesale');
        $sheets = app(\App\Services\SpreadsheetService::class);
        $tmp = sys_get_temp_dir().'/bt-import-'.uniqid();

        // Build an xlsx exactly like a user would fill the template.
        $write = function (string $type, array $rows) use ($tmp) {
            $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
            $book->getActiveSheet()->fromArray([\App\Services\ImportService::TEMPLATES[$type]['headings'], ...$rows]);
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($tmp.$type.'.xlsx');

            return new \Illuminate\Http\UploadedFile($tmp.$type.'.xlsx', $type.'.xlsx', null, null, true);
        };

        $bad = $write('products', [['Soap', 'S-1', '', 'Care', 'crates', 10, 12, 0, '', '', 0, 0], ['', 'S-2', '', '', 'pcs']]);
        $this->post('/imports/products', ['file' => $bad])->assertSessionHas('import_errors', fn ($e) => isset($e[2], $e[3]) && str_contains($e[2][0], 'crates'));
        $this->assertSame(0, \App\Models\Product::count());

        $good = $write('products', [['Soap', 'S-1', '111', 'Care', 'pcs', 10, 12, 5, '3401.1100', '', 40, 10], ['Rice', 'R-1', '', 'Grocery', 'kg', 200, 240, 0, '', '', 0, 0]]);
        $this->post('/imports/products', ['file' => $good])->assertSessionHas('success', 'Imported: 2 new, 0 updated.');
        $soap = \App\Models\Product::where('sku', 'S-1')->first();
        $this->assertSame('3401.1100', $soap->hs_code);
        $this->assertEquals(40.0, app(\App\Services\StockService::class)->onHand($soap->id));
        $this->assertSame(2, \App\Models\Category::count());

        $again = $write('products', [['Bath Soap', 'S-1', '111', 'Care', 'pcs', 11, 13, 5, '', '', 0, 0]]);
        $this->post('/imports/products', ['file' => $again])->assertSessionHas('success', 'Imported: 0 new, 1 updated.');
        $this->assertSame('Bath Soap', $soap->fresh()->name);

        $parties = $write('parties', [['customer', 'Madina Store', 'C-9', '0300', '', '', 'Karachi', '', '', 50000, 15, 1200], ['supplier', 'Unity Foods', '', '', '', '', '', '', '', 0, 0, -3000]]);
        $this->post('/imports/parties', ['file' => $parties])->assertSessionHas('success');
        $this->assertEquals(1200, Party::where('code', 'C-9')->first()->balance());
        $this->assertEquals(-3000, Party::where('name', 'Unity Foods')->first()->balance());

        $this->get('/imports/products/template')->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $res = $this->get('/products?export=xlsx');
        $res->assertOk();
        $file = $tmp.'-export.xlsx';
        file_put_contents($file, $res->streamedContent());
        $rows = $sheets->read($file);
        $this->assertSame('Bath Soap', $rows[2]['name']);
        foreach (['/reports/stock?export=xlsx', '/reports/trial-balance?export=xlsx', '/reports/register?export=xlsx', '/reports/aging?export=xlsx',
            '/parties?type=customer&export=xlsx', '/reports/product-sales?export=xlsx', '/reports/withholding?export=xlsx', '/reports/low-stock?export=xlsx',
            '/reports/party-statement?export=xlsx&party_id='.Party::where('code', 'C-9')->value('id')] as $url) {
            $this->get($url)->assertOk()->assertDownload();
        }
    }
}
