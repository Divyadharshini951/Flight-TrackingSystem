# SkyRoute — Flight Booking & Tracking System

A full-stack student project using **React + Vite**, **PHP REST API**, and **MySQL**. Includes flight search, flight status tracking, booking flow, booking lookup, and an admin seed dataset.

## Requirements
- PHP 8+ with PDO MySQL enabled
- MySQL 8+ / MariaDB
- Node.js 18+
- XAMPP/WAMP/LAMP (XAMPP is easiest on Windows)

## 1. Start and test the PHP API
Open PowerShell in the project root (the folder that contains `backend`, `database`, and `frontend`), then run:
```powershell
Set-Location "C:\Users\DIVYADHARSHINI M\Downloads\SkyRoute_Flight_Booking_Tracking_Project (1)"
C:\xampp\php\php.exe -S 127.0.0.1:8000
```
Keep this terminal open. Apache can also be running; it is not required for this PHP development-server option.

Open `http://127.0.0.1:8000/backend/api.php?action=health`. It should return `{"ok":true,"service":"SkyRoute PHP API"}` even if MySQL is down. This verifies PHP/API only.

The included `frontend/.env` points to `http://127.0.0.1:8000/backend/api.php`. If you change the API address, edit `VITE_API_BASE` there and restart Vite. XAMPP's defaults (`127.0.0.1:3306`, user `root`, empty password) are in `backend/config.example.php`. No `backend/config.php` is required with these defaults. For custom MySQL credentials, copy `backend/config.example.php` to `backend/config.php` and edit that copy.

## 2. Check MySQL and load flights
1. Start MySQL in XAMPP. In a second PowerShell terminal, test its connection:
   ```powershell
   C:\xampp\mysql\bin\mysqladmin.exe --connect-timeout=3 -h 127.0.0.1 -P 3306 -u root ping
   ```
   A working server prints `mysqld is alive`. If it reports a lost connection or handshake error, MySQL has not completed startup. Check `C:\xampp\mysql\data\mysql_error.log` and the XAMPP Control Panel logs. This computer's earlier MySQL log reported `Incorrect file format 'db'`; that is a MySQL system-table problem, not a missing SkyRoute flight table.
   Before any repair, stop MySQL in XAMPP and make a **copy** (do not move) of `C:\xampp\mysql\data` to another drive with enough free space. Keep the original folder unchanged. After the copy, this read-only check can inspect the affected Aria system table:
   ```powershell
   C:\xampp\mysql\bin\aria_chk.exe --check "C:\xampp\mysql\data\mysql\db.MAI"
   ```
   Do not use a repair option yet. If the check reports errors, keep the backup and get help using the full output before attempting repair.
   Do not delete/reset `C:\xampp\mysql\data` or import SkyRoute tables while MySQL is unhealthy.
2. Only after the ping succeeds, open phpMyAdmin at `http://localhost/phpmyadmin`. If the `skyroute` database has not been created, import `database/schema.sql` once. If you already have an older SkyRoute database, back it up and run `database/migrations/001_auth.sql` once for the account tables.
3. Test `http://127.0.0.1:8000/backend/api.php?action=database-health`. It should return `{"ok":true,"database":"connected"}`. Then test `http://127.0.0.1:8000/backend/api.php?action=flights`; it should return a JSON flight list.
4. Start/restart the React app:
   ```powershell
   Set-Location "C:\Users\DIVYADHARSHINI M\Downloads\SkyRoute_Flight_Booking_Tracking_Project (1)\frontend"
   npm run dev
   ```
   Open `http://localhost:5173`. The header reports database availability; **Sign in** opens login and registration.

## Features
- Search flights by origin, destination, and date
- Live-style status board / flight tracking by flight number
- Account registration and login with securely hashed passwords
- Authenticated booking with passenger details and seat count
- Account-scoped booking reference lookup
- Responsive dashboard-style UI
- PHP input validation and PDO prepared statements
- MySQL tables for flights, users, API tokens, and bookings

## Demo limitations
- Flight inventory is seeded sample data, not live airline inventory.
- Tracking status is demo database status, not live GPS/airline data.
- No real payment processing is included.
- For real deployment, add rate limiting, CSRF protections, HTTPS, transactional email, and airline/flight-data provider integrations.
