# Prospect vs Customer, Import (All Fields), Birthday, and Categories – Plan

## Overview

This plan extends the Prospect vs Customer work with: (1) **full customer import** (all customer fields), (2) **birthday** column on customers, and (3) **categorization** so you can clearly see **prospects** (and their leads/products) vs **customers** (and what they have purchased). No destructive changes; existing data stays safe.

---

## Part A: Prospect vs Customer (existing plan summary)

- **Prospect** = person with **no lead** converted to **won**.
- **Customer** = person with **at least one lead** with `stage = 'won'`.
- **Backend**: In [app/Modules/CRM/Http/Controllers/CustomerController.php](app/Modules/CRM/Http/Controllers/CustomerController.php) `index()`, add query parameter `type=prospect|customer` and filter using `whereHas` / `whereDoesntHave('leads', fn ($q) => $q->where('stage', 'won'))`.
- **Frontend**: In [resources/js/views/CustomersView.vue](resources/js/views/CustomersView.vue), add two tabs (Prospects | Customers), pass `type` when loading the list, relabel “Add Prospect” on Prospects tab.
- **Email**: In [app/Http/Controllers/EmailTemplateController.php](app/Http/Controllers/EmailTemplateController.php) `replaceVariables()`, add `{{prospect_products}}` / `{{customer_products}}` from customer’s leads/items (non-won vs won).
- **Detail**: Show “Prospect for: [products]” and “Purchased: [products]” using existing lead/item data.

---

## Part B: Birthday column

- **Migration**: New migration adding `birthday` to `customers` table (e.g. `$table->date('birthday')->nullable();`). Additive only; no drops.
- **Model**: In [app/Modules/CRM/Models/Customer.php](app/Modules/CRM/Models/Customer.php), add `birthday` to `$fillable` and `'birthday' => 'date'` to `$casts`.
- **API**: In `CustomerController::store()` and `update()` (and any validation), allow `birthday` (nullable date).
- **Forms**: In customer create/edit forms (e.g. [resources/js/components/forms/CustomerForm.vue](resources/js/components/forms/CustomerForm.vue) or [resources/js/views/CustomerFormView.vue](resources/js/views/CustomerFormView.vue)), add a birthday field (date input).
- **Detail/List**: Show birthday on customer detail and optionally in list (e.g. compact column or tooltip).
- **Import**: Include `birthday` in import field map and optional fields (see Part C).

---

## Part C: Import customer with all fields

**Backend** – [app/Modules/CRM/Http/Controllers/CustomerController.php](app/Modules/CRM/Http/Controllers/CustomerController.php) `import()`:

- Extend `$fieldMap` to cover **all** customer fields currently in the model, plus `birthday` and (if added) `category`:
  - Already mapped: `name`, `phone`, `email`, `address`, `city`, `postcode`, `vat_number`.
  - Add mappings for: `business_name`, `owner_name`, `whatsapp_number`, `email_secondary`, `sms_number`, `notes`, `source`, `anydesk_rustdesk`, `passwords`, `epos_type`, `lic_days`, `birthday`, and (if you add it) `category`.
- For each field, define multiple possible header names (e.g. `birthday` => `['birthday', 'date of birth', 'dob', 'birth date']`, `business_name` => `['business name', 'company', 'business']`, etc.).
- When building `$customerData`, only set keys that exist in `$columnMap` and that are in the model’s fillable list; leave others null/empty. Parse `birthday` as a date (e.g. Y-m-d or common Excel formats); invalid dates can be stored as null and optionally reported in `error_details`.
- Keep required validation as Name + Phone; all other fields optional.
- Duplicate check: keep current logic (e.g. by phone); do not change or drop existing data.

**Frontend** – [resources/js/components/ImportModal.vue](resources/js/components/ImportModal.vue):

- Extend `requiredFields` / `optionalFields` to include every importable customer field (including `birthday` and `category` if added), so the column-mapping UI shows all fields. Ensure optional fields list matches backend (name, phone, email, address, city, postcode, vat_number, business_name, owner_name, whatsapp_number, email_secondary, sms_number, notes, source, anydesk_rustdesk, passwords, epos_type, lic_days, birthday, category).
- Update the “Expected format” help text to mention that all customer fields can be imported and list the main ones (including Birthday).
- Keep sending only the file; backend continues to auto-detect columns from the first row using the extended `$fieldMap`.

