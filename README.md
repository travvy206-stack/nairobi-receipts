# NTA-Pay: Digital Receipt Management System
**Nairobi Traders Association (Production Architecture)**

A hardened dual-runtime web application enforcing the **"Pay First, Receipt Comes"** transactional model:
- **Backend Core**: PHP 8.2+ (Native PDO, Bcrypt Auth, Strict RBAC, REST APIs)
- **PDF Microservice**: Python 3.11+ / Flask (ReportLab 80mm thermal receipts, QRCode vector generator)
- **Database**: SQLite / MySQL (ACID-compliant storage, audit logging)
- **Frontend**: Production SaaS Dark Mode UI (Tailwind CSS, Lime Neon `#a3e635` & Cyan `#06b6d4` accents)

---

## Architecture & Directory Structure
```
nairobi-receipts/
├── config/             # DB connection singleton & .env loader
├── public/             # Public web root
│   ├── admin/          # Business Owner Admin portal & audit logs
│   │   ├── dashboard.php   # Executive telemetry & staff manager
│   │   ├── audit_logs.php  # Forensic audit log viewer
│   │   └── traders_api.php # Trader account CRUD API
│   ├── api/            # REST API endpoints
│   │   ├── initiate_payment.php     # Step A: STK push dispatch
│   │   ├── check_payment_status.php # Step B & C: Polling & receipt minting
│   │   └── process_receipt.php      # Direct processor
│   ├── index.php       # Step A/B/C: Trader POS terminal & modal lockdown
│   ├── login.php       # Hardened production sign-in portal
│   ├── setup.php       # Initial system setup (runs when 0 admins exist)
│   ├── logout.php      # Session terminator
│   ├── verify.php      # Public QR verification page
│   └── storage.php     # Secure asset stream gateway
├── src/                # Core PHP business logic
│   └── Auth/           # Production AuthService with RBAC and audit logging
├── service_pdf/        # Python Flask microservice (Port 5000)
│   ├── storage/        # Output directory for generated PDFs and QR codes
│   ├── requirements.txt# Python dependencies
│   └── app.py          # Flask microservice
├── db/                 # Database schema, SQLite database, and migrations
│   ├── schema.sql      # DDL table definitions & indexes
│   ├── migrate.php     # Schema migration runner
│   └── init_db.php     # Initializer script
├── start_servers.ps1   # One-click PowerShell startup script
├── start_servers.bat   # One-click Windows batch startup script
├── .env.example        # Environment variable template
└── .env                # Active configuration
```

---

## Quick Start & Service Execution

### 1. Prerequisites
- **PHP 8.2+** with `pdo_sqlite` and `curl` extensions enabled
- **Python 3.11+** with `flask`, `reportlab`, `qrcode`, `pillow` installed

### 2. Spinning Up the Services

#### One-Click PowerShell Startup
Run:
```powershell
.\start_servers.ps1
```

#### Manual Dual-Terminal Startup
**Terminal 1 — Python Flask Microservice (Port 5000):**
```bash
python service_pdf/app.py
```

**Terminal 2 — PHP Web Server (Port 8000):**
```bash
php -S localhost:8000 -t public
```

---

## Core Security & Operational Workflows

### 1. Initial System Setup / Administrator Onboarding
- If the database contains zero administrators, navigating to `http://localhost:8000/login.php` automatically redirects to `http://localhost:8000/setup.php`.
- The first Business Owner (Admin) registers their account (Full Name, M-Pesa Phone, Email, Password).
- Once the primary administrator is established, `setup.php` is permanently locked.

### 2. Multi-Tenant Trader Account Management
- Business Owners log in at `http://localhost:8000/login.php` and access `http://localhost:8000/admin/dashboard.php`.
- Admins can provision new Employee Trader credentials, deactivate/activate counters, and reset trader passwords.
- Traders can only access the POS interface (`/index.php`). Unauthorized attempts to access `/admin/*` trigger a 403 Forbidden response.

### 3. "Pay First, Receipt Comes" M-Pesa Integration Flow
1. **Initiation**: Operator inputs cart line items and customer phone, then clicks "Initiate M-Pesa STK Push". A pending transaction record is created.
2. **Lockdown & Polling**: The terminal modal locks down with a 60-second countdown timer and live polling every 2s. Receipt generation and printing controls remain completely locked and hidden.
3. **Settlement**: Upon payment settlement (HTTP 200 state), the transaction is marked completed and the Python microservice generates the 80mm vector thermal PDF and QR code.
4. **Timeout / Cancellation**: If the customer cancels or 60s expires without a PIN entry, the transaction is marked failed/timed out, logged in `audit_log`, and zero receipt is minted.
