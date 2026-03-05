<?php
class Auth {

    public static function login($email, $password) {
        require_once __DIR__ . '/Database.php';
        $db = Database::getInstance();

        $user = $db->fetchOne(
            "SELECT u.*, r.name as role_name 
             FROM users u 
             JOIN roles r ON u.role_id = r.id 
             WHERE u.email = ? AND u.status = 'active'",
            [$email], 's'
        );

        if ($user && password_verify($password, $user['password'])) {
            session_start();
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role_name'];
            $_SESSION['role_id']   = $user['role_id'];
        // Load permissions into session    
            require_once __DIR__ . '/Permissions.php';
            Permissions::load($db, $user['role_id']);
            // Update last login
            $db->execute(
                "UPDATE users SET last_login = NOW() WHERE id = ?",
                [$user['id']], 'i'
            );

            return true;
        }
        return false;
    }

    public static function check() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }
    }

    public static function role() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return $_SESSION['user_role'] ?? null;
    }

    public static function hasRole(array $roles) {
        return in_array(self::role(), $roles);
    }

    public static function logout() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_destroy();
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }

    public static function id() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return $_SESSION['user_id'] ?? null;
    }
}