Result: import supports **all** customer fields plus **birthday** (and **category** if you add it), with no data loss.

---

## Part C2: Import template generated from products (dynamic columns)

When the user goes to import customers, the **import template/sheet** should be **generated from the products in the DB**: one column per product, so the sheet structure matches your current catalogue.

**Template structure**

- **Row 1 (headers)** = [all customer field columns] + [one column per product].
- Customer columns: name, phone, email, address, city, postcode, vat_number, business_name, owner_name, whatsapp_number, email_secondary, sms_number, notes, source, anydesk_rustdesk, passwords, epos_type, lic_days, birthday, category (if added).
- Product columns: for each product in the DB (e.g. from `Product::orderBy('name')->get(['id','name'])`), add a column whose header is the **product name** (or a safe header like `Product: {name}` so it’s unique and parseable).
- Example: if DB has 5 products (Card Machine, POS, Epos, etc.), the template has those 5 extra columns. So the user uploads a sheet that already has the right columns for “this customer – prospect/purchase for Product A, B, C…”.

**Backend – template download**

- New endpoint, e.g. `GET /api/customers/import-template`, that:
  - Loads all customer field headers (same list as in Part C).
  - Loads products: `Product::orderBy('name')->get(['id', 'name'])`.
  - Builds a CSV (or Excel) with:
    - Row 1: customer headers + product names (as column headers).
    - Row 2 (optional): example row (empty or sample values).
  - Returns the file for download (e.g. `customers_import_template.csv`).
- Alternatively, the frontend can call `GET /api/products` (or a dedicated lightweight endpoint that returns product names/ids), then build the template in the browser and trigger download; either way, the **template is driven by products in the DB**.

**Frontend**

- In [resources/js/components/ImportModal.vue](resources/js/components/ImportModal.vue) (or nearby):
  - Add a “Download template” (or “Download import sheet”) button.
  - On click: either call `GET /api/customers/import-template` and download the file, or fetch products, build the header row (customer fields + product names), generate CSV/Excel and download. The downloaded sheet must include one column per product currently in the DB.

**Import processing (when user uploads a filled sheet)**

- Backend `import()` already parses the first row as headers. Extend it to:
  - Build the **customer** field map as in Part C (all customer columns + birthday + category).
  - After building `$customerData`, detect **product columns**: any header that matches a product name (or `Product: {name}`) in the DB. For each such column, read the cell value for the current row.
  - **Semantics of product column value** (choose one convention and document it):
    - **Option A (recommended):**  
      - Empty = no lead/item for that product.  
      - Non-empty numeric (e.g. `1`, `2`, `5`) = **purchased** quantity → create (or attach to) a **won** lead and a **won** lead_item for that customer and product with that quantity (and a default or 0 unit_price if not provided).  
      - Non-empty non-numeric (e.g. `Y`, `Yes`, `1` as text) = **prospect** only → create a **follow_up** lead and a **pending** lead_item for that customer and product.
    - **Option B:** One column per product = “prospect” (Y/1 = interested). A separate set of columns “Purchased: {Product A}” could be added for quantity purchased; then template has twice as many product-related columns.
- For each row: create the **customer** first (as today), then for each product column that has a value, create the appropriate **lead** and **lead_item** (linking customer_id, product_id, stage, status, quantity/unit_price as per convention). Reuse existing Lead/LeadItem creation logic where possible; avoid duplicates (e.g. same customer + product + stage).

**Summary**

- **Template**: Generated from DB → customer columns + **one column per product** (e.g. 5 products ⇒ 5 extra columns in the import sheet).
- **Download**: Backend endpoint or frontend builds CSV/Excel from products list; user downloads and fills.
- **Upload**: Backend parses customer fields + product columns; for each product column with a value, creates lead/lead_item (prospect or purchased by convention). No data loss; existing customers/leads untouched except where the import adds new records.

