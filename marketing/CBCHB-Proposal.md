# Proposal for the Cameroon Baptist Convention Health Services (CBCHS / CBCHB)

## An Integrated Pharmacy & Healthcare Operations Management System

**Prepared by:** I-NNOVA / [Company legal name]
**For:** The Cameroon Baptist Convention Health Services — Health Board
**Date:** June 2026
**Languages of delivery:** English & French (Français)
**Document version:** 1.0 — Draft for internal review

---

> *"To care for the sick is the work of God. To do it well is the work of good management."*

---

## 0. How to read this document

This proposal is organized in three layers:

1. **What we deliver in Phase 1 — Pharmacy Operations.** Everything in Section 4 is built, tested and ready to deploy. This is what we are committing to put live in your pharmacies within the timelines proposed in Section 11.
2. **What we recommend as Phase 2 — Hospital-Wide Operations & Finance.** Section 5 describes how the same platform extends to manage your wider hospital network, finance department and donor reporting. These modules are part of our roadmap and will be co-designed with CBCHS during Phase 1, then delivered under Phase 2 of the engagement.
3. **What we deliver continuously — Support, Training, Compliance, Growth.** Sections 10–13 describe the long-term partnership.

We are not asking CBCHS to take a leap of faith on Phase 2. We propose a **phased contract** where Phase 1 must deliver measurable results before Phase 2 begins.

---

## 1. Executive Summary

The Cameroon Baptist Convention Health Services (CBCHS) is one of the largest faith-based healthcare providers in Cameroon, serving hundreds of thousands of patients each year across a network of **hospitals, integrated health centres, specialized programs and a central pharmacy / drug supply unit**. Managing such a network demands more than a point-of-sale tool — it demands an **integrated platform** that can track every tablet from procurement to patient, account for every franc transparently to the Convention Board and to international donors, and give every facility manager **real-time visibility** into operations.

**I-NNOVA Pharmacy & Health Operations** is that platform.

Built on a battle-tested point-of-sale and inventory engine, locally customized for African health-care realities, and extended with a **full double-entry accounting module**, the system is designed to:

- **Eliminate stock-outs and expired-drug losses** across every pharmacy through real-time stock visibility and First-Expiry-First-Out (FEFO) dispensing.
- **Capture every dispensing event** — patient, prescriber, batch, expiry, price — for clinical, regulatory and audit purposes.
- **Track every franc** through a transparent, double-entry General Ledger that produces audit-grade financial statements on demand.
- **Manage insurance and donor-funded dispensing** with the same discipline as cash sales — split-payment workflows, third-party billing, and donor fund utilization reports.
- **Consolidate financial and operational performance** of the entire CBCHS pharmacy network into a single Head Office dashboard, with drill-down to any branch, day or transaction.
- **Replace fragmented legacy systems** with one bilingual platform (EN/FR), deployable **on-premise at CBCHS headquarters** or **in a secure cloud environment**, at your choice.

The proposal that follows describes (a) the **Pharmacy Operations** scope we will deliver in **Phase 1**, (b) the **Hospital-Wide & Finance** capabilities we recommend as **Phase 2** to manage the entire CBCHS network from one platform, and (c) the **partnership, training and support model** that surrounds both.

---

## 2. Our Understanding of CBCHS Operations

A serious proposal begins with proof that we understand your world. The following is our read of CBCHS pharmacy and finance operations — we welcome correction during the discovery phase.

### 2.1 The pharmacy network

- A **central pharmacy / drug supply organization** at headquarters procuring from local distributors and international suppliers (UNICEF, IDA Foundation, partner-supplied donations).
- **Hospital-attached pharmacies** at each main hospital (Banso, Mbingo, Mutengene, Nkwen and others), each serving outpatient walk-ins, discharged inpatients, and ward stockrooms.
- **Pharmacy points / dispensaries** at integrated health centres and program sites across the country.
- A constant flow of **inter-facility transfers** from central → hospital → ward → patient.

### 2.2 The financial reality

- A mix of revenue streams: **cash-paying patients, credit / ledger patients, insurance (CNPS, private insurers), and donor-funded free-of-charge dispensing** (ARVs, TB drugs, family planning, vaccines).
- **Donations in kind** that have zero acquisition cost but must be quantified, valued and reported.
- **Foreign-currency procurement** (USD, EUR) settling in XAF, exposing CBCHS to exchange-rate fluctuations.
- **Donor reporting obligations** to faith-based partners, bilateral agencies and possibly multilaterals — each with its own template, frequency and audit expectation.
- **Statutory reporting** to the Cameroon Ministry of Public Health and to OHADA-compliant accounting standards.
- A **board-governance culture** that values transparency, internal controls, and an audit trail that survives turnover of staff.

### 2.3 The pain points we typically see in networks of this size

1. **Stock blindness** — head office cannot see, in real time, what each pharmacy holds.
2. **Expiry losses** — drugs expire on shelves because there is no FEFO discipline or expiry alerting.
3. **Cash leakage** — manual tills and paper receipts make daily reconciliation slow and error-prone.
4. **Delayed financial close** — monthly accounts arrive late, often after the board needs them.
5. **Audit pain** — donor audits consume weeks of finance-team time chasing supporting documents.
6. **Fragmented data** — pharmacy data, sales data, supplier data, accounting data live in different systems (or spreadsheets) and never agree.
7. **Insurance leakage** — third-party claims poorly tracked, money written off because no one followed up.
8. **Donor-fund opacity** — hard to prove to a donor that $50,000 earmarked for ARVs was actually spent on ARVs.

