# WhatsApp API Credentials - Quick Checklist

**For Social Media Manager / Meta Account Admin**

## What You Need to Provide (5 Items):

### 1. Phone Number ID
- **Where to find**: Meta App → WhatsApp → API Setup → "Phone number ID"
- **Looks like**: `123456789012345` (long number)
- **For**: `WHATSAPP_PHONE_NUMBER_ID`

### 2. Access Token
- **Where to find**: Meta App → WhatsApp → API Setup → "Temporary access token"
- **Looks like**: `EAABwzLix...` (very long string)
- **For**: `WHATSAPP_ACCESS_TOKEN`
- **Note**: Get permanent token for production use

### 3. App Secret
- **Where to find**: Meta App → Settings → Basic → "App Secret" (click Show)
- **Looks like**: `abc123def456...` (long string)
- **For**: `WHATSAPP_APP_SECRET`
- **Security**: Keep this secret!

### 4. Verify Token
- **What it is**: A custom random string YOU create (not from Meta)
- **How to create**: Use any random string generator or create one yourself
- **Example**: `MySecureToken2024!@#$`
- **For**: `WHATSAPP_VERIFY_TOKEN`
- **Note**: Remember this - you'll need it for webhook setup

### 5. API URL (Optional - has default)
- **Default value**: `https://graph.facebook.com/v18.0`
- **For**: `WHATSAPP_API_URL`

---

## Step-by-Step Process:

### Step 1: Access Meta Developer
1. Go to: **https://developers.facebook.com/**
2. Login with your Meta Business account

### Step 2: Create App (if not exists)
1. Click **"My Apps"** → **"Create App"**
2. Select **"Business"** type
3. Enter app name and details
4. Click **"Create App"**

### Step 3: Add WhatsApp
1. In app dashboard, find **"WhatsApp"** product
2. Click **"Set Up"** or **"Add"**

### Step 4: Get Credentials
1. Go to **WhatsApp → API Setup** tab
2. Copy **Phone Number ID** (from top section)
3. Copy **Access Token** (from "Temporary access token" section)
4. Go to **Settings → Basic**
5. Copy **App Secret** (click Show button)

### Step 5: Create Verify Token
- Generate any secure random string (at least 32 characters)
- Save it - you'll need it later

---

## Final Format to Provide:

```
WHATSAPP_PHONE_NUMBER_ID=123456789012345
WHATSAPP_ACCESS_TOKEN=EAABwzLix...
WHATSAPP_APP_SECRET=abc123def456...
WHATSAPP_VERIFY_TOKEN=MySecureToken2024!@#$
WHATSAPP_API_URL=https://graph.facebook.com/v18.0
```

---

## Important Notes:

⚠️ **Temporary Access Tokens expire in 24 hours**
- For production, get a **Permanent Token** (see full guide)

⚠️ **App Secret is sensitive**
- Never share publicly
- Keep it secure

⚠️ **Phone Number Required**
- If no Phone Number ID shows, you need to:
  - Add a phone number in WhatsApp → Phone Numbers
  - Verify it via SMS/call
  - Then Phone Number ID will appear

---

## Need Help?

Refer to the detailed guide: `WHATSAPP_CREDENTIALS_GUIDE.md`

Or check Meta documentation: https://developers.facebook.com/docs/whatsapp

