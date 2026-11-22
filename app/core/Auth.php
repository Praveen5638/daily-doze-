<?php
declare(strict_types=1);

final class Auth
{
    private static ?array $customer = null;
    private static bool $customerLoaded = false;
    private static ?array $admin = null;
    private static bool $adminLoaded = false;
    private static array $permissions = [];

    public static function customer(): ?array
    {
        if (self::$customerLoaded) {
            return self::$customer;
        }
        self::$customerLoaded = true;
        $id = (int) ($_SESSION['customer_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $row = Database::one(
            'SELECT id, first_name, last_name, email, phone, status, created_at FROM customers WHERE id = ?',
            [$id]
        );
        if ($row === null || $row['status'] === 'disabled') {
            unset($_SESSION['customer_id']);
            return null;
        }
        self::$customer = $row;
        return $row;
    }

    public static function customerId(): ?int
    {
        $customer = self::customer();
        return $customer ? (int) $customer['id'] : null;
    }

    public static function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $bucket = 'customer:' . request_ip() . ':' . $email;
        if (!RateLimiter::hit($bucket, 'login', 6, 900)) {
            $minutes = max(1, (int) ceil(RateLimiter::retryAfter($bucket, 'login', 900) / 60));
            return ['ok' => false, 'message' => sprintf(t('auth.flash_too_many', 'Too many attempts. Please try again in %d minute(s) or reset your password.'), $minutes)];
        }

        $customer = Database::one('SELECT * FROM customers WHERE email = ?', [$email]);
        if ($customer === null || !password_verify($password, (string) $customer['password_hash'])) {
            return ['ok' => false, 'message' => t('auth.flash_incorrect', 'Email or password is incorrect.')];
        }
        if ($customer['status'] === 'disabled') {
            return ['ok' => false, 'message' => t('auth.flash_disabled', 'This account is disabled. Please contact customer support.')];
        }

        RateLimiter::clear($bucket, 'login');
        session_regenerate_id(true);
        $_SESSION['customer_id'] = (int) $customer['id'];
        Csrf::rotate();
        self::$customer = $customer;
        self::$customerLoaded = true;
        Database::update('customers', ['last_login_at' => date('Y-m-d H:i:s')], 'id = ?', [$customer['id']]);
        Cart::mergeGuestCart();
        return ['ok' => true, 'message' => t('auth.flash_welcome', 'Welcome back.'), 'customer' => $customer];
    }

