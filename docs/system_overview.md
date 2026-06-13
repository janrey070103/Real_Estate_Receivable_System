# System Overview and Architecture

## 1. Project Introduction
The **Real Estate Receivable System** is a comprehensive solution designed to manage real estate transactions, client accounts, property inventory, and installment-based payment collections. It streamlines the process from property inquiry to final payment, ensuring financial accuracy through automated calculations and detailed ledger tracking.

## 2. Technical Stack
- **Backend:** PHP (Procedural/Functional style with PDO for database security)
- **Database:** MySQL/MariaDB
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla + AJAX), Bootstrap 5
- **Icons/UI:** Unicode/Emoji symbols, Custom CSS
- **Visualization:** Mermaid.js for diagrams

## 3. Directory Structure
```text
.
├── api/                # AJAX endpoints for dynamic UI updates
├── assets/             # Static resources (CSS, JS, Fonts)
├── auth/               # Authentication handlers (Login/Logout)
├── client/             # Client Portal (Restricted to 'client' role)
├── db/                 # SQL schemas, seed data, and migration scripts
├── diagrams/           # System architecture and flow diagrams
├── docs/               # Technical documentation
├── includes/           # Core library files, helpers, and DB connection
├── modules/            # Admin/Staff functional modules (Clients, Payments, etc.)
├── reports/            # Financial and analytical reports
├── templates/          # Reusable UI components (Header, Footer, Nav)
└── .env.example        # Environment configuration template
```

## 4. Core Components Architecture
The system follows a modular architecture where core logic is separated from UI and data handling:

- **Data Layer:** MySQL database storing relational data for clients, properties, and payments.
- **Logic Layer (`includes/`):** Contains helper functions for authentication, financial calculations (amortization, penalties), and input validation.
- **Service Layer (`api/`):** Provides JSON endpoints for frontend components to interact with the backend without full page reloads.
- **Presentation Layer (`modules/`, `client/`, `templates/`):** Responsive web interface built with Bootstrap 5, providing different views based on user roles.

## 5. Key Design Principles
- **Compute First:** All financial terms must be calculated and confirmed before a contract is finalized.
- **Property-Centric Transactions:** A property must be selected before any payment or schedule can be processed.
- **Auditability:** Every significant action is logged in the `audit_log` table for accountability.
- **Separation of Concerns:** Clear distinction between Administrative/Finance tasks and Client self-service features.
