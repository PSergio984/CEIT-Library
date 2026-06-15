# Admin Reporting Center Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a reporting dashboard for administrators to track library health and export performance data as PDF/CSV.

**Architecture:** A new Admin page that aggregates data from `BorrowTransaction`, `Violation`, and `Librarian` models. Uses `dompdf` for PDF generation and standard PHP CSV headers for downloads.

**Tech Stack:** Laravel, Livewire/Volt, dompdf.

---

### Task 1: Reporting Dashboard UI

**Files:**
- Create: `app/Livewire/Pages/Admin/AdminReports.php`
- Create: `resources/views/livewire/pages/Admin/admin-reports.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Setup Route and Component**
Register `/admin/reports` and create the base layout with date-range pickers.

- [ ] **Step 2: Implement Aggregate Stats**
Calculate: Total borrows this month, active violations, and top 5 trending papers.

- [ ] **Step 3: Commit**
```bash
git add app/Livewire/Pages/Admin/AdminReports.php resources/views/livewire/pages/Admin/admin-reports.blade.php
git commit -m "feat: create admin reports dashboard UI"
```

### Task 2: CSV Export Functionality

**Files:**
- Modify: `app/Livewire/Pages/Admin/AdminReports.php`

- [ ] **Step 1: Implement `exportCsv()` method**
Generate a stream of borrowing data based on the selected filters.

- [ ] **Step 2: Commit**
```bash
git add app/Livewire/Pages/Admin/AdminReports.php
git commit -m "feat: add CSV export to reports"
```

### Task 3: PDF Export (dompdf)

**Files:**
- Create: `resources/views/reports/library-health-pdf.blade.php`
- Modify: `app/Livewire/Pages/Admin/AdminReports.php`

- [ ] **Step 1: Design the PDF template**
Create a clean Blade view that `dompdf` will render.

- [ ] **Step 2: Implement `exportPdf()`**
Use `PDF::loadView()` to generate and return the download response.

- [ ] **Step 3: Commit**
```bash
git add resources/views/reports/library-health-pdf.blade.php
git commit -m "feat: add PDF export via dompdf"
```
