# Truehost Deployment Notes

- Provision PHP 8.1+ and MySQL database.
- Upload project files under public web root.
- Update `backend/config.php` with production DB credentials.
- Import `database/schema.sql` then `database/seed.sql`.
- Ensure session storage is writable.
- Set HTTPS and secure cookies in production.
- Configure Paystack secret/public keys as server env vars before switching placeholder verify to live API verification.
