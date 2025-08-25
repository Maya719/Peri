## Project Overview

This project is a Laravel-based application called "Peri" that functions as an attendance system. It utilizes the Filament admin panel for its user interface and incorporates features for managing subscriptions, payments (via Stripe), and payroll. The application also supports Google OAuth for authentication.

## Building and Running

### Prerequisites

*   PHP 8.2 or higher
*   Composer
*   Node.js and npm

### Installation

1.  Clone the repository.
2.  Install PHP dependencies: `composer install`
3.  Install JavaScript dependencies: `npm install`
4.  Create a `.env` file by copying `.env.example`.
5.  Generate an application key: `php artisan key:generate`
6.  Run database migrations: `php artisan migrate`

### Running the Application

*   **Development Server:** `npm run dev`
    *   This command starts the Vite development server, the Laravel development server, and a queue worker concurrently.
*   **Testing:** `npm run test`
    *   This command clears the configuration cache and runs the PHPUnit tests.

## Development Conventions

*   **Frameworks:** The project is built on the Laravel 12 framework and utilizes FilamentPHP for the admin panel.
*   **Styling:** Tailwind CSS is used for styling, as indicated by the `tailwind.config.js` file.
*   **Authentication:** The application uses a combination of standard Laravel authentication and Google OAuth.
*   **Database:** The database schema includes tables for users, teams, subscriptions, plans, payments, and attendance.
*   **Code Style:** The project uses `laravel/pint` for code style, which can be run with `vendor/bin/pint`.

## Key Files

*   `composer.json`: Defines the project's PHP dependencies, including Laravel, Filament, and other packages.
*   `package.json`: Defines the project's JavaScript dependencies, including Tailwind CSS and Vite.
*   `routes/web.php`: Contains the application's web routes, including those for authentication, payments, and payroll.
*   `app/Models/User.php`: The User model, which includes fields for personal and work-related information.
*   `app/Filament/Resources/`: This directory contains the Filament resources for managing various aspects of the application, such as subscriptions and payments.
*   `config/app.php`: The main application configuration file.
*   `config/database.php`: The database configuration file.
*   `.env.example`: An example environment file that should be copied to `.env` and configured for the local environment.