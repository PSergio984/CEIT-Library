# Modernize Scripts and Fix SonarQube Issues Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Modernize infrastructure scripts and satisfy SonarQube quality gates regarding line lengths and formatting.

**Architecture:** Surgical updates to shell scripts and PHP files to adhere to modern standards and PSR-12. Use a script for bulk cleanup of `_ide_helper.php` to handle its large size efficiently.

**Tech Stack:** Shell (Bash), PHP, JavaScript (for cleanup script).

---

### Task 1: Modernize Shell Scripts

**Files:**
- Modify: `Docker/start.sh`

- [ ] **Step 1: Update line 38 to use `[[` instead of `[`**

```bash
# Old
if [ ! -L /var/www/html/public/storage ]; then

# New
if [[ ! -L /var/www/html/public/storage ]]; then
```

- [ ] **Step 2: Verify `scripts/00-laravel-deploy.sh` already uses `[[`**

Run: `grep ' \[' scripts/00-laravel-deploy.sh`
Expected: No matches (already modernized).

- [ ] **Step 3: Commit shell script changes**

```bash
git add Docker/start.sh
git commit -m "chore: modernize shell script conditionals to use [["
```

### Task 2: Split Long Lines in Config and Factories

**Files:**
- Modify: `config/mail.php`
- Modify: `database/factories/AcademicPaperFactory.php`
- Modify: `database/factories/ViolationFactory.php`

- [ ] **Step 1: Split long line in `config/mail.php`**

Line 49:
```php
'local_domain' => env(
    'MAIL_EHLO_DOMAIN',
    parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)
),
```

- [ ] **Step 2: Split long line in `database/factories/AcademicPaperFactory.php`**

Line 38:
```php
'paper_type' => $this->faker->randomElement([
    'Thesis', 'Feasib', 'Capstone', 'Research', 'Practicum', 'Report'
]),
```

- [ ] **Step 3: Split long lines in `database/factories/ViolationFactory.php`**

Lines 21-30 (Array elements):
```php
['name' => 'Late Return of Books', 'description' => 'Returning library books beyond the due date', 'penalty' => 5],
// ... change to
[
    'name' => 'Late Return of Books',
    'description' => 'Returning library books beyond the due date',
    'penalty' => 5
],
```

- [ ] **Step 4: Commit line splitting changes**

```bash
git add config/mail.php database/factories/AcademicPaperFactory.php database/factories/ViolationFactory.php
git commit -m "chore: split long lines to satisfy SonarQube quality gates"
```

### Task 3: `_ide_helper.php` Cleanup

**Files:**
- Modify: `_ide_helper.php`

- [ ] **Step 1: Create a cleanup script `cleanup_ide_helper.js`**

```javascript
const fs = require('fs');
const content = fs.readFileSync('_ide_helper.php', 'utf8');
const lines = content.split('\n');

const newLines = lines.map(line => {
    // 1. Remove trailing whitespace
    let newLine = line.replace(/\s+$/, '');

    // 2. Move open curly braces to the beginning of the next line (PSR-12 for classes/methods)
    // Simple regex for classes and methods in _ide_helper.php
    if (newLine.match(/(class|interface|trait|function).*\{$/)) {
        newLine = newLine.replace(/\{$/, '\n' + ' '.repeat(newLine.search(/\S/)) + '{');
    }

    return newLine;
});

fs.writeFileSync('_ide_helper.php', newLines.join('\n'));
```

- [ ] **Step 2: Run the cleanup script**

Run: `node cleanup_ide_helper.js`

- [ ] **Step 3: Verify changes in `_ide_helper.php`**

Run: `grep -nE '(class|interface|trait|function).*\{$' _ide_helper.php`
Expected: No matches (except maybe nested closures if any, but PSR-12 allows them).
Run: `grep -nE '\s+$' _ide_helper.php`
Expected: No matches.

- [ ] **Step 4: Remove the cleanup script and commit**

```bash
rm cleanup_ide_helper.js
git add _ide_helper.php
git commit -m "chore: cleanup _ide_helper.php PSR-12 braces and whitespace"
```

### Task 4: Final Verification and Cleanup

- [ ] **Step 1: Verify .gitignore contains graphify-out/cache**

Run: `grep "graphify-out/cache" .gitignore`

- [ ] **Step 2: Run Pint to ensure final formatting**

Run: `vendor/bin/pint --dirty`

- [ ] **Step 3: Final Commit**

```bash
git add .
git commit --amend -m "chore: modernize scripts and fix SonarQube quality issues"
```
