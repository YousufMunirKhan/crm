# How WhatsApp Cloud API Module Works

## 📋 Overview

The WhatsApp Cloud API module allows your CRM to send and receive WhatsApp messages through Meta's WhatsApp Business Cloud API. This guide explains how everything works.

## 🏗️ Architecture

### 1. **Settings Configuration** (Admin Panel → Settings → WhatsApp Cloud API)

**Location:** `/settings` → Click "WhatsApp Cloud API" tab

**What you configure:**
- **WABA ID**: Your WhatsApp Business Account ID from Meta
- **Phone Number ID**: Your WhatsApp phone number ID from Meta
- **Access Token**: Permanent access token from Meta (encrypted in database)
- **Verify Token**: Token for webhook verification (set same in Meta Dashboard)
- **Graph Version**: API version (default: v20.0)
- **Enable**: Toggle to enable/disable WhatsApp features

**How it works:**
1. Go to Settings page
2. Click "WhatsApp Cloud API" tab
3. Fill in your Meta credentials
4. Click "Test Connection" to verify
5. Click "Save Settings"
6. Enable the toggle

**Webhook URL shown:** `https://yourdomain.com/api/whatsapp/webhook`
- Copy this URL and configure it in Meta Dashboard

### 2. **Template Management** (Admin Panel → WhatsApp Templates)

**Location:** Sidebar → "💬 WhatsApp Templates" (Admin/System Admin only)

**What you can do:**
- **Create Templates**: Create message templates with variables
- **View Status**: See approval status (PENDING/APPROVED/REJECTED)
- **Sync from Meta**: Manually sync approval status
- **Resubmit**: Resubmit rejected templates

**How it works:**
1. Click "Create Template"
2. Enter template name (lowercase, underscores only)
3. Select category (TRANSACTIONAL or MARKETING)
4. Enter body text with variables: `Hello {{1}}, your order {{2}} is ready!`
5. Click "Create Template"
6. Template is submitted to Meta for approval
7. Status updates automatically (or click "Sync from Meta")
8. Once APPROVED, you can use it to send messages

**Template Status:**
- **PENDING**: Submitted, waiting for Meta approval (usually 24-48 hours)
- **APPROVED**: Can be used to send messages
- **REJECTED**: Needs to be fixed and resubmitted

### 3. **Sending Messages**

#### Option A: Send Text Message (Within 24-Hour Window)

**When to use:** Customer sent you a message within last 24 hours

**API Endpoint:** `POST /api/whatsapp/messages/send-text`

**Request:**
```json
{
  "customer_id": 1,
  "message": "Hello! How can I help you?"
}
```

**Response:**
- ✅ **200**: Message sent successfully
- ❌ **422**: Customer outside 24-hour window (use template instead)
- ❌ **500**: Error sending message

**How it works:**
1. System checks if customer has sent a message in last 24 hours
2. If yes → sends free-form text message
3. If no → returns error "Customer is outside 24-hour window"

#### Option B: Send Template Message (Outside 24-Hour Window)

**When to use:** Customer hasn't messaged in 24 hours, or for marketing

**API Endpoint:** `POST /api/whatsapp/messages/send-template`

**Request:**
```json
{
  "customer_id": 1,
  "template_name": "order_confirmation",
  "template_params": ["12345", "99.99"],
  "language": "en_US"
}
```

**Response:**
- ✅ **200**: Template message sent successfully
- ❌ **404**: Template not found or not approved
- ❌ **500**: Error sending message

**How it works:**
1. System verifies template exists and is APPROVED
2. Replaces variables in template: `{{1}}` → "12345", `{{2}}` → "99.99"
3. Sends template message via Meta API
4. Can be sent anytime (no 24-hour restriction)

### 4. **Receiving Messages (Webhook)**

**Webhook URL:** `https://yourdomain.com/api/whatsapp/webhook`

**How it works:**
1. Customer sends you a WhatsApp message
2. Meta sends webhook to your server
3. System:
   - Verifies webhook signature (if app_secret configured)
   - Finds or creates customer by phone number
   - Creates conversation record
   - Stores message in database
   - Updates 24-hour window (allows free-form text for 24h)
   - Logs everything

**Webhook Setup in Meta:**
1. Go to Meta Business Dashboard
2. WhatsApp → API Setup
3. Set Webhook URL: `https://yourdomain.com/api/whatsapp/webhook`
4. Set Verify Token (same as in CRM settings)
5. Subscribe to: `messages` field
6. Click "Verify and Save"

### 5. **24-Hour Conversation Window**

**What is it?**
- Meta's rule: You can only send free-form text messages if customer messaged you within last 24 hours
- Outside window: Must use approved templates

**How it's tracked:**
- `whatsapp_conversations` table stores:
  - `last_inbound_at`: When customer last messaged
  - `window_expires_at`: When window expires (24h after last message)
  - `last_outbound_at`: When you last sent message

