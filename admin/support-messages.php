<?php
session_start();

/* ================= SECURITY ================= */

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: ../auth/login.php");
    exit;
}

$current_role = $_SESSION['role'];

$dashboard_link = "dashboard.php";
if ($current_role === 'super_admin') {
    $dashboard_link = "../super-admin/dashboard.php";
}

/* ================= DATABASE CONNECTION ================= */

$db_loaded = false;

$possible_db_files = [
    __DIR__ . '/../includes/db.php',
    __DIR__ . '/../includes/config.php',
    __DIR__ . '/../includes/connection.php',
    __DIR__ . '/../includes/db_connect.php'
];

foreach ($possible_db_files as $db_file) {
    if (file_exists($db_file)) {
        require_once $db_file;
        $db_loaded = true;
        break;
    }
}

if (!isset($conn) && isset($mysqli)) {
    $conn = $mysqli;
}

if (!isset($conn) && isset($connection)) {
    $conn = $connection;
}

if (!$db_loaded || !isset($conn)) {
    die("Database connection not found. Please check your includes/db.php file.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* ================= ACTIONS ================= */

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $message_id = (int)($_POST['message_id'] ?? 0);

    if ($message_id > 0) {
        if ($action === 'mark_read') {
            $stmt = $conn->prepare("UPDATE support_messages SET status = 'read', read_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $message_id);

            if ($stmt->execute()) {
                $success = "Message marked as read.";
            } else {
                $error = "Could not update message.";
            }

            $stmt->close();
        }

        if ($action === 'mark_new') {
            $stmt = $conn->prepare("UPDATE support_messages SET status = 'new', read_at = NULL WHERE id = ?");
            $stmt->bind_param("i", $message_id);

            if ($stmt->execute()) {
                $success = "Message marked as new.";
            } else {
                $error = "Could not update message.";
            }

            $stmt->close();
        }

        if ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM support_messages WHERE id = ?");
            $stmt->bind_param("i", $message_id);

            if ($stmt->execute()) {
                $success = "Message deleted successfully.";
            } else {
                $error = "Could not delete message.";
            }

            $stmt->close();
        }
    }
}

/* ================= COUNTS ================= */

$total_messages = 0;
$new_messages = 0;
$read_messages = 0;

$count_result = $conn->query("
    SELECT 
        COUNT(*) AS total_messages,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) AS new_messages,
        SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) AS read_messages
    FROM support_messages
");

if ($count_result && $count_result->num_rows > 0) {
    $counts = $count_result->fetch_assoc();
    $total_messages = (int)$counts['total_messages'];
    $new_messages = (int)$counts['new_messages'];
    $read_messages = (int)$counts['read_messages'];
}

/* ================= FILTER ================= */

$filter = $_GET['filter'] ?? 'all';

$where_sql = "";
if ($filter === 'new') {
    $where_sql = "WHERE status = 'new'";
} elseif ($filter === 'read') {
    $where_sql = "WHERE status = 'read'";
}

$messages = [];

