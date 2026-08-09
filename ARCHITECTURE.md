# Architecture & Technical Notes

This document describes the database schema, API endpoints, test cases, and architectural design of the Customer Management Portal.

---

## 1. Architecture Design

The application follows the classic **Model-View-Controller (MVC)** pattern with architectural enhancements suited for production-level apps:

*   **Thin Controllers**: Controllers only receive incoming requests, validate them using Form Requests, delegate business logic to dedicated Service classes, and return responses.
*   **Service Layer**: Contain all reusable business logic.
    *   `CustomerService`: Implements Customer CRUD actions.
    *   `CustomerSyncService`: Orchestrates the connection, JWT authentication, paginated API fetching, batch insertion, duplicate checking, and error logging for third-party syncs.
*   **Form Requests**: Validate user inputs before the request hits controller logic, keeping verification rules separate.
*   **Inertia.js Integration**: Used to build a modern Single Page Application (SPA) using Vue 3 on the frontend while utilizing Laravel's robust routing, authentication, and session handling on the backend. This avoids the complexity of tokens or JWTs on the frontend.
*   **Queueing System**: Long-running synchronization operations are pushed to a background queue (`SyncCustomersJob`) to prevent HTTP request timeouts and provide a responsive user experience.
*   **Polling-based Progress Status**: To show real-time synchronization progress, the frontend polls a status API every 1.5 seconds when a sync is active, retrieving statistics from the database `sync_logs` table.

---

## 2. Database Schema

The portal utilizes two custom tables in addition to default Laravel schema:

