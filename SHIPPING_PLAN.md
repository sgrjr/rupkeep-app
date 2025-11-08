# 🚀 Rupkeep Pilot Car App - Shipping Plan

## Executive Summary
**Goal**: Ship a production-ready pilot car management system to replace Google Sheets workflow.

**Current Status**: ~70% complete. Core CRUD operations work, but missing PDF generation, customer portal, email notifications, and UI polish.

**Estimated Work Remaining**: 40-60 hours (depending on priorities)

---

## 🎯 Launch Criteria (MVP)

### Must-Have Features
- [x] User authentication & role-based permissions
- [x] Organization/multi-tenant structure
- [x] Job creation & management
- [x] Driver log entry (mobile-optimized)
- [x] Invoice calculation logic
- [ ] **PDF invoice generation** ⭐ CRITICAL
- [ ] **Manager dashboard with job status overview** ⭐ CRITICAL
- [ ] **Email notifications** (job assignments, invoice ready)
- [ ] **Customer portal** (login + view invoices)
- [ ] **UI/UX redesign** (minimal, modern, orange branding)
- [ ] **Production deployment & testing**

### Nice-to-Have (Can Ship Without)
- [ ] QuickBooks export
- [ ] One-time magic login links
- [ ] Vehicle maintenance tracking
- [ ] SMS notifications
- [ ] Advanced reporting

---

## 📋 Detailed Task Breakdown

### **Phase 1: Critical Blockers** (Est. 20-25 hours)

#### 1.1 PDF Invoice Generation (6-8 hours)
**Status**: Not started
**Dependencies**: None

**Tasks**:
- [ ] Install PDF library (recommend: laravel-dompdf or barryvdh/laravel-dompdf)
- [ ] Create invoice blade template for PDF rendering
- [ ] Add "Download PDF" button to invoice edit page
- [ ] Add "Print Invoice" option (browser print CSS)
- [ ] Store PDF URLs or generate on-the-fly?
- [ ] Test PDF generation with real data
- [ ] Handle multi-page invoices

**Files to Create/Edit**:
- `composer.json` (add dompdf)
- `app/Http/Controllers/InvoicePdfController.php`
- `resources/views/invoices/pdf.blade.php`
- `routes/web.php` (PDF download route)

**Acceptance Criteria**:
- Manager can download invoice as PDF
- PDF includes all invoice fields (logo, addresses, line items, totals)
- PDF is print-ready (proper margins, page breaks)

---

#### 1.2 Customer Portal (8-10 hours)
**Status**: Not started
**Dependencies**: PDF generation (for download links)

**Tasks**:
- [ ] Create `Customer` user model or use existing User with 'customer' role?
- [ ] Customer registration/invitation flow
- [ ] Customer login page (separate from staff login?)
- [ ] Customer dashboard: list of invoices
- [ ] Invoice detail view (read-only)
- [ ] Filter by date, status (paid/unpaid)
- [ ] Download PDF button
- [ ] Email invitation to customers

**Files to Create/Edit**:
- `app/Models/Customer.php` (add `user_id` if customers are Users?)
- `app/Http/Controllers/CustomerPortalController.php`
- `app/Policies/CustomerPolicy.php`
- `resources/views/customer-portal/dashboard.blade.php`
- `resources/views/customer-portal/invoice-show.blade.php`
- `routes/web.php` (customer routes)

**Acceptance Criteria**:
- Customer can log in with email/password
- Customer sees only their organization's invoices
- Customer can download PDFs
- Customer cannot edit anything

**Decision Needed**: 
- Should customers be `User` records with role='customer', or separate `Customer` table with auth?

---

#### 1.3 Email Notifications (4-5 hours)
**Status**: Brevo configured, not actively used
**Dependencies**: None

**Tasks**:
- [ ] Create notification Mailable classes
  - [ ] JobAssignedNotification (to driver)
  - [ ] InvoiceReadyNotification (to customer)
  - [ ] WelcomeNotification (to new users)
