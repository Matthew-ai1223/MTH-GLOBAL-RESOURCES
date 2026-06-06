# ERFA FARMS Full Application

## Stack

- Frontend: HTML, CSS, JavaScript
- Backend: PHP (PDO)
- Database: MySQL

## Setup

1. Create the database and tables:
   - Open phpMyAdmin or MySQL CLI
   - Run `database/schema.sql`
   - Run `database/seed.sql`
2. Adjust DB credentials if needed:
   - Edit `backend/config.php`
3. Serve via XAMPP Apache:
   - Put project under `htdocs`
   - Open `http://localhost/new%20files/nu-farm/index.html`

## Main Modules

- Authentication + reset password
- E-commerce: categories, products, cart, wishlist, checkout, orders
- Payments: initialize + verify Paystack-style flow
- Farm management: livestock, crops, inventory, staff tasks
- Finance: expenses/income entries
- Admin: users, orders, reports, notifications

## Page Entry Points

- Landing: `index.html`
- Auth: `pages/login.html`, `pages/register.html`, `pages/reset-password.html`
- Public commerce: `pages/shop.html`, `pages/product-details.html`, `pages/cart.html`, `pages/checkout.html`
- Customer: `pages/customer/`
- Staff: `pages/staff/`
- Admin: `pages/admin/`

## Docs

- API: `docs/api.md`
- Tests: `docs/test-plan.md`
- Deployment: `docs/deploy-truehost.md`
