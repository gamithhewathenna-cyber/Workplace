# Employee Portal Enhancement
## Module 1: TODO / Daily Workspace  |  Module 2: Leave Management
### cPanel / PHP 8.0+ / MySQL 5.7+

---

## 📁 File Structure

```
/employee-modules/
├── sql/
│   └── modules_schema.sql          ← Run first in phpMyAdmin
├── includes/
│   ├── config.php                  ← DB config + helpers (EDIT THIS)
│   ├── navbar.php                  ← Top navigation bar
│   └── sidebar.php                 ← Left sidebar navigation
├── assets/
│   ├── css/portal.css              ← All styles
│   └── js/portal.js                ← All JavaScript
├── todo/
│   ├── index.php                   ← Employee dashboard
│   ├── tasks.php                   ← Task list + create
│   ├── time_track.php              ← Time tracker
│   └── report.php                  ← Daily work report
├── leave/
│   ├── index.php                   ← Employee leave dashboard
│   ├── admin.php                   ← Manager approvals + calendar
│   └── holidays.php                ← Holiday management
├── admin/
│   ├── checklist.php               ← Manage recurring checklist
│   └── reports.php                 ← Attendance/productivity/leave reports
├── api/
│   ├── checklist_toggle.php        ← AJAX: toggle checklist item
│   ├── calc_days.php               ← AJAX: calculate working days
│   ├── time_track.php              ← AJAX: start/pause/resume/finish timer
│   └── mark_read.php               ← AJAX: mark notifications read
└── uploads/
    ├── tasks/                      ← Task file attachments
    └── certs/                      ← Medical certificates
```

---

## 🚀 Installation Steps

### Step 1 — Upload Files
Upload the entire `employee-modules/` folder to your hosting root, or merge into your existing project root.

Recommended structure if merging:
```
/public_html/
├── (your existing files)
├── todo/
├── leave/
├── admin/      ← merge with your existing admin/
├── api/
├── assets/css/portal.css
├── assets/js/portal.js
├── includes/config.php   (or merge config)
└── uploads/tasks/  uploads/certs/
```

### Step 2 — Create Database Tables
1. Open **phpMyAdmin** → select your database
2. Click **SQL** tab
3. Paste contents of `sql/modules_schema.sql`
4. Click **Go**

### Step 3 — Configure `includes/config.php`
Edit these lines to match your cPanel database:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database');    // ← your actual DB name
define('DB_USER', 'your_db_user');     // ← your actual DB user
define('DB_PASS', 'your_db_password'); // ← your actual DB password
date_default_timezone_set('Asia/Kuala_Lumpur'); // ← your timezone
```

### Step 4 — Create Upload Folders (chmod 755)
Via cPanel File Manager or SSH:
```bash
mkdir -p uploads/tasks uploads/certs
chmod 755 uploads uploads/tasks uploads/certs
```

### Step 5 — Integrate with Existing Auth
In `includes/config.php`, update these two functions to match your existing session/user system:
```php
function current_employee_id(): int {
    // Change 'employee_id' to whatever key your app uses
    return (int)($_SESSION['employee_id'] ?? $_SESSION['user_id'] ?? 0);
}

function is_manager(): bool {
    // Adjust 'role' key and role values to match your system
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['manager','admin','hr'], true);
}
```

### Step 6 — Link to Existing `employees` Table
The modules reference `employees.id`, `employees.name`, `employees.position`, `employees.role`, `employees.status`.
If your table uses different column names, update the queries in config.php and the relevant pages.

### Step 7 — Add Navigation Links
Add these links to your existing app navigation:
```html
<a href="/todo/index.php">My Workspace</a>
<a href="/leave/index.php">Leave</a>
<!-- Manager only: -->
<a href="/leave/admin.php">Leave Admin</a>
<a href="/admin/reports.php">Reports</a>
```

---

## 🎨 Theming

The CSS uses variables at the top of `portal.css`. Override in your existing stylesheet:
```css
:root {
  --clr-primary:   #your-brand-color;
  --clr-success:   #your-green;
  /* etc. */
}
```

---

## ⚙️ Key Features by File

| File | Features |
|------|----------|
| `todo/index.php` | Live clock, login status, checklist, projects, task list, performance rings |
| `todo/tasks.php` | Full task CRUD, status updates, file attachments, filters, pagination |
| `todo/time_track.php` | Start/pause/resume/finish timer, monthly log |
| `todo/report.php` | Daily report submit + history |
| `leave/index.php` | Leave balances, apply leave, medical cert upload, history |
| `leave/admin.php` | Pending approvals, team calendar, all requests filter |
| `leave/holidays.php` | Add/remove public & company holidays |
| `admin/checklist.php` | Add/edit/toggle/delete recurring daily tasks |
| `admin/reports.php` | Attendance, productivity, leave reports + CSV export |
| `api/time_track.php` | REST API for timer (start/pause/resume/finish) |

---

## 📋 Leave Rules (configured in DB)

| Leave Type | Days | Medical Cert Required | Special Rules |
|-----------|------|----------------------|---------------|
| Annual Leave | 14 | No | Carry-forward up to 5 days |
| Medical Leave | 6 | Yes (if > 2 consecutive days) | — |
| Special Leave | — | No | Only after Annual Leave = 0 |

Adjust in `leave_types` table via phpMyAdmin.

---

## 🔔 Notifications Triggered

| Event | Who Gets Notified |
|-------|------------------|
| Late login | Employee |
| New task assigned | Employee |
| Leave request submitted | All managers |
| Leave approved/rejected | Employee |
| Daily report submitted | All managers |

---

## 📤 Export Options
- **CSV**: Reports page → Export CSV button (attendance, productivity, leave)
- **Print**: Reports page → Print button (print-optimised CSS included)
- **Browser export**: JavaScript `exportTable()` function available for any table

---

## 🔒 Security Notes
- All user input is passed through PDO prepared statements
- `h()` helper escapes all output with `htmlspecialchars`
- File uploads are renamed with `uniqid()` to prevent path traversal
- Manager-only pages redirect non-managers automatically
- Adjust upload file size limits in your `php.ini`: `upload_max_filesize = 10M`

---

## 🛠 Extending

**Add more task statuses**: edit the `ENUM` in `tasks.status` and update the dropdown in `tasks.php`.

**Add more leave types**: insert into `leave_types` table.

**Email notifications**: replace `add_notification()` calls with your existing email library (PHPMailer, SwiftMailer, etc.).

**Cron job for daily checklist**: the checklist is auto-generated on each employee's first login. Alternatively add a cron:
```
0 7 * * * php /path/to/api/gen_checklists.php
```
