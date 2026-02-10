# Supplier & Purchase Order Manager

A PHP + MySQL application for managing suppliers, purchase orders, receiving goods, and stock updates.

## Features

- **Supplier Management**: Name, phone, address, notes.
- **Purchase Orders**:
  - Step-by-step PO creation flow.
  - Multiple line items per PO.
  - Ordered quantity vs received quantity tracking.
  - Statuses: `Pending`, `Partial`, `Completed`.
- **Receiving Goods**:
  - Record received quantities per PO line.
  - Auto-update stock levels.
- **Dashboard**:
  - Pending orders summary.
  - Supplier-wise purchase history.
- **Security**:
  - CSRF protection on form submissions.
  - PDO prepared statements.
- **UI**:
  - Bootstrap admin-style layout.
  - Responsive and mobile-friendly tables.

## Requirements

- PHP 8.1+
- MySQL 8+

## Setup

1. Create a MySQL database:
   ```sql
   CREATE DATABASE supplier_po_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Configure environment variables (optional):
   - `DB_HOST`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`
3. Run schema setup:
   ```bash
   php setup.php
   ```
4. Start local server:
   ```bash
   php -S 0.0.0.0:8000
   ```
5. Open `http://localhost:8000`.

## Database Tables

- `suppliers`
- `purchase_orders`
- `purchase_order_items`
- `receiving_logs`
- `stock_items`

Schema is located at `sql/schema.sql`.
