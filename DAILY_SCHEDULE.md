# 🗓️ Daily Development Schedule - Nov 21, 2025 Ship Date

## Timeline Overview
- **Start Date**: October 29, 2025
- **Ship Date**: November 21, 2025  
- **Total Days**: 23 days
- **Work Capacity**: 2-3 hours/day (avg 2.5h) + some full days
- **Total Hours Available**: ~58 hours (23 days × 2.5h)
- **Estimated Work Needed**: 52-60 hours
- **Buffer**: Minimal (~5-10% contingency)

---

## 📊 Feature Priorities (Based on Your Answers)

### ✅ **MUST SHIP** (Launch Blockers)
1. **Role-based permissions** (whitelist: Guest, Employee:Standard, Employee:Manager, Customer, Admin)
2. **Print-optimized invoice HTML** (not PDF library)
3. **One-time login codes** for customers (24hr default, configurable)
4. **Customer invoice portal** (view, comment, flag invoices)
5. **Proof materials visibility control** (staff vs public)
6. **Email notifications** (Events/Notifications pattern)
7. **Vehicle maintenance module** (oil, inspection, repairs, assignment)
8. **QuickBooks CSV export**
9. **UI redesign** (orange theme, bright construction aesthetic)
10. **Bug fixes** (rate values, billable miles override)

### 🔮 **POST-LAUNCH** (Mark as future features)
- PDF generation library (dompdf)
- SMS notifications
- Advanced vehicle tracking
- Customer analytics

---

## 📅 Week 1: Core Infrastructure (Oct 29 - Nov 4)

### **Day 1 - Wed, Oct 29** (2.5h)
**Goal**: Permission system refactor
- [ ] Update `User` model with new role constants (GUEST, EMPLOYEE_STANDARD, EMPLOYEE_MANAGER, CUSTOMER, SUPER_ADMIN)
- [ ] Update all policies to use whitelist approach
- [ ] Test role permissions
- [ ] Create migration for role changes if needed

**Files**: `app/Models/User.php`, `app/Policies/*.php`

---

### **Day 2 - Thu, Oct 30** (2.5h)
**Goal**: One-time login codes system
- [ ] Create `LoginCode` model & migration (code, user_id, expires_at, used_at)
- [ ] Create `LoginCodeController` (generate, validate, use)
- [ ] Add config for expiry time (default 24h)
- [ ] Create login code entry form
- [ ] Test code generation & validation

**Files**: `app/Models/LoginCode.php`, `app/Http/Controllers/LoginCodeController.php`, `database/migrations/*_create_login_codes_table.php`

---

### **Day 3 - Fri, Oct 31** (3h)
**Goal**: Customer portal foundation
- [ ] Create customer dashboard route & view
- [ ] Customer invoice index (show only their invoices)
- [ ] Customer invoice detail view
- [ ] Test access control (customers can't see other customers' data)

**Files**: `app/Http/Controllers/CustomerPortalController.php`, `resources/views/customer-portal/dashboard.blade.php`, `resources/views/customer-portal/invoices.blade.php`

---

### **Day 4 - Sat, Nov 1** (FULL DAY - 6h)
**Goal**: Invoice commenting & flagging
- [ ] Create `InvoiceComment` model & migration (invoice_id, user_id, comment, is_flagged)
- [ ] Add comments section to invoice detail view
- [ ] Add "Flag for Attention" button
- [ ] Create Livewire component for real-time comments
- [ ] Email notification when invoice is flagged

**Files**: `app/Models/InvoiceComment.php`, `app/Livewire/InvoiceComments.php`, `resources/views/livewire/invoice-comments.blade.php`

---

### **Day 5 - Sun, Nov 2** (3h)
**Goal**: Proof materials visibility
- [ ] Add `is_public` boolean to `attachments` table migration
- [ ] Update attachment upload to allow marking public/private
- [ ] Filter attachments by visibility on customer portal
- [ ] Add toggle for staff to mark attachments public
- [ ] Test visibility rules

