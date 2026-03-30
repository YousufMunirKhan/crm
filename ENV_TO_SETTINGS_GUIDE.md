# How to Transfer .env Values to Settings Page

## Step-by-Step Guide

### 1. Check Your .env File (Lines 68-80)

Look for these variables in your `.env` file:

```env
# Example of what you might see:
WHATSAPP_PHONE_NUMBER_ID=397428073449586
WHATSAPP_ACCESS_TOKEN=EAANwuZCENv0oBQzn1PcHTIttanhni9yKyWzUdYcvfGaZCJAKS8ZCUGZCWOgj0av6Uis1UDj8ZAZAPbGtejLcPWe46A3vOxfmN6WAA3ZBnCugTJIDNzkLU2OtcS4uWfRw5xWYnAlzuW1Qq3eviTiRdWb3fZC4pKSLyHroEZB8pg03dp9IxZCNZAp77bWyZBIG5HaJig723ZC1sNx4pWCPR97ydFKKFVnnz7VZAxXUlFJkH4AuGBy5NZAt8XcizWpzOdl2Dciqkch3AwvnOfdofyuv1UEiyRRjTo8
WHATSAPP_BUSINESS_ACCOUNT_ID=383539848172438
WHATSAPP_APP_ID=968377435537226
WHATSAPP_APP_SECRET=c59623cfaeb15159f9a3e8f957123626
WHATSAPP_VERIFY_TOKEN=your_verify_token_here
WHATSAPP_GRAPH_VERSION=v20.0
```

### 2. Go to Settings Page

1. Login to your CRM
2. Click **Settings** in the sidebar (Admin/System Admin only)
3. Click the **"☁️ WhatsApp Cloud API"** tab

### 3. Map .env Variables to Settings Fields

| .env Variable | Settings Field Name | What to Enter |
|--------------|---------------------|---------------|
| `WHATSAPP_BUSINESS_ACCOUNT_ID` | **WABA ID** | Your WhatsApp Business Account ID (e.g., `383539848172438`) |
| `WHATSAPP_PHONE_NUMBER_ID` | **Phone Number ID** | Your Phone Number ID (e.g., `397428073449586`) |
| `WHATSAPP_ACCESS_TOKEN` | **Access Token** | Your permanent access token (the long string starting with `EAAN...`) |
| `WHATSAPP_VERIFY_TOKEN` | **Verify Token** | Your verify token (e.g., `your_verify_token_here`) |
| `WHATSAPP_GRAPH_VERSION` | **Graph API Version** | API version (e.g., `v20.0`) |

### 4. Fill in the Settings Form

**Example values based on your previous credentials:**

```
WABA ID (WhatsApp Business Account ID): 383539848172438
Phone Number ID: 397428073449586
Access Token: EAANwuZCENv0oBQzn1PcHTIttanhni9yKyWzUdYcvfGaZCJAKS8ZCUGZCWOgj0av6Uis1UDj8ZAZAPbGtejLcPWe46A3vOxfmN6WAA3ZBnCugTJIDNzkLU2OtcS4uWfRw5xWYnAlzuW1Qq3eviTiRdWb3fZC4pKSLyHroEZB8pg03dp9IxZCNZAp77bWyZBIG5HaJig723ZC1sNx4pWCPR97ydFKKFVnnz7VZAxXUlFJkH4AuGBy5NZAt8XcizWpzOdl2Dciqkch3AwvnOfdofyuv1UEiyRRjTo8
Verify Token: your_verify_token_here (or create a new secure one)
Graph API Version: v20.0
```

### 5. Enable WhatsApp

- ✅ Check the **"Enable WhatsApp Cloud API"** checkbox
- Click **"Save Settings"**
- Click **"Test Connection"** to verify

## Important Notes

1. **Access Token**: This is a long string. Make sure to copy it completely (it might wrap in .env file)

2. **Verify Token**: 
   - Must match what you set in Meta Dashboard webhook configuration
   - If you haven't set it in Meta yet, create a secure random string
   - Example: `my_secure_token_2024_xyz123`

3. **Graph Version**: 
   - Default is `v20.0`
   - Check Meta's latest version if needed

4. **Security**: 
   - Access Token is encrypted when stored in database
   - It won't be shown when you view settings (for security)

## After Saving

1. **Test Connection**: Click "Test Connection" button
2. **Configure Webhook**: 
   - Copy the webhook URL shown: `https://yourdomain.com/api/whatsapp/webhook`
   - Go to Meta Dashboard → WhatsApp → API Setup
   - Set webhook URL and verify token
3. **Create Templates**: Go to "💬 WhatsApp Templates" to create message templates

## Troubleshooting

**If "Test Connection" fails:**
- Check that all fields are filled correctly
- Verify Access Token is still valid (tokens can expire)
- Check that Phone Number ID and WABA ID are correct
- Look at browser console for error details

**If settings don't save:**
- Make sure you're logged in as Admin or System Admin
- Check browser console for errors
- Verify API endpoint is accessible: `/api/whatsapp/settings`

