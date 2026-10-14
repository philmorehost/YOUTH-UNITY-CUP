<?php

declare(strict_types=1);

namespace Yuc\Controllers;

use PDO;
use Throwable;
use Yuc\Core\View;
use Yuc\Services\EnvironmentModeService;
use Yuc\Services\PayHubClient;
use Yuc\Services\ShopService;

final class ShopController
{
    private ShopService $shop;
    private PayHubClient $payHub;
    private EnvironmentModeService $environmentMode;

    /** @param array<string,mixed> $config */
    public function __construct(PDO $pdo, private array $config)
    {
        $this->shop = new ShopService($pdo, $config);
        $this->payHub = new PayHubClient($config);
        $this->environmentMode = new EnvironmentModeService($pdo);
    }

    public function index(): void
    {
        $settings = $this->shopSettings();
        $siteMode = $this->environmentMode->currentMode();
        $oldForm = is_array($_SESSION['_shop_old_form'] ?? null) ? $_SESSION['_shop_old_form'] : [];
        unset($_SESSION['_shop_old_form']);
        View::render('shop', [
            'title' => 'Official shop · ' . $settings['site_title'],
            'topNote' => 'YOUTH UNITY CUP OFFICIAL SHOP',
            'bodyClass' => 'public-data-page shop-page',
            'products' => $siteMode === 'demo'
                ? ShopService::demoProducts()
                : $this->shop->publicProducts(),
            'payHubConfigured' => $this->payHub->isInlineConfigured(),
            'contactEmail' => $settings['contact_email'],
            'siteMode' => $siteMode,
            'flash' => yuc_take_flash(),
            'lastOrderAvailable' => isset($_SESSION['last_shop_order']),
            'oldForm' => $oldForm,
        ]);
    }

    public function checkout(): void
    {
        if (!yuc_verify_csrf()) {
            yuc_flash('error', 'Your checkout session expired. Refresh the shop and try again.');
            yuc_redirect('/shop');
        }
        $honeypot = $_POST['company_website'] ?? '';
        if (!is_scalar($honeypot) || trim((string) $honeypot) !== '') {
            yuc_redirect('/shop');
        }
        if ($this->environmentMode->isDemo()) {
            yuc_flash('error', 'Checkout is paused while the site is in Demo mode. No shop orders or payments are accepted during preview.');
            yuc_redirect('/shop');
        }
        if (!$this->payHub->isInlineConfigured()) {
            yuc_flash('error', 'PayHub inline checkout is not fully configured yet. Please contact the tournament team.');
            yuc_redirect('/shop');
        }

        $orderReference = '';
        try {
            $order = $this->shop->createOrder($_POST, yuc_client_ip());
            $orderReference = $order['reference'];
            $providerReference = PayHubClient::createInlineReference();
            $this->shop->attachInlinePayment($orderReference, $providerReference);
            $_SESSION['last_shop_order'] = $orderReference;
            yuc_redirect('/shop/pay?order=' . rawurlencode($orderReference));
        } catch (\InvalidArgumentException $exception) {
            $_SESSION['_shop_old_form'] = $this->safeCheckoutValues($_POST);
            yuc_flash('error', $exception->getMessage());
        } catch (Throwable $exception) {
            if ($orderReference !== '') {
                try {
                    $this->shop->failPaymentInitialization($orderReference);
                } catch (Throwable $cleanupException) {
                    error_log('Youth Unity Cup failed shop reservation cleanup (' . get_class($cleanupException) . ').');
                }
            }
            error_log('Youth Unity Cup PayHub inline checkout preparation failed (' . get_class($exception) . ').');
            yuc_flash('error', 'We could not prepare your PayHub inline checkout. No payment has been confirmed. Please try again or contact the tournament team.');
        }
        yuc_redirect('/shop');
    }

