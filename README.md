# SSC Event Attendance

A separate event-based attendance module that follows the existing `system` Tailwind/violet admin style without modifying the `system` folder.

## Routes

- `/SSCAttendance/` - active event status and scanner entry point
- `/SSCAttendance/scan.php` - QR/barcode keyboard scanner
- `/SSCAttendance/admin/` - SSC dashboard
- `admin/events.php` - create, activate, register, and close events
- `admin/attendance.php` - filter attendance by event, status, or student
- `admin/payments.php` - record partial/full payments and view history
- `admin/clearance.php` - sign only when outstanding balance is zero

## Setup

1. Ensure MySQL and Apache are running in XAMPP.
2. Import `schema.sql`; it creates and selects the `event_attendance` database, including its connected `students` table and event attendance tables.
3. Sign in through `/SSCAttendance/login.php`, then open the SSC panel.
4. Create an event, enable `Register all students`, and activate it.

The module connects to the `event_attendance` database through `config.php`. Its tables use direct names (`events`, `event_students`, `event_attendance`, `fines`, `payments`, and `clearance`), while student records remain in the same database so all event registration and attendance foreign keys stay connected. Scan decisions are validated in `api/scan.php`, including active event, registration, scan windows, one record per event, scan-in-before-scan-out, and duplicate scan-out rules.
