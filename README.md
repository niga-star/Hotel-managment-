# Luxury Ethiopian Hotel Management PMS & Guest Booking Engine

> **Standards Compliance:** ISO/IEC/IEEE 29148:2018 & IEEE Std 830-1998  
> **Database Engine:** **MySQL (hotel_management_db)** via connection pool (`mysql2/promise`) with zero hardcoded files  
> **Architecture:** Three-Tier Decoupled Client-Server-Database Architecture with ACID Transactional Concurrency Controls  
> **Cultural Identity:** Traditional Ethiopian Luxury Hospitality with authentic golden **Tibeb** header embroidery motifs, emerald green palette (`#0B4D34`), and gold accents (`#D4A329`).

---

## 🌟 Live Demo & Quick Start

The unified application is currently running live on:  
👉 **`http://localhost:5000`**

### MySQL Database Configuration (`Backend/.env`)
```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=root
DB_PASSWORD=
DB_NAME=hotel_management_db
JWT_SECRET=ethiopian_luxury_hotel_jwt_secret_2026
PORT=5000
```

### Running the Application
```bash
# 1. Start the MySQL-backed unified server (Serves REST API + Frontend UI):
node Backend/src/server.js

# 2. Run the automated test suites:
node Backend/test-api.js            # Core API & Database endpoints
node Backend/test-webhooks.js       # Phase 3 Ethiopian payment webhooks
node Backend/test-upload.js         # Phase 4 Direct file uploads
node Backend/test-notifications.js  # Phase 5 Automated Email & SMS notifications

# 3. (Optional) Re-seed or migrate MySQL schema anytime:
# Schema DDL: Backend/scripts/schema.sql
node Backend/scripts/seed_mysql.js
```

---

## 📬 Phase 5: Automated Guest Notifications (Email & Ethio SMS)

The platform automatically sends branded email receipts and SMS confirmations directly tied to guest booking state transitions:
- **Triggers:**
  1. **On Booking Confirmed:** Sends HTML Email featuring Ethiopian Tibeb luxury header, booking details, and digital QR receipt link, plus Ethio Telecom SMS confirmation.
  2. **On Front-Desk Check-In:** Sends welcome SMS and email with Room number, Wi-Fi credentials (`EthiopianHotel_Guest`), and concierge info.
  3. **On Front-Desk Check-Out:** Sends thank you email and SMS with final invoice reference and review prompt.
- **Database Table (`guest_notifications` in MySQL):** Tracks `channel` (`email` / `sms`), `recipient`, `subject`, `message_body`, `delivery_reference`, and `sent_at` timestamp.
- **Endpoints:**
  - `GET /api/v1/notifications/booking/:booking_id` (View logs for a booking)
  - `GET /api/v1/notifications` (Admin global notification audit trail)
  - `POST /api/v1/notifications/resend` (1-click resend confirmation from UI)

---

## 📸 Phase 4: Direct File Uploads & Local Media Storage

The application supports direct file upload for room photography and inventory:
- **Endpoints:** `POST /api/v1/upload/room-image` (single upload) and `POST /api/v1/upload/multiple` (batch gallery upload).
- **Storage Location:** `Backend/uploads/rooms/` served statically via `http://localhost:5000/uploads/rooms/`.
- **Validation:** 5MB file size limit, MIME type verification (`image/jpeg`, `image/png`, `image/webp`).
- **Database Binding:** The uploaded image relative path is saved directly into the MySQL `room_images` table with zero hardcoded values.
- **Frontend Dropzone:** In **Admin PMS -> Manage Rooms**, staff can drag and drop or click to upload photo files with live preview.

---

## 💳 Phase 3: Ethiopian Payment Gateways & Webhooks

The platform features full support for Ethiopian payment gateways with zero hardcoded values, stored in the MySQL `payments` table:

| Gateway | Endpoints | Method & Payload Details |
| :--- | :--- | :--- |
| **Chapa** (Cards & Local Wallets) | `/api/v1/payments/initialize`<br>`/api/v1/payments/webhook/chapa` | Initiates hosted transaction or returns sandbox checkout URL. Live IPN validates HMAC SHA256 signature (`x-chapa-signature`) and transitions payment state to `verified`. |
| **Telebirr** (Ethio Telecom) | `/api/v1/payments/initialize`<br>`/api/v1/payments/webhook/telebirr` | Generates official USSD prompt (`*127*100223*AMOUNT#`) and encrypted QR payload. Webhook settles order via `outTradeNo` and sets `paid_at` timestamp in MySQL. |
| **CBE Birr** (Commercial Bank) | `/api/v1/payments/webhook/cbe-birr` | Verifies and reconciles mobile banking and CBE deposit slips. |
| **Webhook Simulator** | `/api/v1/payments/simulate-webhook` | Single-click tester to trigger live IPN clearance for any `TX-XXXXX` reference and watch MySQL update in real-time. |

You can test gateway webhooks directly from the UI by navigating to **Admin PMS -> Payments** and clicking **`[⚡ Test Webhook IPN]`** on any pending reservation.

---

## 🏛️ System Architecture & Subsystems (All 9 Screens Implemented)

