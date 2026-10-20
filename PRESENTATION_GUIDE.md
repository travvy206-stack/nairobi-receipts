# 🎯 NTA-Pay: Presentation Cheat Sheet & Technical Code Map
**Digital Receipt Management System — Nairobi Traders Association**

---

## 📌 1. THE 60-SECOND EXECUTIVE PITCH (How to open your presentation)
> *"Good morning/afternoon. Today I am presenting **NTA-Pay**, a production-grade Digital Receipt Management System engineered specifically for the Nairobi Traders Association.*
>
> *In Nairobi's fast-paced retail and market environments, business owners lose millions annually to **paper receipt forgery, faked M-Pesa SMS messages, and unauthorized manual discounts by counter staff**.*
>
> *NTA-Pay solves this with a strict architectural rule: **'Pay First, Receipt Comes'**. A receipt cannot be printed, downloaded, or issued until Safaricom settles the transaction via real-time M-Pesa STK Push. Every receipt features an authentic vector QR code verifiable on any smartphone, and business owners receive real-time telemetry and forensic audit logging across all their stalls."*

---

## 📂 2. CODE MAP: "WHERE IS WHAT AND WHAT DOES WHAT"

If an examiner, judge, or client asks *"Show me the code that handles X"*, use this table:

| Functional Area | Exact File Path | What It Does / Key Logic |
| :--- | :--- | :--- |
| **POS Terminal UI** | [`public/index.php`](file:///c:/Users/marks/project/nairobi-receipts/public/index.php) | Dark-mode POS terminal with quick catalog, custom line items, cart math, and the 60s lockdown modal. |
| **M-Pesa STK Initiation API** | [`public/api/initiate_payment.php`](file:///c:/Users/marks/project/nairobi-receipts/public/api/initiate_payment.php) | Validates cart, inserts pending transaction into DB, determines trader's Till/Paybill, and fires STK Push. |
| **Payment Polling & Minting API** | [`public/api/check_payment_status.php`](file:///c:/Users/marks/project/nairobi-receipts/public/api/check_payment_status.php) | Polled every 2s by the frontend. When settled, calls Python microservice, generates PDF/QR, and marks receipt completed. |
| **Daraja SDK Client** | [`src/Payment/MpesaDarajaService.php`](file:///c:/Users/marks/project/nairobi-receipts/src/Payment/MpesaDarajaService.php) | Handles Safaricom OAuth token caching, timestamped password generation, and dynamic Till/Paybill dispatch. |
| **Trader Till Settings API** | [`public/api/update_trader_settings.php`](file:///c:/Users/marks/project/nairobi-receipts/public/api/update_trader_settings.php) | Allows individual traders to configure their M-Pesa destination account (Buy Goods Till vs. Paybill). |
| **Safaricom Webhook Listener** | [`public/api/mpesa_callback.php`](file:///c:/Users/marks/project/nairobi-receipts/public/api/mpesa_callback.php) | Asynchronous webhook receiver that verifies Safaricom payloads and updates settlement status in real-time. |
| **Thermal PDF & QR Engine** | [`service_pdf/app.py`](file:///c:/Users/marks/project/nairobi-receipts/service_pdf/app.py) | Python Flask microservice (Port 5000) using **ReportLab** (80mm receipt) & **QRCode** vector generator. |
| **Public QR Verification** | [`public/verify.php`](file:///c:/Users/marks/project/nairobi-receipts/public/verify.php) | Public URL encoded in the QR code. Displays authentic verification badge, trader details, and printable copy. |
| **Business Owner Portal** | [`public/admin/dashboard.php`](file:///c:/Users/marks/project/nairobi-receipts/public/admin/dashboard.php) | Shift revenue telemetry, live counter activity, and staff account management (activation, password reset). |
| **Forensic Audit Logger** | [`public/admin/audit_logs.php`](file:///c:/Users/marks/project/nairobi-receipts/public/admin/audit_logs.php) | Searchable security audit log recording every login, payment initiation, timeout, and role access with IP. |
| **RBAC & Authentication** | [`src/Auth/AuthService.php`](file:///c:/Users/marks/project/nairobi-receipts/src/Auth/AuthService.php) | Session security, password hashing (Bcrypt), role enforcement (403 for unauthorized), and audit dispatches. |
| **System First-Time Setup** | [`public/setup.php`](file:///c:/Users/marks/project/nairobi-receipts/public/setup.php) | Onboarding wizard that runs when 0 admins exist, then permanently self-locks. |
| **Database Schema & DDL** | [`db/schema.sql`](file:///c:/Users/marks/project/nairobi-receipts/db/schema.sql) | SQLite/MySQL relational schema with foreign keys, indexes, and ENUM check constraints. |

---

## 🎬 3. STEP-BY-STEP LIVE DEMO SCRIPT (Follow this during the demo)

### Step 1: Start the Engine (if not already running)
1. Double-click [`start_servers.bat`](file:///c:/Users/marks/project/nairobi-receipts/start_servers.bat) or run in PowerShell:
   ```powershell
   .\start_servers.ps1
   ```
2. Open your browser at: `http://localhost:8000/login.php`

---

### Step 2: Show Trader POS Experience
1. **Log in as Trader**:
   - **Phone**: `0712345678`
   - **Password**: `trader123`
2. **Show the POS Interface**:
   - Point out the **Dark Mode UI**, lime-green neon accents, and clean layout.
   - Click on items in the **Quick Items** bar (e.g. *Maize Flour*, *Cooking Oil*) or type a custom item.
   - Adjust quantities and show real-time subtotal/VAT calculation.
3. **Show Trader Destination Account (Crucial Feature)**:
   - Click the green **"M-Pesa Setup"** / **"Till Settings"** button in the header.
   - Explain to the audience: *"Each market trader can connect their own Buy Goods Till number or Paybill without touching code. Money goes directly to their stall's account."*
4. **Trigger STK Push ("Pay First, Receipt Comes")**:
   - Enter Customer Phone: `0712345678`
   - Click **"Initiate M-Pesa STK Push"**.
   - **Point out the lockdown modal**:
     - *Notice how the screen locks.*
     - *A 60-second countdown begins.*
     - *There is no print button. There is no fake receipt button. The terminal polls Safaricom every 2 seconds.*
5. **Show Successful Settlement**:
   - In Sandbox/Test mode, the system auto-settles after 3 seconds.
   - The modal transitions smoothly to **"Payment Verified & Settled!"**.
   - The 80mm thermal receipt is displayed with the M-Pesa Transaction Code, customer phone, and QR code.
   - Click **"View & Print Receipt"** or **"Download PDF"** to show the crisp vector PDF generated by Python.

---

### Step 3: Show Public QR Code Verification
1. Click **"Verify Online"** or scan the receipt's QR code on a phone.
2. It opens [`http://localhost:8000/verify.php?r=NTA-XXXX`](file:///c:/Users/marks/project/nairobi-receipts/public/verify.php).
3. Point out:
   - The bright green **"VERIFIED AUTHENTIC RECEIPT"** badge.
   - The timestamp, trader stall name, line items, and transaction reference.
   - *"Even if someone photocopies a paper receipt, anyone can scan the QR code to verify if it is authentic in our database."*

---

### Step 4: Show Business Owner Admin & Audit Logs
1. Click **Sign Out** or navigate in a private tab to `http://localhost:8000/login.php`.
2. **Log in as Admin (Business Owner)**:
   - **Phone**: `0700000000`
   - **Password**: `admin123`
3. **Show Shift Telemetry**:
   - Point out **Today's Revenue**, **Transactions Settled**, **Active Counters**, and **Average Basket Size**.
   - Point out the **Staff Management table**: Admins can provision new cashiers, toggle counter status, and manage stalls.
4. **Show Forensic Audit Logs**:
   - Click **Audit Logs** (`/admin/audit_logs.php`).
   - Show the immutable trail: Every payment attempt, login, and staff action is tracked with timestamp and IP address.

---

## 🚀 4. HOW TO PUSH TO GIT (GITHUB / GITLAB)

### Step A: Install Git (if not yet installed)
If typing `git status` in PowerShell gives `CommandNotFoundException`:
1. Download & run the official Windows installer: **[https://git-scm.com/download/win](https://git-scm.com/download/win)**
2. Or in an Administrator PowerShell run:
   ```powershell
   winget install --id Git.Git -e --source winget
   ```
3. Restart your PowerShell terminal so `git` is loaded into PATH.

### Step B: Push Your Code to GitHub
Run these commands in your project folder (`c:\Users\marks\project\nairobi-receipts`):

```bash
# 1. Initialize local repository
git init

# 2. Add all files (respects .gitignore: keeps passwords & local sqlite safe)
git add .

# 3. Create your initial production commit
git commit -m "feat: complete NTA-Pay digital receipt system with dual-runtime architecture"

# 4. Rename default branch to main
git branch -M main

# 5. Link to your GitHub repository (replace with your repo URL)
git remote add origin https://github.com/YOUR_USERNAME/nta-pay.git

# 6. Push code to GitHub
git push -u origin main
```

---

## 🌐 5. HOW TO HOST THE SITE LIVE FROM GIT

Because NTA-Pay is a **dual-runtime application** (PHP 8.2 web app + Python Flask microservice + SQLite), we have already configured a production **`Dockerfile`** and **`docker-compose.yml`** in the repository.

Here are the 3 easiest ways to host it live for free or low cost:

### Option 1: Railway.app (Recommended — Simplest 1-Click Git Deploy)
1. Go to **[railway.app](https://railway.app)** and log in with GitHub.
2. Click **New Project** → **Deploy from GitHub repo**.
3. Select your `nta-pay` repository.
4. Railway will automatically detect the **`Dockerfile`** we created!
5. Add your Environment Variables in the Railway dashboard:
   - `MPESA_ENV`: `sandbox` (or `production`)
   - `MPESA_SHORTCODE`: your shortcode
   - `MPESA_PASSKEY`: your passkey
   - `MPESA_CALLBACK_URL`: `https://YOUR-RAILWAY-APP.railway.app/api/mpesa_callback.php`
6. Click **Deploy**. Your app is live with a public HTTPS URL and automatic SSL in ~2 minutes!

---

### Option 2: Render.com (Free Tier Available)
1. Go to **[render.com](https://render.com)** and log in with GitHub.
2. Click **New +** → **Web Service**.
3. Connect your GitHub repository.
4. Set **Environment** to **Docker** (Render uses our `Dockerfile`).
5. Choose Free or Starter tier.
6. Under **Environment Variables**, add:
   - `PORT`: `8000`
   - `MPESA_ENV`: `sandbox`
   - `MPESA_CALLBACK_URL`: `https://your-service.onrender.com/api/mpesa_callback.php`
7. Click **Create Web Service**. Every future `git push` will auto-deploy!

---

### Option 3: Standard Cloud VPS (DigitalOcean, Linode, AWS EC2, Ubuntu)
If you have a Linux server or want total control:
```bash
# Clone the repository
git clone https://github.com/YOUR_USERNAME/nta-pay.git
cd nta-pay

# Copy environment config
cp .env.example .env
nano .env

# Start with Docker Compose
docker compose up -d --build
```
Your app is now running on port `8000` with automated container restarts!

---

## 💡 6. TOUGH QUESTIONS YOU MIGHT BE ASKED & HOW TO ANSWER

#### Q1: "Why did you build this with two different runtimes (PHP and Python) instead of just one?"
> **Answer**:
> *"We utilized architectural separation of concerns. PHP 8.2 is exceptionally fast, lightweight, and reliable for handling transactional REST APIs, web routing, and session authentication with minimal server overhead. On the other hand, Python has superior vector graphics, QR encoding, and high-precision document geometry engines via ReportLab and Pillow. Offloading PDF rendering to a specialized microservice ensures the main POS checkout terminal remains completely responsive even under heavy receipt generation loads."*

#### Q2: "What happens if a customer takes longer than 60 seconds to enter their M-Pesa PIN?"
> **Answer**:
> *"The terminal enforces a strict 60-second polling timeout window. If Safaricom does not return a confirmed settlement within that window, the terminal unlocks, logs a `timed_out` state in the audit log, and crucially **never mints a receipt**. The cashier can simply click retry. This completely eliminates receipt issuance on failed or delayed payments."*

#### Q3: "How does this prevent dishonest counter employees from pocketing cash?"
> **Answer**:
> *"Under the NTA-Pay system, no receipt can exist without a verifiable Safaricom checkout transaction ID and an immutable database record. Customers expect and demand an authentic digital receipt with a scannable QR code. If a trader accepts cash without running it through the terminal, no receipt is minted, alerting the customer immediately. Furthermore, every action on the terminal is forensic-logged with the employee's ID, time, and IP address."*

#### Q4: "Can different stalls in the market use their own M-Pesa Till numbers?"
> **Answer**:
> *"Yes. We built multi-destination routing. Through the POS 'M-Pesa Setup' modal or the Admin portal, each trader can specify whether they receive payments to a Buy Goods Till number or a Paybill business shortcode. The Daraja service dynamically routes the STK push to the trader's designated account rather than forcing a single global merchant ID."*
