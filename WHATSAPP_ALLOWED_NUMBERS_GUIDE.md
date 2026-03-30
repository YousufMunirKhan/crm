# WhatsApp Business API - Add Recipient Phone Numbers

## Problem
Error: "Recipient phone number not in allowed list"

This happens because WhatsApp Business API in development/testing mode only allows sending messages to **verified phone numbers** that you've added to your Meta Business Account.

## Solution: Add Phone Numbers to Allowed List

### Step 1: Access Meta App Dashboard
1. Go to **https://developers.facebook.com/**
2. Login to your Meta Developer account
3. Select your app (the one with WhatsApp product)

### Step 2: Navigate to WhatsApp Settings
1. In the left sidebar, click **"WhatsApp"**
2. Click **"API Setup"** tab
3. Scroll down to find **"To"** section or **"Phone Numbers"** section

### Step 3: Add Phone Number
1. Look for **"Add phone number"** or **"Manage phone number list"** option
2. Click **"Add phone number"** or **"Manage"**
3. Enter the phone number in **international format** (without + sign)
   - Example: `447432391811` (UK number)
   - Example: `1234567890` (US number)
4. Click **"Send Code"** or **"Verify"**
5. Enter the verification code sent via SMS/WhatsApp
6. Click **"Verify"** or **"Add"**

### Alternative: Using Phone Number ID Section
1. Go to **WhatsApp → API Setup**
2. Look for **"To"** field or **"Recipient phone numbers"**
3. Click **"Add"** or **"Manage"**
4. Add the phone number in international format
5. Verify the number

### Step 4: Verify Phone Number Format
- **Correct format**: `447432391811` (no +, no spaces, no dashes)
- **Wrong formats**: 
  - `+44 7432 391811` ❌
  - `07432 391811` ❌
  - `447432391811` ✅

## For Production Use

Once your app is approved and live, you can send to any phone number without adding them to a list. But during development/testing, you must add each recipient.

## Quick Steps Summary

1. **Meta App Dashboard** → Your App
2. **WhatsApp** → **API Setup**
3. Find **"To"** or **"Phone Numbers"** section
4. **Add phone number** in international format
5. **Verify** the number via SMS/call
6. **Try sending** message again

## Multiple Numbers

You can add multiple phone numbers. Just repeat the process for each recipient you want to test with.

## Important Notes

- Phone numbers must be in **international format** (country code + number, no + sign)
- Each number needs to be **verified** via SMS or call
- This is only required for **development/testing** mode
- In **production**, once approved, you can send to any number

## Troubleshooting

**Can't find "Add phone number" option?**
- Make sure you're in the WhatsApp → API Setup section
- Check if you're in development mode (you should be)
- Some accounts may need business verification first

**Verification code not received?**
- Check the phone number format
- Try voice call instead of SMS
- Make sure the number can receive SMS/calls

**Still getting error after adding?**
- Make sure the number is in international format (no +, no spaces)
- Verify the number was successfully added and verified
- Clear cache and try again: `php artisan config:clear`

