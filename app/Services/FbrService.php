<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Invoice;
use App\Models\JournalLine;
use App\Support\Tenant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reports sales to FBR and stores the fiscal invoice number.
 *
 *  - "pos": Tier-1 retail POS integration (IMS). Sales and returns (credit notes).
 *  - "di":  Digital Invoicing for sales-tax registered sellers. Sale invoices.
 *
 * A failure never blocks the sale: the invoice is marked failed and retried by
 * `billtrix:fbr-sync`. Endpoints and codes follow FBR's published specs; confirm
 * them during FBR onboarding as they change from time to time.
 */
class FbrService
{
    public const ENDPOINTS = [
        'pos' => [
            'sandbox' => 'https://esp.fbr.gov.pk:8244/FBR/v1/api/Live/PostData',
            'production' => 'https://gw.fbr.gov.pk/imsp/v1/api/Live/PostData',
        ],
        'di' => [
            'sandbox' => 'https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata_sb',
            'production' => 'https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata',
        ],
    ];

    /** IMS payment modes. */
    public const PAYMENT_CASH = 1;

    public const PAYMENT_CARD = 2;

    public const PAYMENT_MIXED = 5;

    /** FBR unit-of-measure wording for Digital Invoicing. */
    public const UOM = [
        'pcs' => 'Numbers, pieces, units', 'dzn' => 'Dozen', 'box' => 'Numbers, pieces, units', 'ctn' => 'Numbers, pieces, units',
        'pkt' => 'Numbers, pieces, units', 'bag' => 'Numbers, pieces, units', 'kg' => 'KG', 'g' => 'Gram', 'ltr' => 'Liter', 'm' => 'Meter',
    ];

    public function applies(Invoice $invoice, ?Business $business = null): bool
    {
        $mode = ($business ?? Tenant::business())->setting('fbr_mode', 'off');

        return match ($mode) {
            'pos' => in_array($invoice->type, ['sale', 'sale_return'], true),
            'di' => $invoice->type === 'sale',
            default => false,
        };
    }

    /** Mark a new or changed invoice for reporting and try right away. */
    public function queue(Invoice $invoice): void
    {
        if (! $this->applies($invoice)) {
            return;
        }
        $invoice->forceFill(['fbr_status' => 'pending'])->saveQuietly();
        $this->submit($invoice);
    }

    public function submit(Invoice $invoice): bool
    {
        $business = Tenant::business();
        $mode = $business->setting('fbr_mode');
        if (! $this->applies($invoice, $business) || $invoice->fbr_status === 'synced') {
            return $invoice->fbr_status === 'synced';
        }

        $invoice->loadMissing(['items.product', 'items.unit', 'party', 'returnOf']);
        $payload = $mode === 'pos' ? $this->posPayload($invoice, $business) : $this->diPayload($invoice, $business);
        $useLocal = $mode === 'pos' && $business->feature('fbr_use_local_fiscal');
        $url = $useLocal ? $business->setting('fbr_local_url') : self::ENDPOINTS[$mode][$business->setting('fbr_environment', 'sandbox')];

        try {
            $request = Http::timeout(10)->acceptJson()->asJson();
            if (! $useLocal) {
                $request = $request->withToken($this->token($business));
            }
            $response = $request->post($url, $payload);
            $body = $response->json() ?? [];
            [$ok, $number, $message] = $mode === 'pos' ? $this->readPos($body) : $this->readDi($body);
            $ok = $ok && $response->successful();
        } catch (ConnectionException $e) {
            [$ok, $number, $message, $body] = [false, null, 'FBR could not be reached: '.$e->getMessage(), []];
        } catch (Throwable $e) {
            Log::warning('FBR submission error', ['invoice' => $invoice->id, 'error' => $e->getMessage()]);
            [$ok, $number, $message, $body] = [false, null, $e->getMessage(), []];
        }

        $invoice->forceFill([
            'fbr_status' => $ok ? 'synced' : 'failed',
            'fbr_invoice_number' => $ok ? $number : $invoice->fbr_invoice_number,
            'fbr_attempts' => $invoice->fbr_attempts + 1,
            'fbr_synced_at' => $ok ? now() : $invoice->fbr_synced_at,
            'fbr_response' => mb_substr($message ?: json_encode($body), 0, 2000),
            'fbr_qr' => $ok ? ($body['QRCode'] ?? $body['qrCode'] ?? $invoice->fbr_qr) : $invoice->fbr_qr,
        ])->saveQuietly();

        return $ok;
    }