    public function inlineCheckout(): void
    {
        header('Cache-Control: no-store');
        if ($this->environmentMode->isDemo()) {
            yuc_flash('error', 'PayHub checkout is paused while the site is in Demo mode.');
            yuc_redirect('/shop');
        }
        if (!$this->payHub->isInlineConfigured()) {
            yuc_flash('error', 'PayHub inline checkout is not fully configured yet. Please contact the tournament team.');
            yuc_redirect('/shop');
        }

        $orderInput = $_GET['order'] ?? '';
        $orderReference = is_string($orderInput) ? $orderInput : '';
        $sessionReference = is_string($_SESSION['last_shop_order'] ?? null) ? $_SESSION['last_shop_order'] : '';
        if ($orderReference === '' || !hash_equals($sessionReference, $orderReference)) {
            yuc_flash('error', 'Open the inline payment page from your current shop checkout session.');
            yuc_redirect('/shop');
        }
        $order = $this->shop->inlineOrderForCustomer($orderReference);
        if ($order === null) {
            yuc_redirect('/shop/return?order=' . rawurlencode($orderReference));
        }

        View::render('shop-inline-checkout', [
            'title' => 'Secure checkout · Youth Unity Cup',
            'topNote' => 'PAYHUB SECURE INLINE CHECKOUT',
            'bodyClass' => 'public-data-page shop-page',
            'siteMode' => 'production',
            'order' => $order,
            'payHubPublicKey' => $this->payHub->publicKey(),
            'returnUrl' => '/shop/return?order=' . rawurlencode($orderReference),
            'contactEmail' => $this->shopSettings()['contact_email'],
            'inlinePayHubScript' => true,
        ]);
    }

    public function paymentReturn(): void
    {
        header('Cache-Control: no-store');
        $orderReference = '';
        $orderInput = $_GET['order'] ?? '';
        if (is_string($orderInput) && preg_match('/^YUC-S-[0-9]{6}-[A-F0-9]{10}$/D', $orderInput) === 1) {
            $orderReference = $orderInput;
        } else {
            $providerInput = $_GET['reference'] ?? ($_GET['ref'] ?? '');
            if (is_string($providerInput) && $providerInput !== '') {
                $orderReference = $this->shop->localReferenceForProvider($providerInput) ?? '';
            }
        }
        if ($orderReference === '' && is_string($_SESSION['last_shop_order'] ?? null)) {
            $orderReference = (string) $_SESSION['last_shop_order'];
        }

        $verificationNotice = '';
        $siteMode = $this->environmentMode->currentMode();
        if ($siteMode === 'demo') {
            $verificationNotice = 'This site is in Demo mode. Payment verification is paused; switch back to Production to continue live order checks.';
        } elseif ($orderReference !== '' && $this->payHub->isConfigured()) {
            try {
                $this->shop->refreshPayment($orderReference, $this->payHub);
            } catch (Throwable $exception) {
                error_log('Youth Unity Cup PayHub return verification failed (' . get_class($exception) . ').');
                $verificationNotice = 'We could not reach PayHub to confirm the payment just now. You can safely refresh this page in a moment.';
            }
        }
        $order = $orderReference !== '' ? $this->shop->orderForCustomer($orderReference) : null;
        $inlineCheckoutUrl = '';
        $sessionOrderReference = is_string($_SESSION['last_shop_order'] ?? null) ? $_SESSION['last_shop_order'] : '';
        if ($order !== null && $siteMode !== 'demo' && $this->payHub->isInlineConfigured()
            && ($order['status'] ?? '') === 'pending_payment'
            && hash_equals($sessionOrderReference, $orderReference)) {
            $inlineCheckoutUrl = '/shop/pay?order=' . rawurlencode($orderReference);
        }
        if ($order === null) {
            $verificationNotice = 'No recent shop order is available in this browser. If you completed a payment, use the order reference from your receipt or contact the tournament team.';
        }

        View::render('shop-order', [
            'title' => 'Payment status · Youth Unity Cup',
            'topNote' => 'SHOP ORDER STATUS',
            'bodyClass' => 'public-data-page shop-page',
            'order' => $order,
            'verificationNotice' => $verificationNotice,
            'inlineCheckoutUrl' => $inlineCheckoutUrl,
            'contactEmail' => $this->shopSettings()['contact_email'],
            'siteMode' => $siteMode,
        ]);
    }

