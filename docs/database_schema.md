# Database Schema and Relationships

## 1. Entity Relationship Diagram (Conceptual)
The system uses a relational database model with several key entities:

- **Clients** (1:M) **Properties**
- **Properties** (1:M) **Payment Schedules**
- **Payment Schedules** (1:M) **Payments**
- **Payment Schedules** (1:1) **Invoices**
- **Users** (M:1) **Clients** (Optional linkage for client portal)

## 2. Table Definitions

### `clients`
Stores primary information about the real estate buyers.
- `client_id`: Primary Key
- `name`: Full name of the client
- `email`: Contact email (indexed)
- `phone`: Contact number
- `address`: Physical address
- `created_at/updated_at`: Timestamps

### `properties`
Stores details of real estate units and their sale terms.
- `property_id`: Primary Key
- `client_id`: Foreign Key (Links to `clients`. Can be NULL if property is available)
- `property_name`: Name/Code of the unit
- `total_price`: Total selling price
- `contract_date`: Date of sale/contract
- `term_months`: Number of installment months
- `status`: current status (e.g., 'available', 'sold')

### `payment_schedules`
Stores the breakdown of installments for each sold property.
- `schedule_id`: Primary Key
- `property_id`: Foreign Key (Links to `properties`)
- `schedule_number`: Installment sequence (e.g., 1, 2, 3...)
- `due_date`: When the payment is expected
- `amount_due`: Principal + Interest for that term
- `penalty_amount`: Automatically calculated late fees
- `status`: 'pending', 'paid', 'overdue', 'partially_paid'

### `payments`
Stores records of actual cash inflows.
- `payment_id`: Primary Key
- `schedule_id`: Foreign Key (Links to `payment_schedules`)
- `amount_paid`: Actual amount received
- `date_paid`: Date of transaction
- `receipt_no`: Unique reference/receipt number (indexed)
- `remarks`: Optional notes

### `users`
System user accounts for different roles.
- `user_id`: Primary Key
- `username`: Login identifier
- `password`: Hashed password
- `role`: 'admin', 'finance', 'client'
- `client_id`: Optional Foreign Key (Links to `clients` for 'client' role users)

### `audit_log`
Tracks system-wide activities for security.
- `log_id`: Primary Key
- `user_id`: Foreign Key (Who performed the action)
- `action`: Type of action (Login, Payment, Update, etc.)
- `details`: JSON/Text description of the change
- `ip_address`: Source IP

## 3. Key Relationships and Constraints
- **Cascading Deletes:** Deleting a client removes their properties, schedules, and payments (Data integrity).
- **Unique Constraints:** `receipt_no` in the `payments` table ensures no duplicate receipts.
- **Foreign Keys:** Enforce strict relationships between clients, properties, and schedules.
- **Indexes:** Optimized for searches by client name, email, property name, and receipt number.