- [ ] Add notification triggers:
  - [ ] When job assigned to driver
  - [ ] When invoice created
  - [ ] When user account created
- [ ] Queue jobs for async email sending
- [ ] Test with real Brevo account
- [ ] Add email templates with branding

**Files to Create/Edit**:
- `app/Mail/JobAssignedNotification.php`
- `app/Mail/InvoiceReadyNotification.php`
- `resources/views/mail/job-assigned.blade.php`
- `resources/views/mail/invoice-ready.blade.php`
- `app/Http/Controllers/MyJobsController.php` (add email trigger)
- `app/Http/Controllers/MyInvoicesController.php` (add email trigger)

**Acceptance Criteria**:
- Driver receives email when assigned to job
- Customer receives email when invoice is ready
- Emails contain relevant info (job details, invoice link)
- Emails have orange branding

---

#### 1.4 Manager Dashboard Enhancement (3-4 hours)
**Status**: Basic dashboard exists, needs job status overview
**Dependencies**: None

**Tasks**:
- [ ] Add job status indicators (pending, in-progress, completed, invoiced, paid)
- [ ] Color-coded status badges
- [ ] Quick filters (show unpaid, show pending, etc.)
- [ ] Summary stats (total jobs, total revenue, unpaid invoices)
- [ ] Recent activity feed
- [ ] Make "Jobs" the default dashboard view for managers

**Files to Edit**:
- `app/Livewire/Dashboard.php`
- `resources/views/livewire/dashboard.blade.php`
- `resources/views/pilot-car-jobs/index.blade.php` (already has good layout)

**Acceptance Criteria**:
- Manager sees at-a-glance job status
- Can quickly navigate to jobs needing attention
- Stats are accurate

---

### **Phase 2: UI/UX Polish** (Est. 10-15 hours)

#### 2.1 Design System Refinement (3-4 hours)
**Status**: Orange branding exists, needs consistency
**Dependencies**: None

