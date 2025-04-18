<?php
session_start();
include 'config.php';

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
header('Content-Type: application/json');

try {
    // Validate CSRF token for POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'fetch') {
        $stmt = $pdo->prepare("SELECT * FROM events WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($tasks);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING) ?? '';
        if ($action === 'add') {
            // Validate inputs
            $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
            $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING) ?? null;
            $start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_STRING);
            $end = filter_input(INPUT_POST, 'end', FILTER_SANITIZE_STRING) ?? null;
            $task_type = filter_input(INPUT_POST, 'task_type', FILTER_SANITIZE_STRING);
            $customColor = filter_input(INPUT_POST, 'customColor', FILTER_SANITIZE_STRING) ?? null;
            $recurring = filter_input(INPUT_POST, 'recurring', FILTER_SANITIZE_STRING) ?? 'none';
            $recurringEnd = filter_input(INPUT_POST, 'recurringEnd', FILTER_SANITIZE_STRING) ?? null;
            $allDay = filter_input(INPUT_POST, 'allDay', FILTER_VALIDATE_INT) ?? 0;

            if (!$title || !$start || !$task_type) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO events (user_id, title, description, start, end, task_type, customColor, recurring, recurringEnd, allDay) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $user_id,
                $title,
                $description,
                $start,
                $end,
                $task_type,
                $customColor,
                $recurring,
                $recurringEnd,
                $allDay
            ]);
            echo json_encode(['message' => 'Event added successfully']);
            exit;
        }
        if ($action === 'update') {
            // Validate inputs
            $task_id = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
            $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
            $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING) ?? null;
            $start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_STRING);
            $end = filter_input(INPUT_POST, 'end', FILTER_SANITIZE_STRING) ?? null;
            $task_type = filter_input(INPUT_POST, 'task_type', FILTER_SANITIZE_STRING);
            $customColor = filter_input(INPUT_POST, 'customColor', FILTER_SANITIZE_STRING) ?? null;
            $recurring = filter_input(INPUT_POST, 'recurring', FILTER_SANITIZE_STRING) ?? 'none';
            $recurringEnd = filter_input(INPUT_POST, 'recurringEnd', FILTER_SANITIZE_STRING) ?? null;
            $allDay = filter_input(INPUT_POST, 'allDay', FILTER_VALIDATE_INT) ?? 0;

            if (!$task_id || !$title || !$start || !$task_type) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE events SET title = ?, description = ?, start = ?, end = ?, task_type = ?, customColor = ?, recurring = ?, recurringEnd = ?, allDay = ? WHERE task_id = ? AND user_id = ?");
            $stmt->execute([
                $title,
                $description,
                $start,
                $end,
                $task_type,
                $customColor,
                $recurring,
                $recurringEnd,
                $allDay,
                $task_id,
                $user_id
            ]);
            echo json_encode(['message' => 'Event updated successfully']);
            exit;
        }
        if ($action === 'complete') {
            $task_id = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
            if (!$task_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid task ID']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE events SET status = 'completed' WHERE task_id = ? AND user_id = ?");
            $stmt->execute([$task_id, $user_id]);
            echo json_encode(['message' => 'Task marked as completed']);
            exit;
        }
        if ($action === 'toggle') {
            $task_id = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
            if (!$task_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid task ID']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE events SET status = CASE WHEN status = 'completed' THEN 'pending' ELSE 'completed' END WHERE task_id = ? AND user_id = ?");
            $stmt->execute([$task_id, $user_id]);
            echo json_encode(['message' => 'Task status toggled']);
            exit;
        }
        if ($action === 'delete') {
            $task_id = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
            if (!$task_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid task ID']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM events WHERE task_id = ? AND user_id = ?");
            $stmt->execute([$task_id, $user_id]);
            echo json_encode(['message' => 'Event deleted successfully']);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
} catch (PDOException $e) {
    // Log error securely and show generic message
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred. Please try again later.']);
}
?>
