# WhatsApp Integration Setup Guide

This guide explains how to set up WhatsApp Business Cloud API integration for the Switch & Save CRM.

## Overview

The system uses Meta's WhatsApp Business Cloud API to:
- **Send messages** from the CRM to customers
- **Receive messages** from customers via webhooks
- **Track delivery status** of sent messages

## Prerequisites

1. A Meta Business Account
2. A WhatsApp Business Account
3. A Meta App with WhatsApp Business API enabled
4. A verified phone number for your WhatsApp Business Account

## Step 1: Create Meta App and Get Credentials

1. Go to [Meta for Developers](https://developers.facebook.com/)
2. Create a new app or use an existing one
3. Add "WhatsApp" product to your app
4. Get your credentials:
   - **Phone Number ID**: Found in WhatsApp > API Setup
   - **Access Token**: Temporary or Permanent token from WhatsApp > API Setup
   - **App Secret**: Found in App Settings > Basic
   - **Verify Token**: Create your own secure random string (e.g., use `php artisan key:generate`)

## Step 2: Configure Environment Variables

Add these to your `.env` file:

```env
# WhatsApp Business Cloud API Configuration
WHATSAPP_API_URL=https://graph.facebook.com/v18.0
WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id_here
WHATSAPP_ACCESS_TOKEN=your_access_token_here
WHATSAPP_VERIFY_TOKEN=your_custom_verify_token_here
WHATSAPP_APP_SECRET=your_app_secret_here
```

**Important Notes:**
- Replace `your_phone_number_id_here` with your actual Phone Number ID
- Replace `your_access_token_here` with your Access Token
- Replace `your_custom_verify_token_here` with a secure random string (remember this for webhook setup)
- Replace `your_app_secret_here` with your App Secret

## Step 3: Configure Webhook URL

1. In Meta App Dashboard, go to WhatsApp > Configuration
2. Click "Edit" next to Webhook
3. Set the **Callback URL** to:
   ```
   https://yourdomain.com/api/webhooks/whatsapp
   ```
   (Replace `yourdomain.com` with your actual domain)
4. Set the **Verify Token** to the same value as `WHATSAPP_VERIFY_TOKEN` in your `.env`
5. Subscribe to these webhook fields:
   - `messages` (to receive incoming messages)
   - `message_status` (to receive delivery status updates)

## Step 4: Test the Integration

### Test Sending a Message

1. Go to a customer in the CRM
2. Use the WhatsApp composer
3. Enter a message and send
4. Check the customer's WhatsApp - they should receive the message

### Test Receiving Messages

1. Send a WhatsApp message to your business number from a customer's phone
2. The message should appear in the CRM timeline automatically
3. Check the logs if it doesn't work: `storage/logs/laravel.log`

## Troubleshooting

### Messages Not Sending

1. **Check credentials**: Verify all environment variables are correct
2. **Check phone number format**: The system automatically formats numbers, but ensure the customer's WhatsApp number is correct
3. **Check API limits**: Meta has rate limits - check your app's usage in Meta Dashboard
4. **Check logs**: Look in `storage/logs/laravel.log` for error messages

### Webhook Not Receiving Messages

1. **Verify webhook URL**: Ensure it's publicly accessible (not localhost)
2. **Check verify token**: Must match exactly between Meta and `.env`
3. **Check SSL**: Meta requires HTTPS for webhooks
4. **Check webhook subscription**: Ensure `messages` field is subscribed in Meta Dashboard
5. **Test webhook**: Use Meta's "Test" button in webhook configuration

### Common Errors

**"WhatsApp API credentials not configured"**
- Solution: Check that all WhatsApp environment variables are set in `.env`

**"Invalid signature"**
- Solution: Ensure `WHATSAPP_APP_SECRET` is correct and matches your Meta App

**"Phone number not in international format"**
- Solution: The system auto-formats numbers, but ensure customer phone numbers are correct

## API Rate Limits

Meta WhatsApp Business API has rate limits:
- **Tier 1**: 1,000 conversations per 24 hours
- **Tier 2**: 10,000 conversations per 24 hours
- **Tier 3**: 100,000 conversations per 24 hours

Check your tier in Meta Dashboard > WhatsApp > API Setup.

## Security Best Practices

1. **Never commit `.env` file** to version control
2. **Use permanent access tokens** in production (not temporary tokens)
3. **Rotate tokens regularly** for security
4. **Enable webhook signature verification** (already implemented)
5. **Use HTTPS** for webhook URLs

## Support

For issues with:
- **Meta API**: Check [Meta WhatsApp Business API Documentation](https://developers.facebook.com/docs/whatsapp)
- **CRM Integration**: Check application logs and ensure configuration is correct

## Alternative Providers

If you prefer not to use Meta's API, you can integrate with:
- **Twilio WhatsApp API**: Similar setup, different credentials
- **Other providers**: Modify `WhatsAppService.php` to use their API

To switch providers, update the `sendMessage()` method in `app/Modules/Communication/Services/WhatsAppService.php`.

