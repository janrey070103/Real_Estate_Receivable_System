# User Roles and Security

## 1. Authentication Flow
The system uses session-based authentication implemented in `includes/auth.php`.
1. **Login:** `auth/login.php` validates credentials against the `users` table using `password_verify()`.
2. **Session Creation:** Upon successful login, `login_user()` initializes session variables (`user_id`, `username`, `role`, `client_id`).
3. **Session Security:**
   - `session_regenerate_id(true)` is called during login to prevent session fixation.
   - User Agent and IP address are stored in the session for verification.
   - `verify_session_security()` checks if the User Agent or IP has changed during a session.
   - `check_session_timeout()` automatically logs out users after 8 hours of inactivity.

## 2. User Roles and Permissions

| Role | Description | Permissions |
|------|-------------|-------------|
| **Admin** | System Administrator | Full access to all modules, including user management, audit logs, and system settings. |
| **Finance** | Financial Officer | Access to clients, properties, payments, invoices, reports, and notifications. Restricted from user management and system-level logs. |
| **Client** | Property Buyer | Access only to the Client Portal (`client/` directory). Can only see their own properties, ledger, and payments. |

## 3. Access Control Mechanisms
- **`require_login()`**: Ensures the user is authenticated before accessing a page.
- **`require_module_access($module)`**: Granular check based on the user's role and the specific module requested.
- **`require_client()`**: Specific restriction for the Client Portal to ensure only users with the 'client' role can enter.
- **`access_denied.php`**: A centralized page that handles unauthorized access attempts and redirects users based on their role.

## 4. Security Measures

### Database Security
- **Prepared Statements (PDO):** All database interactions use prepared statements to prevent SQL Injection.
- **Password Hashing:** Passwords are stored using `PASSWORD_DEFAULT` (Bcrypt) and never in plain text.

### Input/Output Security
- **CSRF Protection:** Transactions (like recording payments or updating clients) require a valid CSRF token generated per session.
- **XSS Prevention:** All user-supplied data displayed in the UI is escaped using `htmlspecialchars()` via the `sanitize_output()` or `format_X()` helpers.
- **Data Validation:** `includes/validation_helpers.php` provides centralized logic for validating email, phone, and numeric inputs.

### System Accountability
- **Audit Logging:** Every critical action (payment recording, client updates, property deletions) is recorded in the `audit_log` table with the user ID, timestamp, and details of the change.
- **Error Logging:** System errors are logged to the server's error log rather than displayed to the user in production mode.