I-NNOVA addresses each of these directly. Sections 4 and 5 describe how.

---

## 3. Solution Architecture

> *"One brain. Many sites. Live sync."*

### 3.1 Deployment options — CBCHS chooses

I-NNOVA can be deployed in two ways. The proposal price covers either model.

**Option A — On-premise at CBCHS Headquarters**
- A production server installed at the CBCHS HQ data centre.
- Each pharmacy / facility connects through the CBCHS WAN or a VPN.
- Daily encrypted backups to a local NAS + offsite (mirror) location.
- Recommended for organisations that wish to keep all data physically within their walls.

**Option B — Secure private cloud (hosted by I-NNOVA)**
- Production environment on a dedicated virtual server in a Tier-3 data centre, with hourly snapshots and daily off-site backups.
- Each pharmacy connects over the public internet using HTTPS.
- 99.5% uptime SLA.
- Recommended for organisations that prefer to outsource infrastructure operations.

**Option C — Hybrid (recommended)**
- Primary on-prem deployment at HQ.
- Each branch caches read-only data locally so it keeps working during internet outages.
- Cloud mirror as live disaster-recovery site.
- This is the deployment model we recommend for CBCHS given the geographic spread of facilities and the variable internet connectivity across the country.

### 3.2 What runs where

```
┌─────────────────────────────────────────────────────────────────┐
│  CBCHS HEADQUARTERS                                             │
│  ┌──────────────────┐    ┌──────────────────────┐               │
│  │  I-NNOVA Server  │◄──►│  Central Pharmacy    │  Procurement, │
│  │  (Production DB) │    │  Module              │  Suppliers,   │
│  └────────┬─────────┘    └──────────────────────┘  Transfers    │
│           │                                                     │
│           ▼                                                     │
│  ┌──────────────────┐    ┌──────────────────────┐               │
│  │  Accounting &    │    │  Head Office         │  Real-time    │
│  │  Finance Module  │    │  Dashboard           │  visibility   │
│  └──────────────────┘    └──────────────────────┘  to all sites │
└─────────────────────────────────────────────────────────────────┘
            │                       │
            ▼                       ▼
┌───────────────────────┐   ┌───────────────────────┐
│ HOSPITAL PHARMACY     │   │ HEALTH CENTRE         │
│ (per hospital)        │   │ (per centre)          │
│ • POS counters        │   │ • POS counter         │
│ • Ward stockrooms     │   │ • Local stockroom     │
│ • Cashier till        │   │ • Cashier till        │
│ • Cold-chain storage  │   │ • Offline cache       │
└───────────────────────┘   └───────────────────────┘
```

### 3.3 Devices supported

- Modern Windows / Linux PCs and laptops (preferred for the cashier station).
- Android tablets for ward stock-take, mobile inventory count and supervisor walk-throughs.
- USB barcode scanners, thermal receipt printers (58mm / 80mm), cash drawers, weighing scales, A4/A5 printers for prescriptions and invoices.

### 3.4 Languages

The user interface is delivered in **English** and **French**. Each user chooses their language. Printed receipts, invoices and reports respect the user's language setting. All training materials and documentation are produced in both languages.

---

## 4. Phase 1 — Pharmacy Operations (Primary Scope)

Phase 1 covers everything required to run **every CBCHS pharmacy point** — central, hospital-attached, and health-centre — on I-NNOVA, with full visibility from Head Office.

### 4.1 Master data & catalogue

- **Unified drug catalogue** maintained centrally, pushed to every branch. INN name, brand name, dosage form, strength, pack size, manufacturer, ATC class, controlled-substance flag.
- **Multi-unit pricing** — sell the same drug by tab, strip, pack or box at price points your pharmacist sets. The system enforces unit conversions automatically.
- **Selling price groups** — different prices for cash patients, NHIS/insured patients, staff, charity cases. The right price is chosen automatically based on the patient category at the till.
- **Barcode / SKU** — works with any USB scanner; system can also generate barcodes for repackaged items.

### 4.2 Procurement & supplier management

- **Purchase orders** to local and international suppliers, with multi-currency support (XAF / USD / EUR).
- **Goods received notes** with batch number, manufacturing date, expiry date, supplier invoice reference.
- **Supplier ledger** — every invoice, every payment, every credit note, with current balance owed visible at any time.
- **Donations in kind** received at zero cost but quantified for stock and donor reports.
- **Purchase approvals workflow** *(custom build included in scope)* — requester → pharmacy manager → finance officer, with amount thresholds set by CBCHS.

### 4.3 Inventory & batch / expiry management

This is the heart of pharmacy operations and the area where I-NNOVA goes well beyond a generic POS.