**Automatic Updates:**
- When customer sends message → window resets to 24 hours
- When you send message → `last_outbound_at` updated
- System automatically checks window before sending text messages

### 6. **Message Status Tracking**

**Statuses:**
- `queued`: Message queued for sending
- `sent`: Successfully sent to Meta
- `delivered`: Delivered to customer's phone
- `read`: Customer read the message
- `failed`: Failed to send

**How it works:**
1. You send message → Status: `sent`
2. Meta delivers → Webhook updates status to `delivered`
3. Customer reads → Webhook updates status to `read`
4. All status updates logged in `whatsapp_messages` table

### 7. **API Logging**

**What's logged:**
- All API requests to Meta (with redacted secrets)
- All API responses from Meta
- All webhook requests from Meta
- Errors and exceptions

**Where:** `whatsapp_api_logs` table

**Security:**
- Access tokens, passwords, secrets are automatically redacted
- Correlation IDs for tracking requests
- Full request/response payloads stored

## 🔄 Complete Flow Examples

### Example 1: Customer Sends First Message

1. Customer sends: "Hello, I need help"
2. Webhook received → System creates customer record
3. System creates conversation → Window expires in 24h
4. Message stored in database
5. You can now send free-form text for 24 hours

### Example 2: Sending Follow-up (Within Window)

1. Customer messaged 2 hours ago
2. You call API: `POST /api/whatsapp/messages/send-text`
3. System checks: Window still active ✅
4. Message sent successfully
5. Status updated to `sent` → `delivered` → `read`

### Example 3: Sending Follow-up (Outside Window)

1. Customer last messaged 25 hours ago
2. You call API: `POST /api/whatsapp/messages/send-text`
3. System checks: Window expired ❌
4. Returns 422 error: "Customer is outside 24-hour window"
5. You use template instead: `POST /api/whatsapp/messages/send-template`

### Example 4: Creating and Using Template

1. **Create Template:**
   - Name: `order_confirmation`
   - Body: `Your order #{{1}} for £{{2}} has been confirmed!`
   - Submit to Meta → Status: PENDING

2. **Wait for Approval:**
   - Meta reviews (24-48 hours)
   - Sync templates: `POST /api/whatsapp/templates/sync`
   - Status updates to APPROVED

3. **Use Template:**
   - Call API: `POST /api/whatsapp/messages/send-template`
   - Params: `["12345", "99.99"]`
   - Customer receives: "Your order #12345 for £99.99 has been confirmed!"

## 🎯 Where to Find Everything

### Admin Panel Locations:

1. **Settings:**
   - Sidebar → Settings → "WhatsApp Cloud API" tab
   - Configure credentials here

2. **Templates:**
   - Sidebar → "💬 WhatsApp Templates"
   - Create and manage templates here

3. **Bulk Messaging:**
   - Sidebar → "💬 Bulk WhatsApp"
   - Send to multiple customers (existing feature)

4. **Customer Conversations:**
   - API: `GET /api/whatsapp/conversations?customer_id=1`
   - View messages: `GET /api/whatsapp/conversations/{id}/messages`
   - (Can be integrated into customer detail page)

## 🔧 Background Jobs

### Template Sync Job

**Runs:** Every 15 minutes (automatically)

**What it does:**
- Fetches templates from Meta API
- Updates approval status in database
- Updates rejection reasons if rejected

**Manual trigger:**
- Click "Sync from Meta" button in Templates page
- Or: `POST /api/whatsapp/templates/sync`

## 📊 Database Tables

1. **whatsapp_settings**: Your credentials (encrypted)
2. **whatsapp_templates**: Template definitions and status
3. **whatsapp_conversations**: Customer conversations and windows
4. **whatsapp_messages**: All sent/received messages
5. **whatsapp_api_logs**: API request/response logs

## 🚀 Getting Started Checklist

1. ✅ Run migrations: `php artisan migrate`
2. ✅ Go to Settings → WhatsApp Cloud API
3. ✅ Enter Meta credentials
4. ✅ Test connection
5. ✅ Enable WhatsApp
6. ✅ Configure webhook in Meta Dashboard
7. ✅ Create your first template
8. ✅ Wait for template approval
9. ✅ Start sending messages!

## ❓ Common Questions

**Q: Why can't I send text messages?**
A: Customer is outside 24-hour window. Use an approved template instead.

**Q: How long does template approval take?**
A: Usually 24-48 hours. Check status in Templates page.

**Q: Where are my messages stored?**
A: All messages in `whatsapp_messages` table. View via API endpoints.

**Q: How do I see customer conversations?**
A: Use API: `GET /api/whatsapp/conversations?customer_id=1`

**Q: Can I send to multiple customers?**
A: Yes, use the existing "Bulk WhatsApp" feature, or call the API multiple times.

## 🔗 API Documentation

Full API documentation: See `WHATSAPP_MODULE_SETUP.md`