    public function webhook(): void
    {
        header('Content-Type: application/json; charset=UTF-8', true);
        header('Cache-Control: no-store');
        if (!$this->payHub->isConfigured()) {
            $this->webhookResponse(503, 'Payment verification is not configured.');
        }

        $contentLength = filter_var($_SERVER['CONTENT_LENGTH'] ?? 0, FILTER_VALIDATE_INT);
        if ($contentLength !== false && $contentLength > 65536) {
            $this->webhookResponse(413, 'Webhook payload is too large.');
        }
        $inputStream = fopen('php://input', 'rb');
        $rawBody = is_resource($inputStream) ? stream_get_contents($inputStream, 65537) : false;
        if (is_resource($inputStream)) {
            fclose($inputStream);
        }
        if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > 65536) {
            $this->webhookResponse(400, 'Webhook payload is not valid.');
        }
        $signature = (string) ($_SERVER['HTTP_X_PAYHUB_SIGNATURE'] ?? '');
        if (!$this->payHub->hasValidWebhookSignature($rawBody, $signature)) {
            $this->webhookResponse(401, 'Invalid webhook signature.');
        }

        try {
            $payload = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->webhookResponse(400, 'Webhook JSON is not valid.');
        }
        if (!is_array($payload)) {
            $this->webhookResponse(400, 'Webhook JSON is not valid.');
        }

        $event = is_scalar($payload['event'] ?? null) ? (string) $payload['event'] : '';
        if ($event !== 'charge.success') {
            $this->webhookResponse(200, 'Webhook received.');
        }
        $data = $payload['data'] ?? null;
        $providerReference = is_array($data) && is_scalar($data['reference'] ?? null)
            ? trim((string) $data['reference'])
            : '';
        $orderReference = $providerReference !== '' ? $this->shop->localReferenceForProvider($providerReference) : null;
        if ($orderReference === null) {
            $this->webhookResponse(200, 'Webhook received.');
        }

        try {
            // A signed event is a prompt to reconcile, not the final payment decision.
            $this->shop->refreshPayment($orderReference, $this->payHub);
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup PayHub webhook reconciliation failed (' . get_class($exception) . ').');
            $this->webhookResponse(503, 'Payment verification is temporarily unavailable.');
        }
        $this->webhookResponse(200, 'Webhook processed.');
    }

    /** @return array{site_title:string,contact_email:string} */
    private function shopSettings(): array
    {
        $title = trim((string) ($this->config['app']['site_title'] ?? 'Youth Unity Cup'));
        $contact = trim((string) ($this->config['app']['contact_email'] ?? ''));
        return [
            'site_title' => $title !== '' ? $title : 'Youth Unity Cup',
            'contact_email' => $contact,
        ];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function safeCheckoutValues(array $input): array
    {
        $quantities = [];
        if (is_array($input['quantity'] ?? null)) {
            foreach (array_slice($input['quantity'], 0, 120, true) as $id => $quantity) {
                if (is_scalar($id) && is_scalar($quantity) && preg_match('/^\d{1,12}$/D', (string) $id) === 1) {
                    $quantities[(string) $id] = substr((string) $quantity, 0, 3);
                }
            }
        }
        $value = static function (mixed $candidate, int $limit): string {
            return is_scalar($candidate) ? mb_substr(trim((string) $candidate), 0, $limit) : '';
        };
        return [
            'quantity' => $quantities,
            'customer_name' => $value($input['customer_name'] ?? '', 140),
            'customer_email' => $value($input['customer_email'] ?? '', 190),
            'customer_phone' => $value($input['customer_phone'] ?? '', 40),
            'fulfillment_notes' => $value($input['fulfillment_notes'] ?? '', 500),
        ];
    }

    private function webhookResponse(int $status, string $message): never
    {
        http_response_code($status);
        echo json_encode(['received' => $status < 400, 'message' => $message], JSON_UNESCAPED_SLASHES);
        exit;
    }
}
