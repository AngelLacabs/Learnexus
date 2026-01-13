# SoleSource Voucher Integration Setup Guide

This guide will help you connect the Learnexus course platform with the SoleSource e-commerce voucher system.

---

## Overview

**What this integration does:**
1. 🎓 Student completes a course → Learnexus generates a certificate
2. 🎟️ Learnexus calls SoleSource API → Creates a voucher code (e.g., `REWARD-1A2B`)
3. 📧 Student sees voucher in their dashboard
4. 🛒 Student shops at SoleSource and redeems voucher
5. 🔔 SoleSource sends webhook → Learnexus marks voucher as redeemed

---

## Step 1: Run Database Migration

Apply the schema changes to add SoleSource tracking columns:

```bash
# In MySQL/phpMyAdmin or via CLI:
mysql -u root -p lmslearnexus < database/migrations/2026-01-13-add-solesource-integration.sql
```

**What it does:** Adds `discount_type`, `redeemed_order`, `redeemed_at`, `source`, and `student_identifier` columns to the `vouchers` table.

---

## Step 2: Configure Environment Variables

Create a `.env` file in the Learnexus root (or add to your existing config):

```env
# SoleSource API Configuration
SOLESOURCE_API_KEY=your-shared-secret-key-here
SOLESOURCE_API_BASE_URL=https://dev.art2cart.shop/api
SOLESOURCE_WEBHOOK_SECRET=your-webhook-auth-secret-here
```

**Get these values from SoleSource:**
- `SOLESOURCE_API_KEY`: Shared API key for generating/previewing vouchers
- `SOLESOURCE_WEBHOOK_SECRET`: Secret for webhook authentication (optional but recommended)

---

## Step 3: Load Environment Variables

Add this to your `config/` or top of main files (if not using a framework):

```php
// config/env.php or similar
$dotenv = parse_ini_file(__DIR__ . '/../.env');
foreach ($dotenv as $key => $value) {
    putenv("$key=$value");
}
```

Or install `vlucas/phpdotenv`:
```bash
composer require vlucas/phpdotenv
```

```php
// In your bootstrap file
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
```

---

## Step 4: Expose Webhook Endpoint (Production)

**For Production:**
Make `solesource_webhook.php` publicly accessible:
```
https://your-lms-domain.com/solesource_webhook.php
```

Provide this URL to the SoleSource team so they can set:
```env
COLLAB_WEBHOOK_URL=https://your-lms-domain.com/solesource_webhook.php
```

**For Local Development:**
Use a tunnel service to expose localhost:

### Option A: Cloudflare Tunnel (Recommended)
```bash
cloudflared tunnel --url http://localhost/Learnexus
```
Provides a persistent URL like: `https://xyz.trycloudflare.com`

### Option B: ngrok
```bash
ngrok http 80
```
Provides: `https://abc123.ngrok.io`

Then give SoleSource the tunneled URL:
```
https://xyz.trycloudflare.com/solesource_webhook.php
```

---

## Step 5: Test the Integration

### A. Generate a Test Voucher

1. Log in as a student
2. Complete all lessons and pass the quiz for a course
3. Visit the certificate page: `student/certificate.php?course=<courseID>`
4. Check logs for: `SoleSource: Voucher generated for user X - Code: REWARD-XXXX`

### B. View Vouchers

Navigate to:
```
http://localhost/Learnexus/student/vouchers.php
```

You should see:
- ✅ Voucher code displayed
- ✅ Discount percentage
- ✅ Expiry date
- ✅ "Shop at SoleSource" button

### C. Test Webhook (Manual)

Send a test POST request to your webhook:

```bash
curl -X POST http://localhost/Learnexus/solesource_webhook.php \
  -H "Authorization: Bearer your-webhook-secret" \
  -H "Content-Type: application/json" \
  -d '{
    "code": "REWARD-TEST",
    "order-number": "ORDER-9001",
    "student-id": "learnexus-1",
    "redeemed-at": "2026-01-13T12:00:00Z",
    "discount-applied": 450
  }'
```

**Expected response:**
```json
{"ok": true, "voucher_id": 123, "order_number": "ORDER-9001"}
```

Check the database:
```sql
SELECT * FROM vouchers WHERE voucherCode = 'REWARD-TEST';
-- Should show isUsed = 1, redeemed_order = 'ORDER-9001'
```

---

## Step 6: End-to-End Test

1. **Student completes course** → Certificate generated → Voucher created
2. **Student visits** `student/vouchers.php` → Sees the voucher code
3. **Student clicks "Shop at SoleSource"** → Redirected to `https://dev.art2cart.shop/pages/shop.php?voucher=REWARD-XXXX`
4. **Student redeems at checkout** → SoleSource processes order
5. **SoleSource sends webhook** → Learnexus marks voucher as redeemed
6. **Student refreshes vouchers page** → Status shows "REDEEMED" with order number

---

## Troubleshooting

### Voucher not generating after certificate?
- Check error logs: `tail -f /path/to/php-error.log`
- Verify `SOLESOURCE_API_KEY` is set: `echo getenv('SOLESOURCE_API_KEY');`
- Test API manually:
  ```bash
  curl -X POST https://dev.art2cart.shop/api/vouchers/generate.php \
    -H "Authorization: Bearer YOUR_KEY" \
    -H "Content-Type: application/json" \
    -d '{"student-id":"test-123"}'
  ```

### Webhook not receiving data?
- Check webhook URL is publicly accessible
- Verify `SOLESOURCE_WEBHOOK_SECRET` matches on both sides
- Check SoleSource logs (ask SoleSource team for webhook delivery logs)
- Test with `curl` (see Step 5C above)

### 401 Unauthorized from SoleSource API?
- Double-check `Authorization: Bearer <KEY>` header format
- Ensure no extra spaces or quotes around the key
- Contact SoleSource team to verify API key is active

### Voucher shows "Coming soon" instead of data?
- Run the migration (Step 1)
- Ensure `helpers/solesource_api.php` is being loaded
- Check for SQL errors in error logs

---

## File Summary

| File | Purpose |
|------|---------|
| `database/migrations/2026-01-13-add-solesource-integration.sql` | Adds SoleSource columns to `vouchers` table |
| `helpers/solesource_api.php` | API client for generate/preview endpoints |
| `solesource_webhook.php` | Receives redemption notifications |
| `student/certificate.php` | **MODIFIED:** Calls voucher generation on certificate creation |
| `student/vouchers.php` | **MODIFIED:** Displays student's vouchers with copy/redeem UI |

---

## Next Steps

1. ✅ Run migration
2. ✅ Set environment variables
3. ✅ Expose webhook endpoint
4. ✅ Test voucher generation
5. ✅ Test webhook delivery
6. 🚀 Deploy to production

---

## Support

- **Learnexus Issues:** Contact your Learnexus development team
- **SoleSource API Issues:** Contact SoleSource team with API docs: `docs/voucher-api.md`
- **Integration Issues:** Check logs first, then provide error messages to both teams

---

**Integration Version:** 1.0  
**Last Updated:** January 13, 2026
