# Reporting Redesign Plan

**Status:** decision document, not a menu of options.
**Scope:** the Insights nav group and the views it points at. No backend edits here — backend changes are listed as work items W1–W8.
**Principle:** a manager opens this on Monday to answer two questions — *are we ahead or behind*, and *who needs help*. Plus a CEO's month-end *what did we sell, and what did it cost*. Anything that serves none of those is cut.

---

## 1. The audit — 16 entry points

### Insights nav (10)

| Nav label | Component | Endpoint | Duplicates |
|---|---|---|---|
| My Report | `views/report/ReportMyReportView.vue` | `reporting/employee-self-report` | **Strict subset of Employee Performance** — `getEmployeePerformanceOverview()` calls `getEmployeeSelfReport()` internally |
| Business Reports | `views/ReportsView.vue` (967 lines) | 9 parallel calls | Container for most duplication |
| Geo map | `views/GeoMapView.vue` | `reporting/geo` | None — but it's a *lens*, not a report: no date filter, no totals |
| Today's Report | `views/TodaysReportView.vue` | `daily-activities/todays-report` | **Name collision only** — see §1b |
| Target vs Achievement | `views/report/ReportTargetAchievementView.vue` | `reporting/target-vs-achievement` | Overlaps the Team tab — same people, same month, different numbers on screen |
| Products by Employee | `views/report/ReportProductsByEmployeeView.vue` | `reporting/products-sold-by-employee` | **Exact duplicate** of the table inside the Employee tab |
| Employee Performance | `views/report/ReportEmployeePerformanceView.vue` | `reporting/employee-performance-overview` | **Duplicate of the Employee tab** |
| Salary Reports | `views/SalaryReportsView.vue` | `hr/salaries/report` | None (cost side) |
| Expense Report | `views/ExpensesMonthlyReportView.vue` | `hr/expenses/report/monthly` | None (cost side) |
| Commission Reports | `views/CommissionReportView.vue` | `commission-management/report` | None. **Not read-only** — sends PDFs to staff |

### Business Reports tabs (6)

| Tab | Duplicates |
|---|---|
| Overview | Internally contradictory — see §3 |
| Team | Overlaps Target vs Achievement and the Pipeline tab |
| Employee | **= Employee Performance + Products by Employee combined** |
| Pipeline | Same endpoint as Team, different column subset — two tables of the same rows |
| Products & Revenue | Mislabelled: contains no product data. A third employee table |
| Today | Name-collides with Today's Report |

### 1b. Correction to the original brief

*Today's Report ↔ Today tab* is **not** a data duplicate:

- **Today's Report** — who did what today, per person, including attendance hours. A backwards-looking activity audit.
- **Today tab** — today's outstanding follow-ups by agent, plus recent visits. A forwards-looking worklist.

Different reports with confusingly similar names. Fix is **rename + merge**, not deletion.

### 1c. Findings not in the brief

- **`reporting/funnel` is fetched and never rendered.** One wasted round-trip per page load, and `lost_reasons` — genuinely useful — is discarded.
- **`reporting/sales-performance` is fetched and never rendered.** The only time-series the system produces.
- **`getProductPerformance()` exists, works, and no frontend calls it.** Returns per-product units / revenue / **margin**, plus category totals and an unattributed-lines count. The one CEO-grade dataset in the codebase is unreachable from the UI.
- **Conversion rate mixes cohorts** — leads counted by `created_at`, wins by `closed_at`, then divided. Can exceed 100% in a quiet month. (W2)
- **`pipeline_value` means two different things.** The Overview card labelled "Pipeline Value" sums *all* leads including lost ones.

> **Already fixed on this branch:** `getRevenueByEmployee()` returned `leadRevenue + invoiceRevenue`, double-counting any won-and-invoiced deal, so employee rows summed to more than the company headline above them. De-duplicated, with tests.

---

## 2. The proposed structure — five reports

| | Who | Question it answers |
|---|---|---|
| **R1 Performance** | CEO, Sales Manager | Are we ahead or behind, and who needs help? |
| **R2 Employee** | Manager (anyone), rep (self) | What has this person sold, and are they on track? |
| **R3 Products & Territory** | CEO | What are we selling, at what margin, and where? |
| **R4 Daily activity** | Manager, mid-week | What did the team do today, what's outstanding? |
| **R5 Costs & payouts** | Owner / finance | What did this month cost us? |

R1 replaces four surfaces (Overview, Team, Pipeline, Target vs Achievement) that each answered half a question and disagreed on the numbers. "My Report" is R2 with `agent_id` forced to the current user — not a second component.

