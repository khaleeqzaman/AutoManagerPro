<?php
class Permissions {

    // Master permission list with labels
    public static function all(): array {
        return [
            'inventory.view'    => 'View Inventory',
            'inventory.add'     => 'Add Cars',
            'inventory.edit'    => 'Edit Cars',
            'inventory.delete'  => 'Delete Cars',
            'leads.view'        => 'View Leads',
            'leads.add'         => 'Add Leads',
            'leads.edit'        => 'Edit Leads',
            'leads.delete'      => 'Delete Leads',
            'sales.view'        => 'View Sales',
            'sales.create'      => 'Create Sales',
            'accounts.view'     => 'View Accounts',
            'accounts.manage'   => 'Manage Accounts',
            'expenses.view'     => 'View Expenses',
            'expenses.add'      => 'Add Expenses',
            'expenses.delete'   => 'Delete Expenses',
            'reports.view'      => 'View Reports',
            'users.manage'      => 'Manage Users',
            'settings.manage'   => 'Manage Settings',
        ];
    }

    // Default permissions per role name
    public static function defaults(): array {
        return [
            'Admin' => [
                'inventory.view', 'inventory.add', 'inventory.edit', 'inventory.delete',
                'leads.view', 'leads.add', 'leads.edit', 'leads.delete',
                'sales.view', 'sales.create',
                'accounts.view', 'accounts.manage',
                'expenses.view', 'expenses.add', 'expenses.delete',
                'reports.view', 'users.manage', 'settings.manage',
            ],
            'Manager' => [
                'inventory.view', 'inventory.add', 'inventory.edit', 'inventory.delete',
                'leads.view', 'leads.add', 'leads.edit', 'leads.delete',
                'sales.view', 'sales.create',
                'accounts.view', 'accounts.manage',
                'expenses.view', 'expenses.add', 'expenses.delete',
                'reports.view',
            ],
            'Salesperson' => [
                'inventory.view',
                'leads.view', 'leads.add', 'leads.edit',
                'sales.view', 'sales.create',
            ],
            'Accountant' => [
                'sales.view',
                'accounts.view', 'accounts.manage',
                'expenses.view', 'expenses.add',
                'reports.view',
            ],
        ];
    }

    // Seed default permissions for all roles
    public static function seed(object $db): void {
        $roles = $db->fetchAll("SELECT id, name FROM roles");
        $defaults = self::defaults();

        foreach ($roles as $role) {
            $perms = $defaults[$role['name']] ?? [];
            foreach ($perms as $perm) {
                $db->execute(
                    "INSERT IGNORE INTO role_permissions (role_id, permission, granted)
                     VALUES (?, ?, 1)",
                    [$role['id'], $perm], 'is'
                );
            }
            // Insert denied permissions too (so admin can toggle them)
            foreach (array_keys(self::all()) as $p) {
                if (!in_array($p, $perms)) {
                    $db->execute(
                        "INSERT IGNORE INTO role_permissions (role_id, permission, granted)
                         VALUES (?, ?, 0)",
                        [$role['id'], $p], 'is'
                    );
                }
            }
        }
    }

    // Load permissions into session for logged-in user
    public static function load(object $db, int $roleId): void {
        $rows = $db->fetchAll(
            "SELECT permission, granted FROM role_permissions WHERE role_id = ?",
            [$roleId], 'i'
        );
        $perms = [];
        foreach ($rows as $row) {
            if ($row['granted']) {
                $perms[] = $row['permission'];
            }
        }
        $_SESSION['permissions'] = $perms;
    }

    // Check single permission
    public static function has(string $permission): bool {
        // Admin always has everything regardless of DB
        if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin') {
            return true;
        }
        return in_array($permission, $_SESSION['permissions'] ?? []);
    }

    // Enforce permission — redirect if denied
    public static function require(string $permission, string $redirect = ''): void {
        if (!self::has($permission)) {
            if (session_status() === PHP_SESSION_NONE) session_start();
            $_SESSION['flash'] = [
                'type'    => 'error',
                'message' => 'You do not have permission to access that page.'
            ];
            $redirect = $redirect ?: '/car-showroom/dashboard/index.php';
            header('Location: ' . $redirect);
            exit;
        }
    }
}