$result = $conn->query("
    SELECT id, user_id, name, email, subject, message, status, created_at, read_at
    FROM support_messages
    $where_sql
    ORDER BY created_at DESC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Support Messages - UniStay</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../assets/css/theme.css">

    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --sm-bg: #f7fbff;
            --sm-soft: #edf5ff;
            --sm-card: #ffffff;
            --sm-card-2: #f9fbff;
            --sm-text: #0f172a;
            --sm-muted: #5f6f86;
            --sm-heading: #0f172a;
            --sm-border: #d7e5f5;
            --sm-primary: #2f63d8;
            --sm-primary-dark: #234fb4;
            --sm-success: #16a34a;
            --sm-danger: #dc2626;
            --sm-warning: #f59e0b;
            --sm-shadow: rgba(15, 23, 42, 0.08);
        }

        body.dark-mode {
            --sm-bg: #020617;
            --sm-soft: #081122;
            --sm-card: #0f172a;
            --sm-card-2: #111827;
            --sm-text: #e5e7eb;
            --sm-muted: #9fb0c8;
            --sm-heading: #f8fafc;
            --sm-border: #24344d;
            --sm-primary: #4a86ff;
            --sm-primary-dark: #2f63d8;
            --sm-success: #34d399;
            --sm-danger: #f87171;
            --sm-warning: #fbbf24;
            --sm-shadow: rgba(0, 0, 0, 0.35);
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: var(--sm-bg) !important;
            color: var(--sm-text) !important;
        }

        .admin-page {
            min-height: 100vh;
            background: var(--sm-bg);
        }

        .admin-header {
            padding: 18px 7%;
            background: var(--sm-card);
            border-bottom: 1px solid var(--sm-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            box-shadow: 0 4px 18px var(--sm-shadow);
        }

        .brand-box h1 {
            margin: 0;
            color: var(--sm-heading) !important;
            font-size: 26px;
        }

        .brand-box p {
            margin: 4px 0 0;
            color: var(--sm-muted) !important;
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .btn {
            text-decoration: none;
            border: none;
            cursor: pointer;
            padding: 10px 15px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 14px;
            display: inline-block;
        }

        .btn-primary {
            background: var(--sm-primary);
            color: white !important;
        }

        .btn-secondary {
            background: var(--sm-soft);
            color: var(--sm-primary) !important;
            border: 1px solid var(--sm-border);
        }

        .btn-danger {
            background: #dc2626;
            color: white !important;
        }

        .btn-warning {
            background: #f59e0b;
            color: white !important;
        }

        .container {
            padding: 35px 7% 70px;
            max-width: 1250px;
            margin: 0 auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 26px;
        }

        .stat-card {
            background: var(--sm-card);
            border: 1px solid var(--sm-border);
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 12px 30px var(--sm-shadow);
        }

        .stat-card h2 {
            margin: 0;
            color: var(--sm-heading) !important;
            font-size: 32px;
        }

        .stat-card p {
            margin: 6px 0 0;
            color: var(--sm-muted) !important;
            font-weight: 700;
        }

        .filter-card {
            background: var(--sm-card);
            border: 1px solid var(--sm-border);
            border-radius: 18px;
            padding: 18px;
            box-shadow: 0 12px 30px var(--sm-shadow);
            margin-bottom: 24px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-link {
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 999px;
            background: var(--sm-soft);
            color: var(--sm-primary) !important;
            border: 1px solid var(--sm-border);
            font-weight: 800;
        }

        .filter-link.active {
            background: var(--sm-primary);
            color: white !important;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 14px;
            margin-bottom: 18px;
            font-weight: 700;
        }

        .alert-success {
            background: rgba(22, 163, 74, 0.12);
            color: var(--sm-success) !important;
            border: 1px solid rgba(22, 163, 74, 0.25);
        }

        .alert-error {
            background: rgba(220, 38, 38, 0.10);
            color: var(--sm-danger) !important;
            border: 1px solid rgba(220, 38, 38, 0.25);
        }

        .message-list {
            display: grid;
            gap: 18px;
        }

        .message-card {
            background: var(--sm-card);
            border: 1px solid var(--sm-border);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 12px 30px var(--sm-shadow);
        }

        .message-top {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: flex-start;
            margin-bottom: 14px;
        }

        .message-top h3 {
            margin: 0;
            color: var(--sm-heading) !important;
            font-size: 22px;
        }

        .status-badge {
            padding: 7px 11px;
            border-radius: 999px;
            font-weight: 900;
            font-size: 12px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .status-new {
            background: rgba(245, 158, 11, 0.15);
            color: var(--sm-warning) !important;
            border: 1px solid rgba(245, 158, 11, 0.25);
        }

        .status-read {
            background: rgba(22, 163, 74, 0.12);
            color: var(--sm-success) !important;
            border: 1px solid rgba(22, 163, 74, 0.25);
        }

        .meta {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 16px;
        }

        .meta-box {
            background: var(--sm-card-2);
            border: 1px solid var(--sm-border);
            border-radius: 14px;
            padding: 12px;
        }

        .meta-box strong {
            display: block;
            color: var(--sm-heading) !important;
            margin-bottom: 4px;
            font-size: 13px;
        }

        .meta-box span,
        .meta-box a {
            color: var(--sm-muted) !important;
            text-decoration: none;
            word-break: break-word;
        }

        .message-body {
            background: var(--sm-card-2);
            border: 1px solid var(--sm-border);
            border-radius: 16px;
            padding: 16px;
            color: var(--sm-muted) !important;
            line-height: 1.75;
            white-space: pre-wrap;
            margin-bottom: 16px;
        }

        .message-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .empty-card {
            background: var(--sm-card);
            border: 1px solid var(--sm-border);
            border-radius: 20px;
            padding: 35px;
            text-align: center;
            box-shadow: 0 12px 30px var(--sm-shadow);
        }

        .empty-card h3 {
            margin: 0 0 8px;
            color: var(--sm-heading) !important;
        }

        .empty-card p {
            color: var(--sm-muted) !important;
            margin: 0;
        }

        @media (max-width: 900px) {
            .stats-grid,
            .meta {
                grid-template-columns: 1fr;
            }

            .message-top {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<div class="admin-page">

    <div class="admin-header">
        <div class="brand-box">
            <h1>Support Messages</h1>
            <p>Review messages submitted from the UniStay support page.</p>
        </div>

        <div class="header-actions">
            <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>
            <a href="../index.php" class="btn btn-secondary">Home</a>
            <a href="../support.php" class="btn btn-secondary">Support Page</a>
            <a href="<?php echo h($dashboard_link); ?>" class="btn btn-primary">Dashboard</a>
            <a href="../auth/logout.php" class="btn btn-danger">Logout</a>
        </div>
    </div>

    <div class="container">

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h2><?php echo $total_messages; ?></h2>
                <p>Total Messages</p>
            </div>

            <div class="stat-card">
                <h2><?php echo $new_messages; ?></h2>
                <p>New Messages</p>
            </div>

            <div class="stat-card">
                <h2><?php echo $read_messages; ?></h2>
                <p>Read Messages</p>
            </div>
        </div>

        <div class="filter-card">
            <a href="support-messages.php?filter=all" class="filter-link <?php echo $filter === 'all' ? 'active' : ''; ?>">
                All
            </a>
            <a href="support-messages.php?filter=new" class="filter-link <?php echo $filter === 'new' ? 'active' : ''; ?>">
                New
            </a>
            <a href="support-messages.php?filter=read" class="filter-link <?php echo $filter === 'read' ? 'active' : ''; ?>">
                Read
            </a>
        </div>

        <?php if (empty($messages)): ?>
            <div class="empty-card">
                <h3>No support messages found</h3>
                <p>When someone submits the support form, the message will appear here.</p>
            </div>
        <?php else: ?>
            <div class="message-list">
                <?php foreach ($messages as $msg): ?>
                    <div class="message-card">
                        <div class="message-top">
                            <h3><?php echo h($msg['subject']); ?></h3>

                            <?php if ($msg['status'] === 'new'): ?>
                                <span class="status-badge status-new">New</span>
                            <?php else: ?>
                                <span class="status-badge status-read">Read</span>
                            <?php endif; ?>
                        </div>

                        <div class="meta">
                            <div class="meta-box">
                                <strong>Name</strong>
                                <span><?php echo h($msg['name']); ?></span>
                            </div>

                            <div class="meta-box">
                                <strong>Email</strong>
                                <a href="mailto:<?php echo h($msg['email']); ?>">
                                    <?php echo h($msg['email']); ?>
                                </a>
                            </div>

                            <div class="meta-box">
                                <strong>Submitted</strong>
                                <span><?php echo h($msg['created_at']); ?></span>
                            </div>
                        </div>

                        <div class="message-body"><?php echo h($msg['message']); ?></div>

                        <div class="message-actions">
                            <?php if ($msg['status'] === 'new'): ?>
                                <form method="POST">
                                    <input type="hidden" name="message_id" value="<?php echo (int)$msg['id']; ?>">
                                    <input type="hidden" name="action" value="mark_read">
                                    <button type="submit" class="btn btn-primary">Mark as Read</button>
                                </form>
                            <?php else: ?>
                                <form method="POST">
                                    <input type="hidden" name="message_id" value="<?php echo (int)$msg['id']; ?>">
                                    <input type="hidden" name="action" value="mark_new">
                                    <button type="submit" class="btn btn-warning">Mark as New</button>
                                </form>
                            <?php endif; ?>

                            <form method="POST" onsubmit="return confirm('Delete this support message?');">
                                <input type="hidden" name="message_id" value="<?php echo (int)$msg['id']; ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

</div>

<script src="../assets/js/theme.js"></script>
</body>
</html>