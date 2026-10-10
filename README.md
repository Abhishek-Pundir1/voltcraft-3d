# VoltCraft 3D — Next-Gen Electronics Engineering & Service Booking Platform

VoltCraft 3D is a full-stack, production-ready web application connecting clients with certified hardware, robotics, embedded systems, and electronics service engineers. Built with vanilla **HTML5**, **CSS3**, **JavaScript**, **Three.js**, **PHP 8+**, and **MySQL**.

---

## ⚡ Key Highlights & Features

- **Interactive 3D Three.js Hero Canvas**: Real-time rotating cybernetic PCB motherboard & microchip core with interactive orbital gimbals, mouse parallax response, and ambient electronics plasma particles.
- **20% Advance Escrow Protocol**: Strict advance fee logic (`20% upfront deposit`, `80% post-repair settlement`) with automated status progression:
  $$\text{PENDING} \rightarrow \text{ACCEPTED} \rightarrow \text{20\% ADVANCE ESCROW} \rightarrow \text{CONFIRMED} \rightarrow \text{IN\_PROGRESS} \rightarrow \text{COMPLETED}$$
- **Role-Based Access Control (RBAC)**:
  - **Customer Portal**: Browse engineers, multi-criteria filtering, submit problem diagnosis requests, pay 20% advance via UPI QR or Gateway, live booking timeline tracking, view receipts, and submit 5-star verified reviews.
  - **Engineer Console**: Live availability toggle (`Available` vs `Busy`), accept/decline service requests with reason modal, update service progress, record diagnostic component findings, view earnings ledger, configure custom service rates and UPI ID / QR code.
  - **Admin Control Center**: Executive analytics with Chart.js, engineer verification approval toggles, customer management, services catalog CRUD, booking status overrides, financial audit logs, and complaint resolution.
- **Glassmorphism & Cyber-Dark UI**: Futuristic design system with custom CSS properties, responsive grids, animated statistic counters, 3D card tilt reflection effects, and instant Dark / Light mode toggle.
- **Production-Grade Backend**: Prepared PDO statements everywhere, password hashing via `PASSWORD_BCRYPT`, input sanitization, and REST JSON API endpoints.

---

## 🔑 Demo Account Credentials

Every demo role can be accessed immediately via the **1-Click Demo Login** buttons on `/login.php`, or manually with:

| Role | Email Address | Password | Description / Specialties |
| :--- | :--- | :--- | :--- |
| **Super Admin** | `admin@voltcraft.com` | `admin123` | Full root access, user controls, service CRUD, revenue charts |
| **Lead Engineer** | `rajesh@voltcraft.com` | `engineer123` | Senior Micro-Soldering & Embedded Systems (Bangalore, ₹750/hr) |
| **Engineer** | `priya@voltcraft.com` | `engineer123` | Industrial Automation & PLC/VFD Systems (Mumbai, ₹900/hr) |
| **Engineer** | `amit@voltcraft.com` | `engineer123` | Solar Inverter & Battery BMS Specialist (Delhi NCR, ₹650/hr) |
| **Engineer** | `sneha@voltcraft.com` | `engineer123` | Drone Avionics & Robotics Hardware (Hyderabad, ₹850/hr) |
| **Customer** | `customer@voltcraft.com` | `customer123` | Rohan Mehta (Active bookings, MacBook logic board repair) |
| **Customer** | `neha@voltcraft.com` | `customer123` | Neha Kapoor (Industrial VFD Inverter booking) |

---

## 🛠️ XAMPP & Local Setup Instructions

### 1. Prerequisites
- **XAMPP** (Apache + MySQL / MariaDB + PHP 8.1+)

### 2. Folder Placement
Ensure the project is located within your XAMPP `htdocs` directory:
```
C:/xampp/htdocs/electronice website 3/
```