### 1. Guest Subsystem
- **Screen 4: Room Discovery & Availability Calendar Drawer**
  - Instant category filtering (`Suite`, `Deluxe`, `Standard`), keyword search, and favorites wishlist.
  - Interactive month calendar date-range picker with booked-date validation.
  - Payment channel selection: Credit Card, Mobile Money (**Telebirr** / **CBE Birr**), Digital Wallet.
  - Dynamic price calculation in **ETB** (Ethiopian Birr) and **USD**.
- **Screen 5: Room Details Showcase & Verified Reviews**
  - High-resolution hero photo + 4-thumbnail gallery viewer.
  - Amenity badges: Free Wi-Fi, Breakfast Included, Airport Shuttle, Spa.
  - Sticky booking widget with adult/child guest selectors and instant room reservation.
  - Verified guest reviews and ratings directly retrieved from MySQL.
- **Screen 3: My Bookings & Digital Booking Receipt**
  - Active and past reservation management with `[Modify]` and `[Cancel]` actions.
  - Slide-out Digital Booking Receipt with unique booking reference (`#ETH-89274`).
  - Scannable 2D QR Code check-in barcode for touchless front-desk verification.
  - Printable PDF receipt generation.
- **Screen 7: Sign In / Create Account**
  - Ethiopian luxury resort stone architecture welcome visual.
  - Dual Auth form with Full Name, Ethiopian phone number (`+251` prefix), Email, and Password with visibility toggle.

---

### 2. Front Desk & Reception Subsystem
- **Screen 8: Bookings & Check-In / Check-Out Desk**
  - Operational front-desk console with `Arriving Today (15)`, `Departing Today (8)`, and `Active Guests (42)` filter tabs.
  - `+ Walk-In Booking` modal for guests arriving without online reservations.
  - Real-time Reference search (`#ETH-89274`).
  - 1-click `Confirm Check-In` (marks room as occupied in MySQL) and `Check-Out & Invoice` (marks room available and generates invoice).
  - Quick QR Code Check-In scanner with simulated instant camera verification.

---

### 3. Executive Hotel Management Subsystem (PMS)
- **Screen 2: Hotel Admin Management Dashboard**
  - 4 Executive KPI cards: Room Occupancy (`86%` radial meter), Active Bookings (`42`), Check-Ins Today (`15`), and Monthly Revenue (`ETB 248,500`).
  - Interactive Room Status Matrix: Color-coded room pills (101 to 202) showing `Available` (Green), `Occupied` (Gold), and `Maintenance` (Slate).
  - Live state toggling on any room pill directly updating MySQL tables.
- **Screen 6: Manage Hotel Rooms (Inventory CRUD)**
  - Comprehensive room table with photo, category pills, price in ETB, status badges, edit & delete actions.
  - Integrated "Add / Edit Room" panel with room number, category dropdown, price, capacity, amenity checkboxes, and image upload dropzone.
- **Screen 9: Payments & Transactions Management**
  - Channel performance cards tracking **Telebirr (42%)**, **CBE Birr (31%)**, **Chapa Gateway (18%)**, and **Cash**.
  - Detailed transaction ledger with payment method badges (`Telebirr`, `CBE Birr`, `Chapa`, `Cash`).
  - Status badges (`✓ Verified`, `Pending`), `[Verify Payment]` action, and `[Print Receipt]`.
- **Screen 1: Financial & Occupancy Reports**
  - `Monthly / Annual` segmented pill toggle.
  - Grouped dual-bar chart comparing Monthly Revenue (ETB) and Occupancy across Jan-Dec.
  - Donut chart displaying revenue contribution by room category (`Suite`, `Deluxe`, `Standard`).
  - "Monthly Financial Summary" table detailing Month, Total Bookings, Occupancy %, Revenue, and Net Growth.
  - PDF & Excel export actions.

---

## 🗄️ MySQL Database (Zero Hardcoding)

Every single UI element, rate, status, transaction, and review is stored in normalized relational tables in MySQL (`hotel_management_db`):
- `users`: Staff, receptionist, and guest accounts with hashed passwords and Ethiopian phone numbers.
- `room_categories`: `Suite`, `Deluxe`, `Standard` with base pricing.
- `rooms`: Physical room inventory with room numbers, floor, capacity, prices, and status (`available`, `occupied`, `maintenance`).
- `room_images`: Gallery images for each room.
- `amenities` & `room_amenities`: Wi-Fi, Breakfast, Shuttle, Spa, Swimming Pool, Balcony, etc.
- `bookings`: Reservations with date ranges, guest references, reference codes (`#ETH-XXXXX`), and cryptographic QR verification hashes.
- `payments`: Transaction records with payment gateways (`telebirr`, `cbe_birr`, `chapa`, `cash`).
- `reviews`: Verified stay reviews and 5-star ratings.

### Anti-Double-Booking Concurrency Protection
Reservation requests execute within atomic MySQL transactions using pessimistic locking:
```sql
SELECT id FROM bookings
WHERE room_id = ?
  AND booking_status IN ('confirmed', 'checked_in')
  AND (check_in_date < ? AND check_out_date > ?)
FOR UPDATE;
```
If a conflicting date range exists, MySQL safely rolls back the transaction and returns `HTTP 409 Conflict`, guaranteeing zero double bookings under heavy traffic.