### `customers` Table
Stores local customer records with Soft Deletes support.
```sql
CREATE TABLE `customers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `external_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL, -- Format: 'dummyjson-{id}'
  `first_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active', -- 'active' or 'inactive'
  `address_street` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_city` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_state` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_zip` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_country` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL, -- For Soft Deletes
  PRIMARY KEY (`id`),
  UNIQUE KEY `customers_email_unique` (`email`),
  UNIQUE KEY `customers_external_id_unique` (`external_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### `sync_logs` Table
Tracks synchronization history and real-time execution progress.
```sql
CREATE TABLE `sync_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'running', -- 'running', 'success', 'failed'
  `total_records` int(11) NOT NULL DEFAULT 0,
  `processed_records` int(11) NOT NULL DEFAULT 0,
  `failed_records` int(11) NOT NULL DEFAULT 0,
  `error_message` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failures_log` text COLLATE utf8mb4_unicode_ci DEFAULT NULL, -- JSON formatted array of individual record failures
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. API Documentation

All routes except landing/welcome page require authentication and email verification (`auth`, `verified` middleware).

### Dashboard & Pages (Inertia Render)
*   **`GET /dashboard`**
    *   **Description**: Renders Dashboard containing total counts and recent logs.
    *   **Response**: Inertia page with props: `stats` and `recentLogs`.
*   **`GET /customers`**
    *   **Description**: Renders Customers index page showing searchable, filterable, paginated customer list.
    *   **Query Parameters**:
        *   `search` (string, optional) - filter by first_name, last_name, email, or phone.
        *   `status` (string, optional) - filter by `active` or `inactive`.
    *   **Response**: Inertia page with props: `customers` and `filters`.

### RESTful Customer CRUD (AJAX/Redirect)
*   **`GET /address-lookup`**
    *   **Description**: Local proxy API that forwards autocomplete requests to OpenStreetMap's Nominatim Search API to bypass browser CSP/CORS blocks.
    *   **Query Parameters**: `q` (string, required) - search text (minimum 3 characters).
    *   **Response**: Array of address suggestion objects.
*   **`POST /customers`**
    *   **Description**: Store a new customer.
    *   **Parameters**: `first_name` (required), `last_name` (required), `email` (required, unique), `phone` (nullable), `status` (required: active/inactive), `address_street` (required), `address_city` (required), `address_state` (required), `address_zip` (required), `address_country` (required).
*   **`PUT /customers/{customer}`**
    *   **Description**: Update an existing customer.
    *   **Parameters**: Same as creation (ignores unique email rule for the updated record itself).
*   **`DELETE /customers/{customer}`**
    *   **Description**: Soft-deletes a customer record from the database.

### Synchronization Control (JSON API)
*   **`POST /sync/trigger`**
    *   **Description**: Triggers background synchronization.
    *   **Response** (200 OK):
        ```json
        {
          "message": "Synchronization process started.",
          "log": {
            "id": 5,
            "status": "running",
            "started_at": "2026-08-06T22:00:00Z"
          }
        }
        ```
    *   **Response** (422 Unprocessable - if another sync is active):
        ```json
        {
          "message": "A synchronization process is already running.",
          "log": { ... }
        }
        ```
*   **`GET /sync/status/{log}`**
    *   **Description**: Poll progress of a specific sync operation.
    *   **Response**:
        ```json
        {
          "id": 5,
          "status": "running",
          "total_records": 100,
          "processed_records": 60,
          "failed_records": 0,
          "error_message": null,
          "failures_log": null,
          "started_at": "2026-08-06T22:00:00Z",
          "completed_at": null
        }
        ```
*   **`GET /sync/latest-status`**
    *   **Description**: Fetch status of the last synchronization attempt.
*   **`GET /sync/logs`**
    *   **Description**: Retrieve the list of the last 10 synchronization attempts.

---

## 4. Test Cases Document

The application testing suite contains 39 unit and feature tests. These tests can be run using `php artisan test`.

### Auth & Profile Feature Tests (`tests/Feature/Auth/` & `tests/Feature/ProfileTest.php`)
*   **`AuthenticationTest`**: Tests login form rendering, user authentication with valid credentials, and denial of invalid attempts.
*   **`EmailVerificationTest`**: Verifies redirection of unverified users and successful verification via signed URL.
*   **`PasswordReset` / `PasswordUpdate`**: Tests request forgot password link, reset password, and user password update.
*   **`RegistrationTest`**: Tests new user signup and creation in database.
*   **`ProfileTest`**: Tests updating profile details, email verification resets, and account deletion.

### Customer CRUD Feature Tests (`tests/Feature/CustomerCrudTest.php`)
*   **`test_guests_cannot_access_customers_endpoints`**: Asserts guest users are redirected to login for index, store, update, and delete actions.
*   **`test_authorized_user_can_list_customers`**: Checks that logged-in users see the list page and registered customers.
*   **`test_authorized_user_can_create_customer`**: Asserts validated customer creation stores the record in database.
*   **`test_create_customer_validates_required_fields`**: Asserts validation error session keys are present for missing fields.
*   **`test_create_customer_validates_email_uniqueness`**: Checks email uniqueness validation rule.
*   **`test_authorized_user_can_update_customer`**: Asserts customer modification updates columns.
*   **`test_update_customer_ignores_own_email_uniqueness`**: Verifies that updating a user with their existing email does not throw a validation error.
*   **`test_authorized_user_can_delete_customer`**: Asserts record is soft-deleted and removed from active list.

### Customer Sync Feature Tests (`tests/Feature/CustomerSyncTest.php`)
*   **`test_guests_cannot_trigger_sync`**: Asserts guest users cannot trigger sync and get redirected.
*   **`test_authorized_user_can_trigger_sync`**: Verifies triggering sync returns JSON structure and dispatches `SyncCustomersJob` to queue.
*   **`test_sync_processes_and_stores_customers_successfully`**: Fakes API requests to `DummyJSON`, runs the `CustomerSyncService`, and asserts customer records are created with correct details.
*   **`test_sync_prevents_duplicate_records`**: Asserts that running sync on duplicate emails updates the existing record rather than creating a new database record (count remains 1).
*   **`test_sync_logs_failures_for_invalid_records`**: Fakes API response containing an invalid user (missing email) and asserts that the sync process registers it as a failure, increments `failed_records` count, and records the reason in `failures_log`.
*   **`test_sync_soft_deleted_customer_is_failure_and_not_restored`**: Asserts that sync fakes fail for soft-deleted local customers rather than restoring them.