    /** IMS (Tier-1 POS) request body. */
    public function posPayload(Invoice $invoice, Business $business): array
    {
        $isReturn = $invoice->type === 'sale_return';
        $type = $isReturn ? 3 : 1; // 1 = new sale, 3 = credit note
        $ref = $isReturn ? ($invoice->returnOf?->fbr_invoice_number ?? $invoice->returnOf?->number) : null;

        return [
            'InvoiceNumber' => '',
            'POSID' => (int) $business->setting('fbr_pos_id'),
            'USIN' => $invoice->number,
            'DateTime' => $invoice->created_at->format('Y-m-d H:i:s'),
            'BuyerNTN' => $invoice->party->is_walk_in ? '' : (string) $invoice->party->ntn,
            'BuyerCNIC' => $invoice->party->is_walk_in ? '' : (string) $invoice->party->cnic,
            'BuyerName' => $invoice->party->is_walk_in ? (string) $invoice->customer_name : $invoice->party->name,
            'BuyerPhoneNumber' => $invoice->party->is_walk_in ? (string) $invoice->customer_phone : (string) $invoice->party->phone,
            'TotalBillAmount' => (float) $invoice->total,
            'TotalQuantity' => (float) $invoice->items->sum('quantity'),
            'TotalSaleValue' => round($invoice->items->sum(fn ($i) => (float) $i->amount - (float) $i->tax_amount), 2),
            'TotalTaxCharged' => (float) $invoice->tax_total,
            'Discount' => round((float) $invoice->item_discount + (float) $invoice->discount, 2),
            'FurtherTax' => 0.0,
            'PaymentMode' => $this->paymentMode($invoice),
            'RefUSIN' => $ref,
            'InvoiceType' => $type,
            'Items' => $invoice->items->map(fn ($i) => [
                'ItemCode' => $i->product->sku ?: (string) $i->product_id,
                'ItemName' => $i->product->name,
                'Quantity' => (float) $i->quantity,
                'PCTCode' => (string) $i->product->hs_code,
                'TaxRate' => (float) $i->tax_rate,
                'SaleValue' => round((float) $i->amount - (float) $i->tax_amount, 2),
                'TotalAmount' => (float) $i->amount,
                'TaxCharged' => (float) $i->tax_amount,
                'Discount' => (float) $i->discount_amount,
                'FurtherTax' => 0.0,
                'InvoiceType' => $type,
                'RefUSIN' => $ref,
            ])->values()->all(),
        ];
    }

    /** Digital Invoicing request body. */
    public function diPayload(Invoice $invoice, Business $business): array
    {
        $party = $invoice->party;
        $registered = ! $party->is_walk_in && ($party->ntn || $party->cnic);

        return [
            'invoiceType' => 'Sale Invoice',
            'invoiceDate' => $invoice->date->toDateString(),
            'sellerNTNCNIC' => (string) $business->ntn,
            'sellerBusinessName' => $business->legal_name ?: $business->name,
            'sellerProvince' => (string) $business->province,
            'sellerAddress' => (string) ($invoice->branch?->address ?: $business->address),
            'buyerNTNCNIC' => $registered ? (string) ($party->ntn ?: $party->cnic) : '',
            'buyerBusinessName' => $party->is_walk_in ? ($invoice->customer_name ?: 'Walk-in Customer') : $party->name,
            'buyerProvince' => (string) ($party->province ?: $business->province),
            'buyerAddress' => (string) $party->address,
            'buyerRegistrationType' => $registered && $party->ntn ? 'Registered' : 'Unregistered',
            'invoiceRefNo' => $invoice->number,
            'items' => $invoice->items->map(fn ($i) => [
                'hsCode' => (string) $i->product->hs_code,
                'productDescription' => $i->product->name,
                'rate' => rtrim(rtrim(number_format((float) $i->tax_rate, 3, '.', ''), '0'), '.').'%',
                'uoM' => self::UOM[$i->product->unit?->short_name ?? $i->unit->short_name] ?? 'Numbers, pieces, units',
                'quantity' => (float) $i->base_quantity,
                'totalValues' => (float) $i->amount,
                'valueSalesExcludingST' => round((float) $i->amount - (float) $i->tax_amount, 2),
                'fixedNotifiedValueOrRetailPrice' => 0,
                'salesTaxApplicable' => (float) $i->tax_amount,
                'salesTaxWithheldAtSource' => 0,
                'extraTax' => 0,
                'furtherTax' => 0,
                'sroScheduleNo' => '',
                'fedPayable' => 0,
                'discount' => (float) $i->discount_amount,
                'saleType' => (string) $business->setting('fbr_sale_type'),
                'sroItemSerialNo' => '',
            ])->values()->all(),
        ];
    }

    private function readPos(array $body): array
    {
        $ok = (string) ($body['Code'] ?? '') === '100' && ! empty($body['InvoiceNumber']);

        return [$ok, $body['InvoiceNumber'] ?? null, $body['Response'] ?? ($body['Errors'] ?? null ? json_encode($body['Errors']) : null)];
    }

    private function readDi(array $body): array
    {
        $validation = $body['validationResponse'] ?? [];
        $status = (string) ($validation['statusCode'] ?? '00');
        $ok = ! empty($body['invoiceNumber']) && $status === '00';
        $message = $validation['error'] ?? ($validation['status'] ?? null);
        if (! $ok && ! empty($validation['invoiceStatuses'])) {
            $message = collect($validation['invoiceStatuses'])->pluck('error')->filter()->join('; ') ?: $message;
        }

        return [$ok, $body['invoiceNumber'] ?? null, $message];
    }

    private function paymentMode(Invoice $invoice): int
    {
        $subtypes = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.source_type', 'invoice')->where('journal_entries.source_id', $invoice->id)
            ->whereIn('journal_entries.type', ['receipt', 'payment'])
            ->whereIn('accounts.subtype', ['cash', 'bank'])
            ->distinct()->pluck('accounts.subtype');

        return match (true) {
            $subtypes->count() > 1 => self::PAYMENT_MIXED,
            $subtypes->first() === 'bank' => self::PAYMENT_CARD,
            default => self::PAYMENT_CASH,
        };
    }

    private function token(Business $business): string
    {
        $stored = (string) $business->setting('fbr_token');
        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return $stored;
        }
    }
}
