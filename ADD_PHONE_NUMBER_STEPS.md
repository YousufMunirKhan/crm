# How to Add Phone Number to WhatsApp Allowed List - STEP BY STEP

## The Problem
You're getting: "Recipient phone number not in allowed list"

This means the phone number you're trying to send to needs to be added and verified in your Meta Business Account.

---

## EXACT STEPS TO FIX:

### Step 1: Open Meta App Dashboard
1. Go to: **https://developers.facebook.com/apps/**
2. Login with your Meta account
3. Click on your app (the one with WhatsApp)

### Step 2: Go to WhatsApp API Setup
1. In the left sidebar, click **"WhatsApp"**
2. Click **"API Setup"** tab (should be selected by default)

### Step 3: Find "To" Field or Phone Number List
Look for one of these sections:
- **"To"** field (usually at the top)
- **"Phone number list"** or **"Recipient phone numbers"**
- **"Manage phone number list"** button

### Step 4: Add the Phone Number
1. Click **"Add phone number"** or **"Manage"** button
2. Enter the phone number in **international format**:
   - Remove the + sign
   - Remove all spaces and dashes
   - Example: If customer has `+44 7432 391811`, enter: `447432391811`
   - Example: If customer has `07432 391811`, enter: `447432391811`
3. Click **"Send Code"** or **"Verify"**
4. You'll receive a code via SMS or WhatsApp
5. Enter the verification code
6. Click **"Verify"** or **"Add"**

### Step 5: Verify It's Added
- The phone number should appear in your list
- Make sure it shows as "Verified" or "Active"

### Step 6: Try Sending Again
- Go back to your CRM
- Try sending the WhatsApp message again
- It should work now!

---

## For Customer "Yousuf Munir"

1. **Get their phone number** from the CRM customer list
2. **Convert to international format**:
   - If it's stored as: `07432 391811` → Use: `447432391811`
   - If it's stored as: `+44 7432 391811` → Use: `447432391811`
   - If it's stored as: `447432391811` → Use as is: `447432391811`
3. **Add this number** to Meta using steps above
4. **Verify** the number
5. **Try sending** again

---

## Alternative: Direct Link Method

If you can't find the option, try this:

1. Go to: **https://developers.facebook.com/apps/[YOUR_APP_ID]/whatsapp-business/cloud-api/get-started**
   (Replace [YOUR_APP_ID] with your actual App ID: 968377435537226)

2. Or go to: **https://business.facebook.com/settings/whatsapp-accounts**
   (This might show your WhatsApp Business Account settings)

---

## Phone Number Format Rules

✅ **CORRECT FORMATS:**
- `447432391811` (UK number, no +, no spaces)
- `1234567890` (US number, no +, no spaces)
- `919876543210` (India number, no +, no spaces)

❌ **WRONG FORMATS:**
- `+44 7432 391811` (has + and spaces)
- `07432 391811` (starts with 0, missing country code)
- `44-7432-391811` (has dashes)

---

## Still Can't Find It?

The exact location might vary. Try these:

1. **Meta App Dashboard** → Your App → **WhatsApp** → **API Setup** → Look for **"To"** field
2. **Meta App Dashboard** → Your App → **WhatsApp** → **Phone Numbers** → **Add Number**
3. **Meta Business Manager** → **WhatsApp Accounts** → Select account → **Phone Numbers**

---

## Important Notes

- ⚠️ You must add **each phone number** you want to send to
- ⚠️ Each number must be **verified** (via SMS or call)
- ⚠️ This is only required in **development/testing** mode
- ⚠️ Once your app is **approved for production**, you can send to any number

---

## Quick Test

After adding the number, you can test it using the curl command:

```bash
curl -i -X POST \
  https://graph.facebook.com/v22.0/397428073449586/messages \
  -H 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "messaging_product": "whatsapp",
    "to": "447432391811",
    "type": "text",
    "text": {
      "body": "Test message"
    }
  }'
```

Replace `447432391811` with the actual phone number you added.

