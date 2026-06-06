<?php

declare(strict_types=1);

class AiService
{
    public static function chat(string $userMessage, array $history = [], array $context = []): string
    {
        $config = require __DIR__ . '/../config.php';
        $apiKey = $config['groq']['api_key'];

        $userRole = $context['user']['role'] ?? 'guest';
        $isAdmin = ($userRole === 'admin' || $userRole === 'staff');

        if ($isAdmin) {
            $systemPrompt = "
                You are 'MTH Bot Admin Assistant', the official internal AI for MTH GLOBAL RESOURCES administrators.
                Your primary goal is to help admins manage the platform, analyze inventory, and review orders.
                
                APP CONTEXT:
                - MTH GLOBAL RESOURCES Admin Dashboard.
                - Pages: Overview, Products, Orders, Users.
                
                YOUR CAPABILITIES:
                1. Help admins understand stock levels and order statuses.
                2. Provide quick links to admin tools.
                3. Use your tools to search the database for specific products or metrics.
                
                INTERACTIVE BUTTONS:
                - Use [[Button Label|/url]] format to create a button.
                - Example: 'You can manage products here: [[Manage Products|/pages/admin/products.html]]'
                
                TONE: Professional, concise, data-driven, and operational.
            ";
        } else {
            $systemPrompt = "
                You are 'MTH Bot', the official AI assistant for MTH GLOBAL RESOURCES customers.
                Your primary goal is to help customers shop for quality water and beverages and assist with their orders.
                
                APP CONTEXT:
                - MTH GLOBAL RESOURCES is a premium e-commerce platform for water and beverage products.
                - Pages: Home, Shop (/pages/shop.html), Cart (/pages/cart.html).
                
                YOUR CAPABILITIES:
                1. Answer questions about delivery (Lagos-based), product freshness, and ordering.
                2. Suggest products based on needs (e.g., 'Do you have eggs?').
                3. Use your tools to search for products and add items to the user's cart on their behalf.
                
                INTERACTIVE BUTTONS:
                - Use [[Button Label|/url]] format to create a button.
                - Example: 'Go to your cart: [[View Cart|/pages/cart.html]]'
                
                CRITICAL RULE:
                - You are talking to a CUSTOMER. NEVER mention admin features, internal operations, staff capabilities, or stock management interfaces.
                
                TONE: Friendly, helpful, and refreshing. Use emojis sparingly (💧, 📦, 🚚).
            ";
        }

        $contextString = "";
        if (!empty($context)) {
            $contextString = "\n\nCURRENT USER CONTEXT:\n" . json_encode($context, JSON_PRETTY_PRINT);
        }

        $systemPrompt .= $contextString . "\nYOU HAVE TOOLS AVAILABLE. Use search_products to find products in the database. Use add_to_cart to add items to the cart.";

        if (strpos($apiKey, 'YOUR_GROQ_API_KEY') !== false) {
            return "Hello! I am MTH Bot. Please configure my Groq API Key in config.php to start chatting!";
        }

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $msg) {
            $messages[] = $msg;
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_products',
                    'description' => 'Search for products in the store to get their ID, price, and stock status.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'The search term (e.g. eggs, poultry, tomatoes).'
                            ]
                        ],
                        'required' => ['query']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'add_to_cart',
                    'description' => 'Add a product to the user\'s shopping cart. Requires user to be logged in.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [
                                'type' => 'integer',
                                'description' => 'The ID of the product to add.'
                            ],
                            'quantity' => [
                                'type' => 'integer',
                                'description' => 'The quantity to add.'
                            ]
                        ],
                        'required' => ['product_id', 'quantity']
                    ]
                ]
            ]
        ];

        if ($isAdmin) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'get_low_stock_products',
                    'description' => 'Retrieve a list of products that have low stock.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'threshold' => ['type' => 'integer', 'description' => 'The stock threshold (e.g. 10).']
                        ],
                        'required' => ['threshold']
                    ]
                ]
            ];
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'update_order_status',
                    'description' => 'Update the status of an existing order.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'order_id' => ['type' => 'integer', 'description' => 'The ID of the order.'],
                            'status' => ['type' => 'string', 'enum' => ['pending', 'processing', 'shipped', 'delivered', 'cancelled'], 'description' => 'The new status.']
                        ],
                        'required' => ['order_id', 'status']
                    ]
                ]
            ];
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'get_recent_orders',
                    'description' => 'Retrieve recent orders that need attention.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => ['type' => 'integer', 'description' => 'Number of orders to retrieve (e.g. 5).']
                        ],
                        'required' => ['limit']
                    ]
                ]
            ];
        }

        for ($i = 0; $i < 3; $i++) {
            $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'model' => 'llama-3.1-8b-instant',
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
                'temperature' => 0.7
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                error_log("Groq Curl Error: " . $err);
                return "I'm having trouble reaching the operations brain (Connection Error).";
            }

            $data = json_decode($response, true);
            if (isset($data['error'])) {
                error_log("Groq API Error: " . ($data['error']['message'] ?? 'Unknown error'));
                return "MTH Bot says: " . ($data['error']['message'] ?? 'Something went wrong.');
            }

            $message = $data['choices'][0]['message'] ?? null;
            if (!$message) break;

            $messages[] = $message;

            if (!empty($message['tool_calls'])) {
                foreach ($message['tool_calls'] as $toolCall) {
                    $funcName = $toolCall['function']['name'];
                    $args = json_decode($toolCall['function']['arguments'], true) ?: [];
                    $result = self::executeTool($funcName, $args);
                    
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCall['id'],
                        'name' => $funcName,
                        'content' => json_encode($result)
                    ];
                }
                continue;
            }

            return $message['content'] ?? "I'm sorry, I'm having trouble understanding that right now.";
        }

        return "I'm sorry, my thought process took too long.";
    }

    private static function executeTool(string $name, array $args): mixed
    {
        $pdo = db();
        switch ($name) {
            case 'search_products':
                $query = $args['query'] ?? '';
                $stmt = $pdo->prepare('SELECT id, name, price, stock, category FROM products WHERE name LIKE ? OR category LIKE ? LIMIT 5');
                $stmt->execute(['%'.$query.'%', '%'.$query.'%']);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);

            case 'add_to_cart':
                $userId = currentUserId();
                if (!$userId) return ['error' => 'User is not logged in. Please tell them to login first.'];
                $productId = (int)($args['product_id'] ?? 0);
                $qty = (int)($args['quantity'] ?? 1);
                
                $check = $pdo->prepare('SELECT id, stock, name FROM products WHERE id = ?');
                $check->execute([$productId]);
                $product = $check->fetch();
                if (!$product) return ['error' => 'Product not found.'];
                if ($qty > $product['stock']) return ['error' => 'Not enough stock. Only ' . $product['stock'] . ' available.'];

                $exists = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?');
                $exists->execute([$userId, $productId]);
                $item = $exists->fetch();
                
                if ($item) {
                    $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?')->execute([$item['quantity'] + $qty, $item['id']]);
                } else {
                    $pdo->prepare('INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)')->execute([$userId, $productId, $qty]);
                }
                return ['success' => true, 'message' => "Successfully added $qty of {$product['name']} to cart."];

            case 'get_low_stock_products':
                $threshold = (int)($args['threshold'] ?? 10);
                $stmt = $pdo->prepare('SELECT id, name, stock FROM products WHERE stock < ? ORDER BY stock ASC');
                $stmt->execute([$threshold]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);

            case 'update_order_status':
                $orderId = (int)($args['order_id'] ?? 0);
                $status = $args['status'] ?? 'pending';
                $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
                $stmt->execute([$status, $orderId]);
                return ['success' => true, 'message' => "Order #$orderId status updated to $status."];

            case 'get_recent_orders':
                $limit = (int)($args['limit'] ?? 5);
                $stmt = $pdo->prepare('SELECT id, total_amount, status, created_at FROM orders ORDER BY created_at DESC LIMIT ' . $limit);
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);

            default:
                return ['error' => 'Unknown tool executed.'];
        }
    }
}
