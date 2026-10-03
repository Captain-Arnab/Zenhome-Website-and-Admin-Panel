<?php
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, X-Requested-With");
    header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
    http_response_code(200);
    exit();
}
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Content-Type: application/json");

include 'db.php';
include 'auth_helper.php';

$auth = requireAuth($conn);
$userId = $auth['user_id'];

$method = $_SERVER['REQUEST_METHOD'];

/**
 * Saved carts keep the image and page link from when they were filled. A
 * local image or page that no longer exists is replaced with the live
 * service's (matched by pack_id or slug), else '' / services.php. The stored
 * cart is not changed.
 */
function cart_fix_missing_files(PDO $conn, array $items): array
{
    $root = dirname(__DIR__);
    $missing = function ($path) use ($root): bool {
        if (!is_string($path) || $path === '' || preg_match('#^(https?:)?//#i', $path)) {
            return false;
        }
        $file = ltrim((string) strtok(strtok($path, '#'), '?'), '/');
        return $file === '' || str_contains($file, '..') || !is_file($root . '/' . $file);
    };
    $broken = array_filter($items, fn($i) => is_array($i) && ($missing($i['image'] ?? null) || $missing($i['url'] ?? null)));
    if (!$broken) {
        return $items;
    }
    $live = [];
    try {
        require_once __DIR__ . '/catalog_helper.php';
        $ids = array_values(array_filter(array_map(fn($i) => (int) ($i['pack_id'] ?? 0), $broken)));
        $slugs = array_values(array_filter(array_map(fn($i) => is_string($i['id'] ?? null) ? $i['id'] : '', $broken)));
        foreach (catalog_services($conn, ['ids' => $ids, 'slugs' => $slugs])[0] as $s) {
            $live['id:' . $s['pack_id']] = $s;
            $live['slug:' . $s['slug']] = $s;
        }
    } catch (Throwable $e) {
        error_log('[api/cart] ' . $e->getMessage());
    }
    foreach ($items as &$item) {
        if (!is_array($item)) {
            continue;
        }
        $service = $live['id:' . (int) ($item['pack_id'] ?? 0)] ?? (is_string($item['id'] ?? null) ? ($live['slug:' . $item['id']] ?? null) : null);
        if ($missing($item['image'] ?? null)) {
            $item['image'] = $service['image'] ?? '';
        }
        if ($missing($item['url'] ?? null)) {
            $item['url'] = $service['url'] ?? 'services.php';
        }
    }
    unset($item);
    return $items;
}

// GET: return current cart (persisted, so it survives refresh/close)
if ($method === 'GET') {
    $stmt = $conn->prepare("SELECT items FROM cart WHERE user_id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $items = [];
    if ($row && !empty($row['items'])) {
        $items = json_decode($row['items'], true);
        if (!is_array($items)) {
            $items = [];
        }
    }
    $items = cart_fix_missing_files($conn, $items);
    echo json_encode([
        "statusCode" => 200,
        "status" => "success",
        "items" => $items
    ]);
    exit;
}

// POST: set cart items (add/update cart). Body: { "items": [ ... ] }
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $items = isset($input['items']) && is_array($input['items']) ? $input['items'] : [];
    $jsonItems = json_encode($items);
    $stmt = $conn->prepare("
        INSERT INTO cart (user_id, items) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE items = VALUES(items), updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$userId, $jsonItems]);
    echo json_encode([
        "statusCode" => 200,
        "status" => "success",
        "message" => "Cart updated.",
        "items" => $items
    ]);
    exit;
}

// DELETE: clear cart (e.g. after checkout or user action)
if ($method === 'DELETE') {
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->execute([$userId]);
    echo json_encode([
        "statusCode" => 200,
        "status" => "success",
        "message" => "Cart cleared.",
        "items" => []
    ]);
    exit;
}

http_response_code(405);
echo json_encode(["statusCode" => 405, "status" => "error", "message" => "Method not allowed."]);
