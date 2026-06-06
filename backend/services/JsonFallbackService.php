<?php

declare(strict_types=1);

class JsonFallbackService
{
    private static $data = null;

    private static function load(): array
    {
        if (self::$data === null) {
            $path = __DIR__ . '/../../database/fallback_db.json';
            if (file_exists($path)) {
                $content = file_get_contents($path);
                self::$data = json_decode($content, true);
                if (!is_array(self::$data)) {
                    self::$data = [];
                }
            } else {
                self::$data = [];
            }
        }
        return self::$data;
    }

    // ─── Catalog ────────────────────────────────────────────────────────────────

    public static function getProducts(string $search = '', string $category = ''): array
    {
        $data = self::load();
        $products = $data['products'] ?? [];

        if ($search !== '') {
            $products = array_filter($products, fn($p) =>
                stripos($p['name'] ?? '', $search) !== false ||
                stripos($p['category'] ?? '', $search) !== false
            );
        }

        if ($category !== '') {
            $products = array_filter($products, fn($p) => ($p['category'] ?? '') === $category);
        }

        return array_values($products);
    }

    public static function getProductDetails(int $id): ?array
    {
        $data = self::load();
        foreach ($data['products'] ?? [] as $p) {
            if ((int) $p['id'] === $id) return $p;
        }
        return null;
    }

    public static function getCategories(): array
    {
        $data = self::load();

        // Use explicit categories table if present
        if (!empty($data['categories'])) {
            return array_values($data['categories']);
        }

        // Derive from products
        $seen = [];
        $categories = [];
        foreach ($data['products'] ?? [] as $p) {
            $name = $p['category'] ?? '';
            if ($name !== '' && !in_array($name, $seen, true)) {
                $seen[] = $name;
                $categories[] = ['id' => 0, 'name' => $name];
            }
        }
        usort($categories, fn($a, $b) => strcmp($a['name'], $b['name']));
        return $categories;
    }

    // ─── Auth ───────────────────────────────────────────────────────────────────

    public static function login(string $email, string $password): ?array
    {
        $data = self::load();
        foreach ($data['users'] ?? [] as $user) {
            if (strtolower($user['email']) === strtolower($email)) {
                if (password_verify($password, $user['password_hash'])) {
                    return $user;
                }
            }
        }
        return null;
    }

    public static function getUserById(int $id): ?array
    {
        $data = self::load();
        foreach ($data['users'] ?? [] as $user) {
            if ((int) $user['id'] === $id) return $user;
        }
        return null;
    }

    // ─── Users (Admin) ──────────────────────────────────────────────────────────

    public static function getUsers(): array
    {
        $data = self::load();
        return array_map(function ($u) {
            return [
                'id'         => $u['id'],
                'name'       => $u['name'] ?? '',
                'email'      => $u['email'] ?? '',
                'role'       => $u['role'] ?? 'customer',
                'phone'      => $u['phone'] ?? '',
                'address'    => $u['address'] ?? '',
                'created_at' => $u['created_at'] ?? '',
            ];
        }, array_values($data['users'] ?? []));
    }

    // ─── Orders ─────────────────────────────────────────────────────────────────

    public static function getOrders(int $userId): array
    {
        $data = self::load();
        $orders = array_filter($data['orders'] ?? [], fn($o) => (int) $o['user_id'] === $userId);
        return array_map(fn($o) => self::orderSummary($o), array_values($orders));
    }

    public static function getAllOrders(): array
    {
        $data = self::load();
        return array_map(fn($o) => self::orderSummary($o), array_values($data['orders'] ?? []));
    }

    public static function getOrderDetails(int $id): ?array
    {
        $data = self::load();
        foreach ($data['orders'] ?? [] as $o) {
            if ((int) $o['id'] === $id) return $o;
        }
        return null;
    }

    private static function orderSummary(array $o): array
    {
        return [
            'id'             => $o['id'],
            'user_id'        => $o['user_id'],
            'customer_name'  => $o['customer_name'] ?? 'Guest',
            'status'         => $o['status'] ?? 'Pending',
            'total_amount'   => $o['total_amount'] ?? 0,
            'payment_status' => $o['payment_status'] ?? 'Pending',
            'payment_method' => $o['payment_method'] ?? 'Online',
            'created_at'     => $o['created_at'] ?? '',
        ];
    }

    // ─── Inventory ──────────────────────────────────────────────────────────────

    public static function getInventory(): array
    {
        $data = self::load();
        return array_values($data['inventory_items'] ?? []);
    }

    // ─── Livestock ──────────────────────────────────────────────────────────────

    public static function getLivestock(): array
    {
        $data = self::load();
        return array_values($data['livestock'] ?? []);
    }

    // ─── Crops ──────────────────────────────────────────────────────────────────

    public static function getCrops(): array
    {
        $data = self::load();
        return array_values($data['crop_cycles'] ?? []);
    }

    // ─── Tasks ──────────────────────────────────────────────────────────────────

    public static function getTasks(array $user): array
    {
        $data = self::load();
        $tasks = $data['farm_tasks'] ?? [];
        // In fallback mode, staff see all tasks (no assignment table available)
        return array_values($tasks);
    }

    // ─── Finance ────────────────────────────────────────────────────────────────

    public static function getExpenses(): array
    {
        $data = self::load();
        return array_values($data['expenses'] ?? []);
    }

    public static function getIncome(): array
    {
        $data = self::load();
        return array_values($data['income_entries'] ?? []);
    }

    // ─── Reports ────────────────────────────────────────────────────────────────

    public static function getReportsSummary(): array
    {
        $data = self::load();
        $orders   = $data['orders'] ?? [];
        $expenses = $data['expenses'] ?? [];
        $income   = $data['income_entries'] ?? [];

        $paidOrders = array_filter($orders, fn($o) => ($o['payment_status'] ?? '') === 'Paid');
        $totalIncome = array_reduce($income, fn($carry, $e) => $carry + (float) ($e['amount'] ?? 0), 0.0);

        return [
            'total_users'    => count($data['users'] ?? []),
            'total_products' => count($data['products'] ?? []),
            'total_orders'   => count($orders),
            'paid_orders'    => count($paidOrders),
            'expense_total'  => array_reduce($expenses, fn($carry, $e) => $carry + (float) ($e['amount'] ?? 0), 0.0),
            'income_total'   => $totalIncome,
        ];
    }

    // ─── Notifications ──────────────────────────────────────────────────────────

    public static function getNotifications(int $userId): array
    {
        $data = self::load();
        $all  = $data['notifications'] ?? [];
        $result = array_filter($all, fn($n) => $n['user_id'] === null || (int) $n['user_id'] === $userId);
        return array_values($result);
    }
}
