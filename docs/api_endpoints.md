# API Endpoints and Module Purposes

## 1. Internal API Endpoints (`api/`)
These endpoints are used by the frontend via AJAX for dynamic data retrieval and calculations.

| Endpoint | Method | Purpose | Key Parameters |
|----------|--------|---------|----------------|
| `calculate_balance.php` | GET/POST | Computes projected balance after a proposed payment. | `schedule_id`, `amount_paid` |
| `get_client_properties.php`| GET | Retrieves all properties owned by a specific client. | `client_id` |
| `get_property_info.php` | GET | Fetches detailed pricing and terms for a property. | `property_id` |
| `get_schedules.php` | GET | Lists all payment schedules for a property. | `property_id` |
| `search_payments.php` | GET | Real-time search for payment records. | `query` (name, property, receipt) |

## 2. Core Modules (`modules/`)
Functional blocks for system administration and operations.

### Client Management
- `clients.php`: List and search all clients.
- `client_add.php` / `client_edit.php`: Create and update client profiles.
- `client_properties.php`: View properties linked to a client.
- `client_ledger.php`: Dedicated Statement of Account (SOA) view for a client.

### Property Management
- `properties.php`: Master list of all properties (sold and available).
- `property_add.php` / `property_edit.php`: Inventory management and sales term configuration.

### Payment Operations
- `payments.php`: Main interface for initiating payments.
- `record_payment.php`: Detailed payment entry form with real-time balance preview.
- `payment_ledger.php`: Historical log of all payments across the system.

### Financial Calculations
- `generate_schedule.php`: Automated generation of monthly installments based on total price and terms.
- `apply_late_fees.php`: Background or manual trigger to calculate penalties for overdue items.

## 3. Client Portal (`client/`)
Self-service modules for clients to track their own accounts.
- `dashboard.php`: Summary of accounts, next due dates, and total balance.
- `my_ledger.php`: Personal transaction history and running balance.
- `my_properties.php`: Details of units purchased.
- `my_payments.php`: History of payments made.

## 4. Reports (`reports/`)
Analytical tools for management.
- `aging_report.php`: Detailed analysis of overdue accounts categorized by days past due (30, 60, 90+ days).
