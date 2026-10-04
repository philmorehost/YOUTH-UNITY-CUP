<?php

declare(strict_types=1);

namespace Yuc\Controllers;

use DateTimeZone;
use InvalidArgumentException;
use PDO;
use PDOException;
use Throwable;
use Yuc\Core\ConfigStore;
use Yuc\Core\View;
use Yuc\Services\EnvironmentModeService;
use Yuc\Services\HeroService;
use Yuc\Services\PayHubClient;
use Yuc\Services\ShopService;
use Yuc\Services\TournamentService;

final class AdminOperationsController
{
    private TournamentService $tournament;
    private ShopService $shop;
    private PayHubClient $payHub;
    private EnvironmentModeService $environmentMode;
    private HeroService $hero;

    /** @param array<string,mixed> $config */
    public function __construct(private PDO $pdo, private array $config)
    {
        $this->tournament = new TournamentService($pdo, $config);
        $this->shop = new ShopService($pdo, $config);
        $this->payHub = new PayHubClient($config);
        $this->environmentMode = new EnvironmentModeService($pdo);
        $this->hero = new HeroService($pdo);
    }

    public function manage(string $resource): void
    {
        $admin = $this->requireAdmin();
        $isDemoMode = $this->environmentMode->isDemo();
        $allowed = ['teams', 'players', 'venues', 'fixtures', 'registrations', 'transactions', 'products', 'orders', 'settings', 'homepage-hero', 'security', 'activity'];
        if (!in_array($resource, $allowed, true)) {
            http_response_code(404);
            View::render('not-found', ['title' => 'Not found · Youth Unity Cup']);
            return;
        }

        $payInput = $_GET['pay'] ?? 0;
        $payId = filter_var(is_scalar($payInput) ? $payInput : 0, FILTER_VALIDATE_INT) ?: 0;
        if ($resource === 'orders' && $payId > 0) {
            if ($isDemoMode || !$this->payHub->isInlineConfigured()) {
                yuc_flash('error', $isDemoMode
                    ? 'PayHub checkout is paused while the site is in Demo mode.'
                    : 'PayHub inline checkout is not currently configured.');
                yuc_redirect('/admin/orders');
            }
            $payOrder = $this->shop->inlineOrderForAdmin($payId);
            if ($payOrder === null) {
                yuc_flash('error', 'That order is no longer awaiting an inline payment.');
                yuc_redirect('/admin/orders');
            }
            View::render('shop-inline-checkout', [
                'title' => 'PayHub inline checkout · Youth Unity Cup Admin',
                'topNote' => 'PAYHUB SECURE INLINE CHECKOUT',
                'bodyClass' => 'admin-page',
                'siteMode' => 'production',
                'order' => $payOrder,
                'payHubPublicKey' => $this->payHub->publicKey(),
                'returnUrl' => '/admin/orders',
                'contactEmail' => (string) ($this->config['app']['contact_email'] ?? ''),
                'admin' => $admin,
                'inlinePayHubScript' => true,
            ]);
            return;
        }

        $rows = [];
        $formValues = [];
        $editId = filter_var($_GET['edit'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        $editIpInput = $_GET['edit_ip'] ?? '';
        $editIp = is_string($editIpInput) ? trim($editIpInput) : '';
        $archivedInput = $_GET['show_archived'] ?? '';
        $showArchived = is_scalar($archivedInput) && (string) $archivedInput === '1';
        if ($resource === 'teams') {
            $rows = $this->tournament->teams();
        } elseif ($resource === 'players') {
            $rows = $this->tournament->players();
        } elseif ($resource === 'venues') {
            $rows = $this->tournament->venues();
        } elseif ($resource === 'fixtures') {
            $rows = $this->tournament->adminFixtures();
        } elseif ($resource === 'registrations') {
            $rows = $this->tournament->registrations();
        } elseif ($resource === 'transactions') {
            $rows = $this->tournament->transactions($showArchived);
        } elseif ($resource === 'products') {
            $rows = $this->shop->adminProducts();
        } elseif ($resource === 'orders') {
            $rows = $this->shop->orders($showArchived, !$isDemoMode);
        } elseif ($resource === 'security') {
            $rows = $this->tournament->blockedIps();
        }
        $orderProducts = $resource === 'orders' ? $this->shop->publicProducts(!$isDemoMode) : [];
        $audit = ['entries' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        $auditSearch = '';
        $auditCategory = '';
        if ($resource === 'activity') {
            $pageInput = $_GET['page'] ?? 1;
            $searchInput = $_GET['q'] ?? '';
            $categoryInput = $_GET['category'] ?? '';
            $requestedPage = filter_var(is_scalar($pageInput) ? $pageInput : 1, FILTER_VALIDATE_INT);
            $auditSearch = is_string($searchInput) ? mb_substr(trim($searchInput), 0, 120) : '';
            $auditCategory = is_string($categoryInput) ? $categoryInput : '';
            $audit = $this->tournament->auditEntries($requestedPage === false ? 1 : $requestedPage, $auditSearch, $auditCategory);
            $rows = $audit['entries'];
            $auditCategory = in_array($auditCategory, ['auth', 'security', 'system', 'registration', 'transaction', 'tournament'], true) ? $auditCategory : '';
        }
        if ($editId > 0 && in_array($resource, ['teams', 'venues', 'fixtures'], true)) {
            $formValues = $this->tournament->find($resource, $editId) ?? [];
            if ($resource === 'fixtures' && isset($formValues['kickoff_at'])) {
                $timezone = (string) ($this->config['app']['timezone'] ?? 'UTC');
                if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
                    $timezone = 'UTC';
                }
                $date = new \DateTimeImmutable((string) $formValues['kickoff_at'], new DateTimeZone('UTC'));
                $formValues['kickoff_at'] = $date->setTimezone(new DateTimeZone($timezone))->format('Y-m-d\TH:i');
            }
        } elseif ($editId > 0 && $resource === 'players') {
            $formValues = $this->tournament->findPlayer($editId) ?? [];
        } elseif ($editId > 0 && $resource === 'products') {
            $formValues = $this->shop->findProduct($editId) ?? [];
        } elseif ($editId > 0 && $resource === 'registrations') {
            $formValues = $this->tournament->findRegistration($editId) ?? [];
        } elseif ($editId > 0 && $resource === 'transactions') {
            $formValues = $this->tournament->findTransactionForAdmin($editId) ?? [];
        } elseif ($editId > 0 && $resource === 'orders') {
            $formValues = $this->shop->findOrderForAdmin($editId) ?? [];
        } elseif ($resource === 'security' && $editIp !== '') {
            $formValues = $this->tournament->findBlockedIp($editIp) ?? [];
        }

        $old = $_SESSION['_old_form'] ?? [];
        if (is_array($old) && ($old['resource'] ?? '') === $resource && is_array($old['values'] ?? null)) {
            $formValues = $old['values'];
        }
        unset($_SESSION['_old_form']);
        if (in_array($resource, ['transactions', 'orders'], true) && !empty($formValues['archived_at'])) {
            $formValues = [];
        }

        $settings = $resource === 'settings' ? $this->tournament->settings() : [];
        $heroSettings = $resource === 'homepage-hero' ? $this->hero->settings() : [];
        View::render('admin-manage', [
            'title' => ($resource === 'activity' ? 'Audit activity' : ($resource === 'homepage-hero' ? 'Homepage hero' : ucfirst($resource))) . ' · Youth Unity Cup Admin',
            'topNote' => 'ADMIN CONTROL ROOM',
            'bodyClass' => 'admin-page',
            'admin' => $admin,
            'resource' => $resource,
            'rows' => $rows,
            'formValues' => $formValues,
            'orderProducts' => $orderProducts,
            'teams' => $resource === 'fixtures' ? $this->tournament->teams(true) : ($resource === 'players' ? $this->tournament->teams() : []),
            'venues' => $resource === 'fixtures' ? $this->tournament->venues(true) : [],
            'siteMode' => $isDemoMode ? 'demo' : 'production',
            'settings' => $settings,
            'heroSettings' => $heroSettings,
            'appTimezone' => (string) ($this->config['app']['timezone'] ?? 'UTC'),
            'mail' => is_array($this->config['mail'] ?? null) ? $this->config['mail'] : [],
            'payHubConfigured' => $this->payHub->isInlineConfigured(),
            'payHubSecretConfigured' => $this->payHub->isConfigured(),
            'payHubPublicConfigured' => $this->payHub->publicKey() !== '',
            'auditTotal' => $audit['total'],
            'auditPage' => $audit['page'],
            'auditPages' => $audit['pages'],
            'auditSearch' => $auditSearch,
            'auditCategory' => $auditCategory,
            'showArchived' => $showArchived,
            'flash' => yuc_take_flash(),
        ]);
    }

    public function save(string $resource): void
    {
        $admin = $this->requireAdmin();
        $this->verifyCsrf('/admin/' . $resource);
        $this->guardDemoWrite($resource, '/admin/' . $resource);
        $values = $_POST;
        try {
            if ($resource === 'teams') {
                $this->tournament->saveTeam($values, (int) $admin['id']);
                yuc_flash('success', 'Team details saved.');
            } elseif ($resource === 'players') {
                $this->tournament->savePlayer($values, (int) $admin['id']);
                yuc_flash('success', 'Player profile saved.');
            } elseif ($resource === 'venues') {
                $this->tournament->saveVenue($values, (int) $admin['id']);
                yuc_flash('success', 'Venue details saved.');
            } elseif ($resource === 'fixtures') {
                $this->tournament->saveFixture($values, (int) $admin['id'], (string) ($this->config['app']['timezone'] ?? 'UTC'));
                yuc_flash('success', 'Fixture details saved.');
            } elseif ($resource === 'registrations') {
                $reference = $this->tournament->saveRegistration($values, (int) $admin['id']);
                yuc_flash('success', 'Registration ' . $reference . ' was saved.');
            } elseif ($resource === 'transactions') {
                $wasEdit = (int) ($values['id'] ?? 0) > 0;
                $reference = $this->tournament->saveTransaction($values, (int) $admin['id']);
                yuc_flash('success', $wasEdit ? 'Transaction ' . $reference . ' was updated.' : 'Transaction ' . $reference . ' was recorded. Notification delivery status is shown on the dashboard.');
            } elseif ($resource === 'products') {
                $this->shop->saveProduct($values, (int) $admin['id']);
                yuc_flash('success', 'Shop product details saved.');
            } elseif ($resource === 'orders') {
                $this->shop->updateOrderDetails($values, (int) $admin['id']);
                yuc_flash('success', 'Shop order customer and fulfillment details saved. Payment data was not changed.');
            } elseif ($resource === 'security') {
                $this->tournament->saveBlockedIp($values, (int) $admin['id']);
                yuc_flash('success', 'The IP block was saved.');
            } else {
                yuc_flash('error', 'That form cannot be saved.');
            }
        } catch (InvalidArgumentException $exception) {
            $_SESSION['_old_form'] = ['resource' => $resource, 'values' => $values];
            yuc_flash('error', $exception->getMessage());
        } catch (PDOException $exception) {
            error_log('Youth Unity Cup admin data save failed (' . (string) $exception->getCode() . ').');
            $_SESSION['_old_form'] = ['resource' => $resource, 'values' => $values];
            yuc_flash('error', 'The record could not be saved. Check for duplicate team, venue, SKU, or reference values and verify the database connection.');
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup admin data save failed (' . get_class($exception) . ').');
            yuc_flash('error', 'The record could not be saved. Please try again.');
        }
        yuc_redirect('/admin/' . $resource);
    }

    public function createOrder(): void
    {
        $admin = $this->requireAdmin();
        $redirect = '/admin/orders';
        $this->verifyCsrf($redirect);
        $this->guardDemoWrite('orders', $redirect);
        $values = $_POST;
        if (!$this->payHub->isInlineConfigured()) {
            yuc_flash('error', 'Configure both PayHub keys before creating an order so its inline payment can be tracked safely.');
            yuc_redirect($redirect);
        }

        $orderReference = '';
        try {
            $order = $this->shop->createOrder($values, yuc_client_ip(), (int) $admin['id']);
            $orderReference = (string) $order['reference'];
            $providerReference = PayHubClient::createInlineReference();
            $this->shop->attachInlinePayment($orderReference, $providerReference);
            yuc_redirect('/admin/orders?pay=' . (int) $order['id']);
        } catch (InvalidArgumentException $exception) {
            $this->closeIncompleteOrder($orderReference);
            $_SESSION['_old_form'] = ['resource' => 'orders', 'values' => $this->safeOrderFormValues($values)];
            yuc_flash('error', $exception->getMessage());
        } catch (Throwable $exception) {
            $this->closeIncompleteOrder($orderReference);
            error_log('Youth Unity Cup admin shop-order creation failed (' . get_class($exception) . ').');
            $_SESSION['_old_form'] = ['resource' => 'orders', 'values' => $this->safeOrderFormValues($values)];
            yuc_flash('error', 'The order could not be created or its PayHub inline checkout could not be prepared. Any stock reservation has been released. Please try again.');
        }
        yuc_redirect($redirect);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function safeOrderFormValues(array $input): array
    {
        $value = static function (mixed $candidate, int $limit): string {
            return is_scalar($candidate) ? mb_substr(trim((string) $candidate), 0, $limit) : '';
        };
        $quantities = [];
        if (is_array($input['quantity'] ?? null)) {
            foreach (array_slice($input['quantity'], 0, 120, true) as $id => $quantity) {
                if (is_scalar($id) && is_scalar($quantity) && filter_var((string) $id, FILTER_VALIDATE_INT) !== false) {
                    $quantities[(string) $id] = mb_substr((string) $quantity, 0, 3);
                }
            }
        }
        return [
            'create_order' => '1',
            'customer_name' => $value($input['customer_name'] ?? '', 140),
            'customer_email' => $value($input['customer_email'] ?? '', 190),
            'customer_phone' => $value($input['customer_phone'] ?? '', 40),
            'fulfillment_notes' => $value($input['fulfillment_notes'] ?? '', 500),
            'quantity' => $quantities,
        ];
    }

    private function closeIncompleteOrder(string $reference): void
    {
        if ($reference === '') {
            return;
        }
        try {
            $this->shop->failPaymentInitialization($reference);
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup failed admin shop reservation cleanup (' . get_class($exception) . ').');
        }
    }

    public function delete(string $resource): void
    {
        $admin = $this->requireAdmin();
        $redirect = '/admin/' . $resource;
        $this->verifyCsrf($redirect);
        $this->guardDemoWrite($resource, $redirect);
        try {
            if ($resource === 'security') {
                $ipInput = $_POST['ip_address'] ?? '';
                if (!is_scalar($ipInput)) {
                    throw new InvalidArgumentException('The selected IP address is not valid.');
                }
                $this->tournament->unblockIp((string) $ipInput, (int) $admin['id']);
                yuc_flash('success', 'The IP block was removed.');
            } else {
                $idInput = $_POST['id'] ?? null;
                $id = filter_var(is_scalar($idInput) ? $idInput : null, FILTER_VALIDATE_INT);
                if ($id === false || $id < 1) {
                    throw new InvalidArgumentException('The selected record is not valid.');
                }
                if ($resource === 'teams') {
                    $this->tournament->deleteTeam($id, (int) $admin['id']);
                    yuc_flash('success', 'Team deleted.');
                } elseif ($resource === 'players') {
                    $this->tournament->deletePlayer($id, (int) $admin['id']);
                    yuc_flash('success', 'Player profile deleted.');
                } elseif ($resource === 'venues') {
                    $this->tournament->deleteVenue($id, (int) $admin['id']);
                    yuc_flash('success', 'Venue deleted.');
                } elseif ($resource === 'fixtures') {
                    $this->tournament->deleteFixture($id, (int) $admin['id']);
                    yuc_flash('success', 'Fixture deleted.');
                } elseif ($resource === 'registrations') {
                    $this->tournament->deleteRegistration($id, (int) $admin['id']);
                    yuc_flash('success', 'Registration deleted. Personal details were removed; the audit log retains only its reference.');
                } elseif ($resource === 'products') {
                    $deleted = $this->shop->deleteProduct($id, (int) $admin['id']);
                    yuc_flash('success', $deleted ? 'Product deleted.' : 'Product has order history, so it was archived from the public catalog instead of being erased.');
                } else {
                    throw new InvalidArgumentException('Delete is not available for that record type.');
                }
            }
        } catch (InvalidArgumentException $exception) {
            yuc_flash('error', $exception->getMessage());
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup record deletion failed (' . get_class($exception) . ').');
            yuc_flash('error', 'The record could not be deleted. Check whether it is still referenced by tournament or order history.');
        }
        yuc_redirect($redirect);
    }

    public function archive(string $resource): void
    {
        $admin = $this->requireAdmin();
        $redirect = '/admin/' . $resource;
        $this->verifyCsrf($redirect);
        $this->guardDemoWrite($resource, $redirect);
        $idInput = $_POST['id'] ?? null;
        $id = filter_var(is_scalar($idInput) ? $idInput : null, FILTER_VALIDATE_INT);
        if (!in_array($resource, ['transactions', 'orders'], true) || $id === false || $id < 1) {
            yuc_flash('error', 'The selected record was not valid for archiving.');
            yuc_redirect($redirect);
        }
        $archiveInput = $_POST['archived'] ?? '';
        if (!is_scalar($archiveInput) || !in_array((string) $archiveInput, ['0', '1'], true)) {
            yuc_flash('error', 'Choose a valid archive action.');
            yuc_redirect($redirect);
        }
        try {
            $archived = (string) $archiveInput === '1';
            if ($resource === 'transactions') {
                $this->tournament->setTransactionArchived($id, $archived, (int) $admin['id']);
            } else {
                $this->shop->setOrderArchived($id, $archived, (int) $admin['id']);
            }
            yuc_flash('success', $archived ? 'Record archived. The payment/audit history remains preserved.' : 'Record restored.');
        } catch (InvalidArgumentException $exception) {
            yuc_flash('error', $exception->getMessage());
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup record archive failed (' . get_class($exception) . ').');
            yuc_flash('error', 'The record could not be archived. Please try again.');
        }
        yuc_redirect($redirect);
    }

    public function unblockIp(): void
    {
        $admin = $this->requireAdmin();
        $this->verifyCsrf('/admin/security');
        $this->guardDemoWrite('security', '/admin/security');
        try {
            $this->tournament->unblockIp((string) ($_POST['ip_address'] ?? ''), (int) $admin['id']);
            yuc_flash('success', 'The temporary IP block was removed.');
        } catch (InvalidArgumentException $exception) {
            yuc_flash('error', $exception->getMessage());
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup IP block update failed (' . get_class($exception) . ').');
            yuc_flash('error', 'The IP block could not be changed.');
        }
        yuc_redirect('/admin/security');
    }

    public function updateStatus(string $resource): void
    {
        $admin = $this->requireAdmin();
        $this->verifyCsrf('/admin/' . $resource);
        $this->guardDemoWrite($resource, '/admin/' . $resource);
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            yuc_flash('error', 'The selected record was not valid.');
            yuc_redirect('/admin/' . $resource);
        }

        try {
            if ($resource === 'teams') {
                $this->setSimpleStatus('teams', $id, (string) ($_POST['status'] ?? ''), ['active', 'inactive'], (int) $admin['id']);
            } elseif ($resource === 'venues') {
                $this->setSimpleStatus('venues', $id, (string) ($_POST['status'] ?? ''), ['active', 'inactive'], (int) $admin['id']);
            } elseif ($resource === 'registrations') {
                $this->tournament->updateRegistrationStatus($id, (string) ($_POST['status'] ?? ''), (int) $admin['id']);
            } elseif ($resource === 'transactions') {
                $this->tournament->updateTransactionStatus($id, (string) ($_POST['status'] ?? ''), (int) $admin['id']);
            } elseif ($resource === 'orders') {
                $orderStatus = $_POST['status'] ?? '';
                $this->shop->updateOrderStatus($id, is_scalar($orderStatus) ? (string) $orderStatus : '', (int) $admin['id']);
            } else {
                throw new InvalidArgumentException('Status changes are not available for that record.');
            }
            yuc_flash('success', 'The record status was updated.');
        } catch (InvalidArgumentException $exception) {
            yuc_flash('error', $exception->getMessage());
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup status update failed (' . get_class($exception) . ').');
            yuc_flash('error', 'The record status could not be updated.');
        }
        yuc_redirect('/admin/' . $resource);
    }

    public function saveHomepageHero(): void
    {
        $admin = $this->requireAdmin();
        $this->verifyCsrf('/admin/homepage-hero');
        $this->guardDemoWrite('homepage-hero', '/admin/homepage-hero');
        try {
            $this->hero->save($_POST, $_FILES, (int) $admin['id'], yuc_client_ip());
            yuc_flash('success', 'The homepage hero was updated.');
        } catch (InvalidArgumentException $exception) {
            yuc_flash('error', $exception->getMessage());
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup homepage hero update failed (' . get_class($exception) . ').');
            yuc_flash('error', 'The homepage hero could not be saved. Check protected storage permissions and try again.');
        }
        yuc_redirect('/admin/homepage-hero');
    }

    public function saveSettings(): void
    {
        $admin = $this->requireAdmin();
        $this->verifyCsrf('/admin/settings');
        $this->guardDemoWrite('settings', '/admin/settings');
        $siteTitle = trim((string) ($_POST['site_title'] ?? ''));
        $timezone = trim((string) ($_POST['timezone'] ?? 'UTC'));
        $contactEmail = trim((string) ($_POST['contact_email'] ?? ''));
        $maxAttempts = filter_var($_POST['max_login_attempts'] ?? '5', FILTER_VALIDATE_INT);
        $window = filter_var($_POST['login_window_minutes'] ?? '15', FILTER_VALIDATE_INT);
        $block = filter_var($_POST['block_duration_minutes'] ?? '15', FILTER_VALIDATE_INT);

        try {
            if ($siteTitle === '' || mb_strlen($siteTitle) > 100) {
                throw new InvalidArgumentException('Site title is required and must be at most 100 characters.');
            }
            if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
                throw new InvalidArgumentException('Choose a valid timezone.');
            }
            if ($contactEmail !== '' && filter_var($contactEmail, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('Enter a valid contact email address.');
            }
            if ($maxAttempts === false || $maxAttempts < 3 || $maxAttempts > 20) {
                throw new InvalidArgumentException('Failed-login limit must be between 3 and 20.');
            }
            if ($window === false || $window < 5 || $window > 120) {
                throw new InvalidArgumentException('The login-attempt window must be between 5 and 120 minutes.');
            }
            if ($block === false || $block < 5 || $block > 1440) {
                throw new InvalidArgumentException('The temporary IP block duration must be between 5 and 1440 minutes.');
            }

            $mail = is_array($this->config['mail'] ?? null) ? $this->config['mail'] : [];
            $smtpHost = trim((string) ($_POST['smtp_host'] ?? ''));
            $smtpPort = filter_var($_POST['smtp_port'] ?? '587', FILTER_VALIDATE_INT);
            $smtpEncryption = (string) ($_POST['smtp_encryption'] ?? 'tls');
            $smtpUsername = trim((string) ($_POST['smtp_username'] ?? ''));
            $smtpPassword = (string) ($_POST['smtp_password'] ?? '');
            $fromEmail = trim((string) ($_POST['mail_from_email'] ?? ''));
            $fromName = trim((string) ($_POST['mail_from_name'] ?? 'Youth Unity Cup'));
            $notificationsTo = trim((string) ($_POST['notifications_to'] ?? ''));
            $sameCredentials = false;

            if ($smtpHost !== '') {
                if (!preg_match('/^[A-Za-z0-9.-]{1,253}$/', $smtpHost)
                    || $smtpPort === false || $smtpPort < 1 || $smtpPort > 65535
                    || !in_array($smtpEncryption, ['tls', 'ssl', 'none'], true)) {
                    throw new InvalidArgumentException('Check the SMTP host, port, and encryption settings.');
                }
                if (filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false || filter_var($notificationsTo, FILTER_VALIDATE_EMAIL) === false) {
                    throw new InvalidArgumentException('Enter valid sender and notification-recipient email addresses.');
                }
                if ($fromName === '' || mb_strlen($fromName) > 120) {
                    throw new InvalidArgumentException('Sender name is required and must be at most 120 characters.');
                }
                $sameCredentials = $smtpHost === (string) ($mail['host'] ?? '')
                    && $smtpUsername === (string) ($mail['username'] ?? '');
                if ($smtpPassword === '' && !$sameCredentials && $smtpUsername !== '') {
                    throw new InvalidArgumentException('Enter the SMTP password for the new SMTP account.');
                }
            } else {
                $smtpUsername = '';
                $smtpPassword = '';
                $fromEmail = $contactEmail !== '' ? $contactEmail : (string) ($this->config['admin']['email'] ?? '');
                $fromName = 'Youth Unity Cup';
                $notificationsTo = (string) ($this->config['admin']['email'] ?? '');
                $smtpPort = 587;
                $smtpEncryption = 'tls';
            }

            if ($smtpPassword === '' && $smtpHost !== '' && $sameCredentials) {
                $smtpPassword = (string) ($mail['password'] ?? '');
            }

            $payments = is_array($this->config['payments'] ?? null) ? $this->config['payments'] : [];
            $savedPayHubSecret = $payments['secret_key'] ?? '';
            $currentPayHubSecret = is_scalar($savedPayHubSecret) ? (string) $savedPayHubSecret : '';
            $savedPayHubPublic = $payments['public_key'] ?? '';
            $currentPayHubPublic = is_scalar($savedPayHubPublic) ? (string) $savedPayHubPublic : '';
            $submittedPayHubSecretInput = $_POST['payhub_secret_key'] ?? '';
            $submittedPayHubPublicInput = $_POST['payhub_public_key'] ?? '';
            $submittedPayHubSecret = is_string($submittedPayHubSecretInput) ? trim($submittedPayHubSecretInput) : '';
            $submittedPayHubPublic = is_string($submittedPayHubPublicInput) ? trim($submittedPayHubPublicInput) : '';
            $clearPayHubSecret = (string) ($_POST['payhub_clear_secret'] ?? '') === '1';
            $clearPayHubPublic = (string) ($_POST['payhub_clear_public'] ?? '') === '1';
            if ($submittedPayHubSecret !== '' && !PayHubClient::isValidKey($submittedPayHubSecret)) {
                throw new InvalidArgumentException('Enter a valid PayHub secret key, or leave the field blank to keep the saved key.');
            }
            if ($submittedPayHubPublic !== '' && !PayHubClient::isValidKey($submittedPayHubPublic)) {
                throw new InvalidArgumentException('Enter a valid PayHub public key, or leave the field blank to keep the saved key.');
            }
            $payHubSecret = $clearPayHubSecret
                ? ''
                : ($submittedPayHubSecret !== '' ? $submittedPayHubSecret : $currentPayHubSecret);
            $payHubPublic = $clearPayHubPublic
                ? ''
                : ($submittedPayHubPublic !== '' ? $submittedPayHubPublic : $currentPayHubPublic);

            $settings = [
                'site_title' => $siteTitle,
                'timezone' => $timezone,
                'contact_email' => $contactEmail,
                'max_login_attempts' => (string) $maxAttempts,
                'login_window_minutes' => (string) $window,
                'block_duration_minutes' => (string) $block,
            ];
            $this->tournament->saveSettings($settings, (int) $admin['id']);

            $config = ConfigStore::load() ?? $this->config;
            $config['app']['site_title'] = $siteTitle;
            $config['app']['timezone'] = $timezone;
            $config['app']['contact_email'] = $contactEmail;
            $config['mail'] = [
                'host' => $smtpHost,
                'port' => $smtpPort === false ? 587 : (int) $smtpPort,
                'encryption' => $smtpEncryption,
                'username' => $smtpUsername,
                'password' => $smtpPassword,
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'notifications_to' => $notificationsTo,
            ];
            $config['payments'] = array_merge($payments, [
                'provider' => 'payhub',
                'secret_key' => $payHubSecret,
                'public_key' => $payHubPublic,
            ]);
            ConfigStore::finalize($config);
            yuc_flash('success', 'Site, security, email, and PayHub payment settings have been saved.');
        } catch (InvalidArgumentException $exception) {
            yuc_flash('error', $exception->getMessage());
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup settings save failed (' . get_class($exception) . ').');
            yuc_flash('error', 'Settings could not be saved. Check database and protected configuration-folder permissions.');
        }
        yuc_redirect('/admin/settings');
    }

    private function setSimpleStatus(string $table, int $id, string $status, array $allowed, int $adminId): void
    {
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Choose a valid status.');
        }
        $statement = $this->pdo->prepare('UPDATE ' . $table . ' SET status=:status, updated_at=UTC_TIMESTAMP() WHERE id=:id');
        $statement->execute(['status' => $status, 'id' => $id]);
        if ($statement->rowCount() < 1) {
            $exists = $this->pdo->prepare('SELECT id FROM ' . $table . ' WHERE id=:id');
            $exists->execute(['id' => $id]);
            if ($exists->fetchColumn() === false) {
                throw new InvalidArgumentException('That record no longer exists.');
            }
        }
        $audit = $this->pdo->prepare(
            'INSERT INTO audit_logs (user_id, event_key, description, ip_address, created_at) '
            . 'VALUES (:user_id, :event, :description, :ip, UTC_TIMESTAMP())'
        );
        $audit->execute(['user_id' => $adminId, 'event' => 'tournament.' . $table . '_status', 'description' => 'Updated ' . $table . ' record #' . $id . ' to ' . $status, 'ip' => yuc_client_ip()]);
    }

    private function guardDemoWrite(string $resource, string $redirect): void
    {
        if (!$this->environmentMode->isDemo() || in_array($resource, ['teams', 'players', 'venues', 'fixtures'], true)) {
            return;
        }
        yuc_flash('error', 'This production record is read-only in Demo mode. Switch to Production before changing registrations, payments, shop data, site settings, or security records.');
        yuc_redirect($redirect);
    }

    /** @return array<string,mixed> */
    private function requireAdmin(): array
    {
        $admin = yuc_current_admin();
        if ($admin === null) {
            yuc_redirect('/admin/login');
        }
        $statement = $this->pdo->prepare('SELECT status FROM users WHERE id=:id LIMIT 1');
        $statement->execute(['id' => (int) $admin['id']]);
        if ($statement->fetchColumn() !== 'active') {
            unset($_SESSION['admin_user']);
            yuc_flash('error', 'This administrator account is not active.');
            yuc_redirect('/admin/login');
        }
        return $admin;
    }

    private function verifyCsrf(string $redirect): void
    {
        if (!yuc_verify_csrf()) {
            yuc_flash('error', 'Your session expired. Refresh the page and try again.');
            yuc_redirect($redirect);
        }
    }
}
