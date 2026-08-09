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

## 4. Test Cases

The suite contains **57 tests**: 44 feature tests and 13 unit tests. Run the full suite with `php artisan test`, or run only the unit suite with `php artisan test --testsuite=Unit`.

### Feature tests

#### Authentication (`tests/Feature/Auth/AuthenticationTest.php`)
- `test_login_screen_can_be_rendered`
- `test_users_can_authenticate_using_the_login_screen`
- `test_users_can_not_authenticate_with_invalid_password`
- `test_users_can_logout`
- `test_login_fails_with_invalid_captcha`

#### Email verification (`tests/Feature/Auth/EmailVerificationTest.php`)
- `test_email_verification_screen_can_be_rendered`
- `test_unverified_users_can_request_a_new_verification_notification`
- `test_email_can_be_verified`
- `test_email_is_not_verified_with_invalid_hash`

#### Password confirmation (`tests/Feature/Auth/PasswordConfirmationTest.php`)
- `test_confirm_password_screen_can_be_rendered`
- `test_password_can_be_confirmed`
- `test_password_is_not_confirmed_with_invalid_password`

#### Password reset (`tests/Feature/Auth/PasswordResetTest.php`)
- `test_reset_password_link_screen_can_be_rendered`
- `test_reset_password_link_can_be_requested`
- `test_reset_password_screen_can_be_rendered`
- `test_password_can_be_reset_with_valid_token`

#### Password update (`tests/Feature/Auth/PasswordUpdateTest.php`)
- `test_password_can_be_updated`
- `test_correct_password_must_be_provided_to_update_password`

#### Registration (`tests/Feature/Auth/RegistrationTest.php`)
- `test_registration_screen_can_be_rendered`
- `test_new_users_can_register`

#### Customer CRUD (`tests/Feature/CustomerCrudTest.php`)
- `test_guests_cannot_access_customers_endpoints`
- `test_authorized_user_can_list_customers`
- `test_authorized_user_can_access_create_page`
- `test_authorized_user_can_access_edit_page`
- `test_authorized_user_can_create_customer`
- `test_create_customer_validates_required_fields`
- `test_create_customer_validates_email_uniqueness`
- `test_authorized_user_can_update_customer`
- `test_update_customer_ignores_own_email_uniqueness`
- `test_authorized_user_can_delete_customer`
- `test_customer_listing_applies_search_status_and_name_sorting`

#### Customer sync (`tests/Feature/CustomerSyncTest.php`)
- `test_guests_cannot_trigger_sync`
- `test_authorized_user_can_trigger_sync`
- `test_sync_trigger_is_rejected_while_another_sync_is_running`
- `test_sync_processes_and_stores_customers_successfully`
- `test_sync_prevents_duplicate_records` — rejects a matching email or external ID without altering the existing record.
- `test_sync_prevents_duplicate_external_ids_when_the_email_has_changed` — rejects a matching external ID even when the incoming email is new.
- `test_sync_logs_failures_for_invalid_records`
- `test_sync_soft_deleted_customer_is_failure_and_not_restored`

#### Dashboard (`tests/Feature/DashboardAccessTest.php`)
- `test_authenticated_user_can_access_dashboard_at_root_url`

#### Profile (`tests/Feature/ProfileTest.php`)
- `test_profile_page_is_displayed`
- `test_profile_information_can_be_updated`
- `test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged`

#### CAPTCHA replenish (`tests/Feature/CaptchaReplenishTest.php`)
- `test_it_returns_a_replenished_captcha_image`

### Unit tests

#### CAPTCHA rule (`tests/Unit/CaptchaTest.php`)
- `test_it_skips_validation_when_captcha_is_disabled`
- `test_it_accepts_the_expected_code_case_insensitively`
- `test_it_accepts_a_code_from_the_captcha_pool`
- `test_it_rejects_an_unknown_code`

#### CAPTCHA service (`tests/Unit/CaptchaServiceTest.php`)
- `test_it_generates_images_and_persists_a_captcha_pool`
- `test_it_keeps_only_the_ten_most_recent_captcha_codes`

#### Customer model (`tests/Unit/CustomerTest.php`)
- `test_it_formats_only_available_address_parts`
- `test_it_exposes_the_customers_full_name`

#### DummyJSON API service (`tests/Unit/DummyJsonApiServiceTest.php`)
- `test_it_authenticates_with_configured_credentials`
- `test_it_rejects_missing_credentials`
- `test_it_rejects_a_successful_auth_response_without_a_token`
- `test_it_fetches_a_user_batch_with_the_token_and_pagination`
- `test_it_throws_when_fetching_users_fails`