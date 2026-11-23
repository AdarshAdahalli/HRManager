# InternHub Global - LAMP HR & Intern Management System

A multi-timezone Human Resource & Intern Management System built on the **LAMP Stack** (Linux/Windows, Apache, MySQL, PHP 8.2).

Includes smart timezone conversion, optimal meeting overlap matrix calculation, live attendance & punch tracking, task deliverables board, and a dedicated blocker/difficulty help desk.

---

## 🌟 Key Features

### 1. 🏢 HR Panel & Dashboard
- **Executive Metrics**: Total active interns, live sessions today, tasks by status (Pending, In Progress, Completed), open blockers flagged by interns, and upcoming global meetings.
- **Intern Directory & Credential Issuance**:
  - Add new intern with auto-generated **Intern ID** (`INT-2026-XXX`) and secure random password.
  - Generates credential summary card with 1-click clipboard copy.
  - Remove / delete intern or reset password at any time.
- **Task Management**:
  - Assign deliverables with priority (`Low`, `Medium`, `High`, `Urgent`), deadline, and description.
  - Track progress percentage (0% - 100%) and see which tasks have reported blockers.
- **Attendance & Punch Logs**:
  - Live login and logout timestamp records.
  - Automatically calculates session duration (hours & minutes).
  - Shows login time converted into both HR physical time and the intern's local time.
- **Blockers & Difficulty Help Desk**:
  - View technical/access hurdles reported by interns.
  - Respond with guidance and update ticket status (`Open`, `In Review`, `Resolved`).

### 2. 🌍 Smart Overlap Matrix & Timezone Coordinator
- **The Global Problem**: Interns reside across different continents and timezones (e.g. USA, UK, India, Japan). HR cannot conduct individual 1-on-1 meetings all day.
- **The Solution**:
  1. Interns submit their preferred weekly free hours in their **own local physical timezone** (e.g., 9:00 AM EDT).
  2. The system converts every intern's availability window into **HR's physical timezone** (e.g. `Asia/Kolkata` or `America/New_York`).
  3. Divides the 24-hour day into forty-eight 30-minute intervals and generates an **Interactive Heatmap** (0% to 100% attendance).
  4. Identifies and recommends the **Top Common Meeting Windows** maximizing attendance.
  5. Shows an exact breakdown of what time it will be for each attendee:
     - **HR (Kolkata)**: 07:30 PM IST
     - **Alex (New York)**: 09:00 AM EST
     - **Sofia (London)**: 02:00 PM GMT
     - **Aarav (Bangalore)**: 07:30 PM IST
     - **Kenji (Tokyo)**: 11:00 PM JST
  6. **1-Click Scheduling**: Automatically notifies all interns and records meeting invites with their local start time pre-calculated.

### 3. 🎓 Intern Panel
- **Personal Workspace**:
  - Active attendance punch badge with live elapsed session duration and quick logout.
  - Upcoming scheduled team meetings displayed in **their own local timezone**.
- **Task Deliverables**:
  - View assigned tasks, deadlines, and urgency level.
  - Update completion percentage (0 - 100%), status, and log progress notes.
- **Difficulty / Blocker Reporting**:
  - Dedicated form to notify HR of impediments, missing permissions, or errors.
  - Track HR responses and resolution in real time.
- **Free Hours & Timezone Configuration**:
  - Auto-detect local device timezone with 1 click.
  - Define weekly available windows and view live preview of how they convert into HR physical time.
- **Personal Attendance History**:
  - Complete history of login/logout timestamps and weekly hours logged.

---

## 🚀 Running the Project

### Option A: Instant Run with PHP Dev Server (No Configuration Required)
Double-click `start_dev_server.bat` or run:
```bash
C:\xampp\php\php.exe -S localhost:8000
```
Then open your browser to:
[http://localhost:8000/](http://localhost:8000/)

### Option B: Run via XAMPP Apache & MySQL
1. Double click `copy_to_xampp.bat` to copy the project to `C:\xampp\htdocs\intern_management`.
2. Open **XAMPP Control Panel** and start **Apache** and **MySQL**.
3. Open your browser:
   [http://localhost/intern_management/](http://localhost/intern_management/)
4. The system automatically creates the database `intern_management_db` and loads the initial schema and seed data!

### Option C: Run with Docker Compose
```bash
docker compose up -d
```
Access at [http://localhost:8080/](http://localhost:8080/).

---

## 🔑 Default Login Credentials

| Role | Name | Email | Password | Country / Timezone |
| :--- | :--- | :--- | :--- | :--- |
| **HR Manager** | Sarah Jenkins | `hr@internhub.com` | `Password123!` | 🇺🇸 New York (`America/New_York`) |
| **Intern** | Alex Rivera | `alex@internhub.com` | `Password123!` | 🇺🇸 USA (`America/New_York`) |
| **Intern** | Sofia Bennett | `sofia@internhub.com` | `Password123!` | 🇬🇧 UK (`Europe/London`) |
| **Intern** | Aarav Patel | `aarav@internhub.com` | `Password123!` | 🇮🇳 India (`Asia/Kolkata`) |
| **Intern** | Kenji Sato | `kenji@internhub.com` | `Password123!` | 🇯🇵 Japan (`Asia/Tokyo`) |

*(On the login screen, you can also click the convenient **1-Click Demo Login** buttons to sign in without typing!)*

---

## 📂 Project Directory Structure

```
intern_management/
├── config/
│   ├── app.php                # App settings, HR timezone, base URL detection
│   ├── database.php           # PDO database connection with auto MySQL/SQLite fallback
│   └── timezone_helper.php    # Timezone conversion & 48-slot overlap matrix calculator
├── includes/
│   ├── auth.php               # Login, logout, session guards, attendance tracking
│   ├── header.php             # Modern responsive navbar with live dual-timezone digital clocks
│   ├── footer.php             # Footer & background attendance heartbeat
│   └── navigation.php         # Role-specific top menu bar
├── hr/
│   ├── index.php              # HR dashboard metrics & live intern directory
│   ├── interns.php            # Add/remove intern & auto ID/password generator
│   ├── tasks.php              # Assign & track project deliverables
│   ├── difficulties.php       # Review & resolve reported blocker tickets
│   ├── attendance.php         # Complete login/logout session logs
│   └── meeting_scheduler.php  # Heatmap overlap matrix & 1-click scheduler
├── intern/
│   ├── index.php              # Intern workspace & local meeting calendar
│   ├── tasks.php              # Update task percentage & status
│   ├── report_difficulty.php  # Notify HR of roadblocks & view guidance
│   ├── my_schedule.php        # Set weekly free timings & auto-detect timezone
│   └── attendance.php         # Personal login/logout punches
├── api/
│   ├── availability.php       # API for overlap matrix calculation & timezone conversions
│   ├── heartbeat.php          # 60s background ping to keep session duration accurate
│   ├── tasks.php              # Async task progress updates
│   └── difficulty.php         # Blocker ticket submission & resolution
├── sql/
│   ├── schema.sql             # MySQL schema definitions
│   └── seed.sql               # Default sample data for HR and worldwide interns
├── setup.php                  # Web installer & database diagnostics
├── copy_to_xampp.bat          # 1-click deploy to C:\xampp\htdocs\intern_management
├── start_dev_server.bat       # 1-click PHP dev server on localhost:8000
└── docker-compose.yml         # Containerized LAMP stack
```
