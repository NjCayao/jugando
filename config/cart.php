<?php
// config/cart.php - Sistema de carrito de compras - VERSIÓN LIMPIA
class Cart
{
    private static $sessionKey = 'cart';

    /**
     * Agregar producto al carrito
     */
    public static function addItem($productId, $quantity = 1)
    {
        try {
            // Validar producto
            $product = self::getProductDetails($productId);
            if (!$product) {
                throw new Exception('Producto no encontrado');
            }

            if (!$product['is_active']) {
                throw new Exception('Producto no disponible');
            }

            // Validar cantidad
            $quantity = max(1, min(10, intval($quantity)));

            // Inicializar carrito si no existe
            if (!isset($_SESSION[self::$sessionKey])) {
                $_SESSION[self::$sessionKey] = [];
            }

            // Agregar o actualizar producto
            if (isset($_SESSION[self::$sessionKey][$productId])) {
                $_SESSION[self::$sessionKey][$productId]['quantity'] = min(
                    10,
                    $_SESSION[self::$sessionKey][$productId]['quantity'] + $quantity
                );
            } else {
                $_SESSION[self::$sessionKey][$productId] = [
                    'id' => $productId,
                    'name' => $product['name'],
                    'slug' => $product['slug'],
                    'price' => floatval($product['price']),
                    'is_free' => $product['is_free'],
                    'image' => $product['image'],
                    'category_name' => $product['category_name'],
                    'quantity' => $quantity,
                    'added_at' => time()
                ];
            }

            $totals = self::getTotals();

            return [
                'success' => true,
                'message' => 'Producto agregado al carrito',
                'cart_count' => self::getItemsCount(),
                'cart_total' => $totals['total']
            ];
        } catch (Exception $e) {
            logError("Error en Cart::addItem: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar cantidad de un producto
     */
    public static function updateItem($productId, $quantity)
    {
        try {
            if (!isset($_SESSION[self::$sessionKey][$productId])) {
                throw new Exception('Producto no encontrado en el carrito');
            }

            $quantity = intval($quantity);

            if ($quantity <= 0) {
                return self::removeItem($productId);
            }

            // Limitar cantidad máxima
            $quantity = min(10, $quantity);

            $_SESSION[self::$sessionKey][$productId]['quantity'] = $quantity;

            $totals = self::getTotals();

            return [
                'success' => true,
                'message' => 'Cantidad actualizada',
                'cart_count' => self::getItemsCount(),
                'cart_total' => $totals['total'],
                'totals' => $totals,
                'item_subtotal' => formatPrice($_SESSION[self::$sessionKey][$productId]['price'] * $quantity)
            ];
        } catch (Exception $e) {
            logError("Error en Cart::updateItem: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Remover producto del carrito
     */
    public static function removeItem($productId)
    {
        try {
            if (!isset($_SESSION[self::$sessionKey][$productId])) {
                throw new Exception('Producto no encontrado en el carrito');
            }

            $productName = $_SESSION[self::$sessionKey][$productId]['name'];
            unset($_SESSION[self::$sessionKey][$productId]);

            $totals = self::getTotals();

            return [
                'success' => true,
                'message' => "\"$productName\" eliminado del carrito",
                'cart_count' => self::getItemsCount(),
                'cart_total' => $totals['total'],
                'cart_empty' => self::isEmpty(),
                'totals' => $totals
            ];
        } catch (Exception $e) {
            logError("Error en Cart::removeItem: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Vaciar carrito
     */
    public static function clear()
    {
        $_SESSION[self::$sessionKey] = [];
        return [
            'success' => true,
            'message' => 'Carrito vaciado',
            'cart_count' => 0,
            'cart_total' => 0
        ];
    }

    /**
     * Obtener items del carrito
     */
    public static function getItems()
    {
        if (!isset($_SESSION[self::$sessionKey])) {
            return [];
        }

        // Verificar que los productos aún existan y estén activos
        $validItems = [];
        foreach ($_SESSION[self::$sessionKey] as $productId => $item) {
            $product = self::getProductDetails($productId);
            if ($product && $product['is_active']) {
                // Actualizar datos del producto por si cambiaron
                $item['name'] = $product['name'];
                $item['price'] = floatval($product['price']);
                $item['is_free'] = $product['is_free'];
                $item['image'] = $product['image'];

                $validItems[$productId] = $item;
            }
        }

        // Actualizar carrito con items válidos
        $_SESSION[self::$sessionKey] = $validItems;

        return $validItems;
    }

    /**
     * Verificar si el carrito está vacío
     */
    public static function isEmpty()
    {
        return empty(self::getItems());
    }

    /**
     * Verificar si un producto está en el carrito
     */
    public static function hasItem($productId)
    {
        $items = self::getItems();
        return isset($items[$productId]);
    }

    /**
     * Obtener cantidad de un producto específico
     */
    public static function getItemQuantity($productId)
    {
        $items = self::getItems();
        return $items[$productId]['quantity'] ?? 0;
    }

    /**
     * Obtener número de productos en el carrito
     */
    public static function getItemsCount()
    {
        $items = self::getItems();
        return array_sum(array_column($items, 'quantity'));
    }

    /**
     * Calcular totales del carrito
     */
    public static function getTotals()
    {
        $items = self::getItems();

        $subtotal = 0;
        $itemsCount = 0;
        $freeItems = [];
        $paidItems = [];

        foreach ($items as $item) {
            $itemsCount += $item['quantity'];

            if ($item['is_free']) {
                $freeItems[] = $item;
            } else {
                $itemSubtotal = $item['price'] * $item['quantity'];
                $subtotal += $itemSubtotal;
                $paidItems[] = $item;
            }
        }

        // Calcular impuestos (si están configurados)
        $taxRate = floatval(Settings::get('tax_rate', '0'));
        $tax = $subtotal * ($taxRate / 100);

        $total = $subtotal + $tax;

        return [
            'items_count' => $itemsCount,
            'subtotal' => $subtotal,
            'subtotal_raw' => $subtotal,
            'tax' => $tax,
            'tax_raw' => $tax,
            'tax_rate' => $taxRate,
            'total' => $total,
            'total_raw' => $total,
            'free_items_count' => count($freeItems),
            'paid_items_count' => count($paidItems),
            'has_free_items' => !empty($freeItems),
            'has_paid_items' => !empty($paidItems)
        ];
    }

    /**
     * Validar carrito
     */
    public static function validate()
    {
        $items = self::getItems();
        $errors = [];

        if (empty($items)) {
            $errors[] = 'El carrito está vacío';
            return ['valid' => false, 'errors' => $errors];
        }

        foreach ($items as $productId => $item) {
            $product = self::getProductDetails($productId);

            if (!$product) {
                $errors[] = "Producto \"{$item['name']}\" no encontrado";
                unset($_SESSION[self::$sessionKey][$productId]);
                continue;
            }

            if (!$product['is_active']) {
                $errors[] = "Producto \"{$item['name']}\" no está disponible";
                unset($_SESSION[self::$sessionKey][$productId]);
                continue;
            }

            if ($item['quantity'] > 10) {
                $_SESSION[self::$sessionKey][$productId]['quantity'] = 10;
                $errors[] = "Cantidad de \"{$item['name']}\" ajustada al máximo (10)";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Preparar datos para checkout
     */
    public static function prepareCheckoutData()
    {
        $validation = self::validate();
        if (!$validation['valid']) {
            return ['valid' => false, 'errors' => $validation['errors']];
        }

        $items = self::getItems();
        $totals = self::getTotals();

        $freeItems = array_filter($items, fn($item) => $item['is_free']);
        $paidItems = array_filter($items, fn($item) => !$item['is_free']);

        $requiresPayment = !empty($paidItems) && $totals['total'] > 0;

        return [
            'valid' => true,
            'items' => $items,
            'totals' => $totals,
            'free_items' => $freeItems,
            'paid_items' => $paidItems,
            'requires_payment' => $requiresPayment,
            'items_count' => count($items),
            'products_count' => $totals['items_count']
        ];
    }

    /**
     * Convertir carrito a items de orden
     */
    public static function convertToOrderItems()
    {
        $items = self::getItems();
        $orderItems = [];

        foreach ($items as $item) {
            $orderItems[] = [
                'product_id' => $item['id'],
                'product_name' => $item['name'],
                'price' => $item['is_free'] ? 0 : $item['price'],
                'quantity' => $item['quantity']
            ];
        }

        return $orderItems;
    }

    /**
     * Obtener detalles del producto
     */
    private static function getProductDetails($productId)
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT p.*, c.name as category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.id = ?
            ");
            $stmt->execute([$productId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            logError("Error obteniendo producto $productId: " . $e->getMessage());
            return false;
        }
    }
}

// Funciones helper para compatibilidad con el código existente
function addToCart($productId, $quantity = 1) {
    return Cart::addItem($productId, $quantity);
}

function updateCartItem($productId, $quantity) {
    return Cart::updateItem($productId, $quantity);
}

function removeFromCart($productId) {
    return Cart::removeItem($productId);
}

function getCartCount() {
    return Cart::getItemsCount();
}

function getCartTotal() {
    $totals = Cart::getTotals();
    return $totals['total'];
}

function clearCart() {
    return Cart::clear();
}
?>