- **Receive by batch** — every receipt records batch number, manufacturing date, expiry date, supplier, cost.
- **FEFO dispensing** *(custom enhancement included in scope)* — when a cashier dispenses a drug, the system automatically selects the batch closest to expiry first, not just the oldest purchase.
- **Per-location stock** — every pharmacy, every ward stockroom, every cold-chain fridge can be a separate stock location. Transfers between them are tracked end-to-end.
- **Expiry alerts** *(custom dashboard included in scope)* — daily dashboard of items expiring in 30 / 60 / 90 days, with single-click stock-transfer or write-off action.
- **Cold-chain location tagging** — vaccines, insulin and other temperature-sensitive items can be flagged so they are only assigned to cold-chain storage points.
- **Cycle counting & inventory count** — section-by-section counts without freezing operations, with variance reports for management review.
- **Stock adjustments** with mandatory reason codes (expired, damaged, donated, theft, recount) — every adjustment is logged with the user, time and reason.
- **Re-order point alerts** — per branch, per drug, configurable, surfaced on the manager dashboard.

### 4.4 Dispensing & point of sale

- **Sub-second item lookup** on catalogues of 10,000+ SKUs.
- **Patient capture** — name, age, sex, patient ID, phone number, optionally linked to a permanent patient record.
- **Prescription capture** *(custom build included in scope)* — prescriber name, prescription reference number, diagnosis (ICD-10 or free text), dosage instructions printed on the label.
- **Multiple payment methods on one sale** — cash + mobile money + insurance copay, in any combination.
- **Insurance / third-party billing** *(custom build included in scope)* — patient pays the copay now, the balance is automatically posted to the insurer's account as a receivable, ready for the monthly claim file.
- **Free / donor-funded dispensing** *(custom build included in scope)* — drugs given free at the till still post their consumption value to a "Donor Program Expense" account so donor reports can prove fund utilization.
- **Returns and refunds** with full audit trail and stock re-instatement.
- **Receipts** printed on thermal printer (default), A5 or A4 if needed, or sent by email / WhatsApp.

### 4.5 Cash management

- **Per-cashier till opening and closing** — opening float declared at start of shift, closing cash counted by denomination at end of shift, system computes expected vs. actual and flags variances.
- **Cash drops and deposits** — partial cash removals during long shifts, with two-signature workflow.
- **Multi-tender reconciliation** — at end of day, cash, mobile money and card / POS terminal totals are each reconciled separately.
- **Petty-cash tracking** — small disbursements at branch level, tied back to expense categories.

### 4.6 Multi-branch & inter-facility transfers

- **Stock transfer requests** between facilities, with central pharmacy or hospital pharmacy approval before dispatch.
- **In-transit visibility** — stock leaves source location, sits in "in transit", arrives at destination, and only then is added to receiving stock. No phantom inventory.
- **Inter-facility consumption visibility** — head office can see, per drug, where it was received, where it was transferred, and where it was dispensed.

### 4.7 Pharmacy reports library (Phase 1)

A non-exhaustive list of reports delivered out of the box. Every report can be filtered by date range, branch, drug category, cashier and exported to PDF, Excel or CSV, and printed.

**Operational**
1. Daily Sales Summary by Branch and Cashier
2. Sales by Product (top movers / slow movers)
3. Sales by Patient (for credit and chronic-care patients)
4. Sales by Prescriber
5. Hourly Sales Heatmap (staffing optimisation)
6. Cashier Shift Reconciliation Report
7. Receipt Reprint Audit Log

**Stock**
8. Current Stock by Branch / by Category
9. **Stock Expiry Forecast — 30 / 60 / 90 / 180 days**
10. **FEFO Compliance Report** — proves expired stock would have been dispensed first
11. Stock Movement (in, out, transferred, adjusted) per drug
12. Inter-facility Transfer Register
13. Re-order Suggestion List
14. Stock Adjustment Reasons Summary
15. Inventory Count Variance Report
16. Slow-Moving / Dead-Stock Report
17. Cold-Chain Stock Position
18. Stock Valuation (FIFO / FEFO basis)

**Financial / pharmacy-management**
19. Gross Margin by Drug / by Category / by Branch
20. Insurance Claims Outstanding (per insurer)
21. Donor-Funded Dispensing Report (per program / per donor / per period)
22. Patient Credit Ledger (outstanding patient debts)
23. Supplier Payables Ageing
24. Purchase Price Variance (purchases above the standard cost)
25. Wastage / Expiry Loss Report (in units and in monetary value)

**Compliance & audit**
26. Controlled Substances Dispensing Register
27. Prescription Audit Log (every prescription, every dispensing)
28. Cash Discrepancy Log (cashier overs and shorts)
29. User Activity Log (who did what, when, from where)
30. Data Change Audit (every change to price, quantity, master data, with old/new values)

### 4.8 Internal controls built into the system

Faith-based and donor-funded organisations are judged on internal controls as much as on results. I-NNOVA is built around the following principles by default:

- **Segregation of duties** — the user who creates a purchase order cannot approve it; the user who receives stock cannot adjust it without a second approval; the user who dispenses cannot reverse the sale.
- **Role-based access** — each user sees only the screens and data they need. Roles are bilingual (EN/FR), pre-built for cashier, pharmacist, branch manager, finance officer, internal auditor, head-office viewer, super-administrator. Custom roles are easy.
- **Approval workflows** with amount thresholds for purchase orders, stock adjustments, write-offs and refunds.
- **Immutable audit trail** — financial transactions cannot be deleted; they can only be reversed by a counter-entry, preserving the audit trail.
- **Data change log** — every change to a price, quantity or master record records who, when, old value, new value, and reason.
- **Mandatory reason codes** for stock adjustments, sale returns, and price overrides.
- **Daily automated backups** retained for 90 days, plus monthly archives retained for 7 years.