---

## Part D: Categories – clear picture (Prospects vs Customers, leads, purchases)

You want to **categorize** and see:

1. **Prospects** vs **Customers** (tabs).
2. **Prospect** → their **leads** (which products they’re interested in).
3. **Customer** → **what they have purchased** (won products/items).

**1 and 2 and 3** are achieved by:

- **Prospects tab**: List of people with no won lead; on detail, show “Prospect for: [Product A, Product B]” from their **non-won** leads and lead items (already in DB: `customer → leads → product_id` and `lead_items.product_id`).
- **Customers tab**: List of people with at least one won lead; on detail, show “Purchased: [Product X, Product Y]” (or “What they have purchased”) from **won** leads and won items (already in DB).
- No new tables for this; only query and UI changes.

**Optional: Customer category (tag/segment)**

- If you also want a **label** to categorize prospects/customers (e.g. “VIP”, “Retail”, “Wholesale”):
  - **Migration**: Add nullable `category` (or `customer_category`) string column to `customers`. Additive only.
  - **Model**: Add to `$fillable`.
  - **API**: Accept `category` in create/update and in import (Part C).
  - **UI**: Add category dropdown or text field in customer form; show in list and detail; optional filter on list (e.g. filter by category in addition to prospect/customer).

This gives you:

- **Prospects** → tab + “Prospect for [products]” (from leads).
- **Customers** → tab + “What they have purchased” (from won items).
- Optional **category** for segmenting/tagging.

---

## Part G: Assignment logs and visibility (customer / prospect and lead)

When someone assigns a customer/prospect or a lead to another user, there must be **logs** of who assigned whom and when. The **assigning person** (and the assignee) should be able to see that data. When the **assigned person** does any action (follow-up, stage change, activity), that should be visible so the assigner can see what was done.

**1. Customer / prospect assignment logs**

- **Data today**: [CustomerUserAssignment](app/Modules/CRM/Models/CustomerUserAssignment.php) stores `customer_id`, `user_id`, `assigned_by`, `assigned_at`, `notes`. So “who assigned whom” is already stored. [HasAuditLog](app/Traits/HasAuditLog.php) on this model may log create/delete to [AuditLog](app/Models/AuditLog.php).
- **Expose as “assignment log”**: Add an API (e.g. on customer show or a dedicated endpoint) that returns **assignment history** for a customer: list of assignments (who was assigned, who assigned, when, notes). Include current assignments from `customer_user_assignments`; optionally include past unassigns from `audit_logs` (where auditable_type = CustomerUserAssignment, action = deleted) so “unassigned on date by X” is visible.
- **UI**: On customer/prospect detail page, show an **Assignment log** section: “Assigned to: [User A] by [User B] on [date]”, and history of changes. The **assigning person** sees this when they open the customer; the **assignee** sees it too.

**2. Lead assignment logs**

- **Data today**: Lead has `assigned_to` (current assignee). When it changes, [LeadController](app/Modules/CRM/Http/Controllers/LeadController.php) fires `NewLeadAssigned`. [Lead](app/Modules/CRM/Models/Lead.php) uses HasAuditLog, so **updates** (including `assigned_to` changes) are stored in `audit_logs` (old_values, new_values, user_id).
- **Expose as “assignment log”**: For a lead, return **assignment history**: e.g. query `AuditLog` where `auditable_type` = Lead, `auditable_id` = lead id, and where `new_values` / `old_values` contain `assigned_to`; or add a dedicated **lead_assignment_logs** table (lead_id, previous_assigned_to, new_assigned_to, assigned_by, assigned_at) populated when `assigned_to` changes for clearer querying and display. Prefer dedicated table if audit_log payload is complex to parse.
- **UI**: On lead detail (and optionally on customer detail for each lead), show **Lead assignment log**: “Assigned to [User B] by [User A] on [date]”, and previous assignees. So the person who assigned the lead can see “I assigned this lead to John on date”.

**3. Assigning person still sees the lead (with logs)**

