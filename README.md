# Customer Management Portal

A production-grade Customer Management Portal built using **Laravel** (Latest Stable), **Vue 3** (via Inertia.js), **Tailwind CSS**, and **MySQL**. The application features user authentication, email verification, full Customer CRUD, and third-party customer synchronization with real-time progress updates.

For deep-dive details on database structure, API documentation, and tests, please check out the [Architecture & Technical Notes](ARCHITECTURE.md).

---

## Technical Stack
*   **Backend**: Laravel (MVC, dedicated Services, Form Requests, Queues, Database Transactions)
*   **Frontend**: Vue 3 (Composition API), Inertia.js (SPA, form helper, routing integration), Tailwind CSS (Vanilla responsive utility styles)
*   **Database**: MySQL
*   **API Integration**: DummyJSON Authentication & paginated Users API

---

## Core Features
1.  **Authentication & Profile**:
    *   Secure User Login, Registration, Forgot Password recovery, and Email Verification.
    *   Profile updates and password change forms.
2.  **Customer CRUD**:
    *   Add, Edit, and Delete customers.
    *   Search and filter customer records dynamically on the list page.
3.  **Third-Party Synchronization (Sync Customers)**:
    *   Trigger background sync connecting to `DummyJSON` Auth API.
    *   Retrieve all records in paginated batches (30 per batch) with database transaction-safety.
    *   **Duplicate Prevention**: Identifies duplicate customer records by matching incoming emails or external IDs. Any duplicates are marked as failures in the sync log and database entries remain untouched.
    *   **Error Logging**: Stores failed record details (invalid fields or duplicate records) in the database logs and Laravel error logs.
    *   **Progress Indicators**: Shows a real-time progress bar, success/failure counts, and sync history logs.

---

## Installation & Setup

### Prerequisites
*   **PHP**: `^8.2` (preferably `8.4` or later)
*   **Composer**: `^2.x`
*   **Node.js & npm**
*   **MySQL Server**

### Step-by-Step Installation

1.  **Clone / Download the Repository** and navigate to the project directory.

2.  **Install PHP Dependencies**:
    ```bash
    composer install
    ```

3.  **Install Node.js Dependencies**:
    ```bash
    npm install
    ```

4.  **Configure Environment**:
    *   Breeze has set up the default `.env` file. Modify your database settings if necessary:
    ```ini
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=customer_portal
    DB_USERNAME=root
    DB_PASSWORD=
    ```
    *   *(Optional)* Ensure you have a MySQL database named `customer_portal` created on your localhost database server.

5.  **Generate Application Key**:
    ```bash
    php artisan key:generate
    ```

6.  **Run Database Migrations**:
    ```bash
    php artisan migrate
    ```

7.  **Compile Frontend Assets**:
    ```bash
    npm run build
    ```

---

## Running the Application

### Option A: Bare-Metal (Local Host)

To run the application locally on your host machine:

1.  **Start Laravel server**:
    ```bash
    php artisan serve
    ```
2.  **Start Vite dev server (Optional - for hot reloading)**:
    ```bash
    npm run dev
    ```
3.  **Start Laravel Queue Worker** (Required to process the Customer Sync in the background):
    ```bash
    php artisan queue:work
    ```
4.  **Access the App**: Navigate to `http://127.0.0.1:8000`.

---

### Option B: Docker Containers (via Laravel Sail)

If you prefer to run the application in Docker containers, only **Docker Desktop** is required.

1.  **Start the Containers**:
    ```bash
    ./vendor/bin/sail up -d
    ```
    *(On Windows, you can run this using WSL2, Git Bash, or `bash vendor/bin/sail up -d` inside PowerShell).*
2.  **Run Migrations**:
    ```bash
    ./vendor/bin/sail artisan migrate
    ```
3.  **Start Laravel Queue Worker** inside the container:
    ```bash
    ./vendor/bin/sail artisan queue:work
    ```
4.  **Access the App & Mail**:
    *   **Web Portal**: Navigate to **`http://localhost`**
    *   **Mailpit (Email Capture Dashboard)**: Navigate to **`http://localhost:8025`** to view verified emails and password resets.

---

## Running the Test Suite

The test suite includes 39 test cases verifying Authentication, Profile management, Customer CRUD, third-party pagination, and duplicate prevention.

*   To run the tests on your **local host**:
    ```bash
    php artisan test
    ```
*   To run the tests in **Docker**:
    ```bash
    ./vendor/bin/sail artisan test
    ```
