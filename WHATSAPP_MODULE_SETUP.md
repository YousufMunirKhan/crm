# WhatsApp Cloud API Module Setup

This document describes the WhatsApp Cloud API integration module for the CRM system.

## Overview

The WhatsApp module integrates Meta's WhatsApp Business Cloud API, allowing the CRM to:
- Send text messages (within 24-hour conversation window)
- Send template messages (outside 24-hour window)
- Create and manage WhatsApp message templates
- Receive inbound messages via webhook
- Track delivery and read statuses
- Store conversations and message history

## Architecture

### Database Tables

1. **whatsapp_settings** - Stores WhatsApp API credentials (encrypted)
2. **whatsapp_templates** - Stores message templates and approval status
3. **whatsapp_conversations** - Tracks customer conversations and 24-hour windows
4. **whatsapp_messages** - Stores all sent/received messages
5. **whatsapp_api_logs** - Logs all API requests/responses (with redacted secrets)

### Services

- **WhatsAppCloudClient** - Low-level HTTP client for Meta API
- **WhatsAppServiceV2** - Main service for sending messages
- **WhatsAppTemplateService** - Template creation and sync
- **ConversationWindowService** - 24-hour window management

### Models

- `WhatsAppSetting` - Encrypted access token storage
- `WhatsAppTemplate` - Template management
- `WhatsAppConversation` - Conversation tracking
- `WhatsAppMessage` - Message storage
- `WhatsAppApiLog` - API logging

## Installation

### 1. Run Migrations

```bash
php artisan migrate
```

This creates all required tables.

### 2. Environment Configuration

Add to `.env`:

```env
WHATSAPP_GRAPH_VERSION=v20.0
```

**Note:** Actual credentials (access_token, phone_number_id, waba_id) are stored in the database via the settings API, not in `.env`. The `.env` values are only used as defaults.

### 3. Configure Webhook

1. Go to Meta Business Dashboard
2. Navigate to WhatsApp > API Setup
3. Set webhook URL: `https://yourdomain.com/api/whatsapp/webhook`
4. Set verify token (must match what you configure in CRM)
5. Subscribe to: `messages` field

## API Endpoints

### Settings

- `GET /api/whatsapp/settings` - Get current settings
- `POST /api/whatsapp/settings` - Save settings
  ```json
  {
    "waba_id": "123456789",
    "phone_number_id": "987654321",
    "access_token": "EAAN...",
    "verify_token": "your_verify_token",
    "graph_version": "v20.0",
    "is_enabled": true
  }
  ```
- `POST /api/whatsapp/settings/test-connection` - Test API connection

### Templates

- `GET /api/whatsapp/templates` - List templates (with filters: `?status=APPROVED&category=TRANSACTIONAL`)
- `POST /api/whatsapp/templates` - Create template
  ```json
  {
    "name": "hello_world",
    "category": "TRANSACTIONAL",
    "language": "en_US",
    "components": [
      {
        "type": "BODY",
        "text": "Hello {{1}}, welcome to our service!"
      }
    ]
  }
  ```
- `GET /api/whatsapp/templates/{id}` - Get template details
- `POST /api/whatsapp/templates/{id}/resubmit` - Resubmit rejected template
- `POST /api/whatsapp/templates/sync` - Manually sync templates from Meta

### Messages

- `POST /api/whatsapp/messages/send-text` - Send text message (within 24h window)
  ```json
  {
    "customer_id": 1,
    "message": "Hello, how can I help you?"
  }
  ```
  **Response 422 if outside window:**
  ```json
  {
    "message": "Customer is outside 24-hour window. Please use an approved template.",
    "error": "WINDOW_EXPIRED"
  }
  ```

- `POST /api/whatsapp/messages/send-template` - Send template message
  ```json
  {
    "customer_id": 1,
    "template_name": "hello_world",
    "template_params": ["John"],
    "language": "en_US"
  }
  ```

- `GET /api/whatsapp/conversations` - List conversations
  - Query params: `?customer_id=1` or `?phone=+447432391811`
- `GET /api/whatsapp/conversations/{id}/messages` - Get conversation messages

### Webhook (Public)

- `GET /api/whatsapp/webhook` - Webhook verification (Meta calls this)
- `POST /api/whatsapp/webhook` - Webhook handler (Meta calls this)

## Business Rules

### 24-Hour Conversation Window

- **Within Window:** Customer sent a message within last 24 hours
  - ✅ Can send free-form text messages
  - ✅ Can send template messages
  
- **Outside Window:** No inbound message in last 24 hours
  - ❌ Cannot send free-form text (returns 422)
  - ✅ Can only send APPROVED template messages