**Files**: `database/migrations/*_add_is_public_to_attachments.php`, `app/Http/Controllers/AttachmentsController.php`

---

### **Day 6 - Mon, Nov 3** (2h)
**Goal**: Email notification infrastructure
- [ ] Create Laravel Events: `JobAssigned`, `InvoiceReady`, `InvoiceFlagged`
- [ ] Create Listeners: `SendJobAssignedNotification`, `SendInvoiceReadyNotification`
- [ ] Create Mailable classes with Blade templates
- [ ] Test with Brevo

**Files**: `app/Events/*.php`, `app/Listeners/*.php`, `app/Mail/*.php`, `resources/views/mail/*.blade.php`

---

### **Day 7 - Tue, Nov 4** (2h)
**Goal**: Hook up notifications to app actions
- [ ] Dispatch `JobAssigned` when driver assigned to job
- [ ] Dispatch `InvoiceReady` when invoice created
- [ ] Dispatch `InvoiceFlagged` when customer flags invoice
- [ ] Test end-to-end email flow
- [ ] Add email preview routes for testing

**Files**: `app/Http/Controllers/MyJobsController.php`, `app/Http/Controllers/MyInvoicesController.php`

---

## 📅 Week 2: Vehicle Maintenance & Bug Fixes (Nov 5 - Nov 11)

### **Day 8 - Wed, Nov 5** (2.5h)
**Goal**: Vehicle maintenance schema
- [ ] Create migration: add maintenance fields to `vehicles` table (last_oil_change, next_oil_change, last_inspection, next_inspection, in_garage, assigned_to_user_id)
- [ ] Update `Vehicle` model with relationships & accessors
- [ ] Create `VehicleMaintenance` model for maintenance history
- [ ] Run migrations

**Files**: `database/migrations/*_add_maintenance_to_vehicles.php`, `app/Models/Vehicle.php`, `app/Models/VehicleMaintenance.php`

---

### **Day 9 - Thu, Nov 6** (3h)
**Goal**: Vehicle maintenance UI (view)
- [ ] Create vehicle index with maintenance status indicators
- [ ] Add maintenance details section to vehicle show page
- [ ] Color-coded alerts (red if overdue, yellow if due soon, green if OK)
- [ ] Show current assignment (which user has the vehicle)

**Files**: `resources/views/vehicles/index.blade.php`, `resources/views/vehicles/show.blade.php`

---

### **Day 10 - Fri, Nov 7** (2.5h)
**Goal**: Vehicle maintenance UI (edit)
- [ ] Create Livewire component for editing maintenance dates
- [ ] Add "Log Maintenance" modal (oil change, inspection, repair)
- [ ] Create maintenance history timeline
- [ ] Test date calculations (next due dates)

**Files**: `app/Livewire/VehicleMaintenance.php`, `resources/views/livewire/vehicle-maintenance.blade.php`

---

### **Day 11 - Sat, Nov 8** (FULL DAY - 6h)
**Goal**: Bug fixes & data integrity
- [ ] Fix: Rate values not saving (PilotCarJob form validation)
- [ ] Fix: Billable miles override UI & calculation
- [ ] Fix: Missing "show" link on jobs list
- [ ] Fix: Main contact tag for customers
- [ ] Add unit tests for invoice calculations
- [ ] Test edge cases (negative values, zero rates, etc.)

**Files**: `app/Livewire/EditPilotCarJob.php`, `app/Models/PilotCarJob.php`, `resources/views/pilot-car-jobs/index.blade.php`, `tests/Unit/InvoiceCalculationTest.php`

---

### **Day 12 - Sun, Nov 9** (3h)
**Goal**: QuickBooks CSV export
- [ ] Create export controller & route
- [ ] Map invoice fields to QuickBooks CSV format
- [ ] Add "Export to CSV" button on invoices index
- [ ] Test with sample data
- [ ] Handle date formatting, currency formatting