- **Problem**: When a lead is assigned to someone else, it disappears from the assigner’s dashboard (because list is filtered by `assigned_to = current user`).
- **Fix**: Give the assigner a way to see leads they **assigned to others**:
  - **Option A**: Add a filter or section “Leads I assigned” (leads where current user is in assignment history as “assigned_by” or previous assignee). Backend: e.g. `GET /api/leads?assigned_by_me=1` returning leads that the current user assigned to someone else (from lead_assignment_logs or audit_logs).
  - **Option B**: Same via a dedicated “Assignment history” or “My assignments” page: list of “I assigned [Lead X] to [User] on [date]” with link to lead. Either way, the assigning person can open the lead and see the assignment log + assignee actions.
- **UI**: Dashboard or leads list: tab/filter “Leads I assigned” or link to “My assignment history”. From there, open lead → see assignment log + timeline (assignee actions).

**4. Assignee actions visible to assigner**

- **Data today**: [LeadActivity](app/Modules/CRM/Models/LeadActivity.php) stores lead_id, user_id, type, description, meta (follow-up, stage change, appointment, etc.). So “assignee did X” is already recorded.
- **Expose**: On lead detail (and customer timeline), the **timeline** already includes activities (with user). Ensure the timeline is visible to the **assigning person** when they open the lead (same as assignee): i.e. lead detail is accessible to users who assigned it (or are in assignment history), not only to current `assigned_to`. So in [LeadController](app/Modules/CRM/Http/Controllers/LeadController.php) `show()`, allow access if `assigned_to === user` OR user is in lead assignment history (assigned_by or previous assignee).
- **UI**: On lead/customer detail, keep or add a clear **Activity / Timeline** section: “User X did: follow-up on date”, “User X changed stage to Won”, etc. Assigning person sees this when they open the lead.

**Summary for Part G**

| What | How |
|------|-----|
| Customer assignment log | API + UI: list who was assigned, by whom, when (from customer_user_assignments + optional audit for unassigns). |
| Lead assignment log | Store lead assignment changes (dedicated table or audit_log); API + UI: “Assigned to X by Y on date”. |
| Assigning person sees lead | “Leads I assigned” filter/page and allow lead detail access for assigner (and assignee). |
| Assignee actions | Timeline/activities on lead (and customer) visible to assigner when they open the lead; use existing LeadActivity. |

No destructive changes; only additive (e.g. lead_assignment_logs table if used, and new API/UI).

---

## Part E: Data safety (live environment)

- **Migrations**: Only **add** columns (`birthday`, optionally `category`). No renames, no drops, no changing primary keys.
- **Import**: Import only **inserts** or updates (if you later add “update by phone”); do not delete or truncate.
- **Prospect/Customer**: Derived from existing `leads.stage`; no migration required for that. Optional: add `has_won_lead` boolean later for performance; backfill from current leads.

---

## Summary of changes

| Area | Change |
|------|--------|
| **Birthday** | Migration add `birthday` (date, nullable); model fillable/casts; API; forms; detail/list; import. |
| **Import** | Backend: extend `$fieldMap` to all customer fields + birthday (+ category if added); parse dates. Frontend: extend optional/required fields list and help text. |
| **Import template from products** | Template = customer columns + one column per product (from DB). Download endpoint or frontend builds CSV/Excel; upload parses product columns and creates leads/lead items (prospect or purchased by convention). |
| **Categories (picture)** | Prospects tab + “Prospect for: [products]”. Customers tab + “Purchased: [products]”. Use existing leads/items. |
| **Optional category** | Migration add `category`; model; API; form; list/detail/filter; import. |
| **Prospect vs Customer** | As in Part A (type filter, tabs, email variables, detail). |
| **Assignment logs and visibility** | Customer/lead assignment history (who assigned whom, when); “Leads I assigned” so assigner still sees the lead; assignee actions (timeline) visible to assigner; access rules so assigner can open lead. |

This keeps the picture clear: **prospects** (and their leads/products) vs **customers** (and what they have purchased), with **full import** including **birthday**, optional **category**, and a **product-based import template** (one column per product in DB), plus **assignment logs** (customer/lead) and **visibility** for the assigning person (logs + “Leads I assigned” + assignee actions on timeline), without losing data.
