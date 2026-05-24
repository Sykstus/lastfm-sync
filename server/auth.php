<?php
/**
 * Auth helper — logowanie do panelu
 */

require_once __DIR__ . '/config.php';

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
session_name(SESSION_NAME);
session_start();

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return $_SESSION['user'] ?? null;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (currentUser()['role'] !== 'admin') {
        header('Location: dashboard.php?error=forbidden');
        exit;
    }
}

function attemptLogin(string $username, string $password): bool {
    try {
        $stmt = getDB()->prepare('SELECT * FROM panel_users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user) return false;
        if (!password_verify($password, $user['password'])) return false;
        // Aktualizuj last_login
        getDB()->prepare('UPDATE panel_users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user']    = [
            'id'          => $user['id'],
            'username'    => $user['username'],
            'lastfm_user' => $user['lastfm_user'],
            'role'        => $user['role'],
        ];
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function logout() {
    session_destroy();
    header('Location: login.php');
    exit;
}
