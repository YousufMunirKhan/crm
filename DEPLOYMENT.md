# Deployment Guide

## Deploying on Existing Database (Production)

When deploying to an **existing database** with live data (customers, users, etc.):

1. **Run only new migrations** – never use `migrate:fresh` or `migrate:refresh`:
   ```bash
   php artisan migrate --force
   ```

2. **(Optional) Ensure admin exists** – safe, does not delete or overwrite existing users:
   ```bash
   php artisan db:seed --class=AdminUserSeeder
   ```
   Or with all seeders:
   ```bash
   php artisan migrate --force --seed
   ```

3. **Never run these in production** – they drop all tables and delete all data:
   - `php artisan migrate:fresh` – drops all tables and reruns migrations
   - `php artisan migrate:refresh` – same as above
   - `php artisan migrate:fresh --seed` – wipes DB then reseeds

### Why might users/admin disappear?

- **migrate:fresh** or **migrate:refresh** – drops all tables including `users`
- **Migration failure + rollback** – if a migration fails, Laravel rolls back the batch; if the batch includes schema changes that affect `users`, data can be lost. Fix the failing migration and rerun `migrate` instead of using `fresh`/`refresh`

### Seeders are safe

`AdminUserSeeder` and `RoleSeeder` use `firstOrCreate` – they only create records if they do not exist. They never truncate or delete users.
