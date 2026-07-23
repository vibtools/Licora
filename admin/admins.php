<?php
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/database.php';
require_once '../includes/admin_helpers.php';

$auth = new Auth();
if (!$auth->isAdminLoggedIn()) { header('Location: login.php'); exit; }
$db = Database::getInstance();
$msg = '';
$error = '';
$hasRole = AdminHelpers::columnExists('admin_users', 'role');
$currentAdminId = (int)($_SESSION['admin_id'] ?? 0);

function valid_admin_role($role) {
    return in_array($role, ['super_admin', 'manager', 'viewer'], true) ? $role : 'viewer';
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Security::requireCSRFToken($_POST['csrf_token'] ?? '');

        if (isset($_POST['create_admin'])) {
            AdminHelpers::requireDelete();
            $username = Security::sanitize($_POST['username'] ?? '');
            $email = Security::sanitize($_POST['email'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            $role = valid_admin_role($_POST['role'] ?? 'viewer');

            if ($username === '' || $password === '') {
                $error = 'Username and password are required.';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters.';
            } else {
                if ($hasRole) {
                    $stmt = $db->prepare('INSERT INTO admin_users (username, password, email, role, created_at) VALUES (:u, :p, :e, :r, NOW())');
                    $stmt->execute([':u' => $username, ':p' => Security::hashPassword($password), ':e' => $email ?: null, ':r' => $role]);
                } else {
                    $stmt = $db->prepare('INSERT INTO admin_users (username, password, email, created_at) VALUES (:u, :p, :e, NOW())');
                    $stmt->execute([':u' => $username, ':p' => Security::hashPassword($password), ':e' => $email ?: null]);
                }
                AdminHelpers::audit('admin', (int)$db->lastInsertId(), 'admin_created', 'Admin user created: ' . $username);
                AdminHelpers::clearTemporaryAdminCredentialCache();
                $msg = 'Admin created successfully.';
            }
        }

        if (isset($_POST['update_admin']) || isset($_POST['update_role'])) {
            AdminHelpers::requireDelete();
            $id = (int)($_POST['admin_id'] ?? 0);
            $username = Security::sanitize($_POST['username'] ?? '');
            $email = Security::sanitize($_POST['email'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            $role = valid_admin_role($_POST['role'] ?? 'viewer');

            if ($id <= 0) {
                $error = 'Invalid admin selected.';
            } else {
                $sets = [];
                $params = [':id' => $id];
                if ($username !== '') { $sets[] = 'username = :u'; $params[':u'] = $username; }
                $sets[] = 'email = :e'; $params[':e'] = $email ?: null;
                if ($password !== '') { $sets[] = 'password = :p'; $params[':p'] = Security::hashPassword($password); }
                if ($hasRole) { $sets[] = 'role = :r'; $params[':r'] = $role; }
                if (!empty($sets)) {
                    $stmt = $db->prepare('UPDATE admin_users SET ' . implode(', ', $sets) . ' WHERE id = :id');
                    $stmt->execute($params);
                    AdminHelpers::audit('admin', $id, 'admin_updated', 'Admin user updated');
                    AdminHelpers::clearTemporaryAdminCredentialCache();
                    $msg = 'Admin updated successfully.';
                }
            }
        }

        if (isset($_POST['delete_admin'])) {
            AdminHelpers::requireDelete();
            $id = (int)($_POST['admin_id'] ?? 0);
            if ($id <= 0) {
                $error = 'Invalid admin selected.';
            } elseif ($id === $currentAdminId) {
                $error = 'You cannot delete your own admin account.';
            } else {
                $stmt = $db->prepare('DELETE FROM admin_users WHERE id = :id');
                $stmt->execute([':id' => $id]);
                AdminHelpers::audit('admin', $id, 'admin_deleted', 'Admin user deleted');
                AdminHelpers::clearTemporaryAdminCredentialCache();
                $msg = 'Admin deleted successfully.';
            }
        }
    }
} catch (Exception $e) {
    $error = 'Admin action failed. Please check duplicate username or database permission.';
}

$users = $db->query('SELECT * FROM admin_users ORDER BY id ASC')->fetchAll();
$csrf = Security::generateCSRFToken();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admins</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <script>tailwind=window.tailwind||{};tailwind.config={corePlugins:{preflight:false},darkMode:'class'};</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/admin-ui.css">
</head>
<body class="admin-ui">
<?php include 'includes/navbar.php'; ?>
<div class="container-fluid admin-shell">
    <div class="page-hero d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
        <div>
            <h2><i class="bi bi-people"></i> Admin Users</h2>
            <p>Create, edit, delete, and control Super Admin, Manager, Viewer permission. Viewer শুধু দেখতে পারবে।</p>
        </div>
        <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#createAdminModal" <?php echo AdminHelpers::canDelete() ? '' : 'disabled'; ?>><i class="bi bi-person-plus"></i> New Admin</button>
    </div>

    <?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><?php echo Security::escape($msg); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?php echo Security::escape($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if (!$hasRole): ?><div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Role column নেই। Role control চালু করতে migration-v4.sql run করতে হবে। Existing admin create/edit/delete কাজ করবে।</div><?php endif; ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Admin Account List</h5>
            <span class="badge bg-light text-dark">Default 10 rows</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" data-ui-paginate="true" data-ui-page-size="10" id="admins-table">
                    <thead class="table-dark"><tr><th>ID</th><th>Username</th><th>Email</th><th>Current Role</th><th>Last Login</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>#<?php echo (int)$u['id']; ?></td>
                            <td><strong><?php echo Security::escape($u['username']); ?></strong><?php if ((int)$u['id'] === $currentAdminId): ?> <span class="badge bg-info">You</span><?php endif; ?></td>
                            <td><?php echo Security::escape($u['email'] ?? ''); ?></td>
                            <td><span class="badge bg-primary"><?php echo Security::escape($u['role'] ?? 'super_admin'); ?></span></td>
                            <td><?php echo Security::escape($u['last_login'] ?? 'Never'); ?></td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editAdmin<?php echo (int)$u['id']; ?>" <?php echo AdminHelpers::canDelete() ? '' : 'disabled'; ?>><i class="bi bi-pencil"></i> Edit</button>
                                    <form method="POST" data-confirm="Delete this admin user?" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo Security::escape($csrf); ?>">
                                        <input type="hidden" name="delete_admin" value="1">
                                        <input type="hidden" name="admin_id" value="<?php echo (int)$u['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger" <?php echo (AdminHelpers::canDelete() && (int)$u['id'] !== $currentAdminId) ? '' : 'disabled'; ?>><i class="bi bi-trash"></i> Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <nav><ul class="pagination justify-content-end" data-ui-pager-for="admins-table"></ul></nav>
        </div>
    </div>
</div>

<div class="modal fade" id="createAdminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="POST" class="needs-validation" novalidate>
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-person-plus"></i> Create New Admin</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo Security::escape($csrf); ?>">
                <input type="hidden" name="create_admin" value="1">
                <div class="mb-3"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required maxlength="50"></div>
                <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" maxlength="100"></div>
                <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required minlength="6"></div>
                <div class="mb-3"><label class="form-label">Role</label><select name="role" class="form-select" <?php echo $hasRole ? '' : 'disabled'; ?>><option value="super_admin">Super Admin</option><option value="manager">Manager</option><option value="viewer" selected>Viewer</option></select></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="bi bi-save"></i> Create Admin</button></div>
        </form>
    </div></div>
</div>

<?php foreach ($users as $u): ?>
<div class="modal fade" id="editAdmin<?php echo (int)$u['id']; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="POST" class="needs-validation" novalidate>
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Admin #<?php echo (int)$u['id']; ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo Security::escape($csrf); ?>">
                <input type="hidden" name="update_admin" value="1">
                <input type="hidden" name="admin_id" value="<?php echo (int)$u['id']; ?>">
                <div class="mb-3"><label class="form-label">Username</label><input type="text" name="username" class="form-control" value="<?php echo Security::escape($u['username']); ?>" required maxlength="50"></div>
                <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?php echo Security::escape($u['email'] ?? ''); ?>" maxlength="100"></div>
                <div class="mb-3"><label class="form-label">New Password <small class="text-muted">blank রাখলে আগের password থাকবে</small></label><input type="password" name="password" class="form-control" minlength="6"></div>
                <div class="mb-3"><label class="form-label">Role</label><select name="role" class="form-select" <?php echo $hasRole ? '' : 'disabled'; ?>><option value="super_admin" <?php echo (($u['role'] ?? 'super_admin') === 'super_admin') ? 'selected' : ''; ?>>Super Admin</option><option value="manager" <?php echo (($u['role'] ?? '') === 'manager') ? 'selected' : ''; ?>>Manager</option><option value="viewer" <?php echo (($u['role'] ?? '') === 'viewer') ? 'selected' : ''; ?>>Viewer</option></select></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button></div>
        </form>
    </div></div>
</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/admin-ui.js"></script>
</body>
</html>