**Files**: `app/Http/Controllers/QuickBooksExportController.php`, `resources/views/invoices/index.blade.php`

---

### **Day 13 - Mon, Nov 10** (2h)
**Goal**: Print-optimized invoice HTML
- [ ] Create print CSS (@media print)
- [ ] Design clean invoice template (logo, addresses, line items, totals)
- [ ] Add "Print Invoice" button
- [ ] Test in Chrome, Firefox, Edge
- [ ] Ensure proper page breaks

**Files**: `resources/views/invoices/print.blade.php`, `resources/css/print.css`

---

### **Day 14 - Tue, Nov 11** (2h)
**Goal**: Soft delete improvements
- [ ] Add "Restore" buttons to all deleted records views
- [ ] Add "Show Deleted" filter toggle
- [ ] Confirm-before-delete modals for all destructive actions
- [ ] Add toast notifications for delete/restore
- [ ] Test restore functionality

**Files**: All index views, `app/Livewire/DeleteConfirmationButton.php`

---

## 📅 Week 3: UI Redesign & Polish (Nov 12 - Nov 18)

### **Day 15 - Wed, Nov 12** (FULL DAY - 6h)
**Goal**: UI redesign - Design system
- [ ] Audit singleparentproject.org for orange usage patterns
- [ ] Update CSS variables in `app.css` (proper orange shades)
- [ ] Create button component library (.btn-primary, .btn-secondary, .btn-danger)
- [ ] Standardize form input styles
- [ ] Update navigation menu design
- [ ] Create consistent card/panel styles

**Files**: `resources/css/app.css`, `tailwind.config.js`, `resources/views/components/*.blade.php`

---

### **Day 16 - Thu, Nov 13** (3h)
**Goal**: Dashboard redesign
- [ ] Redesign manager dashboard with job status overview
- [ ] Add color-coded status badges (pending, in-progress, completed, invoiced, paid)
- [ ] Add summary stats cards (total jobs, revenue, unpaid invoices)
- [ ] Add quick action buttons
- [ ] Mobile-responsive layout

**Files**: `resources/views/livewire/dashboard.blade.php`

---

### **Day 17 - Fri, Nov 14** (3h)
**Goal**: Job list redesign
- [ ] Update jobs index with new design
- [ ] Add status filters (paid/unpaid, canceled, etc.)
- [ ] Improve card layout
- [ ] Add missing "Show" links
- [ ] Better mobile layout

**Files**: `resources/views/pilot-car-jobs/index.blade.php`, `app/Http/Controllers/JobsController.php`

---

### **Day 18 - Sat, Nov 15** (FULL DAY - 6h)
**Goal**: Mobile optimization sprint
- [ ] Test all views on mobile (320px, 375px, 768px)
- [ ] Fix any layout breaks
- [ ] Optimize touch targets (44px minimum)
- [ ] Improve mobile navigation
- [ ] Test form submissions on mobile
- [ ] Add hamburger menu improvements
- [ ] Test on real devices (iPhone, Android)

**Files**: All blade templates, `resources/css/app.css`

---

### **Day 19 - Sun, Nov 16** (3h)
**Goal**: Error handling & validation
- [ ] Improve error messages (user-friendly language)
- [ ] Add toast notification system (Alpine.js)
- [ ] Improve success feedback
- [ ] Better 404/403 error pages with branding
- [ ] Add loading states to all async actions
- [ ] Test validation on all forms

**Files**: `resources/views/errors/*.blade.php`, all Livewire components

---

### **Day 20 - Mon, Nov 17** (2h)
**Goal**: Feature testing
- [ ] Create manual testing checklist
- [ ] Test all user roles (admin, manager, employee, customer)
- [ ] Test multi-tenant isolation
- [ ] Test permission boundaries
- [ ] Test CSV import with large files

**Files**: Create `TESTING_CHECKLIST.md`

---

