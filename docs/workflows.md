# Business Processes and Workflows

## 1. Property Acquisition Workflow
This process covers how a property moves from available inventory to a sold unit.
1. **Selection:** A staff member selects an "Available" property from the inventory (`modules/properties.php`).
2. **Client Linkage:** The property is assigned to an existing client or a new client profile is created (`modules/client_add.php`).
3. **Financial Computation:**
   - Define `Total Price`, `Down Payment`, `Interest Rate`, and `Term Months`.
   - The system automatically calculates the monthly amortization using the standard formula.
4. **Contract Finalization:** Once terms are agreed upon, the `contract_date` is set, and the property status changes to "Sold".
5. **Schedule Generation:** The system generates a complete `payment_schedules` table for the entire duration of the term.

## 2. Payment Processing Workflow
The core operational process for recording collections.
1. **Search:** Search for a client or specific property in the Payments module (`modules/payments.php`).
2. **Schedule Selection:** View the outstanding installments (schedules) for the property.
3. **Recording Payment:**
   - Select a specific schedule item (usually the oldest pending one).
   - Enter `Amount Paid`, `Date Paid`, and `Receipt Number`.
   - **Real-time Balance:** The UI shows a projected balance update before submission.
4. **Validation:** System checks for duplicate receipt numbers and ensures payment doesn't exceed the balance.
5. **Updates:**
   - Record entry in `payments` table.
   - Update `status` of the `payment_schedules` item ('paid' or 'partially_paid').
   - Log the transaction in `audit_log`.

## 3. Late Fee and Penalty Processing
Ensuring financial compliance for delayed payments.
1. **Threshold Check:** System identifies schedules where `due_date` < `current_date` and status is not 'paid'.
2. **Penalty Calculation:** A pre-defined percentage penalty is applied to the `amount_due`.
3. **Manual Override:** Admin/Finance can manually adjust or waive penalties if necessary.

## 4. Ledger and Reporting Flow
Data flow for financial oversight.
1. **Transaction Entry:** Every schedule (debit) and payment (credit) is stored in the database.
2. **SOA Generation:** The `client_ledger.php` module aggregates all debits and credits chronologically to calculate a running balance.
3. **Aging Analysis:**
   - The system scans all unpaid schedules.
   - Categorizes them into brackets (0-30, 31-60, 61-90, 90+ days).
   - Generates the Aging Report for collection prioritization.

## 5. Client Self-Service Flow
Empowering clients through the portal.
1. **Access:** Client logs in with their dedicated account.
2. **Visibility:** Client views their dashboard for a quick summary of next due dates and total outstanding balance.
3. **Detailed Ledger:** Client can generate and print their own Statement of Account (`client/my_ledger.php`).
4. **Document Access:** View and download uploaded documents related to their purchase (`client/my_documents.php`).
