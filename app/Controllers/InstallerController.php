<?php

declare(strict_types=1);

namespace Yuc\Controllers;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;
use Yuc\Core\ConfigStore;
use Yuc\Core\Database;
use Yuc\Core\View;
use Yuc\Services\LicenseService;
use Yuc\Services\NotificationService;
use Yuc\Services\RequirementsService;
use Yuc\Services\SchemaInstaller;

final class InstallerController
{
    public function show(): void
    {
        if (ConfigStore::isInstalled()) {
            yuc_redirect('/admin/login');
        }

        $inspection = (new RequirementsService())->inspect();
        $pending = ConfigStore::loadPending();
        $state = is_array($_SESSION['installer'] ?? null) ? $_SESSION['installer'] : [];

        if (is_array($pending) && isset($pending['database'])) {
            $state['step'] = 3;
            $state['license'] = $pending['license'] ?? ($state['license'] ?? null);
            $_SESSION['installer'] = $state;
        } elseif ((int) ($state['step'] ?? 1) > 1 && empty($state['license']['valid'])) {
            $state['step'] = 1;
            $_SESSION['installer'] = $state;
        }

        $step = max(1, min(3, (int) ($state['step'] ?? 1)));
        $license = is_array($state['license'] ?? null) ? $state['license'] : null;
        $oldAdmin = is_array($state['old_admin'] ?? null) ? $state['old_admin'] : [];
        $domain = '';
        try {
            $domain = (new LicenseService())->requestDomain();
        } catch (Throwable) {
            // The license stage will display a safe, actionable message on submit.
        }

        View::render('installer', [
            'title' => 'Installation · Youth Unity Cup',
            'bodyClass' => 'installer-page',
            'topNote' => 'SETUP WIZARD',
            'step' => $step,
            'checks' => $inspection['checks'],
            'checksReady' => $inspection['ready'],
            'license' => $license,
            'licenseApi' => LicenseService::API_URL,
            'licenseDocs' => LicenseService::DOCS_URL,
            'domain' => $domain,
            'oldAdmin' => $oldAdmin,
            'pendingTableCount' => is_array($pending['tables'] ?? null) ? count($pending['tables']) : 0,
            'schemaTables' => is_array($pending['tables'] ?? null) ? $pending['tables'] : [],
            'flash' => yuc_take_flash(),
        ]);
    }

    public function validateLicense(): void
    {
        $this->verifyCsrfOrRedirect();
        if (!(new RequirementsService())->inspect()['ready']) {
            yuc_flash('error', 'Resolve the PHP and folder permission checks before continuing.');
            yuc_redirect('/install');
        }

        $key = trim((string) ($_POST['license_key'] ?? ''));
        if ($key === '' || strlen($key) > 512) {
            yuc_flash('error', 'Enter a valid license key to continue.');
            yuc_redirect('/install');
        }

        try {
            $service = new LicenseService();
            $domain = $service->requestDomain();
            $result = $service->verify($key, $domain);
            if (!$result['valid']) {
                yuc_flash('error', $result['message']);
                yuc_redirect('/install');
            }

            $_SESSION['installer'] = [
                'step' => 2,
                'license' => [
                    'key' => $key,
                    'domain' => $domain,
                    'validated_at' => gmdate('Y-m-d H:i:s'),
                    'valid' => true,
                ],
            ];
            yuc_flash('success', 'The license key was verified for this domain.');
            yuc_redirect('/install');
        } catch (Throwable $exception) {
            yuc_flash('error', $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The license verification request could not be completed.');
            yuc_redirect('/install');
        }
    }

    public function installDatabase(): void
    {
        $this->verifyCsrfOrRedirect();
        $state = is_array($_SESSION['installer'] ?? null) ? $_SESSION['installer'] : [];
        if (empty($state['license']['valid'])) {
            yuc_flash('error', 'Verify the license key before configuring the database.');
            yuc_redirect('/install');
        }
        if (!(new RequirementsService())->inspect()['ready']) {
            yuc_flash('error', 'Resolve the system requirements before installing the schema.');
            yuc_redirect('/install');
        }

        $host = trim((string) ($_POST['db_host'] ?? 'localhost'));
        $port = filter_var($_POST['db_port'] ?? '3306', FILTER_VALIDATE_INT);
        $name = trim((string) ($_POST['db_name'] ?? ''));
        $username = trim((string) ($_POST['db_username'] ?? ''));
        $password = (string) ($_POST['db_password'] ?? '');

        if ($host === '' || !preg_match('/^[A-Za-z0-9.:-]+$/', $host)) {
            yuc_flash('error', 'Enter a database host name or IP address.');
            yuc_redirect('/install');
        }
        if ($port === false || $port < 1 || $port > 65535) {
            yuc_flash('error', 'Enter a database port between 1 and 65535.');
            yuc_redirect('/install');
        }
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $name)) {
            yuc_flash('error', 'Database names may contain letters, numbers, and underscores only.');
            yuc_redirect('/install');
        }
        if ($username === '' || strlen($username) > 190 || strlen($password) > 1024) {
            yuc_flash('error', 'Enter a database username and a valid database password.');
            yuc_redirect('/install');
        }

