<?php

declare(strict_types=1);

class JsonFallbackService
{
    private static $data = null;

    private static function load()
    {
        if (self::$data === null) {
            $path = __DIR__ . '/../../database/fallback_db.json';
            if (file_exists($path)) {
                $content = file_get_contents($path);
                self::$data = json_decode($content, true);
                if (!is_array(self::$data)) {
                    self::$data = ['users' => [], 'products' => []];
                }
            } else {
                self::$data = ['users' => [], 'products' => []];
            }
        }
        return self::$data;
    }

    public static function getProducts(string $search = '', string $category = '')
    {
        $data = self::load();
        $products = $data['products'] ?? [];

        if ($search !== '') {
            $products = array_filter($products, function ($p) use ($search) {
                return stripos($p['name'], $search) !== false || stripos($p['category'], $search) !== false;
            });
        }

        if ($category !== '') {
            $products = array_filter($products, function ($p) use ($category) {
                return $p['category'] === $category;
            });
        }

        return array_values($products);
    }

    public static function getProductDetails(int $id)
    {
        $data = self::load();
        foreach ($data['products'] as $p) {
            if ((int)$p['id'] === $id) return $p;
        }
        return null;
    }

    public static function getCategories()
    {
        $data = self::load();
        $products = $data['products'] ?? [];
        $categories = [];
        foreach ($products as $p) {
            if (isset($p['category']) && !in_array($p['category'], $categories)) {
                $categories[] = $p['category'];
            }
        }
        sort($categories);
        return array_map(function ($name) {
            return ['id' => 0, 'name' => $name];
        }, $categories);
    }

    public static function login(string $email, string $password)
    {
        $data = self::load();
        foreach ($data['users'] as $user) {
            if (strtolower($user['email']) === strtolower($email)) {
                if (password_verify($password, $user['password_hash'])) {
                    return $user;
                }
            }
        }
        return null;
    }

    public static function getUserById(int $id)
    {
        $data = self::load();
        foreach ($data['users'] as $user) {
            if ((int)$user['id'] === $id) return $user;
        }
        return null;
    }
}