---

## 5. Phase 2 — Hospital-Wide Operations & Finance (Recommended Extension)

> *Phase 2 is offered as a recommendation. CBCHS may adopt it in full, in part, or postpone it. We propose to co-design Phase 2 with the CBCHS Finance Director and Director of Operations during the second half of Phase 1.*

Pharmacy operations are the entry point. The same I-NNOVA platform can extend to manage **the rest of the hospital and the entire Convention health network** from one connected system.

### 5.1 Hospital cashier & patient billing

- Outpatient consultation fees, laboratory tests, imaging, procedures, hospitalisation charges — all sold through the same till system with the same audit discipline.
- **Patient master record** unifying every consultation, every prescription, every payment across all CBCHS facilities — one patient ID for life.
- **Inpatient ward billing** — every drug dispensed from a ward stockroom, every consumable used, every service charged is posted to the inpatient's bill in real time.
- **Discharge summary with itemised invoice** issued at the point of discharge.

> **Note on patient clinical records:** I-NNOVA is a *business operations and financial* platform. We do not replace electronic medical records (EMR). If CBCHS uses OpenMRS, DHIS2 or any other EMR/HMIS, we recommend an **integration layer** in Phase 2 so that patient demographics flow into I-NNOVA and dispensing data flows back into the EMR.

### 5.2 Insurance & third-party billing

- Insurer master records with contract terms, copay percentages, claim formats.
- Per-sale split: patient copay collected at till, insurer portion posted as receivable.
- Monthly **claim file generation** in each insurer's required format (CSV / printed batches).
- **Claims tracking** — submitted, accepted, partially paid, rejected, written off — with ageing reports.

### 5.3 Donor & restricted-fund accounting

- Each grant / donation entered as a **funding envelope** with donor name, period, total budget, programme allocation, restrictions.
- Expenses (dispensing, purchases, salaries posted from your HR system) are **tagged to envelopes** at point of entry.
- **Fund Utilization Report per donor / per programme / per period** — the report a finance officer prints, signs, and sends to the donor without spending the weekend rebuilding it in Excel.
- Optional: **donor-specific report templates** so each donor receives reports in their preferred format (USAID, Global Fund, faith-based partners).

### 5.4 Programme / cost-centre accounting

- Every transaction (sale, dispensing, purchase, payment, expense, journal entry) can be tagged with a **Programme** (HIV, TB, Maternal Health, Eye Care, Palliative Care, OPD, Surgery, etc.) in addition to location.
- The Income Statement, Balance Sheet and Trial Balance can be sliced by Programme.
- Enables CBCHS to answer board questions like *"What did the HIV programme cost us last quarter, and how does it compare to budget?"* in seconds.

### 5.5 Full double-entry accounting module

I-NNOVA includes a fully integrated **double-entry General Ledger** designed to OHADA accounting principles. Section 6 below describes it in detail because CBCHS specifically asked.

### 5.6 Budgeting & variance

- Enter annual budgets per account, per branch, per programme, per period.
- **Budget vs. Actual** reports run at any moment, by any dimension.
- Donor budgets entered once; variance reports generated continuously.

### 5.7 Bank reconciliation

- Import bank statements (CSV or OFX).
- Auto-match against journal entries; flag exceptions for the accountant.
- Monthly bank reconciliation report signed off by the accountant and reviewed by the auditor.

### 5.8 Multi-currency accounting

- Post in transaction currency (USD, EUR, XAF).
- Daily exchange rates entered or fetched automatically.
- Monthly **FX revaluation** of foreign-currency balances; gain/loss posted to the appropriate income/expense account.

### 5.9 Inter-company / inter-fund eliminations

- Transfers between central pharmacy and hospital pharmacy, or between programmes, can be tagged for elimination so that consolidated financial statements net to zero on internal trade.

### 5.10 Approval workflows for finance

- Purchase requisitions, supplier payments, journal entries and large refunds all route through configurable multi-step approvals.
- All approvals captured with timestamp, user and optional comment for audit.

### 5.11 Mobile companion app *(advanced — Phase 2 optional)*

- Android app for ward nurses to scan barcodes and request stock from pharmacy.
- For supervisors to perform spot inventory counts.
- For branch managers to see live KPIs (sales, stock, expiry alerts) on a phone.

### 5.12 Notifications (SMS / Email / WhatsApp)

- Low-stock alerts to branch managers.
- Expiry alerts to head pharmacist.
- Prescription-ready notification to patient.
- Insurance-claim-paid notification to finance team.

---

## 6. Accounting Module — Deep Dive

CBCHS specifically requested how the accounting module will help. This section is for the Finance Director.

### 6.1 Architecture

The I-NNOVA Accounting Module is a **fully integrated, double-entry General Ledger** that lives inside the same database as POS, inventory and procurement. There is no nightly "export then import" of data into a separate accounting package — every sale, every purchase, every payment posts to the GL **in the same database transaction** that records it operationally. This eliminates the most common source of reconciliation pain in hybrid setups.

### 6.2 Chart of Accounts

