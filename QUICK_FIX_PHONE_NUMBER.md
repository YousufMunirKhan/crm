# ⚠️ QUICK FIX: Add Phone Number to Meta

## The Error You're Seeing
"Recipient phone number not in allowed list"

## What This Means
Meta WhatsApp Business API requires you to **add and verify** each phone number before you can send messages to it (in development mode).

## EXACT STEPS (2 minutes):

### Step 1: Open This Link
**https://developers.facebook.com/apps/968377435537226/whatsapp-business/cloud-api/get-started**

(Or go to: developers.facebook.com → Your App → WhatsApp → API Setup)

### Step 2: Find "To" Field
- Look for a field labeled **"To"** or **"Phone number"**
- Or look for **"Add phone number"** or **"Manage phone number list"** button

### Step 3: Add the Phone Number
1. Get Yousuf Munir's phone number from your CRM
2. Convert it to **international format** (no +, no spaces):
   - Example: `+44 7432 391811` → `447432391811`
   - Example: `07432 391811` → `447432391811`
3. Enter the number in the "To" field
4. Click **"Send Code"** or **"Verify"**
5. Enter the code you receive via SMS/WhatsApp
6. Click **"Verify"**

### Step 4: Try Again
Go back to your CRM and try sending the message again.

---

## Still Can't Find It?

Try these alternative locations:

1. **Meta App Dashboard** → **WhatsApp** → **API Setup** → Scroll down to find **"To"** field
2. **Meta Business Manager** → **WhatsApp Accounts** → Select account → **Phone Numbers**
3. Look for a **"Test"** or **"Send Test Message"** section - the "To" field is usually there

---

## Phone Number Format

✅ **USE THIS FORMAT:**
- Remove + sign
- Remove all spaces
- Remove all dashes
- Just numbers: `447432391811`

❌ **DON'T USE:**
- `+44 7432 391811` (has + and spaces)
- `07432 391811` (starts with 0)
- `44-7432-391811` (has dashes)

---

## Need the Exact Phone Number?

Check the error message - it should now show the exact phone number being used. Add THAT exact number to Meta.

---

## Once Added

After adding and verifying the number:
1. Wait 1-2 minutes
2. Try sending the message again from your CRM
3. It should work!

---

**This is a one-time setup per phone number. Once added, you can send unlimited messages to that number.**

