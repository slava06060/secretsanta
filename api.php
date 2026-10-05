<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

function reply(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}
function require_login(): int {
    if (empty($_SESSION['user_id'])) reply(['ok' => false, 'error' => 'Please log in again.'], 401);
    return (int)$_SESSION['user_id'];
}
function require_csrf(): void {
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf']) || !is_string($sent) || !hash_equals($_SESSION['csrf'], $sent)) {
        reply(['ok' => false, 'error' => 'Security token expired. Refresh the page and try again.'], 403);
    }
}
function online_count(PDO $pdo): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE last_seen >= (NOW() - INTERVAL ? SECOND)');
    $stmt->execute([ONLINE_WINDOW_SECONDS]);
    return (int)$stmt->fetchColumn();
}
function is_drawn(PDO $pdo): bool {
    return (int)$pdo->query('SELECT COUNT(*) FROM assignments')->fetchColumn() === 4;
}
function own_result(PDO $pdo, int $uid): ?string {
    $stmt = $pdo->prepare('SELECT recipient.nickname FROM assignments a JOIN users recipient ON recipient.id=a.recipient_id WHERE a.giver_id=?');
    $stmt->execute([$uid]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (string)$value;
}

try {
    $pdo = db();
    $input = body();
    $action = (string)($input['action'] ?? $_GET['action'] ?? '');

    if ($action === 'login') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(['ok'=>false, 'error'=>'Invalid request.'], 405);
        $nickname = trim((string)($input['nickname'] ?? ''));
        $password = (string)($input['password'] ?? '');
        $stmt = $pdo->prepare('SELECT id, nickname, password_hash FROM users WHERE nickname=?');
        $stmt->execute([$nickname]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            usleep(250000);
            reply(['ok'=>false, 'error'=>'Incorrect nickname or password.'], 401);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['nickname'] = $user['nickname'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $pdo->prepare('UPDATE users SET last_seen=NOW() WHERE id=?')->execute([(int)$user['id']]);
        reply(['ok'=>true, 'nickname'=>$user['nickname'], 'csrf'=>$_SESSION['csrf']]);
    }

    if ($action === 'logout') {
        require_login();
        require_csrf();
        $uid = (int)$_SESSION['user_id'];
        $pdo->prepare('UPDATE users SET last_seen=NULL WHERE id=?')->execute([$uid]);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        reply(['ok'=>true]);
    }

    $uid = require_login();
    if ($action === 'heartbeat') {
        $pdo->prepare('UPDATE users SET last_seen=NOW() WHERE id=?')->execute([$uid]);
        reply(['ok'=>true]);
    }

    if ($action === 'status') {
        $pdo->prepare('UPDATE users SET last_seen=NOW() WHERE id=?')->execute([$uid]);
        $count = online_count($pdo);
        $drawn = is_drawn($pdo);
        reply([
            'ok'=>true,
            'nickname'=>(string)$_SESSION['nickname'],
            'online'=>$count,
            'total'=>4,
            'all_online'=>$count === 4,
            'drawn'=>$drawn,
            'recipient'=>$drawn ? own_result($pdo, $uid) : null,
            'csrf'=>$_SESSION['csrf'] ?? '',
        ]);
    }

    if ($action === 'start') {
        require_csrf();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(['ok'=>false, 'error'=>'Invalid request.'], 405);
        $pdo->beginTransaction();
        try {
            $pdo->query('SELECT id FROM draw_state WHERE id=1 FOR UPDATE')->fetch();
            if ((int)$pdo->query('SELECT COUNT(*) FROM assignments')->fetchColumn() === 4) {
                $pdo->commit();
                reply(['ok'=>true, 'drawn'=>true, 'recipient'=>own_result($pdo, $uid)]);
            }
            $count = online_count($pdo);
            if ($count !== 4) {
                $pdo->rollBack();
                reply(['ok'=>false, 'error'=>'Everyone must be online before the draw can start.'], 409);
            }

            $users = $pdo->query('SELECT id FROM users ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
            if (count($users) !== 4) {
                $pdo->rollBack();
                reply(['ok'=>false, 'error'=>'The four accounts have not been set up yet.'], 500);
            }

            // Shuffle until we have a derangement: nobody gets themselves.
            $recipients = $users;
            $valid = false;
            for ($attempt = 0; $attempt < 1000; $attempt++) {
                shuffle($recipients);
                $valid = true;
                foreach ($users as $i => $giver) {
                    if ((int)$giver === (int)$recipients[$i]) { $valid = false; break; }
                }
                if ($valid) break;
            }
            if (!$valid) {
                $pdo->rollBack();
                reply(['ok'=>false, 'error'=>'Could not create a valid draw. Please try again.'], 500);
            }

            $insert = $pdo->prepare('INSERT INTO assignments (giver_id, recipient_id) VALUES (?, ?)');
            foreach ($users as $i => $giver) $insert->execute([(int)$giver, (int)$recipients[$i]]);
            $pdo->exec('UPDATE draw_state SET started_at=NOW() WHERE id=1');
            $pdo->commit();
            reply(['ok'=>true, 'drawn'=>true, 'recipient'=>own_result($pdo, $uid)]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    reply(['ok'=>false, 'error'=>'Unknown action.'], 400);
} catch (Throwable $e) {
    error_log('Secret Santa API error: ' . $e->getMessage());
    reply(['ok'=>false, 'error'=>'Server error. Check the PHP/MySQL setup.'], 500);
}