- Pre-loaded with an OHADA-compatible Chart of Accounts, customisable to CBCHS's preferred structure.
- Five primary classifications: **Assets, Liabilities, Equity, Income, Expenses**, with unlimited sub-types.
- Account hierarchy: primary type → sub-type → account → sub-account.
- Per-account general-ledger codes (GL codes) following CBCHS conventions.
- Multi-branch (each account can be tracked across all branches or restricted to specific ones).

### 6.3 Automatic journal entries

The following operational events automatically post the correct double-entry journal:

| Operational event | Debit | Credit |
|---|---|---|
| Cash sale of drugs | Cash on hand | Drug sales revenue |
| Cash sale with VAT | Cash on hand | Sales revenue + VAT payable |
| Credit sale to patient | Patient receivables | Sales revenue |
| Insurance copay + claim | Cash + Insurer receivable | Sales revenue |
| Cost of goods sold at dispensing | COGS expense | Drug inventory |
| Receipt of purchased stock | Drug inventory | Supplier payables |
| Payment to supplier | Supplier payables | Bank / Cash |
| Donor-funded free dispensing | Donor program expense | Drug inventory |
| Donation in kind received | Drug inventory | Donor income (in kind) |
| Stock write-off (expired) | Expiry loss expense | Drug inventory |
| Stock adjustment (theft) | Stock loss expense | Drug inventory |
| Cashier till variance (short) | Cash shortage expense | Cash on hand |
| Bank deposit | Bank | Cash on hand |
| Operating expense paid | Expense account | Bank / Cash |
| Manual journal entry | (any) | (any) |

Every automatic entry can be drilled back to the originating operational document — click a line on the General Ledger and land on the actual sale, purchase or payment.

### 6.4 The four core financial statements

I-NNOVA produces, at any moment, for any period, the four statements that a board, an external auditor or a donor will ask for:

1. **Trial Balance** — by sub-type, with opening balance, period activity and closing balance. (Recently upgraded.)
2. **Balance Sheet** (As-of-date) — Assets vs. Liabilities + Equity, with sub-type grouping, current-period Net Income auto-computed, balanced indicator, drill-through to ledger. (Recently upgraded.)
3. **Income Statement / Profit & Loss** *(Phase 2 enhancement)* — for any period, by branch, by programme, with prior-period comparison.
4. **Cash Flow Statement** *(Phase 2 enhancement)* — direct method, monthly.

Each statement can be:
- Filtered by branch and / or programme
- Exported to PDF, CSV
- Printed in the user's preferred language
- Drilled down to the underlying journal entries and source documents

### 6.5 General Ledger drill-down

- Every account, every line, every transaction is one click away from its source document.
- Auditors love this. Finance officers save days every month.

### 6.6 Supporting subsidiary ledgers

- **Accounts Receivable** — patients, insurers, programme partners — with ageing buckets (current, 30, 60, 90+).
- **Accounts Payable** — suppliers — with ageing and due-date alerts.
- **Cash and Bank Accounts** — per till, per bank account, reconciled monthly.
- **Inventory Subledger** — perpetual inventory, FEFO valuation, reconciling to the GL Drug Inventory control account.

### 6.7 Reporting & exports

- All financial reports export to **PDF (formatted) and CSV (raw)**.
- Trial Balance, Balance Sheet, Income Statement, Cash Flow, GL Detail, AR Ageing, AP Ageing, Budget vs Actual, Donor Fund Utilization, FX Revaluation.

### 6.8 Audit & compliance posture

- All financial transactions are immutable; reversals are by counter-entry.
- Every change to master data captured with user, timestamp, before/after values.
- 7-year archival retention by default.
- Daily encrypted backups (in addition to operational backups) of financial data.
- User-activity log per finance user, exportable for external auditors.

---

## 7. Reports Library — Master List

A consolidated index of every report you can expect (Phase 1 + Phase 2 combined). Each is filterable, exportable (PDF / CSV), printable, and respects the user's language.

**Pharmacy operations:** Daily Sales Summary, Sales by Product, Sales by Patient, Sales by Prescriber, Hourly Sales Heatmap, Stock by Branch, Stock Expiry Forecast 30/60/90/180, FEFO Compliance, Stock Movement, Inter-facility Transfer, Re-order Suggestion, Stock Adjustment Reasons, Inventory Count Variance, Slow-Moving / Dead Stock, Cold-Chain Stock, Stock Valuation, Gross Margin by Drug/Category/Branch, Wastage / Expiry Loss.

**Cash & till:** Cashier Shift Reconciliation, Multi-Tender Reconciliation (cash / mobile money / card), Cash Discrepancy Log, Petty-Cash Movements.

**Receivables & payables:** Patient Credit Ledger, Insurance Claims Outstanding (per insurer), Insurer Claim File (per period), Supplier Payables Ageing, Purchase Price Variance.

**Donor & programme:** Donor-Funded Dispensing (per programme / donor / period), Donor Fund Utilization vs Budget, Donor-In-Kind Donations Received, Programme P&L (per programme).

**Finance:** Trial Balance, Balance Sheet, Income Statement, Cash Flow, General Ledger Detail, Sub-Ledger Reports (AR / AP / Cash / Inventory), Bank Reconciliation, FX Revaluation, Budget vs Actual.

**Audit & compliance:** Controlled Substances Dispensing Register, Prescription Audit Log, User Activity Log, Data Change Audit, Approval Workflow Audit.