### Template Status

- **PENDING** - Submitted to Meta, awaiting approval
- **APPROVED** - Can be used for sending
- **REJECTED** - Must be fixed and resubmitted

## Background Jobs

### SyncWhatsAppTemplatesJob

Runs every 15 minutes to sync template approval status from Meta.

**Manual trigger:**
```bash
php artisan queue:work
```

Or schedule in cron:
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## Usage Examples

### 1. Configure WhatsApp Settings

```bash
curl -X POST https://yourdomain.com/api/whatsapp/settings \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "waba_id": "123456789",
    "phone_number_id": "987654321",
    "access_token": "EAAN...",
    "verify_token": "my_secure_token",
    "is_enabled": true
  }'
```

### 2. Create a Template

```bash
curl -X POST https://yourdomain.com/api/whatsapp/templates \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "order_confirmation",
    "category": "TRANSACTIONAL",
    "language": "en_US",
    "components": [
      {
        "type": "BODY",
        "text": "Your order #{{1}} has been confirmed. Total: £{{2}}"
      }
    ]
  }'
```

### 3. Send Text Message (Within Window)

```bash
curl -X POST https://yourdomain.com/api/whatsapp/messages/send-text \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": 1,
    "message": "Hello! How can I help you today?"
  }'
```

### 4. Send Template Message (Outside Window)

```bash
curl -X POST https://yourdomain.com/api/whatsapp/messages/send-template \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": 1,
    "template_name": "order_confirmation",
    "template_params": ["12345", "99.99"]
  }'
```

## Security

### Access Token Encryption

Access tokens are encrypted at rest using Laravel's `Crypt` facade. The encrypted value is stored in `access_token_encrypted` column and automatically decrypted when accessed via the model's `access_token` attribute.

### Webhook Verification

- **GET request:** Validates `hub.verify_token` against stored settings
- **POST request:** Validates `X-Hub-Signature-256` header if `WHATSAPP_APP_SECRET` is configured

### API Logging

All API requests/responses are logged in `whatsapp_api_logs` table with:
- Sensitive data redacted (access_token, password, secret, etc.)
- Correlation ID for tracking
- Request/response payloads
- Error messages

## Phone Number Format

Phone numbers are automatically formatted to E.164 format:
- Removes spaces, dashes, parentheses
- Defaults to +44 (UK) if no country code detected
- Example: `07432391811` → `447432391811`

## Error Handling

### Common Errors

1. **"WhatsApp is not enabled or configured"**
   - Solution: Enable WhatsApp in settings and provide credentials

2. **"Customer is outside 24-hour window"**
   - Solution: Use an approved template instead of text message

3. **"Template not found or not approved"**
   - Solution: Create template and wait for Meta approval, or use `POST /api/whatsapp/templates/sync`

4. **"Recipient phone number not in allowed list"**
   - Solution: Add phone number to Meta Business Account allowed list (development mode only)

## Testing

### Test Connection

```bash
curl -X POST https://yourdomain.com/api/whatsapp/settings/test-connection \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Check Logs

```bash
# View API logs
php artisan tinker
>>> \App\Modules\Communication\Models\WhatsAppApiLog::latest()->take(10)->get();

# View recent messages
>>> \App\Modules\Communication\Models\WhatsAppMessage::latest()->take(10)->get();
```

## Troubleshooting

### Templates Not Syncing

1. Check if job is running: `php artisan queue:work`
2. Check scheduler: `php artisan schedule:list`
3. Manually sync: `POST /api/whatsapp/templates/sync`

### Webhook Not Receiving Messages

1. Verify webhook URL in Meta Dashboard
2. Check verify token matches
3. Check `whatsapp_api_logs` for incoming webhooks
4. Ensure webhook route is public (no auth middleware)

### Messages Not Sending

1. Check settings are enabled: `GET /api/whatsapp/settings`
2. Verify access token is valid
3. Check API logs for error details
4. Ensure phone number is in allowed list (dev mode)

## Migration from Old WhatsApp Service

The new module (`WhatsAppServiceV2`) coexists with the existing `WhatsAppService`. To migrate:

1. Configure new settings via `/api/whatsapp/settings`
2. Update frontend to use new endpoints
3. Old endpoints remain functional for backward compatibility

## Support

For issues or questions:
- Check `whatsapp_api_logs` table for detailed error information
- Review Meta Business API documentation: https://developers.facebook.com/docs/whatsapp/cloud-api
- Check Laravel logs: `storage/logs/laravel.log`

