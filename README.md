# Tabaany Platform

Tabaany is a Symfony 6.4 web application with authentication, user profile management, and a complete admin control center.

## Overview

This project includes:
- Public landing page
- Login and signup flow
- Welcome email on registration
- User profile management with account deletion
- Admin dashboard with analytics and account controls
- User CRUD module

## Tech Stack

- PHP 8.1+
- Symfony 6.4
- Doctrine ORM
- Twig templates
- Symfony Security
- Symfony Mailer with Google Mailer
- MySQL (current local setup)
- Messenger (Doctrine + sync transport for emails)

## Main Features

### Authentication and Security
- Custom login authenticator
- Role-based access control for admin routes
- Blocked-account protection at authentication level
- CSRF protection on profile and admin write actions

### Registration and Email
- Signup form with backend validation
- Password minimum length: 8
- Phone number validation: exactly 8 digits
- Automatic welcome email after successful signup

### Profile
- Profile update page
- Profile picture upload (stored as data URI)
- Account self-deletion endpoint with CSRF protection

### Admin Panel
- Dashboard with key metrics:
  - Total, active, blocked, and pending users
  - Admin users count
  - TOTP enabled users
  - Profile and face-login readiness signals
  - Role distribution
  - 7-day registration trend
- User directory with:
  - Search
  - Status filter
  - Role filter
  - Sorting
- User actions:
  - Block or reactivate account
  - Update role
  - View user details
  - Export users report

## Project Structure

```text
.
|- assets/                  Frontend JS/CSS
|- bin/                     Symfony console and tools
|- config/                  Framework and package configuration
|- docs/admin-history/      Historical implementation notes
|- migrations/              Doctrine migrations (currently empty)
|- public/                  Web root (front controller)
|- src/
|  |- Controller/           Route handlers
|  |- Entity/               Doctrine entities
|  |- Form/                 Symfony form types and validation
|  |- Repository/           Doctrine repositories
|  |- Security/             Authenticator, user provider, user checker
|  |- Service/              Application services (mailer)
|- templates/               Twig views
|- tests/                   Test bootstrap and tests
|- .env                     Default environment config
|- compose.yaml             Optional PostgreSQL service template
|- composer.json            PHP dependencies
```

## Important Routes

### Public
- `/` Home page
- `/login` Login page
- `/signup` Registration page
- `/logout` Logout action

### User
- `/dashboard` User dashboard
- `/profile` View and update profile
- `/profile/delete` Delete current account

### Admin (ROLE_ADMIN required)
- `/admin` Admin dashboard
- `/admin/users` User management list
- `/admin/users/export` Export users report
- `/admin/users/{id}/view` User detail page
- `/admin/users/{id}/status` Update user status
- `/admin/users/{id}/role` Update user role
- `/admin/users/{id}/delete` Delete user

### User CRUD module
- `/utilisateurs`
- `/utilisateurs/new`
- `/utilisateurs/{id}`
- `/utilisateurs/{id}/edit`

## Local Setup

## 1. Requirements
- PHP 8.1 or newer
- Composer
- MySQL server
- Symfony CLI (recommended)

## 2. Install dependencies

```bash
composer install
```

## 3. Configure environment

Edit `.env` or preferably create `.env.local`:

```dotenv
APP_ENV=dev
APP_SECRET=change_me
DATABASE_URL="mysql://root:@127.0.0.1:3306/tabaany?serverVersion=8.0&charset=utf8mb4"
MAILER_DSN="gmail://YOUR_EMAIL:YOUR_APP_PASSWORD@default"
```

Recommended: keep real secrets in `.env.local`, not in `.env`.

## 4. Database setup

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

If no migrations exist yet:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

## 5. Run the app

Using Symfony CLI:

```bash
symfony server:start
```

Or PHP built-in server:

```bash
php -S 127.0.0.1:8000 -t public
```

## 6. Run tests

```bash
php bin/phpunit
```

## Mailer Notes

- The app uses `symfony/google-mailer` with Gmail DSN.
- Welcome emails are sent from the configured sender to the new user email entered in signup.
- Messenger routing currently sends mail synchronously (`SendEmailMessage: sync`).

## Security Notes

- Do not commit production credentials.
- Move secrets to `.env.local` and production secret stores.
- Rotate email app passwords if exposed.

## Current Data Model

- `Role`
  - `id`, `nom`, `description`
- `Utilisateur`
  - identity: `nom`, `prenom`, `email`, `motDePasse`
  - status: `statut` (ACTIF, BLOQUE, EN_ATTENTE)
  - profile: `numTel`, `nfcId`, `profilePicture`, `language`, `themePreference`
  - security: `totpSecret`, `totpEnabled`
  - face login fields: `faceEncoding`, `faceConfidence`, `faceSamplesCount`, `lastFaceLogin`
  - analytics field: `loyaltyPoints`

## Notes

Historical admin setup and migration notes are stored in:
- `docs/admin-history/`

If you want, a second README can be added under `docs/` for API-style endpoint documentation only.