**Management dashboards:** Branch Manager Dashboard (today's sales, stock, expiry alerts, top movers), Head Office Dashboard (revenue & cost across all branches, real-time), Finance Dashboard (cash position, AR/AP ageing, monthly P&L trend), CEO Dashboard (KPI tiles + drill-down).

---

## 8. How I-NNOVA Solves CBCHS's Likely Pain Points

| Pain point (Section 2.3) | How I-NNOVA solves it |
|---|---|
| Stock blindness | Real-time stock across every branch, visible from head office, drillable to batch level. |
| Expiry losses | FEFO dispensing enforced at the till; expiry alerts at 30/60/90 days; transfer-or-write-off action one click away. |
| Cash leakage | Per-cashier shift reconciliation; multi-tender breakdown; mandatory variance reasons; immutable audit trail. |
| Delayed financial close | Operational entries post to GL in the same transaction; close cycle reduces from weeks to days. |
| Audit pain | Drill-down from any line of any financial statement to the source operational document, with user, timestamp, and old/new values. |
| Fragmented data | One database, one truth, one platform for pharmacy, procurement, cash, accounting. |
| Insurance leakage | Claim auto-posted at point of dispensing; monthly claim file generated automatically; ageing tracked until paid. |
| Donor-fund opacity | Per-grant fund envelopes; programme tagging on every expense; fund utilization report on demand. |

---

## 9. Why I-NNOVA — Differentiators

| | Generic POS / accounting | I-NNOVA |
|---|---|---|
| Pharmacy fit | Adapted | **Built for pharmacy** (batch, FEFO, prescription, cold-chain) |
| Accounting integration | Nightly export, weekly reconciliations | **Same database, same transaction** — no reconciliation needed |
| Donor reporting | Manual spreadsheet | **Built-in fund-utilization reports** |
| Bilingual | English only or French only | **EN + FR, per user** |
| Local presence | Email support from overseas | **On-the-ground in Cameroon**, in-person training |
| Deployment flexibility | Cloud only, or on-prem only | **Cloud, on-prem or hybrid — your choice** |
| Customisation | Closed product | **Source-customisable**, CBCHS-specific extensions delivered as part of the contract |
| Audit posture | "Trust us" | Immutable transactions, full audit trail, OHADA-aligned |

---

## 10. Training Plan

Different audiences, different curricula, delivered in the user's language.

| Audience | Duration | Format | Outcome |
|---|---|---|---|
| Cashiers / dispensers | 1 day per cohort | Hands-on at the till | Confident on POS, returns, shift reconciliation |
| Pharmacists | 2 days | Hands-on + scenarios | Confident on receiving, batch/expiry, transfers, prescription capture |
| Branch managers | 2 days | Mixed classroom + reports walk-through | Daily/weekly management routines, reports interpretation |
| Finance / accounting team | 5 days | Workshop + live close-of-books | Operate full accounting module, monthly close, donor reports |
| Internal auditors | 1 day | Reports + audit-trail walk-through | Confident running self-audits |
| IT administrators | 3 days | Technical (server, backups, user admin, integrations) | Self-sufficient site administration |

A **train-the-trainer** option is available so CBCHS can grow internal capability and reduce dependency on the vendor.

All training is delivered in the user's preferred language and accompanied by printed and digital manuals in EN and FR.

---

## 11. Implementation Roadmap

> Phase 1 must succeed before Phase 2 begins. Each phase ends with a formal acceptance milestone signed by both parties.

### Phase 0 — Discovery & Design (Weeks 1–3)

- Stakeholder interviews (CEO, Director of Operations, Finance Director, Chief Pharmacist, IT Manager, key branch managers).
- Inventory of existing systems and data to migrate.
- Confirmation of deployment model (on-prem / cloud / hybrid).
- Finalisation of the Chart of Accounts, user roles, branch list, drug catalogue cleanup plan.
- Signed Statement of Work for Phase 1.

### Phase 1 — Pharmacy Operations Pilot (Weeks 4–10)

- Server installation and configuration.
- Data migration from legacy system (catalogue, suppliers, opening balances).
- Custom builds delivered: FEFO, expiry dashboard, prescription capture, insurance billing, donor-funded dispensing.
- Pilot at **one (1) hospital pharmacy + the central pharmacy**.
- Training of pilot users.
- Two weeks of "hyper-care" on-site support.
- **Acceptance milestone:** pilot site reconciles for one full month with zero unresolved exceptions.

### Phase 2A — Pharmacy Roll-out (Weeks 10–20)

- Roll-out to remaining hospital pharmacies (one per fortnight, with on-site support).
- Roll-out to integrated health-centre pharmacies (in clusters of 3–5).
- Continuous training reinforcement.
- **Acceptance milestone:** all pharmacy points live; head-office dashboard shows real-time data from every site.

### Phase 2B — Hospital-Wide & Finance Module (Weeks 20–36)

- Co-design with CBCHS on patient billing scope and EMR integration (if any).
- Finance module deep activation: budgets, donor envelopes, bank reconciliation, FX revaluation.
- Income Statement & Cash Flow reports delivered.
- Approval workflows configured.
- Training of finance team, auditors, programme managers.
- **Acceptance milestone:** first monthly close produced from I-NNOVA, signed by the Finance Director.

### Phase 3 — Optimisation & Hand-Over (Weeks 36–52)

- Mobile companion app (if scoped).
- SMS / WhatsApp notifications (if scoped).
- Custom donor templates per donor.
- Train-the-trainer programme for CBCHS internal team.
- Hand-over of operations manual, runbooks, disaster-recovery procedure.
- **Acceptance milestone:** CBCHS internal IT and Finance teams operate the system independently.

A condensed timeline is achievable (12–14 weeks to full pharmacy roll-out) if CBCHS dedicates a full-time project manager and the legacy data is clean.

---

## 12. Support & Service Level

Three tiers; CBCHS chooses.

| | **Standard** | **Business** | **Enterprise** *(recommended)* |
|---|---|---|---|
| Channels | Email, WhatsApp | Email, WhatsApp, Phone | Email, WhatsApp, Phone, **on-site visits** |
| Hours | 8h–17h Mon–Fri | 7h–19h Mon–Sat | **24/7** |
| Severity 1 (system down) response | 4 h | 1 h | **15 min** |
| Severity 2 (feature broken) response | 1 working day | 4 h | 1 h |
| Severity 3 (question / how-to) response | 3 working days | 1 working day | 4 h |
| Quarterly on-site review | — | 1 | 4 |
| Dedicated support engineer | — | — | ✅ |
| Quarterly system health-check report | — | ✅ | ✅ |
| Custom development hours included / quarter | — | 8 h | 40 h |

All tiers include software updates, security patches, and the bilingual help desk.

---

## 13. Data Security, Backup & Disaster Recovery

- **Encryption in transit** — HTTPS / TLS 1.2+ for every network connection.
- **Encryption at rest** *(cloud and on-prem options)* — database files encrypted on disk.
- **Role-based access control** — least-privilege principle, enforced per screen and per data record.
- **Daily encrypted backups** — operational data; retained 90 days online, 7 years archived.
- **Hourly snapshots** *(cloud)* — point-in-time recovery to any hour within the last 7 days.
- **Off-site backup** — mirrored to a geographically separate location (cloud) or a second NAS at a designated CBCHS site (on-prem).
- **Disaster Recovery** — documented runbook with target recovery time (RTO) of 4 hours and recovery-point (RPO) of 1 hour for the Enterprise tier.
- **Penetration testing** annually, with remediation included.
- **Data ownership** — all CBCHS data remains the property of CBCHS. On contract termination, a complete export is delivered in open formats (CSV, SQL dump).

---

## 14. Compliance Posture

- **OHADA** — Chart of Accounts and reporting aligned to OHADA accounting standards.
- **Cameroon Ministry of Public Health** — controlled-substance dispensing register, prescription audit log, retention rules.
- **Donor audits** — fund utilization, programme P&L, transaction-level drill-down, immutable history.
- **Internal audit** — segregation of duties, approval workflows, change logs.
- **Patient data** — minimal demographic data stored for billing purposes; patient clinical records remain in the EMR, not in I-NNOVA, per international good practice.

---

## 15. Investment Model

> *Indicative structure. Final numbers to be finalised once branch count, user count and Phase 2 scope are confirmed during Phase 0 discovery.*

The total investment for CBCHS combines four components:

1. **One-off setup & implementation fee** (Phase 0 + Phase 1) — discovery, custom builds, data migration, installation, pilot, training, hyper-care.
2. **Annual licence subscription** — per branch / per user, includes software updates and standard support.
3. **Phase 2 module activation fee** — patient billing, full accounting deep-activation, donor reporting templates (paid only if CBCHS proceeds with Phase 2).
4. **Optional enhancements** — mobile app, SMS gateway, additional donor templates, custom integrations.

Three indicative package shapes:

| | **Pharmacy Starter** | **Pharmacy Plus** *(recommended)* | **Convention-Wide** |
|---|---|---|---|
| Scope | Phase 1 only, central + main hospitals | Phase 1 full pharmacy network | Phase 1 + Phase 2 hospital-wide & finance |
| Branches | Up to 5 | Up to 20 | Unlimited |
| Users | Up to 25 | Up to 100 | Unlimited |
| Support tier | Business | Enterprise | Enterprise + dedicated PM |
| Custom development | Standard (FEFO, expiry, prescription) | Standard + Insurance + Donor dispensing | All Phase 2 modules |
| Setup fee | XAF [to be quoted] | XAF [to be quoted] | XAF [to be quoted] |
| Annual subscription | XAF [to be quoted] | XAF [to be quoted] | XAF [to be quoted] |

We are happy to structure payment to align with CBCHS's budget cycle (e.g. 30% on contract, 40% on Phase 1 acceptance, 30% on Phase 2 acceptance, then annual subscription).

---

## 16. Why I-NNOVA Is the Right Partner

- **Local company, local presence** — Cameroon-based, bilingual team, available in person at CBCHS sites.
- **Proven product** — live customers running multi-branch operations on I-NNOVA today.
- **Healthcare-aware** — we know pharmacy, batch, expiry, FEFO, prescription, insurance, donor — not generic retail.
- **Source-customisable** — we own and control the source code. When CBCHS asks for something specific, we can build it. We are not waiting for an overseas vendor to prioritise a ticket.
- **Aligned values** — we understand the mission-driven nature of CBCHS and structure the partnership to support it, not to extract from it.

---

## 17. Next Steps

We propose the following next steps, in order:

1. **Live demonstration** at CBCHS HQ, focused on Pharmacy Operations with selected CBCHS pharmacy data, in front of the Finance Director, Director of Operations and Chief Pharmacist.
2. **Discovery workshop** (1 day) to confirm scope, branch list, deployment preference, and Phase 0 timeline.
3. **Pilot at one site** (4 weeks) at I-NNOVA's risk — if the pilot does not achieve agreed acceptance criteria, CBCHS is not committed to proceed.
4. **Full Phase 1 contract** signed upon successful pilot acceptance.
5. **Phase 2 scoping** in parallel with Phase 1 roll-out.

---

## 18. Contact

**[Your Company Name]**
[Address line 1]
[Address line 2]
Yaoundé / Douala / [city], Cameroon

**Contact persons:**
- [Account Executive name] — [phone] — [email]
- [Technical Lead name] — [phone] — [email]

**Web:** www.i-nnova.com
**Email:** sales@i-nnova.com
**WhatsApp:** [number]

---

*This proposal is valid for 60 days from the date on the cover page.*

---

# Annex A — Items We Will Build Behind the Scenes Before Pitching

> **Internal note — not to be shared with CBCHS in the proposal itself.**
> The following are gaps in the current I-NNOVA product that we will close before the live demonstration so that everything in this proposal can be shown working.

| # | Item | Why it matters for CBCHS | Effort estimate (relative) |
|---|---|---|---|
| 1 | **FEFO dispensing** (true First-Expiry-First-Out, not just FIFO) | Core pharmacy expectation; cannot demo credibly without it | Medium |
| 2 | **Batch & expiry deep workflow** (receive, track, alert, write-off) | Top pain point for any pharmacy | Medium–High |
| 3 | **Expiry dashboard** (30/60/90/180 day buckets with single-click actions) | Visible "wow" in the demo | Small |
| 4 | **Prescription capture module** (prescriber, ref, diagnosis, dosage on label) | Required for regulatory compliance and clinical safety | Medium |
| 5 | **Insurance / third-party billing workflow** (copay + receivable + claim file) | CBCHS has insurance patients — must show | Medium–High |
| 6 | **Donor-funded free dispensing** (zero price, posts COGS to donor expense account) | Differentiator for faith-based / NGO sector | Small (depends on accounting hook) |
| 7 | **Income Statement / P&L report** (Phase 2 leg of the accounting triangle) | Finance Director will ask | Small (extend existing accounting reports) |
| 8 | **Cash Flow Statement** (direct method, monthly) | Board-level report | Medium |
| 9 | **Programme / cost-centre tagging** on transactions + grouping in reports | Donor-fund reporting depends on it | Medium |
| 10 | **Donor fund envelope** entity + Fund Utilization report | Donor-fund tracking | Medium |
| 11 | **Bank reconciliation** workflow | Standard finance expectation | Medium |
| 12 | **Budget entry + Budget vs Actual report** | Standard finance expectation | Medium |
| 13 | **Multi-currency proper accounting** (FX revaluation, gain/loss posting) | International procurement and donor reporting | Medium |
| 14 | **Cashier shift reconciliation enhancements** (multi-tender, denomination count) | Already partial; needs polish | Small–Medium |
| 15 | **Controlled substances dispensing register** report | Cameroon regulatory expectation | Small |
| 16 | **Patient record (lightweight)** — name, age, sex, ID, phone, history of dispensing | Useful even before EMR integration | Medium |
| 17 | **Approval workflows** for purchases / payments / large refunds / stock adjustments | Internal controls / audit expectation | Medium–High |
| 18 | **Head Office consolidated dashboard** (live KPIs across all branches) | Will be the screen the CEO looks at | Medium |
| 19 | **Donor & programme reports** templates | Specific donors to be identified | Per donor — Small once framework exists |
| 20 | **Mobile companion app** (Android, scanner mode, stock requests) | Nice-to-have for demo; can be shown later | Large (defer to Phase 3 if time-pressed) |
| 21 | **EMR / OpenMRS integration adapter** | Only if CBCHS uses one — to be confirmed | Large; design first, build later |
| 22 | **FR translation completeness audit** | Bilingual proposal must be bilingual product | Small–Medium |
| 23 | **OHADA-aligned default Chart of Accounts** loadable as a seed | Saves weeks of setup work | Small |

**Suggested build sequence** (highest impact first, demo-readable first):
1. Items 1, 2, 3 (batch/FEFO/expiry) — essential, demo-able together
2. Item 4 (prescription)
3. Items 5, 6 (insurance + donor dispensing)
4. Items 7, 8, 9, 10 (P&L, cash flow, programme tagging, donor envelopes)
5. Items 11, 12, 13 (bank rec, budget, multi-currency accounting)
6. Items 17, 18 (approvals, consolidated dashboard)
7. Items 14, 15, 16, 19, 22, 23 (polish + regulatory + templates)
8. Items 20, 21 (mobile, EMR — Phase 3 / on demand)

**Estimated effort to be demo-ready on items 1–10:** the bulk of focused build time. This is what should be prioritised before the demo to CBCHS.

---

*End of proposal.*