        $database = [
            'host' => $host,
            'port' => (int) $port,
            'name' => $name,
            'username' => $username,
            'password' => $password,
        ];

        try {
            $pdo = Database::connect($database);
            $tables = (new SchemaInstaller())->install($pdo);
            $pending = [
                'database' => $database,
                'license' => $state['license'],
                'tables' => $tables,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ];
            ConfigStore::savePending($pending);
            $_SESSION['installer'] = [
                'step' => 3,
                'license' => $state['license'],
            ];
            yuc_flash('success', 'The database connected and the Youth Unity Cup schema is ready.');
            yuc_redirect('/install');
        } catch (PDOException $exception) {
            error_log('Youth Unity Cup schema installation failed (' . (string) $exception->getCode() . ').');
            yuc_flash('error', 'The database connection or schema setup failed. Check the host, database name, account permissions, and MySQL version.');
            yuc_redirect('/install');
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup schema installation failed (' . get_class($exception) . ').');
            yuc_flash('error', $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The schema could not be installed. Check the database configuration and try again.');
            yuc_redirect('/install');
        }
    }

    public function createAdmin(): void
    {
        $this->verifyCsrfOrRedirect();
        $pending = ConfigStore::loadPending();
        if (!is_array($pending) || !is_array($pending['database'] ?? null) || !is_array($pending['license'] ?? null)) {
            yuc_flash('error', 'Complete the database and schema step before creating an administrator.');
            yuc_redirect('/install');
        }

        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['admin_email'] ?? ''));
        $username = trim((string) ($_POST['admin_username'] ?? ''));
        $password = (string) ($_POST['admin_password'] ?? '');
        $confirmation = (string) ($_POST['admin_password_confirmation'] ?? '');
        $smtpHost = trim((string) ($_POST['smtp_host'] ?? ''));
        $smtpPort = filter_var($_POST['smtp_port'] ?? '587', FILTER_VALIDATE_INT);
        $smtpEncryption = (string) ($_POST['smtp_encryption'] ?? 'tls');
        $smtpUsername = trim((string) ($_POST['smtp_username'] ?? ''));
        $smtpPassword = (string) ($_POST['smtp_password'] ?? '');
        $mailFrom = trim((string) ($_POST['mail_from_email'] ?? $email));
        $mailFromName = trim((string) ($_POST['mail_from_name'] ?? 'Youth Unity Cup'));
        $notificationsTo = trim((string) ($_POST['notifications_to'] ?? $email));

        $_SESSION['installer']['old_admin'] = [
            'full_name' => $fullName,
            'admin_email' => $email,
            'admin_username' => $username,
            'smtp_host' => $smtpHost,
            'smtp_port' => $smtpPort === false ? '587' : (string) $smtpPort,
            'smtp_encryption' => $smtpEncryption,
            'smtp_username' => $smtpUsername,
            'mail_from_email' => $mailFrom,
            'mail_from_name' => $mailFromName,
            'notifications_to' => $notificationsTo,
        ];

        if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 120) {
            $this->adminError('Enter your full name (2 to 120 characters).');
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->adminError('Enter a valid administrator email address.');
        }
        if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username)) {
            $this->adminError('Choose a username between 3 and 60 characters using letters, numbers, dots, underscores, or hyphens.');
        }
        if (mb_strlen($password) < 12 || $password !== $confirmation) {
            $this->adminError('Use a password of at least 12 characters and make sure both password fields match.');
        }

        if ($smtpHost !== '') {
            if (!preg_match('/^[A-Za-z0-9.-]{1,253}$/', $smtpHost)) {
                $this->adminError('Enter the SMTP host name only, without a protocol or path.');
            }
            if ($smtpPort === false || $smtpPort < 1 || $smtpPort > 65535) {
                $this->adminError('Enter a valid SMTP port between 1 and 65535.');
            }
            if (!in_array($smtpEncryption, ['tls', 'ssl', 'none'], true)) {
                $this->adminError('Choose TLS, SSL, or no encryption for the SMTP server.');
            }
            if ($smtpUsername !== '' && $smtpPassword === '') {
                $this->adminError('Enter the SMTP password or leave both SMTP authentication fields empty.');
            }
            if (filter_var($mailFrom, FILTER_VALIDATE_EMAIL) === false || filter_var($notificationsTo, FILTER_VALIDATE_EMAIL) === false) {
                $this->adminError('Enter valid sender and notification recipient email addresses.');
            }
            if ($mailFromName === '' || mb_strlen($mailFromName) > 120) {
                $this->adminError('Enter a sender name up to 120 characters.');
            }
        }

        $mailConfig = [
            'host' => $smtpHost,
            'port' => $smtpPort === false ? 587 : (int) $smtpPort,
            'encryption' => $smtpEncryption,
            'username' => $smtpUsername,
            'password' => $smtpPassword,
            'from_email' => $smtpHost === '' ? $email : $mailFrom,
            'from_name' => $smtpHost === '' ? 'Youth Unity Cup' : $mailFromName,
            'notifications_to' => $smtpHost === '' ? $email : $notificationsTo,
        ];

        $pdo = null;
        try {
            $pdo = Database::connect($pending['database']);
            $pdo->beginTransaction();
            $existing = $pdo->query("SELECT id, full_name, email, username, password_hash FROM users WHERE role = 'super_admin' ORDER BY id ASC LIMIT 1 FOR UPDATE")->fetch();

            if (is_array($existing)) {
                $sameAccount = strtolower((string) $existing['email']) === strtolower($email)
                    && strtolower((string) $existing['username']) === strtolower($username)
                    && password_verify($password, (string) $existing['password_hash']);
                if (!$sameAccount) {
                    throw new RuntimeException('A super-admin account already exists in this database. Re-enter the same administrator details if you are resuming setup.');
                }
                $adminId = (int) $existing['id'];
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                if (!is_string($passwordHash)) {
                    throw new RuntimeException('The administrator password could not be securely hashed.');
                }
                $insert = $pdo->prepare(
                    "INSERT INTO users (full_name, email, username, password_hash, role, status, created_at, updated_at) "
                    . "VALUES (:full_name, :email, :username, :password_hash, 'super_admin', 'active', UTC_TIMESTAMP(), UTC_TIMESTAMP())"
                );
                $insert->execute([
                    'full_name' => $fullName,
                    'email' => $email,
                    'username' => $username,
                    'password_hash' => $passwordHash,
                ]);
                $adminId = (int) $pdo->lastInsertId();
            }

            $initialSettings = [
                'site_title' => 'Youth Unity Cup',
                'timezone' => 'UTC',
                'contact_email' => '',
                'max_login_attempts' => '5',
                'login_window_minutes' => '15',
                'block_duration_minutes' => '15',
            ];
            $saveSetting = $pdo->prepare('INSERT IGNORE INTO system_settings (setting_key, setting_value, updated_at) VALUES (:setting_key, :setting_value, UTC_TIMESTAMP())');
            foreach ($initialSettings as $settingKey => $settingValue) {
                $saveSetting->execute(['setting_key' => $settingKey, 'setting_value' => $settingValue]);
            }

            $pdo->commit();
            $appConfig = [
                'app' => [
                    'name' => 'Youth Unity Cup',
                    'site_title' => 'Youth Unity Cup',
                    'contact_email' => '',
                    'installation_complete' => true,
                    'timezone' => 'UTC',
                    'installed_at' => gmdate('Y-m-d H:i:s'),
                ],
                'database' => $pending['database'],
                'mail' => $mailConfig,
                'license' => $pending['license'],
                'admin' => [
                    'id' => $adminId,
                    'email' => $email,
                    'username' => $username,
                ],
            ];
            ConfigStore::finalize($appConfig);
        } catch (PDOException $exception) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Youth Unity Cup admin setup failed (' . (string) $exception->getCode() . ').');
            $this->adminError('The administrator could not be saved. Check that the schema is installed and that this email and username are not already in use.');
        } catch (Throwable $exception) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Youth Unity Cup admin setup failed (' . get_class($exception) . ').');
            $this->adminError($exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The administrator account could not be created. Check the database connection and try again.');
        }

        $receipt = [
            'name' => $fullName,
            'email' => $email,
            'username' => $username,
            'emailConfigured' => $mailConfig['host'] !== '',
        ];
        $_SESSION['install_receipt'] = $receipt;
        unset($_SESSION['installer']);

        if ($pdo instanceof PDO) {
            try {
                $config = ConfigStore::load() ?? [];
                (new NotificationService($pdo, $config))->notifyActivity(
                    'system.install_completed',
                    'Youth Unity Cup installation completed',
                    'The Youth Unity Cup installation is complete and the first super-administrator account is ready.',
                    [
                        'user_email' => $email,
                        'user_id' => $adminId,
                        'notify_admin' => true,
                        'ip_address' => yuc_client_ip(),
                        'context' => ['admin_username' => $username],
                    ]
                );
            } catch (Throwable $exception) {
                error_log('Youth Unity Cup install notification could not be queued (' . get_class($exception) . ').');
            }
        }

        yuc_redirect('/install/complete');
    }

    public function complete(): void
    {
        if (!ConfigStore::isInstalled()) {
            yuc_redirect('/install');
        }

        $receipt = $_SESSION['install_receipt'] ?? null;
        if (!is_array($receipt)) {
            yuc_redirect('/admin/login');
        }

        View::render('installer', [
            'title' => 'Installation complete · Youth Unity Cup',
            'bodyClass' => 'installer-page',
            'topNote' => 'SETUP COMPLETE',
            'step' => 4,
            'checks' => [],
            'checksReady' => true,
            'receipt' => $receipt,
            'flash' => yuc_take_flash(),
        ]);
    }

    private function verifyCsrfOrRedirect(): void
    {
        if (!yuc_verify_csrf()) {
            yuc_flash('error', 'Your setup session expired. Refresh the page and try again.');
            yuc_redirect('/install');
        }
    }

    private function adminError(string $message): void
    {
        yuc_flash('error', $message);
        yuc_redirect('/install');
    }
}