    public static function register(array $data): array
    {
        $email = strtolower(trim((string) $data['email']));
        $phone = preg_replace('/[^0-9+]/', '', (string) $data['phone']) ?? '';
        $password = (string) $data['password'];
        $firstName = trim((string) $data['first_name']);
        $lastName = trim((string) $data['last_name'] ?? '');

        if ($firstName === '' || mb_strlen($firstName) > 80) {
            return ['ok' => false, 'message' => t('auth.flash_first', 'Please enter your first name.')];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => t('auth.flash_email', 'Please enter a valid email address.')];
        }
        if (strlen(preg_replace('/\D/', '', $phone) ?? '') < 10) {
            return ['ok' => false, 'message' => t('auth.flash_phone', 'Please enter a valid mobile number.')];
        }
        if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,72}$/', $password)) {
            return ['ok' => false, 'message' => t('auth.flash_password', 'Password must be at least 8 characters and include a letter and a number.')];
        }
        if (Database::value('SELECT COUNT(*) FROM customers WHERE email = ?', [$email]) > 0) {
            return ['ok' => false, 'message' => t('auth.flash_exists', 'An account with this email already exists. Please log in instead.')];
        }

        $id = Database::insert('customers', [
            'first_name' => $firstName,
            'last_name' => $lastName !== '' ? $lastName : null,
            'email' => $email,
            'phone' => $phone,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'status' => 'active',
        ]);
        $customer = Database::one('SELECT * FROM customers WHERE id = ?', [$id]);
        if ($customer === null) {
            return ['ok' => false, 'message' => t('auth.flash_create_failed', 'Could not create the account. Please try again.')];
        }

        session_regenerate_id(true);
        $_SESSION['customer_id'] = $id;
        Csrf::rotate();
        self::$customer = $customer;
        self::$customerLoaded = true;
        Cart::mergeGuestCart();

        Mailer::send(
            $email,
            'Welcome to ' . setting('site_name', 'Daily Dose Export'),
            email_wrap(
                'Welcome, ' . $firstName,
                '<p>Thank you for creating an account with ' . e(setting('site_name', 'Daily Dose Export')) . '.</p>'
                . '<p>You can now save delivery addresses, view your orders and keep a wishlist.</p>',
                ['url' => url('shop'), 'label' => 'Explore the shop']
            )
        );
        return ['ok' => true, 'message' => t('auth.flash_ready', 'Your account is ready.'), 'customer' => $customer];
    }

    public static function logout(): void
    {
        unset($_SESSION['customer_id']);
        self::$customer = null;
        self::$customerLoaded = true;
        session_regenerate_id(true);
        Csrf::rotate();
    }

    public static function admin(): ?array
    {
        if (self::$adminLoaded) {
            return self::$admin;
        }
        self::$adminLoaded = true;
        $id = (int) ($_SESSION['admin_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $row = Database::one(
            'SELECT a.*, r.name AS role_name, r.slug AS role_slug
             FROM admins a JOIN admin_roles r ON r.id = a.role_id
             WHERE a.id = ? AND a.status = "active"',
            [$id]
        );
        if ($row === null) {
            unset($_SESSION['admin_id']);
            return null;
        }
        self::$admin = $row;
        return $row;
    }

    public static function adminLogin(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $bucket = 'admin:' . request_ip() . ':' . $email;
        if (!RateLimiter::hit($bucket, 'login', 5, 900)) {
            return ['ok' => false, 'message' => 'Too many attempts. Try again in 15 minutes.'];
        }
        $admin = Database::one(
            'SELECT a.*, r.name AS role_name, r.slug AS role_slug
             FROM admins a JOIN admin_roles r ON r.id = a.role_id
             WHERE a.email = ?',
            [$email]
        );
        if ($admin === null || !password_verify($password, (string) $admin['password_hash'])) {
            return ['ok' => false, 'message' => 'Email or password is incorrect.'];
        }
        if ($admin['status'] !== 'active') {
            return ['ok' => false, 'message' => 'This staff account is disabled.'];
        }
        RateLimiter::clear($bucket, 'login');
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        Csrf::rotate();
        self::$admin = $admin;
        self::$adminLoaded = true;
        self::$permissions = [];
        Database::update('admins', ['last_login_at' => date('Y-m-d H:i:s')], 'id = ?', [$admin['id']]);
        Notifications::audit('admin.login', 'admin', (string) $admin['id']);
        return ['ok' => true, 'message' => 'Signed in.', 'admin' => $admin];
    }

    public static function adminLogout(): void
    {
        unset($_SESSION['admin_id']);
        self::$admin = null;
        self::$adminLoaded = true;
        self::$permissions = [];
        session_regenerate_id(true);
        Csrf::rotate();
    }

    public static function adminCan(string $permission): bool
    {
        $admin = self::admin();
        if ($admin === null) {
            return false;
        }
        if ($admin['role_slug'] === 'super_admin') {
            return true;
        }
        if (!isset(self::$permissions[$admin['role_id']])) {
            self::$permissions[$admin['role_id']] = array_column(
                Database::all('SELECT permission FROM admin_permissions WHERE role_id = ?', [$admin['role_id']]),
                'permission'
            );
        }
        return in_array($permission, self::$permissions[$admin['role_id']], true);
    }

    public static function requireAdmin(?string $permission = null): void
    {
        if (self::admin() === null) {
            $next = $_SERVER['REQUEST_URI'] ?? '/admin/index.php';
            redirect('admin/login.php?next=' . urlencode($next));
        }
        if ($permission !== null && !self::adminCan($permission)) {
            http_response_code(403);
            include BASE_PATH . '/public/admin/inc/forbidden.php';
            exit;
        }
    }

    public static function requireCustomer(string $next = 'account.php'): array
    {
        $customer = self::customer();
        if ($customer === null) {
            redirect('login.php?next=' . urlencode($next));
        }
        return $customer;
    }
}
