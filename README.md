# Tabaany - patched version

This package was updated to work with the SQL schema from the provided dump and the target database name `tabaanyf`.

## Main changes

- Database connection switched to `tabaanyf`
- Panier and Reservation flow remapped to the real SQL schema
- Paymee card payment integration added in the checkout flow
- Paymee return, cancel, and webhook handling added
- Admin login now redirects directly to the admin dashboard
- Backoffice `panier` and `reservation` pages redesigned as dashboard pages
- Added filters, KPIs, top clients, payment insights, PDF export, and Excel export
- Added safer ownership checks and more consistent reservation status handling

## Important setup notes

### 1. Database
Use the SQL dump you provided and import it into a database named:

```sql
CREATE DATABASE tabaanyf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then import your `.sql` file into `tabaanyf`.

### 2. Environment
The `.env` file already includes Paymee settings. Review these before production deployment:

- `PAYMEE_API_KEY`
- `PAYMEE_ACCOUNT_NUMBER`
- `PAYMEE_BASE_URL`
- `PAYMEE_USE_SANDBOX`
- `APP_URL`

Set `APP_URL` to your real public URL in deployment so Paymee can reach the return and webhook routes.

### 3. Paymee
This integration is implemented as a hosted Paymee checkout redirect from the panier checkout page.

Routes added:

- `/cart/paymee/return`
- `/cart/paymee/cancel`
- `/cart/paymee/webhook`

### 4. Pricing logic
Because the SQL schema does not store a complete normalized pricing model for all establishment types, the project now uses a reasonable service-pricing layer:

- tourist places / voyages: based on place price and number of people
- hotels: estimated from price range and room count
- restaurants / cafés / spa: estimated from price range and adults/children

You can later refine this by adding precise pricing tables per service type.

## Recommended next step

After extracting the zip:

```bash
composer install
php bin/console cache:clear
symfony server:start
```

If you deploy locally and need Paymee webhooks to reach your app, use a public tunnel or deploy to a public server.

# Paymee v12 notes
# Browser returns stay local; webhook can use ngrok if set
