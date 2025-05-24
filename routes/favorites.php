<?php
// routes/favorites.php

// Dependencies
require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/send.php';
require_once __DIR__ . '/../config/database.php';
$pdo = getDatabaseConnection();

// Retrieve the user_id from the request body or from a session or token
$input = json_decode(file_get_contents('php://input'), true);
$userId = isset($input['user_id']) ? (int)$input['user_id'] : 0; // Get the user_id from the body
$token = isset($input['auth_token']) ? $input['auth_token'] : ''; // Get auth_token from request

// Check if user is authenticated via token
if (!$userId && !$token) {
    send(['error' => 'User not authenticated or missing user_id'], 401);
    exit;
}

// If no user_id is provided, attempt to get it from token
if (!$userId && $token) {
    // Assuming your authentication system decodes the token to get user_id
    // Replace this with your actual token validation logic
    $userId = validateToken($token); // Example: function that decodes the token and retrieves the user_id
    if (!$userId) {
        send(['error' => 'Invalid or expired token'], 401);
        exit;
    }
}

// Handle POST → toggle favorite (favorite/unfavorite)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId = isset($input['item_id']) ? (int)$input['item_id'] : 0;

    if (!$itemId) {
        send(['error' => 'Missing item_id'], 400);
        exit;
    }

    // If user is logged in (has user_id), interact with database
    if ($userId) {
        $pdo->beginTransaction();
        try {
            $chk = $pdo->prepare("SELECT 1 FROM user_favorites WHERE user_id = :uid AND item_id = :iid");
            $chk->execute([':uid' => $userId, ':iid' => $itemId]);

            if ($chk->fetch()) {
                // If already favorited, un-favorite (remove from "My Looks")
                $del = $pdo->prepare("DELETE FROM user_favorites WHERE user_id = :uid AND item_id = :iid");
                $del->execute([':uid' => $userId, ':iid' => $itemId]);
                send(['favorited' => false]);
            } else {
                // If not favorited, add it to "My Looks"
                $ins = $pdo->prepare("INSERT INTO user_favorites (user_id, item_id) VALUES (:uid, :iid)");
                $ins->execute([':uid' => $userId, ':iid' => $itemId]);
                send(['favorited' => true]);
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            send(['error' => 'Database error'], 500);
        }
    } else {
        // For non-logged-in users, store the hearted item locally in session
        $favorites = isset($_SESSION['myLooks']) ? $_SESSION['myLooks'] : [];

        if (in_array($itemId, $favorites)) {
            // Remove from "My Looks"
            $favorites = array_diff($favorites, [$itemId]);
            send(['favorited' => false]);
        } else {
            // Add to "My Looks"
            $favorites[] = $itemId;
            send(['favorited' => true]);
        }

        // Store in session
        $_SESSION['myLooks'] = $favorites;
    }

    exit;
}

// Handle GET → list this user’s favorites
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // If user is logged in (has user_id), fetch favorites from DB
    if ($userId) {
        $stmt = $pdo->prepare("SELECT i.* FROM user_favorites uf JOIN items i ON i.id = uf.item_id WHERE uf.user_id = :uid ORDER BY uf.created_at DESC");
        $stmt->execute([':uid' => $userId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        send($items);
    } else {
        // If user is not logged in, retrieve local favorites from session
        $favorites = isset($_SESSION['myLooks']) ? $_SESSION['myLooks'] : [];
        send($favorites); // Return the local favorites
    }

    exit;
}

// Handle DELETE → remove item from favorites (unheart)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $itemId = isset($input['item_id']) ? (int)$input['item_id'] : 0;

    if (!$itemId) {
        send(['error' => 'Missing item_id'], 400);
        exit;
    }

    // If user is logged in (has user_id), remove from DB
    if ($userId) {
        $chk = $pdo->prepare("SELECT 1 FROM user_favorites WHERE user_id = :uid AND item_id = :iid");
        $chk->execute([':uid' => $userId, ':iid' => $itemId]);

        if ($chk->fetch()) {
            $del = $pdo->prepare("DELETE FROM user_favorites WHERE user_id = :uid AND item_id = :iid");
            $del->execute([':uid' => $userId, ':iid' => $itemId]);
            send(['message' => 'Item removed from favorites']);
        } else {
            send(['error' => 'Item not found in favorites'], 404);
        }
    } else {
        // If user is not logged in, remove from session (local storage)
        $favorites = isset($_SESSION['myLooks']) ? $_SESSION['myLooks'] : [];

        if (($key = array_search($itemId, $favorites)) !== false) {
            unset($favorites[$key]);
            send(['message' => 'Item removed from favorites']);
        } else {
            send(['error' => 'Item not found in local favorites'], 404);
        }

        // Update session
        $_SESSION['myLooks'] = $favorites;
    }

    exit;
}

// Fallback for other methods
send(['error' => 'Method not allowed'], 405);

// Token validation function (for the sake of example)
function validateToken($token) {
    // Your token validation logic here (e.g., decoding JWT token)
    // Example return value for testing
    return $token === 'validToken123' ? 1 : 0; // Returns user_id or 0 if invalid token
}
