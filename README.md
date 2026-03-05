# AutoManager Pro 🚗
### Car Showroom Management System

---

## What is AutoManager Pro?

AutoManager Pro is a full-featured, web-based car showroom management system built with PHP, MySQL, and Tailwind CSS. It is designed for Pakistani car dealerships and showrooms to manage their complete operations from a single dashboard.

The system covers the full lifecycle of every vehicle — from the moment it is purchased and added to inventory, through lead tracking and sales, all the way to financial reporting and commission management.

**Key highlights:**
- Multi-role access control (Admin, Manager, Salesperson, Accountant)
- Complete inventory management with photo uploads and cost tracking
- Lead and customer relationship management (CRM)
- Sales invoicing with auto-calculated profit
- Accounts, transactions, and commission payment tracking
- Expense management with categorisation
- Financial reports (monthly & yearly)
- Configurable settings (showroom info, currency, invoice prefix, tax)
- Custom fields for inventory (Auction Grade, Import Country, etc.)
- Collapsible sidebar with role-based menu hiding
- CSRF protection on all forms

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 7.4 or higher (8.x recommended) |
| MySQL | 5.7 or higher (8.x recommended) |
| Web Server | Apache (XAMPP recommended for local) |
| Browser | Chrome, Firefox, Edge (modern versions) |

> This system was built and tested on **XAMPP** on Windows. The instructions below assume XAMPP.

---

## Installation

### Step 1 — Copy project files

Copy the entire `car-showroom` folder into your XAMPP web root:

```
C:\xampp\htdocs\car-showroom\
```

Your folder structure should look like this:

```
car-showroom/
├── config/
│   └── database.php
├── core/
│   ├── Auth.php
│   ├── Database.php
│   ├── functions.php
│   └── Permissions.php
├── dashboard/
│   └── index.php
├── modules/
│   ├── inventory/
│   ├── leads/
│   ├── sales/
│   ├── accounts/
│   ├── expenses/
│   ├── reports/
│   ├── users/
│   └── settings/
├── public/
│   ├── css/
│   │   ├── layout.css
│   │   └── fa/               ← Font Awesome (local copy)
│   └── uploads/              ← Car images saved here
├── views/
│   └── layouts/
│       ├── sidebar.php
│       └── topbar.php
├── car_showroom.sql           ← Database file
├── reset.php                  ← Data reset utility
└── index.php                  ← Login page
```

---

### Step 2 — Create the database

1. Start **Apache** and **MySQL** in XAMPP Control Panel
2. Open your browser and go to: `http://localhost/phpmyadmin`
3. Click **New** in the left sidebar
4. Create a database named exactly: `car_showroom`
5. Select the new database, click the **Import** tab
6. Click **Choose File** → select `car_showroom.sql` from the project root
7. Click **Go** — all tables will be created automatically

---

### Step 3 — Configure database connection

Open `config/database.php` and update these values if needed:

```php
define('DB_HOST',     'localhost');
define('DB_USER',     'root');        // your MySQL username
define('DB_PASS',     '');            // your MySQL password (blank for XAMPP default)
define('DB_NAME',     'car_showroom');
define('BASE_URL',    'http://localhost/car-showroom');
```

> If you changed MySQL root password in XAMPP, update `DB_PASS` accordingly.

---

### Step 4 — Set uploads folder permissions

Make sure the uploads directory is writable. On Windows with XAMPP this usually works by default. If car images fail to upload, right-click the folder and ensure it is not read-only:

```
C:\xampp\htdocs\car-showroom\public\uploads\
```

---

### Step 5 — Install Font Awesome locally (already included)

Font Awesome 6.5.0 is included locally in `public/css/fa/`. No internet connection is required for icons to work. If icons show as squares, check that these files exist:

```
public/css/fa/all.min.css
public/css/fa/webfonts/fa-solid-900.woff2
public/css/fa/webfonts/fa-solid-900.ttf
public/css/fa/webfonts/fa-regular-400.woff2
public/css/fa/webfonts/fa-regular-400.ttf
```

---

### Step 6 — Open the app

Go to: `http://localhost/car-showroom/`

You should see the **AutoManager Pro login screen**. Use the Admin credentials from `CREDENTIALS.md`.

---

## How to Use

### First Login

1. Go to `http://localhost/car-showroom/`
2. Log in with the Admin account (see `CREDENTIALS.md`)
3. Go to **Settings** → fill in your showroom name, phone, email, city
4. Go to **Admin → Users** → add your staff accounts
5. Go to **Admin → Permissions** → adjust role permissions if needed

---

### Day-to-Day Workflow