### **Day 21 - Tue, Nov 18** (2h)
**Goal**: Performance & security audit
- [ ] Review all N+1 query issues (use Laravel Debugbar)
- [ ] Add eager loading where needed
- [ ] Check CSRF protection on all forms
- [ ] Review mass assignment vulnerabilities
- [ ] Check SQL injection risks
- [ ] Test rate limiting on login attempts

**Files**: All controllers, models

---

## 📅 Week 4: Launch Prep (Nov 19 - Nov 21)

### **Day 22 - Wed, Nov 19** (FULL DAY - 6h)
**Goal**: Production deployment
- [ ] Set up production database (MySQL)
- [ ] Configure production `.env` (database, Brevo, etc.)
- [ ] Set up queue worker (Windows Task Scheduler or supervisor)
- [ ] Configure SSL certificate
- [ ] Test production environment
- [ ] Set up backup strategy
- [ ] Deploy to production server

**Files**: `.env.production`, server configuration

---

### **Day 23 - Thu, Nov 20** (3h)
**Goal**: Data migration & user setup
- [ ] Export latest data from Google Sheets
- [ ] Import via CSV upload
- [ ] Verify data integrity
- [ ] Create initial admin accounts
- [ ] Create initial customer accounts
- [ ] Send invitation emails

**Files**: None (data operations)

---

### **Day 24 - Fri, Nov 21** (3h)
**Goal**: SHIP DAY 🚀
- [ ] Final smoke tests on production
- [ ] Create user documentation
- [ ] Train first user (walkthrough)
- [ ] Set up support channel (email/phone)
- [ ] Monitor for issues
- [ ] Celebrate launch! 🎉

---

## ⚠️ Contingency Plan

If you fall behind schedule:

### **Priority 1** (Must Have):
- Role-based auth with login codes
- Customer portal (view invoices)
- Email notifications
- Print-optimized invoices
- Bug fixes (rate values, billable miles)

### **Priority 2** (Nice to Have):
- Invoice comments/flagging
- Proof materials visibility
- QuickBooks export
- Full UI redesign

### **Priority 3** (Can Ship Without):
- Vehicle maintenance (move to post-launch)
- Advanced mobile optimizations
- Toast notifications

### **Buffer Days**:
If you get a full weekend day or two, use for:
- UI polish
- Extra testing
- Documentation
- Training videos

---

## 📊 Daily Time Tracking

| Date | Planned Hours | Actual Hours | Tasks Completed | Notes |
|------|---------------|--------------|-----------------|-------|
| Oct 29 | 2.5h | ___ | ___ | ___ |
| Oct 30 | 2.5h | ___ | ___ | ___ |
| Oct 31 | 3h | ___ | ___ | ___ |
| Nov 1 | 6h | ___ | ___ | ___ |
| Nov 2 | 3h | ___ | ___ | ___ |
| ... | ... | ... | ... | ... |

**Instructions**: Fill in actual hours and tasks as you go. This helps identify if you're ahead or behind schedule.

---

## 🎯 Weekly Goals Summary

### Week 1 (Oct 29 - Nov 4):
**Goal**: Auth system, login codes, customer portal, notifications
**Hours**: ~18h

### Week 2 (Nov 5 - Nov 11):
**Goal**: Vehicle maintenance, bug fixes, QuickBooks, print invoices
**Hours**: ~20h

### Week 3 (Nov 12 - Nov 18):
**Goal**: UI redesign, mobile optimization, testing
**Hours**: ~20h

### Week 4 (Nov 19 - Nov 21):
**Goal**: Deployment, data migration, launch
**Hours**: ~12h

**Total**: ~70 hours (with buffer for delays)

---

## 🤝 Let's Get Started!

**Next Action**: Tell me which day's tasks you want to start with, and I'll help you build them step-by-step!

**Suggested**: Let's start with Day 1 (Permission system refactor) right now. Ready?

---

**Last Updated**: 2025-10-29

