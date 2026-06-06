# ERFA FARMS API (Current)

Base: `backend/api.php?route=...`

- Auth: `auth.register`, `auth.login`, `auth.logout`, `auth.me`, `auth.reset.request`, `auth.reset.confirm`
- Profile: `profile.update`
- Catalog: `categories.list`, `products.list`, `products.details`
- Cart: `cart.add`, `cart.update`, `cart.remove`, `cart.list`
- Wishlist: `wishlist.add`, `wishlist.remove`, `wishlist.list`
- Orders: `orders.checkout`, `orders.list`, `orders.all`, `orders.status.update`
- Payments: `payments.initialize`, `payments.verify`
- Farm: `livestock.list/create`, `crops.list/create`, `inventory.list/create`, `tasks.list/create`, `tasks.update.status`
- Finance: `finance.expenses.list/create`, `finance.income.list/create`
- Admin/Reports: `admin.users.list`, `reports.summary`
- Notifications: `notifications.list/create`, `notifications.mark.read`
- Email logs: `emails.log`
