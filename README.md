# 📚 GSTU Library Management System — DIMS

> **A full-stack Database & Information Management System (DIMS) for Gopalganj Science and Technology University's Central Library.**
>
> Features a sleek **Dark Theme UI** with glassmorphism design, dual-role authentication (Admin & Student), real-time book browsing, and automated fine calculation in **BDT (৳)**.

---

## ✨ Core Features

- **🔐 Dual Authentication** — Secure login for **Administrators** and **Students** with BCrypt password hashing (`password_hash` / `password_verify`).
- **📊 Student Dashboard** — Personalized overview displaying total borrowed books, currently borrowed items, and outstanding fines (formatted as ৳ BDT).
- **📖 Book Management** — Full CRUD for books with **category organization**, real-time availability tracking, and paginated browsing.
- **🏷️ Category & Department Management** — Admin-managed hierarchical categories (3NF-compliant) and department lists.
- **🔄 Borrow / Return Workflow** — Request-based borrowing with approval queue (`Pending → issued → Return Pending → returned`).
- **💰 Automated Fine Calculation** — 14-day free period, then **৳10/day** overdue fine computed on-the-fly.
- **📢 Scrolling Marquee Notice Board** — Dynamic policy ticker alerting users of fine policies and library rules.
- **🔍 Live Search & Filters** — Real-time book search by title/author with category filter chips.
- **🎓 Student Self-Registration** — With **real-time department suggestions** via HTML `<datalist>`, auto-generated member IDs (`STU-XXXX`).
- **📱 Fully Responsive** — Mobile-first glassmorphism layout using Tailwind CSS.

---

## 🛠️ Technology Stack

| Layer          | Technology                                                              |
| -------------- | ----------------------------------------------------------------------- |
| **Frontend**   | HTML5, CSS3, JavaScript (ES6+), Tailwind CSS (CDN), Glassmorphism Theme |
| **Backend**    | PHP 8.x (OOP-focused, procedural with PDO abstraction layer)           |
| **Database**   | MySQL 8.x via **PDO** (Native Prepared Statements)                      |
| **Auth**       | BCrypt (`PASSWORD_BCRYPT` — cost factor 10)                             |
| **Server**     | XAMPP / Apache + PHP                                                    |

---

## 🗄️ Database Architecture & Security

### 🔒 PDO — 100% SQL Injection Protection

All database queries use **PHP Data Objects (PDO)** with **native prepared statements** (`PDO::ATTR_EMULATE_PREPARES => false`). This ensures queries and data are sent separately to MySQL, making SQL injection **structurally impossible**.

```php
// config/database.php — Connection configuration
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // ❌ No emulation — real native prepares
];
```

### 🔑 BCrypt Password Hashing

Passwords are hashed using `password_hash($password, PASSWORD_BCRYPT)` and verified with `password_verify()`, ensuring industry-standard cryptographic storage.

### 📐 Third Normal Form (3NF) Relational Model

| Table            | Purpose                                    | Key Relationships                          |
| ---------------- | ------------------------------------------ | ------------------------------------------ |
| `admins`         | Admin credentials                          | —                                          |
| `students`       | Self-registered student accounts           | —                                          |
| `members`        | Library member records (FK bridge)         | Referenced by `issued_books`               |
| `categories`     | Book categories (Fiction, Science, etc.)   | One-to-Many → `books`                      |
| `books`          | Book inventory with quantity tracking      | FK → `categories`                          |
| `departments`    | University department list                 | Used for registration datalist             |
| `issued_books`   | **Junction table** (Many-to-Many)          | FK → `books`, FK → `members`              |

### ⚙️ Database Configuration

| Parameter | Value                    |
| --------- | ------------------------ |
| **Host**  | `localhost`              |
| **Port**  | `3307`                   |
| **Name**  | `library_db`             |
| **Charset** | `utf8mb4`              |
| **User**  | `root` (default XAMPP)   |

---

## 🚀 Installation & Setup Guide

### Prerequisites

- [XAMPP](https://www.apachefriends.org/) (PHP 8.x + MySQL 8.x)
- Git
- A modern web browser

### Step 1 — Clone the Repository

```bash
git clone https://github.com/your-username/gstu-library-dims.git
cd gstu-library-dims
```

Move the project folder to `C:\xampp\htdocs\` (Windows) or `/opt/lampp/htdocs/` (Linux).

### Step 2 — Configure MySQL Port (3307)

XAMPP's default MySQL port is `3306`. This project uses **port 3307**. To avoid conflicts:

1. Open **XAMPP Control Panel**.
2. Click **Config** → **my.ini** next to MySQL.
3. Find the line `port=3306` and change it to:
   ```ini
   port=3307
   ```
4. Restart MySQL from the XAMPP Control Panel.

> **Note:** The connection is already configured in `config/database.php`:
> ```php
> define('DB_PORT', '3307');
> ```

### Step 3 — Import the Database

1. Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
2. Click **New** → create a database named `library_db` with collation `utf8mb4_unicode_ci`.
3. Click the **Import** tab.
4. Choose the `database.sql` file from the project root and click **Go**.

The script will create all tables (`admins`, `students`, `members`, `categories`, `books`, `departments`, `issued_books`) and seed sample data.

### Step 4 — Launch the Application

Open your browser and navigate to:

```
http://localhost/University library system/
```

### 🔑 Default Credentials

| Role    | Identifier   | Password   |
| ------- | ------------ | ---------- |
| Admin   | `admin`      | `admin123` |
| Student | *(register)* | *(set during registration)* |

---

## 🔮 Future Scope

- 💳 **Online Fine Payment** — Integration with bKash / Nagad / Rocket for digital fine collection.
- 📨 **SMS & Email Alerts** — Automated due-date reminders and overdue notifications via SMS gateway (Twilio, etc.) and PHPMailer.
- 📊 **Advanced Reporting** — Graphical analytics dashboard (Chart.js) for borrowing trends, popular books, and revenue from fines.
- 🔔 **Push Notifications** — Real-time borrow/return status updates using WebSockets or Server-Sent Events.
- 📖 **E-Book Integration** — Digital book previews and PDF repository within the system.
- 🌐 **Multi-Language Support** — Localization for Bengali/English interface toggle.

---

## 👨‍💻 Author

**Gopalganj Science and Technology University** — Department of Computer Science & Engineering

- Project Supervisor: *[Faculty Name]*
- Developed as part of the **Database & Information Management System (DIMS)** course.

---

<div align="center">
  <sub>Built with ❤️ by CSE, GSTU — 2026</sub>
</div>
