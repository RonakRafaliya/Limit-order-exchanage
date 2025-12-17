# Limit Order Exchange

A limit order exchange application built with Laravel 12, Inertia.js, Vue 3, and Tailwind CSS 4.

## Tech Stack

- **Backend**: Laravel 12, Sanctum
- **Frontend**: Vue 3, Inertia.js v2, TypeScript
- **Styling**: Tailwind CSS 4
- **Real-time**: Pusher/Laravel Echo

## Requirements

- PHP 8.2+
- Node.js 18+
- Composer
- MySQL

## Setup

### Quick Setup

```bash
composer setup
```

This will install dependencies, copy `.env`, generate app key, run migrations, and build assets.

### Manual Setup

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd Limit-order-exchanage
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install Node dependencies**
   ```bash
   npm install
   ```

4. **Environment configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Database setup**

   I have included a `database.sql` file in the root folder with 2 test users and some sample data. You can easily import this into your database:

   **Option A: Using phpMyAdmin**
   - Open phpMyAdmin
   - Create a new database (e.g., `limit_order_exchange`)
   - Click on "Import" tab
   - Choose the `database.sql` file and click "Go"

   **Option B: Using MySQL Workbench**
   - Open MySQL Workbench
   - Connect to your server
   - Go to File → Run SQL Script
   - Select the `database.sql` file and run it

   **Option C: Using command line**
   ```bash
   mysql -u your_username -p your_database_name < database.sql
   ```

   After importing, update your `.env` file with your database details:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=your_database_name
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

6. **Build assets**
   ```bash
   npm run build
   ```

## Running the Application

### Development

```bash
composer run dev
```

This starts the Laravel server, queue worker, log viewer, and Vite dev server concurrently.

### Production

```bash
npm run build
php artisan serve
```

**Test Users for Login:**

   | Email | Password |
   |-------|----------|
   | user1@example.com | password |
   | user2@example.com | password |

## Real-time Updates (Pusher)

This app uses Pusher for real-time order book updates. To test this feature, you need Pusher credentials.

**Note:** I have provided the Pusher credentials in the additional notes while submitting this assessment. Please add them to your `.env` file:

```
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=<from additional notes>
PUSHER_APP_KEY=<from additional notes>
PUSHER_APP_SECRET=<from additional notes>
PUSHER_APP_CLUSTER=<from additional notes>
```

Without these credentials, the app will still work but you won't see real-time updates when orders are placed or matched.