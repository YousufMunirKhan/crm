# WhatsApp Business API - Complete Setup Guide for Meta Account

This guide will help you get all the required credentials from your Meta (Facebook) Business Account to enable WhatsApp messaging in the CRM system.

## Prerequisites

1. A **Meta Business Account** (business.facebook.com)
2. A **Facebook Page** (for your business)
3. A **Meta Developer Account** (developers.facebook.com)

---

## Step 1: Create or Access Meta Developer Account

1. Go to **https://developers.facebook.com/**
2. Click **"Get Started"** or **"Log In"** if you already have an account
3. Accept the terms and conditions
4. Complete the account verification if prompted

---

## Step 2: Create a Meta App

1. In the Meta Developer Dashboard, click **"My Apps"** (top right)
2. Click **"Create App"** button
3. Select **"Business"** as the app type
4. Click **"Next"**
5. Fill in the app details:
   - **App Name**: Your Business Name (e.g., "Switch & Save CRM")
   - **App Contact Email**: Your business email
   - **Business Account**: Select your business account
6. Click **"Create App"**

---

## Step 3: Add WhatsApp Product to Your App

1. After creating the app, you'll see the app dashboard
2. Look for **"Add Products"** section or scroll down to find **"WhatsApp"**
3. Click **"Set Up"** or **"Add"** next to WhatsApp
4. You'll be redirected to WhatsApp setup page

---

## Step 4: Get Phone Number ID

1. In the WhatsApp setup page, go to **"API Setup"** tab (left sidebar)
2. You'll see a section called **"Phone number ID"**
3. **Copy the Phone Number ID** - This is a long number (e.g., `123456789012345`)
4. **Save this** - You'll need it for: `WHATSAPP_PHONE_NUMBER_ID`

**Note**: If you don't have a phone number yet, you'll need to:
   - Add a phone number to your WhatsApp Business Account
   - Verify it via SMS/call
   - Then the Phone Number ID will appear

---

## Step 5: Get Access Token

1. Still in the **"API Setup"** tab
2. Scroll down to **"Temporary access token"** section
3. You'll see a token that looks like: `EAABwzLix...` (very long string)
4. **Copy this token** - This is your temporary token
5. **Save this** - You'll need it for: `WHATSAPP_ACCESS_TOKEN`

**Important**: 
- Temporary tokens expire in 24 hours
- For production, you need a **Permanent Token** (see Step 8 below)

---

## Step 6: Get App Secret

1. Go to **"Settings"** → **"Basic"** (left sidebar in app dashboard)
2. Scroll down to find **"App Secret"** section
3. Click **"Show"** button next to App Secret
4. Enter your Facebook password if prompted
5. **Copy the App Secret** - This is a long string (e.g., `abc123def456...`)
6. **Save this** - You'll need it for: `WHATSAPP_APP_SECRET`

**Security Note**: Keep this secret secure and never share it publicly!

---

## Step 7: Create Verify Token

1. This is a **custom token you create yourself** (not from Meta)
2. Generate a secure random string. You can:
   - Use an online generator: https://randomkeygen.com/
   - Or use this command: `php artisan key:generate` (if you have access)
   - Or create any random secure string (at least 32 characters)
3. **Example**: `MySecureVerifyToken2024!@#$%`
4. **Save this** - You'll need it for: `WHATSAPP_VERIFY_TOKEN`
5. **Remember this** - You'll need it again when setting up webhooks

---

## Step 8: Get Permanent Access Token (For Production)

**Temporary tokens expire in 24 hours. For production use, you need a permanent token:**

1. Go to **"WhatsApp"** → **"API Setup"** in your app dashboard
2. Scroll to **"Access Tokens"** section
3. Click **"Add or Remove Permissions"**
4. Select your System User or create one
5. Grant **"whatsapp_business_messaging"** permission
6. Click **"Generate Token"**
7. **Copy the permanent token** - This won't expire
8. **Save this** - Replace the temporary token with this for: `WHATSAPP_ACCESS_TOKEN`

---

## Step 9: Summary - What to Provide

Once you have all the credentials, provide them in this format:

```
WHATSAPP_API_URL=https://graph.facebook.com/v18.0
WHATSAPP_PHONE_NUMBER_ID=[Phone Number ID from Step 4]
WHATSAPP_ACCESS_TOKEN=[Access Token from Step 5 or 8]
WHATSAPP_VERIFY_TOKEN=[Your custom token from Step 7]
WHATSAPP_APP_SECRET=[App Secret from Step 6]
```

---

## Step 10: Verify Phone Number (If Not Done)

If you haven't verified a phone number yet:

1. Go to **"WhatsApp"** → **"Phone Numbers"** in app dashboard
2. Click **"Add Phone Number"**
3. Enter your business phone number
4. Choose verification method (SMS or Voice Call)
5. Enter the verification code you receive
6. Once verified, you'll get the Phone Number ID

---

## Troubleshooting

### Can't find WhatsApp product?
- Make sure you selected "Business" app type when creating the app
- Some accounts may need business verification first

### No Phone Number ID showing?
- You need to add and verify a phone number first
- Go to WhatsApp → Phone Numbers section

### Access Token expired?
- Temporary tokens expire in 24 hours
- Use Step 8 to get a permanent token

### App Secret not showing?
- Make sure you're logged in as an admin of the app
- You may need to verify your developer account

---

## Security Best Practices

1. **Never share** your App Secret publicly
2. **Use Permanent Tokens** for production (not temporary)
3. **Rotate tokens** regularly for security
4. **Keep credentials** in `.env` file (never commit to git)

---

## Need Help?

If you encounter any issues:
1. Check Meta Developer Documentation: https://developers.facebook.com/docs/whatsapp
2. Verify your business account status
3. Ensure all required permissions are granted
4. Contact Meta Business Support if needed

---

## Quick Checklist

- [ ] Meta Developer Account created
- [ ] Meta App created (Business type)
- [ ] WhatsApp product added to app
- [ ] Phone Number ID obtained
- [ ] Access Token obtained (temporary or permanent)
- [ ] App Secret obtained
- [ ] Verify Token created (custom)
- [ ] Phone number verified (if needed)
- [ ] All credentials saved securely

---

**Once you have all these credentials, provide them to the technical team to add to the `.env` file.**