**1. Add a car to inventory**
- Inventory → Add Car
- Fill in make, model, year, color, purchase price, sale price
- Add any repair or preparation costs
- Upload photos, fill custom fields
- Save → car appears as "Available"

**2. Track a lead**
- Leads → Add Lead
- Enter customer name, phone, link to a car
- Set source (OLX, PakWheels, Walk-in, Facebook, Referral)
- Update status as the conversation progresses
- Add notes after each follow-up

**3. Record a sale**
- Sales → New Sale
- Select the car and enter buyer details
- Enter final sale price, transfer fee, commission
- System calculates profit automatically
- Save → invoice generated, car marked as Sold

**4. Manage finances**
- Accounts → view all cash and bank accounts
- Expenses → record rent, salaries, fuel, marketing
- Accounts → Commissions → pay salesperson commission
- All transactions logged automatically

**5. View reports**
- Reports → monthly and yearly summaries
- Revenue, expenses, profit, top-selling cars

---

### User Roles

| Role | Access |
|---|---|
| **Admin** | Everything — full access always |
| **Manager** | Inventory, Leads, Sales, Accounts, Expenses, Reports |
| **Salesperson** | Inventory (view), Leads, Sales |
| **Accountant** | Sales (view), Accounts, Expenses, Reports |

> Permissions can be customised per role under **Admin → Permissions**.
> Changes take effect on the user's **next login**.

---

### Custom Fields

To add extra fields to the inventory form (e.g. Auction Grade, Import Country, Sunroof):

1. Go to **Admin → Custom Fields** (or sidebar under Admin)
2. Click **Add Field**
3. Choose field type: Text, Number, Dropdown, Checkbox, or Textarea
4. Fields appear automatically in the Add Car and Edit Car forms

---

## Resetting the App

A `reset.php` file is included in the root for wiping all data while keeping the Admin account.

1. Go to `http://localhost/car-showroom/reset.php`
2. Enter the reset token (see `CREDENTIALS.md`)
3. Confirm — all cars, sales, leads, accounts, expenses and settings are deleted
4. Admin user and all roles/permissions are preserved and re-seeded

> ⚠️ **Delete `reset.php` before deploying to a live/production server.**

---

## Folder Structure Reference

| Folder / File | Purpose |
|---|---|
| `config/database.php` | DB credentials and BASE_URL |
| `core/Auth.php` | Login, logout, session management |
| `core/Database.php` | MySQLi wrapper (fetchOne, fetchAll, execute, insert) |
| `core/functions.php` | Helpers: clean(), formatPrice(), setFlash(), getSetting() |
| `core/Permissions.php` | RBAC — load, check, seed permissions |
| `modules/inventory/` | All car management pages |
| `modules/leads/` | Lead/CRM pages |
| `modules/sales/` | Sales and invoice pages |
| `modules/accounts/` | Accounts and transactions pages |
| `modules/expenses/` | Expense management |
| `modules/reports/` | Reports and analytics |
| `modules/users/` | User management |
| `modules/settings/` | Settings and permissions management |
| `views/layouts/sidebar.php` | Sidebar nav (included on every page) |
| `views/layouts/topbar.php` | Top bar (included on every page) |
| `public/css/layout.css` | Global layout styles |
| `public/uploads/` | Uploaded car images |
| `car_showroom.sql` | Full database schema + seed data |
| `reset.php` | Data reset utility (delete after use) |
| `CREDENTIALS.md` | All login credentials |

---

## Troubleshooting

| Problem | Solution |
|---|---|
| Icons show as squares | Hard refresh with `Ctrl+Shift+R`. Check FA files exist in `public/css/fa/` |
| Can't log in | Check `config/database.php` credentials. Ensure MySQL is running |
| Blank page / errors | Enable PHP error display in `php.ini`: `display_errors = On` |
| Images not uploading | Check `public/uploads/` folder exists and is writable |
| Permission denied error | User must log out and back in after role permission changes |
| Car not in sale dropdown | Only "Available" cars appear. Check car status in inventory |
| Database import fails | Ensure you created the `car_showroom` database first before importing |

---

## Security Notes

- All forms are CSRF protected
- Passwords are hashed with `password_hash()` (bcrypt)
- All user input is sanitised with `clean()` / `htmlspecialchars()`
- Role-based access enforced on every page via `Permissions::require()`
- **Delete `reset.php` before going live**
- Change all default passwords before production use

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.x (procedural + OOP classes) |
| Database | MySQL 8.x via MySQLi |
| Frontend | Tailwind CSS (CDN) + custom CSS |
| Icons | Font Awesome 6.5.0 (local) |
| Fonts | Plus Jakarta Sans (Google Fonts) |
| Server | Apache via XAMPP |

---

*AutoManager Pro — Built for Pakistani car showrooms*
