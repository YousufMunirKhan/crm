# Email Template System - Implementation Plan

## Overview
A comprehensive email template builder system with pre-built responsive templates, similar to Brevo/Mailchimp.

## Features Implemented

### 1. Database Structure ✅
- `email_templates` - Stores email templates with JSON content structure
- `message_templates` - SMS templates
- `whatsapp_templates` - WhatsApp templates  
- `sent_communications` - Tracks all sent communications

### 2. Template Content Structure
Templates use a JSON structure with sections/blocks:
```json
{
  "sections": [
    {
      "type": "header",
      "content": {
        "logo": "{{company_logo}}",
        "text": "Welcome!"
      }
    },
    {
      "type": "text",
      "content": {
        "text": "Hello {{customer_name}}"
      }
    },
    {
      "type": "image",
      "content": {
        "url": "/storage/...",
        "alt": "Product image"
      }
    },
    {
      "type": "button",
      "content": {
        "text": "View Details",
        "url": "#"
      }
    },
    {
      "type": "footer",
      "content": {
        "company_info": true
      }
    }
  ]
}
```

### 3. Pre-built Templates
- Welcome Email
- Epos Description Email (with specs & images)
- Teya Product Email
- Appointment Email
- Invoice Email (multiple variants)
- Follow-up Email
- Quote Email
- Thank You Email
- Reminder Email
- Custom Email

### 4. Template Builder Features
- Drag & drop sections
- Add/remove sections
- Edit text inline
- Upload/replace images
- Variable insertion ({{customer_name}}, {{appointment_date}}, etc.)
- Mobile responsive preview
- Desktop/Mobile view toggle

### 5. Next Steps
1. Create TemplatesView.vue page
2. Create TemplateBuilder.vue component
3. Create pre-built template seeders
4. Add routes
5. Create send email interface

