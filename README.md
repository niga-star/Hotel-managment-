# 🏨 Ethiopian Luxury Hotel Management & PMS Suite (v3.0.0)

> **Enterprise Ethiopian Hotel Property Management System (PMS), Guest Booking Engine & Fiscal Compliance Platform**  
> Built with **Vue 3 (Vite)**, **PHP Laravel 11/12**, and **MySQL**.

---

## 🌟 Architecture Overview

```
+---------------------------------------------------------------------------------+
|                               Vue 3 + Vite Frontend                             |
|                              (http://localhost:3000)                            |
|  - Modern Composition API (<script setup>) & TailwindCSS UI                     |
|  - Real-time Deep Linking with Vue Router                                       |
|  - Bilingual Localization Engine (Amharic & English toggles)                    |
|  - Architectural Floor Map & Dynamic Booked-Dates Calendar                      |
|  - Official Ethiopian Legal Tax Invoice Modal with Browser Print Support        |
+----------------------------------------+----------------------------------------+
                                         | REST API (JSON & Multipart)
                                         v
+----------------------------------------+----------------------------------------+
|                               PHP Laravel Backend                               |
|                              (http://127.0.0.1:8000)                            |
|  - 60+ Modular REST API Endpoints under /api/v1/...                             |
|  - Eloquent ORM Models & ACID Database Transactions                             |
|  - Concurrency Lock: Pessimistic Row Locking (`FOR UPDATE`)                     |
|  - Multi-Gateway Payment Engine (Telebirr SuperApp SDK, Chapa IPN, CBE Birr)    |
|  - Ethiopian SMS Dispatch Engine (Afromessage / Ethio Telecom SMS)              |
|  - Role-Based Access Control (RBAC) Security Middleware                         |
|  - Night Audit Ledger & RevPAR Financial Analytics with CSV Export              |
+----------------------------------------+----------------------------------------+
                                         | SQL Queries
                                         v
+----------------------------------------+----------------------------------------+
|                               MySQL Database                                    |
|                            (hotel_management_db)                                |
|  - Users, Room Categories, Rooms, Amenities, Bookings, Payments, Audit Logs     |
+---------------------------------------------------------------------------------+
```

---

## 🚀 Key Modules & Capabilities

1. **Guest Booking Engine**:
   - Dynamic room discovery with seasonal pricing and promo code validator (`LUXURYETHIOPIA`, `HOLIDAY2026`).
   - Dynamic Booked-Dates calendar with automatic collision prevention and real-time validation.
   - Addon services: Bed & Breakfast, Half/Full Board, Ethiopian Coffee Ceremony, and Airport Shuttle.

2. **Visual Hotel Floor Map & Room Matrix**:
   - Interactive architectural floor navigator (Level 1, Level 2, Level 3, Sky Penthouse).
   - Live color-coded occupancy badges (Available [Green], Occupied [Red], Maintenance [Yellow], Dirty [Purple]).
   - Quick status and housekeeping toggle modal with direct occupant lookup.

3. **Front Desk & PMS Operations**:
   - Check-In & Check-Out clearance desks.
   - Legal Ethiopian Tax Invoice (`INV-ETH-XXXXX`) with TIN `0048921849`, 15% VAT, 10% Service Charge, 2% Tourism Levy, and scannable verification QR.
   - Digital registration card generator.

4. **Multi-Channel Ethiopian Payments**:
   - Telebirr H5 web pay and merchant QR code payload generator.
   - Chapa checkout session initialization with HMAC signature verification.
   - Proof of transfer receipt upload and admin review workflow with 20-minute SLA countdown timer.

5. **Housekeeping Mobile PWA Portal**:
   - Dedicated mobile-first turnover queue for housekeeping attendants.
   - 1-tap "Mark Clean", "Inspected", and "Report Defect" buttons.
   - Real-time shift completion progress bar.

6. **Night Audit & Hospitality Financials**:
   - Automated RevPAR (Revenue Per Available Room) and ADR (Average Daily Rate) analytics.
   - One-click daily Night Audit ledger export to CSV for hotel accounting.

7. **Production Containerization**:
   - Multi-stage Docker build for Vue 3 and Laravel.
   - `docker-compose.yml` orchestrating Frontend, Backend, MySQL, and Nginx.

---

## 💻 Quick Start Guide

### Option A: Local Development

#### 1. Database (MySQL via XAMPP)
Ensure MySQL is running on port `3306` with database `hotel_management_db`.

#### 2. Start PHP Laravel Backend
```powershell
cd backend_laravel
php artisan serve --port=8000
```
*API is live at `http://127.0.0.1:8000`*

#### 3. Start Vue 3 Vite Frontend
```powershell
cd frontend
npm run dev
```
*Web application is live at `http://localhost:3000`*

---

### Option B: Docker Compose
```bash
docker compose up -d --build
```

---

## 👨‍💻 Author & Repository

- **Author**: niga-star (`amirhassen2025@gmail.com`)
- **Repository**: [https://github.com/niga-star/Hotel-managment-.git](https://github.com/niga-star/Hotel-managment-.git)
- **License**: ISC
