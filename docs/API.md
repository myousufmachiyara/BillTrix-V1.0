# BillTrix Mobile API (v1)

JSON over HTTPS. Base URL: `https://your-domain/api/v1`. All responses are JSON; errors use HTTP status codes
(`401` not signed in, `403` no permission, `404` not found or another business's record, `422` validation — body has
`message` and `errors`), `429` rate limited (120 requests/minute per token).

## Authentication

```http
POST /auth/login
{ "email": "rep@shop.pk", "password": "…", "device_name": "Ali's Galaxy A54" }
```
`email` can be replaced by `"login": "admin"` to sign in with a username.

Response: `token`, `user`, `business` (name, currency, enabled `features`), `permissions`.
Send `Authorization: Bearer <token>` on every other call. The user's role needs the **API → Use** permission
(Owner, Manager and Cashier have it by default). Devices can be signed out from *Settings → Users → edit user*.

| Method | Path | Notes |
|---|---|---|
| GET | `/auth/me` | Profile, features, permissions |
| POST | `/auth/logout` | Revokes the current token |

## Reference data

| Method | Path | Notes |
|---|---|---|
| GET | `/lookups` | categories, units, warehouses, price levels, cash/bank `payment_accounts` |
| GET | `/products?q=&barcode=&category_id=&warehouse_id=&party_id=&updated_since=&page=&per_page=` | Paginated. `stock` is for `warehouse_id` (or all). `party_id` applies that customer's special prices. `units[]` gives pack units with `factor`. |
| GET | `/products/{id}` | Product + stock per warehouse + batches (when tracked) |

## Customers & suppliers

| Method | Path | Notes |
|---|---|---|
| GET | `/parties?type=customer\|supplier&q=` | With `balance` (positive = owes you) |
| POST | `/parties` | `type`, `name`, `phone`, `address`, `city`, `ntn`, `cnic`, `credit_limit` |
| GET | `/parties/{id}` | Balance and `open_invoices` |
| GET | `/parties/{id}/statement?from=&to=` | Opening, lines with running balance, closing |

## Documents

Line items always use: `product_id`, `unit_id`, `quantity`, `price`, optional `discount_percent`, `tax_rate`.

| Method | Path | Notes |
|---|---|---|
| GET | `/orders?type=quotation\|sale_order\|purchase_order&status=pending&party_id=` | |
| POST | `/orders` | `type`, `party_id`, `warehouse_id`, `date`, `due_date`, `items[]`, `discount`, `notes` — field order taking |
| GET | `/orders/{id}` | Lines include `pending_quantity` |
| GET | `/invoices?type=sale\|purchase\|sale_return\|purchase_return&party_id=&from=&to=&outstanding=1` | |
| POST | `/invoices` | Same fields as orders plus `payments[] {account_id, amount}`, `order_id`, `items[].order_item_id`, `return_of_id` + `items[].source_item_id` for returns, `items[].batch_no`/`expiry_date` for batch products |
| GET | `/invoices/{id}` | Includes `fbr` status/number and `print_url` |
| POST | `/receipts` | Recovery: `party_id`, `cash_account_id`, `amount`, optional `allocations[] {invoice_id, amount}` |
| GET | `/dashboard` | Sales today/month, profit, cash, receivables, payables, stock value, 30-day trend |

Stock, credit limits, below-cost rules, withholding tax and FBR reporting apply exactly as in the web app.

### Example: sale with cash + card

```http
POST /invoices
{
  "type": "sale", "party_id": 12, "warehouse_id": 1,
  "items": [{ "product_id": 5, "unit_id": 1, "quantity": 2, "price": 585 }],
  "payments": [{ "account_id": 1, "amount": 1000 }, { "account_id": 2, "amount": 170 }]
}
```