### 3. Database Setup (MySQL)
1. Open your browser and navigate to **phpMyAdmin**: `http://localhost/phpmyadmin/` (or open the MySQL CLI / XAMPP Shell).
2. Create a new database named **`voltcraft_db`**:
   ```sql
   CREATE DATABASE IF NOT EXISTS voltcraft_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Import the included `database.sql` file:
   - In phpMyAdmin: Select `voltcraft_db` &rarr; click **Import** &rarr; choose `database.sql` &rarr; click **Go**.
   - Or via Windows PowerShell / CMD:
     ```powershell
     Get-Content "C:\xampp\htdocs\electronice website 3\database.sql" | C:\xampp\mysql\bin\mysql.exe -u root
     ```

### 4. Running the Website
1. Start **Apache** and **MySQL** from your XAMPP Control Panel.
2. Open your web browser and visit:
   ```
   http://localhost/electronice%20website%203/
   ```
3. The platform auto-detects `BASE_URL` dynamically, allowing seamless routing across subfolders or custom virtual hosts.

---

## 📁 Project Architecture

```
/
├── index.php                      # Homepage (Three.js 3D Hero, services, featured engineers, 20% advance diagram)
├── about.php                      # Mission, lab standards, benchmarks, and credentials
├── services.php                   # Electronic services catalog with pricing & booking triggers
├── engineers.php                  # Interactive directory with live AJAX search & multi-filtering
├── engineer-detail.php            # Detailed engineer profile, skills progress, UPI QR, and reviews
├── how-it-works.php               # Complete guide to 20% advance escrow and guarantees
├── contact.php                    # Technical inquiry form & laboratory helpline
├── faq.php                        # Categorized accordion FAQ
├── login.php                      # Unified login with 1-click test credentials
├── register.php                   # Role-based registration (Customer or Engineer)
├── logout.php                     # Secure session termination
│
├── customer/
│   ├── dashboard.php              # Customer command center with live active tracking & stats
│   ├── profile.php                # Personal information & default diagnostic address
│   ├── engineers.php              # Internal engineer directory & direct booking modal
│   ├── new-request.php            # Service request form with live 20% advance preview & upload
│   ├── requests.php               # Service requests ledger, statuses & 20% advance pay buttons
│   ├── booking-details.php        # Booking timeline dossier, balance settlement & review modal
│   ├── payment.php                # 20% advance escrow payment gateway with UPI QR & verification
│   ├── payments.php               # Payment transactions ledger with printable receipts
│   └── notifications.php          # In-app notification center
│
├── engineer/
│   ├── dashboard.php              # Engineer diagnostic console, availability toggle & requests
│   ├── profile.php                # Profile editor, skills manager, custom rates & UPI QR setup
│   ├── requests.php               # Incoming requests with Accept / Decline reason modal
│   ├── request-details.php        # Drill-down request view with client information
│   ├── bookings.php               # Bookings management, status transitions & diagnostic findings
│   ├── earnings.php               # Financial analytics, payouts, and revenue charts
│   ├── payments.php               # Customer payments audit log
│   └── notifications.php          # Engineer notifications center
│
├── admin/
│   ├── login.php                  # Dedicated administrator login
│   ├── dashboard.php              # Executive overview with Chart.js analytics & platform KPIs
│   ├── users.php                  # User accounts directory
│   ├── customers.php              # Customer accounts management & suspension controls
│   ├── engineers.php              # Engineer approvals & IPC verification badge toggle
│   ├── services.php               # Electronic services catalog CRUD & pricing controls
│   ├── bookings.php               # Platform bookings audit & status override
│   ├── payments.php               # Complete financial audit ledger
│   ├── complaints.php             # Customer complaints & support ticket resolution
│   └── reports.php                # Revenue reports & engineer performance rankings
│
├── api/
│   ├── auth.php                   # Login, register, session endpoints
│   ├── booking.php                # Request creation, acceptance, status updates
│   ├── engineer.php               # Search, multi-criteria filtering, availability toggle
│   ├── customer.php               # Customer profile updates & reviews
│   ├── payment.php                # 20% advance processing, reference validation, balance settlements
│   ├── notification.php           # Notifications retriever & mark-as-read
│   └── stats.php                  # System metrics & analytics for dashboards
│
├── assets/
│   ├── css/
│   │   ├── style.css              # Cyber-dark design system, typography, glass cards, buttons
│   │   ├── dashboard.css          # Portal layouts, tracking timeline, stats cards, tables
│   │   └── three-hero.css         # Three.js canvas container & floating HUD elements
│   ├── js/
│   │   ├── three.min.js           # Local Three.js library (r128)
│   │   ├── three-hero.js          # Interactive 3D cybernetic core, gimbals, particles & mouse parallax
│   │   ├── tilt.js                # Vanilla 3D perspective tilt effect on interactive cards
│   │   ├── main.js                # Themes, mobile navigation, counter animations, modals, toasts
│   │   └── api-client.js          # REST client wrapper for AJAX endpoints
│   ├── images/
│   │   ├── qr/                    # Vector UPI QR codes (SVG)
│   │   └── avatars/               # Stylized profile avatars
│   └── uploads/                   # Uploaded board schematics & custom QR codes
│
├── config/
│   └── database.php               # PDO connection, dynamic BASE_URL detection & constants
│
├── includes/
│   ├── header.php                 # Global navigation bar, auth dropdown, theme toggle
│   ├── footer.php                 # Footer links, trust badges, script attachments
│   ├── customer-sidebar.php       # Customer portal navigation
│   ├── engineer-sidebar.php       # Engineer portal navigation
│   ├── admin-sidebar.php          # Admin portal navigation
│   ├── auth.php                   # Authentication and role authorization guards
│   └── functions.php              # Sanitization, badges, currency formatters, SVG avatar fallback
│
└── database.sql                   # Complete schema + 11 users + 8 services + bookings + payments + reviews
```

---

## 🔒 Security Best Practices Implemented

1. **SQL Injection Immunity**: 100% of database operations use parameterized PDO prepared statements.
2. **XSS Protection**: All dynamic user-submitted content is sanitized using `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
3. **Password Security**: Passwords are cryptographically salted and hashed using `password_hash($pass, PASSWORD_BCRYPT)`.
4. **Session Protection**: `session_regenerate_id(true)` upon authentication; strict role-based access redirection.
5. **CSRF & Verification Safeguards**: Form tokens and strict payment transaction reference checks before confirmation.
6. **Safe File Uploads**: Extension whitelist (`jpg`, `jpeg`, `png`, `webp`, `pdf`) with random hex hashing and size limits.

---

## 💡 Customization & External Payment Gateway Hook
The advance payment system is structured with isolated transaction processors in `api/payment.php` and `customer/payment.php`. To plug in **Razorpay** or **Stripe**:
1. Add your API Keys in `config/database.php`.
2. Swap the verification logic in `api/payment.php` with the official Razorpay SDK (`verifyPaymentSignature()`) or Stripe webhook handler.

---

*VoltCraft 3D &copy; 2026. Built with precision for electronics engineering professionals.*
