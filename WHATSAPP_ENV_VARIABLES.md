# WhatsApp Environment Variables

## Required for WhatsApp Cloud API Module

Add these to your `.env` file:

```env
# WhatsApp Cloud API Configuration
WHATSAPP_GRAPH_VERSION=v20.0
```

## Optional (Used as Fallbacks)

These are optional because the actual credentials are stored in the database via the Settings API. However, you can set them as defaults:

```env
# WhatsApp Cloud API (Optional - stored in DB via Settings API)
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_VERIFY_TOKEN=your_secure_verify_token_here
WHATSAPP_APP_SECRET=
WHATSAPP_BUSINESS_ACCOUNT_ID=
```

## Important Notes

1. **Graph Version**: This is the only required variable. It sets the Meta API version (default: v20.0)

2. **Credentials Storage**: 
   - Actual credentials (WABA ID, Phone Number ID, Access Token) are stored in the `whatsapp_settings` table
   - They are encrypted at rest
   - Configure them via Admin Panel → Settings → WhatsApp Cloud API

3. **Verify Token**: 
   - Set this in `.env` as a default
   - Must match what you set in Meta Dashboard webhook configuration
   - Can be overridden in Settings page

4. **App Secret**: 
   - Used for webhook signature verification
   - Optional but recommended for security

## Complete .env Section

```env
# WhatsApp Cloud API
WHATSAPP_GRAPH_VERSION=v20.0
WHATSAPP_VERIFY_TOKEN=your_secure_verify_token_here
WHATSAPP_APP_SECRET=your_app_secret_here
```

**Note:** Phone Number ID, Access Token, and WABA ID should be configured via the Settings page in the admin panel, not in `.env` (for security).