**Rejected deliberately:** a standalone funnel report, a communications report, a standalone geo page.

---

## 3. Metric definitions

### Revenue — settled

Three candidates exist:
- **A — lead-won value:** sales-side truth, "we closed it"
- **B — invoiced value:** finance-side truth, "we billed it"
- **C — de-duplicated total:** A excluding invoiced leads, plus B

**Decision: C is the headline, named "Revenue". B is the secondary, named "Billed".**

A alone undercounts (a sale invoiced without a won lead line never appears). B alone undercounts (much won business is never invoiced here). C counts every sale exactly once and is already computed correctly.

B earns its place not as a rival total but as a **quality signal** — shown as *"of which billed £X (Y%)"*. A falling billed share is a real problem, and it is invisible today because the two sit side by side as equals.

**A is retired from every headline.** It survives only as a leaderboard column named **"Won value"**, where targets and commission need the sales-side figure.

### Pipeline — settled

The Overview's `PIPELINE VALUE` sums all leads **including lost ones**. A pipeline number that includes deals you already lost is not a pipeline number. **Delete it.** Only `OPEN PIPELINE` survives, renamed **"Open pipeline"** everywhere.

### Dictionary — each line becomes caption text under its metric

| Metric | Definition for a non-technical manager |
|---|---|
| Revenue | All sales this period, counted once each — invoiced where an invoice exists, won deal value where it does not. |
| Billed | The part of Revenue with an invoice raised, in £ and as a % of Revenue. |
| Won value | Value of product lines this person closed, invoiced or not. Used for targets and commission. |
| Margin | Sale price minus cost, for products sold in the period. |
| Cost of sales | Salaries + expenses + commission paid in the month. |
| Open pipeline | Deals still live — not won, not lost. |
| New opportunities | Leads created in this period. |
| Products won | Product lines closed as won. Counts lines, not deals. |
| Deals won | Leads that reached Won. One deal can hold several won products. |
| Conversion | Of leads created this period, the share since won. |
| Target / Achieved / Attainment | What they were set, what they did, and the % — under 80% red, 80–99% amber, 100%+ green. |

**Naming rules.** "Won Products" and "Won" never appear on one screen without qualifiers. **"Sales Revenue" and "Billed Revenue" as sibling cards are banned.** Every stat card gets a caption — a number with no definition does not ship.

---

## 4. What gets deleted or merged

**Deleted** (route redirected, not removed):
- `views/report/ReportTargetAchievementView.vue` → R1 columns
- `views/report/ReportEmployeePerformanceView.vue` → R2
- `views/report/ReportProductsByEmployeeView.vue` → R2
- `views/report/ReportMyReportView.vue` → R2 scoped to self

**Merged into a shell:**
- `GeoMapView.vue` → tab in R3
- `SalaryReportsView.vue`, `ExpensesMonthlyReportView.vue`, `CommissionReportView.vue` → tabs in R5 (PDF/send actions unchanged)

**Restructured:**
- `ReportsView.vue` — tabs removed, becomes R1 only. Target ~350 lines, from 967.
- `TodaysReportView.vue` — becomes R4, absorbing the old Today tab's two panels.

**Redirects — do not delete these paths.** Staff have bookmarks and emailed links:

| Old | New |
|---|---|
| `/report/target-achievement` | `/reports` |
| `/report/employee-performance` | `/reports/employee` (preserve `?employee_id` / `?agent_id`) |
| `/report/products-by-employee` | `/reports/employee` |
| `/report/my-report` | `/reports/my` |
| `/reports/geo` | `/reports/products?view=map` |
| `/salaries/reports`, `/expenses/monthly-report`, `/commission/report` | `/reports/costs?tab=…` |
| `/todays-report` | **keep** — deep-linked from notifications |

---

## 5. The new Insights nav

Replaces the current ten rows:

```
Insights
  1. My performance    /reports/my         — all users
  2. Performance       /reports            — Admin / Manager / System Admin
  3. Employee          /reports/employee   — Admin / Manager / System Admin
  4. Products          /reports/products   — Admin / Manager / System Admin
  5. Daily activity    /todays-report      — Admin / Manager / System Admin
  6. Costs & payouts   /reports/costs      — Admin / System Admin
```

Six rows, down from ten. A rep sees one. Order is deliberate: *how are we doing → who → what → today → what it cost*.

`NavSections` keys are unchanged, so existing per-user nav permissions keep working without a migration.

