# Email Management – Specification

## Tables (where data is saved)

- **Filter-based sends:** Recipients come from `customers` (filtered by audience/products). Each send is logged in **`sent_communications`** (recipient_email, template_id, subject, content, status, sent_at, sent_by, customer_id).
- **Upload-list (custom upload):**
  - **`email_lists`** – Each uploaded list: id, name, original_file_name, **template_id** (which template will be sent to this list), created_by, created_at.
  - **`email_list_recipients`** – Each row from the CSV: id, email_list_id, email, name, status (pending/sent/failed), error_message, sent_at.
  - When you send to a list, each send is also logged in **`sent_communications`** (customer_id is null for list sends).

So: **custom-upload emails** are stored in **`email_lists`** (list + chosen template) and **`email_list_recipients`** (each email/name and send status). Sent emails from lists are also in **`sent_communications`**.

---

## 1. Overview

- **Location:** Under Marketing menu (e.g. "Email Management" or "Bulk Email").
- **Purpose:** Filter customers/prospects (like a data analyst), preview who gets the email, export list, choose template, preview email, send, and keep records of who was sent.

---

## 2. Audience & "All" Behaviour

- **Audience options:** Prospect | Customer | **Both** (same filters apply to both when "Both" is selected).
- **"All" means no filter – everyone in that segment:**
  - **All customers** = every customer (no product filter).
  - **All prospects** = every prospect (no product filter).
  - **Both + All** = send to **all customers and all prospects** (entire base).

So: if user selects "All" for customer and "All" for prospect (or audience "Both" with no product filter), the email goes to **everyone**.

---

## 3. Filters (Data Analyst Style)

Same filter logic for **Prospect** and **Customer**; only the meaning of "has" differs:

- **Prospect:** "has" = interested in (lead/product on lead or lead_items). "does not have" = no lead/product for that product.
- **Customer:** "has" = has product (won lead / won lead item). "does not have" = no won lead/item for that product.

**Product-based rules (per product):**

| Option              | Meaning |
|---------------------|--------|
| **All**             | No filter on this product – include everyone in the selected audience. |
| **Has product**     | Only contacts who have (customer) or are interested in (prospect) this product. |
| **Does not have**   | Only contacts who do **not** have / are **not** interested in this product. |

**Combining:**

- Multiple products: e.g. "Has Card Machine" + "Does not have POS" (AND between rules).
- If **no product filter** is applied (or user chooses "All" for audience): send to **all** in that audience (all customers, all prospects, or both).

**Examples (analyst use cases):**

- Send to everyone → Audience: Both, no product filter (or "All").
- Send only to customers who have Card Machine → Customer + Has: Card Machine.
- Send only to those who **don’t** have Card Machine → Customer + Does not have: Card Machine.
- Send to prospects interested in Product A but not Product B → Prospect + Has: A, Does not have: B.
- Send to all prospects → Prospect + All (no product filter).
- Send to all customers → Customer + All (no product filter).

(First version can support: one audience type, then multiple rules like "Has P1", "Does not have P2", etc., with AND between them. OR logic can be added later if needed.)

---

## 4. Export Before Sending

- After applying filters, show the **list of contacts** (name, email, type: prospect/customer) that match.
- **Export** (e.g. CSV/Excel) this list **before** sending – so user can see exactly who will receive the email.
- Export can include: name, email, type (prospect/customer), business name, and any other useful columns.

---

## 5. Template & Preview

- **Choose template:** User selects the email template to send (from existing Email Templates).
- **Preview box (two parts):**
  1. **Who:** "This email will go to **X recipients**" and a preview of the list (with option to export as above).
  2. **How it looks:** Rendered preview of the template (subject + body) with sample merge fields (e.g. `{{name}}` → "John") so the email looks as the recipient will see it.

---

## 6. Sending & Records

- **Send:** One action sends the chosen template to all filtered contacts (prospect and/or customer, including "both").
- **Records:** For each recipient we **record** the send (e.g. in `sent_communications` or equivalent):
  - customer_id, recipient_email, template used, subject/content snapshot, status (sent/failed), sent_at, sent_by.
- So we have a **history**: "We sent this template to these customers/prospects on this date."

Optional: a **campaign** or **batch** id to group one "Email Management" send (e.g. "Campaign 2025-01 – Card Machine upsell") so we can later list "all campaigns" and "who was sent in each".

---

## 7. Flow Summary

1. **Audience:** Prospect | Customer | Both.
2. **Filters:** Per product: All / Has / Does not have (and combine multiple products). "All" = no product filter → everyone in that segment.
3. **Result list:** See and **export** the contacts (and emails) that match.
4. **Template:** Choose the email template.
5. **Preview:** Who gets it + how the email looks.
6. **Send** → store a record for each sent email (and optionally group by campaign).

---

## 8. Confirmed Points

- Same filter logic for prospect and customer; "Both" sends to both segments with same rules.
- "All" = no filter → all customers and/or all prospects get the email when selected.
- Export before sending: yes.
- We keep records of sent emails (who received which template, when).
- Filter design is analyst-friendly: Has / Does not have / All, with flexibility to combine.