**Tasks**:
- [ ] Audit all views for color consistency (#f9b104 orange)
- [ ] Create reusable button styles (.btn-primary, .btn-secondary)
- [ ] Standardize form inputs (Tailwind @apply)
- [ ] Improve typography (headings, body text)
- [ ] Add subtle animations (transitions, hover states)
- [ ] Mobile menu improvements

**Files to Edit**:
- `resources/css/app.css` (consolidate styles)
- `tailwind.config.js` (add custom colors)
- All blade templates (apply consistent classes)

**Acceptance Criteria**:
- Consistent branding across all pages
- Clean, modern aesthetic
- No visual bugs on mobile

---

#### 2.2 Mobile Optimization (4-5 hours)
**Status**: Log form is mobile-friendly, other views need work
**Dependencies**: None

**Tasks**:
- [ ] Test all views on mobile (320px, 375px, 768px)
- [ ] Fix any layout breaks
- [ ] Optimize touch targets (buttons 44px min)
- [ ] Simplify navigation for mobile
- [ ] Test form submission on mobile devices
- [ ] Add PWA manifest (optional - makes app installable)

**Files to Edit**:
- All blade templates (responsive classes)
- `resources/css/app.css` (media queries)
- `public/manifest.json` (if PWA)

**Acceptance Criteria**:
- All core workflows work on mobile
- No horizontal scrolling
- Easy to tap buttons

---

#### 2.3 Error Handling & Validation (3-4 hours)
**Status**: Basic validation exists, needs improvement
**Dependencies**: None

**Tasks**:
- [ ] Add client-side validation (Livewire rules)
- [ ] Improve error messages (user-friendly)
- [ ] Add success toasts/notifications
- [ ] Handle edge cases (deleted records, missing data)
- [ ] Add confirmation modals for destructive actions
- [ ] Improve 404/403 error pages

**Files to Edit**:
- All Livewire components (validation rules)
- `resources/views/errors/404.blade.php`
- `resources/views/errors/403.blade.php`
- Add toast notification system (Alpine.js or library)

**Acceptance Criteria**:
- Users get clear feedback on actions
- No confusing error messages
- Destructive actions require confirmation

---

### **Phase 3: Data Integrity & Testing** (Est. 8-10 hours)

#### 3.1 Soft Delete UI (2-3 hours)
**Status**: Models have SoftDeletes, UI is basic
**Dependencies**: None

**Tasks**:
- [ ] Add "Restore" buttons on deleted records
- [ ] Show deleted records in admin view (with filter)
- [ ] Add "Permanently Delete" option (admin only)
- [ ] Confirm before delete modal
- [ ] Toast notification on restore

**Files to Edit**:
- All index views (jobs, users, customers, etc.)
- Controllers (restore methods exist, need routes)
- `routes/web.php` (add restore routes)

**Acceptance Criteria**:
- Accidental deletes are recoverable
- Admins can permanently delete if needed
- Clear visual indicator for deleted items

---

#### 3.2 Data Validation & Calculations (3-4 hours)
**Status**: Invoice calculations exist, need verification
**Dependencies**: None

**Tasks**:
- [ ] Verify billable miles calculation
- [ ] Test rate value saving (DEV_NOTES mentions bug)
- [ ] Add validation rules for all numeric fields
- [ ] Test edge cases (negative mileage, zero rates, etc.)
- [ ] Add unit tests for invoice calculations
- [ ] Document calculation formulas

**Files to Edit**:
- `app/Models/PilotCarJob.php` (calculation methods)
- `app/Livewire/EditPilotCarJob.php` (form validation)
- `tests/Unit/InvoiceCalculationTest.php` (create)

**Acceptance Criteria**:
- Invoice totals are accurate
- Rate values save correctly
- No negative amounts

---

#### 3.3 Feature Testing (3-4 hours)
**Status**: Jetstream tests exist, custom features untested
**Dependencies**: All Phase 1 & 2 features complete

**Tasks**:
- [ ] Manual testing checklist (create below)
- [ ] Write feature tests for critical paths
- [ ] Test multi-tenant isolation (users can't see other org data)
- [ ] Test permission boundaries (drivers can't access admin)
- [ ] Load testing (CSV import with large files)

**Files to Create**:
- `tests/Feature/JobManagementTest.php`
- `tests/Feature/InvoiceGenerationTest.php`
- `tests/Feature/CustomerPortalTest.php`
- `TESTING_CHECKLIST.md`

**Acceptance Criteria**:
- All critical paths have tests
- No cross-tenant data leaks
- App handles expected load

---

### **Phase 4: Deployment & Launch** (Est. 5-8 hours)

#### 4.1 Production Setup (2-3 hours)
**Status**: Dev environment on Windows IIS
**Dependencies**: All features complete

**Tasks**:
- [ ] Set up production database (MySQL)
- [ ] Configure `.env` for production
- [ ] Set up queue worker (supervisor or Windows service)
- [ ] Configure Brevo API keys
- [ ] Set up file storage (S3 or local disk)
- [ ] SSL certificate
- [ ] Domain setup

**Acceptance Criteria**:
- App runs on production server
- Queue jobs process
- Emails send successfully

---

#### 4.2 Data Migration (1-2 hours)
**Status**: CSV import exists
**Dependencies**: Production DB ready

**Tasks**:
- [ ] Export latest data from Google Sheets
- [ ] Import to production via dashboard upload
- [ ] Verify data integrity
- [ ] Create initial admin users
- [ ] Create initial customer accounts (if portal ready)

**Acceptance Criteria**:
- All historical data imported
- Users can log in
- No data corruption

---

#### 4.3 User Training & Documentation (2-3 hours)
**Status**: No docs
**Dependencies**: None

**Tasks**:
- [ ] Create user guide (screenshots + instructions)
  - [ ] How to create a job
  - [ ] How to fill out a log (drivers)
  - [ ] How to generate an invoice
  - [ ] How to manage customers
- [ ] Record video walkthrough (optional)
- [ ] Create admin guide
- [ ] Document common troubleshooting

**Files to Create**:
- `docs/USER_GUIDE.md`
- `docs/ADMIN_GUIDE.md`

**Acceptance Criteria**:
- Non-technical users can use the app
- Common questions answered

---

## 🐛 Bug Fixes from DEV_NOTES

| Issue | Priority | Est. Time | Status |
|-------|----------|-----------|--------|
| Rate values not being saved | HIGH | 1h | ❌ TODO |
| Billable miles override UI | MEDIUM | 2h | ❌ TODO |
| Missing "show" link on jobs list | LOW | 30min | ❌ TODO |
| Main contact tag missing | MEDIUM | 1h | ❌ TODO |
| Notification address not used | MEDIUM | 1h | ❌ TODO |

---

## 📅 Proposed Timeline

### **Sprint 1** (Week 1): Critical Features
- Day 1-2: PDF invoice generation
- Day 3-4: Customer portal
- Day 5: Email notifications

### **Sprint 2** (Week 2): Polish & Testing
- Day 1-2: UI/UX redesign
- Day 3: Mobile optimization
- Day 4-5: Testing & bug fixes

### **Sprint 3** (Week 3): Launch Prep
- Day 1: Production setup
- Day 2: Data migration
- Day 3: User training
- Day 4-5: Final testing & soft launch

---

## ⚠️ Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Customer portal auth complexity | HIGH | Decide early: separate table or User role? |
| PDF generation performance | MEDIUM | Generate PDFs async via queue |
| Data migration issues | HIGH | Test import on staging first |
| Email deliverability | MEDIUM | Use Brevo, warm up domain |
| User adoption | HIGH | Provide training & support |

---

## 🎨 UI Redesign Mockup Priorities

1. **Dashboard** (manager's first view)
2. **Job List** (most-used page)
3. **Log Entry Form** (mobile-first, driver's main task)
4. **Invoice View** (customer-facing)
5. **Customer Portal** (external users)

---

## 🚦 Go/No-Go Checklist

### Before Launch:
- [ ] All Phase 1 tasks complete
- [ ] All Phase 2 tasks complete (or acceptable trade-offs)
- [ ] All Phase 3 tests pass
- [ ] Production environment ready
- [ ] Data migrated successfully
- [ ] At least 1 user trained
- [ ] Backup strategy in place
- [ ] Support plan defined (who handles issues?)

---

## ✅ DECISIONS MADE (Oct 29, 2025)

### Architecture Decisions:
1. **Customer portal auth**: Unified auth system with login codes (24h expiry, configurable)
2. **PDF approach**: Print-optimized HTML (PDF library = future feature)
3. **Driver notifications**: Email only (SMS = future feature, use Events/Notifications pattern)
4. **QuickBooks**: CSV export (not API)
5. **One-time login links**: 24h default, customers access all their invoices, can comment/flag
6. **UI inspiration**: singleparentproject.org (orange usage), keep bright construction theme
7. **Vehicle maintenance**: LAUNCH BLOCKER - must have oil, inspection, repair tracking
8. **Target ship date**: **November 21, 2025**

### New Feature Requirements:
- Invoice commenting system (customers can comment and flag invoices)
- Proof materials visibility control (staff-only vs public)
- Vehicle assignment tracking (who currently has each vehicle)

### Role Structure:
1. GUEST/UNAUTHENTICATED USER
2. EMPLOYEE:STANDARD
3. EMPLOYEE:MANAGER
4. CUSTOMER
5. ADMIN/SUPER_USER

### Post-Launch Features:
- PDF generation library (dompdf)
- SMS notifications
- Advanced vehicle tracking

---

## 📅 See DAILY_SCHEDULE.md for detailed day-by-day breakdown

**Total Time Available**: ~58 hours (23 days × 2.5h avg)
**Estimated Work Needed**: 52-60 hours
**Buffer**: Minimal (~10% contingency)

**Next Step**: Start Day 1 tasks (Permission system refactor)

---

**Last Updated**: 2025-10-29