---

## 6. Screen layouts

### R1 — Performance (`/reports`)

**Above the fold, four cards only:**
1. **Revenue** — caption *"of which billed £X (Y%)"*
2. **Attainment** — company achieved ÷ target, with progress bar. Caption *"£X of £Y, day N of M"*
3. **Open pipeline** — caption *"N deals still live"*
4. **Products won** — with an up/down delta vs last period

**Primary chart:** revenue over the period with a **pace line** — cumulative actual against pro-rata target. The single visual that answers "ahead or behind" at a glance.

**Leaderboard**, sorted by attainment, RAG-coloured:

| Employee | Target | Achieved | Attainment | Products won | Revenue | Open pipeline | Conversion | Appointments |
|---|---|---|---|---|---|---|---|---|

Row click → R2. This one table replaces Team + Pipeline + Products & Revenue + Target vs Achievement.

**Below:** pipeline stages · **lost reasons** (new — data was being discarded) · activity roll-up.

### R2 — Employee (`/reports/employee`, `/reports/my`)

Name, rank badge, three attainment tiles. **No chart** — a chart of one person's month is noise; progress bars are the right visual. Tables: won lines this month · last week (collapsed) · product/category targets.

### R3 — Products (`/reports/products`)

Cards: Revenue · Units · **Margin** (never shown in the UI before — the reason this page exists). Chart: top 10 by revenue with margin % labelled, so high-revenue/low-margin is visually obvious. Table sortable on every column. `view=map` tab renders the existing Leaflet map with the date filter applied (W6). Surface `unattributed_invoice_lines` when non-zero.

### R4 — Daily activity (`/todays-report`)

**Above the fold:** outstanding follow-ups by agent with the Log action — the only actionable thing in Insights, so it goes at the top — plus recent visits. Below: the existing activity matrix, unchanged. No chart; this is a worklist.

### R5 — Costs & payouts (`/reports/costs`)

One roll-up card: **Total cost of sales**, caption *"Salaries £X · Expenses £Y · Commission £Z"*. Three tabs rendering the existing components unchanged. Chart: 12-month stacked split (W5).

---

## 7. Migration — risk-ordered

| Step | Work | Risk |
|---|---|---|
| 1 | ~~Fix leaderboard revenue~~ **done on this branch** | — |
| 2 | Stop fetching `funnel` and `sales-performance`, or render them | none |
| 3 | **Rename and re-caption only** — delete the "Pipeline Value" card, rename Sales Revenue → Revenue, make Billed a caption, add §3 captions | very low — delivers most of the "confusing" complaint immediately |
| 4 | Nav consolidation **with redirects**, before deleting any component | low, fully reversible |
| 5 | Build R1 by flattening `ReportsView.vue` | medium — the big one. Needs W2, W3, W4 |
| 6 | Build R2, then delete three components **after** verifying numbers match side by side | medium |
| 7 | Build R3 — purely additive, touches nothing existing. Needs W6 | medium |
| 8 | Build R4. Needs W8 | low |
| 9 | Build R5 last — tab shell around unchanged components. Needs W5, W7. **Regression-test the PDF-send actions** — the only write actions in Insights | highest risk, lowest value |

### Backend work items

| ID | Change | Step |
|---|---|---|
| W1 | ~~De-duplicate `getRevenueByEmployee()`~~ **done** | — |
| W2 | Cohort-correct conversion rate | 5 |
| W3 | `getSalesPerformance()` returns revenue per bucket, not just counts | 5 |
| W4 | Fold target-vs-achievement into the `agents` payload | 5 |
| W5 | New `cost-of-sales` endpoint | 9 |
| W6 | `getGeoAnalytics()` accepts a date range | 7 |
| W7 | Combined nav visibility for the three cost sections | 9 |
| W8 | Role-gate the GPT report button (currently two hardcoded email addresses) | 8 |

Optional follow-up: collapse R1's nine parallel requests into one endpoint — but only after Steps 5–6 settle which fields are actually needed.

---

## 8. Open questions — flagged, not guessed

1. **Is a target set for every rep every month?** The RAG leaderboard only works if targets are populated. If `total_employees_with_targets` is well below headcount, the attainment column will be mostly blank and R1 needs a fallback ranking.
2. **Does commission pay on won value or invoiced value?** §3 assumes won value. If it pays on invoiced, the leaderboard column must say so. Not traced far enough to answer.
3. **Should R5 live in Insights at all, or move to People & money?** Merging is right either way; placement is a separate IA question.
