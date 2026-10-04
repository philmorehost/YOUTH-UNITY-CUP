<?php

declare(strict_types=1);

namespace Yuc\Services;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class ShopService
{
    private const MAX_PRICE_KOBO = 999999999999;
    private const MAX_QUANTITY_PER_PRODUCT = 20;
    private const RESERVATION_MINUTES = 20;

    private NotificationService $notifications;

    /** @param array<string,mixed> $config */
    public function __construct(private PDO $pdo, array $config)
    {
        $this->notifications = new NotificationService($pdo, $config);
    }

    /** @return list<array<string,mixed>> */
    public function publicProducts(bool $releaseExpiredReservations = true): array
    {
        if ($releaseExpiredReservations) {
            $this->releaseExpiredReservations();
        }
        return $this->pdo->query(
            "SELECT id, sku, name, description, price_kobo, stock_quantity "
            . "FROM shop_products WHERE status='active' AND stock_quantity > 0 ORDER BY name, id LIMIT 120"
        )->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function adminProducts(): array
    {
        return $this->pdo->query(
            'SELECT p.id, p.sku, p.name, p.description, p.price_kobo, p.stock_quantity, p.status, p.created_at, p.updated_at, '
            . '(SELECT COUNT(*) FROM shop_order_items i WHERE i.product_id=p.id) AS order_item_count '
            . 'FROM shop_products p ORDER BY p.updated_at DESC, p.id DESC LIMIT 500'
        )->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findProduct(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM shop_products WHERE id=:id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }
        $row['price_amount'] = self::formatKobo((int) $row['price_kobo']);
        return $row;
    }

    /** @param array<string,mixed> $input */
    public function saveProduct(array $input, int $adminId): void
    {
        $id = filter_var(is_scalar($input['id'] ?? null) ? $input['id'] : 0, FILTER_VALIDATE_INT);
        if ($id === false || $id < 0) {
            throw new InvalidArgumentException('The selected product is not valid.');
        }
        $sku = strtoupper(self::formText($input['sku'] ?? ''));
        if (preg_match('/^[A-Z0-9][A-Z0-9._-]{0,59}$/D', $sku) !== 1) {
            throw new InvalidArgumentException('Use a SKU with 1–60 letters, numbers, periods, hyphens, or underscores.');
        }
        $name = self::formText($input['name'] ?? '');
        if ($name === '' || mb_strlen($name) > 140) {
            throw new InvalidArgumentException('Product name is required and must be at most 140 characters.');
        }
        $description = self::formText($input['description'] ?? '');
        if (mb_strlen($description) > 1000) {
            throw new InvalidArgumentException('Product description must be at most 1,000 characters.');
        }
        $priceKobo = self::amountToKobo(self::formText($input['price'] ?? ''));
        if ($priceKobo < 1 || $priceKobo > self::MAX_PRICE_KOBO) {
            throw new InvalidArgumentException('Enter a product price greater than ₦0 and no more than ₦9,999,999,999.99.');
        }
        $stock = filter_var(is_scalar($input['stock_quantity'] ?? null) ? $input['stock_quantity'] : null, FILTER_VALIDATE_INT);
        if ($stock === false || $stock < 0 || $stock > 2000000000) {
            throw new InvalidArgumentException('Available stock must be a whole number from 0 to 2,000,000,000.');
        }
        $status = self::formText($input['status'] ?? 'active');
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException('Choose a valid product status.');
        }

        if ($id > 0) {
            $statement = $this->pdo->prepare(
                'UPDATE shop_products SET sku=:sku, name=:name, description=:description, price_kobo=:price, '
                . 'stock_quantity=:stock, status=:status, updated_at=UTC_TIMESTAMP() WHERE id=:id'
            );
            $statement->execute([
                'sku' => $sku, 'name' => $name, 'description' => $description, 'price' => $priceKobo,
                'stock' => $stock, 'status' => $status, 'id' => $id,
            ]);
            if ($statement->rowCount() === 0) {
                $exists = $this->pdo->prepare('SELECT id FROM shop_products WHERE id=:id');
                $exists->execute(['id' => $id]);
                if ($exists->fetchColumn() === false) {
                    throw new InvalidArgumentException('That product no longer exists.');
                }
            }
            $this->audit($adminId, 'transaction.shop_product_updated', 'Updated shop product ' . $sku);
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO shop_products (sku, name, description, price_kobo, stock_quantity, status, created_at, updated_at) '
            . 'VALUES (:sku, :name, :description, :price, :stock, :status, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $statement->execute([
            'sku' => $sku, 'name' => $name, 'description' => $description, 'price' => $priceKobo,
            'stock' => $stock, 'status' => $status,
        ]);
        $this->audit($adminId, 'transaction.shop_product_created', 'Created shop product ' . $sku);
    }

    public function deleteProduct(int $id, int $adminId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $query = $this->pdo->prepare('SELECT sku, name FROM shop_products WHERE id=:id LIMIT 1 FOR UPDATE');
            $query->execute(['id' => $id]);
            $product = $query->fetch();
            if (!is_array($product)) {
                throw new InvalidArgumentException('That product no longer exists.');
            }
            $usage = $this->pdo->prepare('SELECT COUNT(*) FROM shop_order_items WHERE product_id=:id');
            $usage->execute(['id' => $id]);
            $hasOrderHistory = (int) $usage->fetchColumn() > 0;
            if ($hasOrderHistory) {
                $this->pdo->prepare("UPDATE shop_products SET status='inactive', updated_at=UTC_TIMESTAMP() WHERE id=:id")
                    ->execute(['id' => $id]);
                $this->audit($adminId, 'transaction.shop_product_archived', 'Archived shop product ' . (string) $product['sku']);
            } else {
                $this->pdo->prepare('DELETE FROM shop_products WHERE id=:id')->execute(['id' => $id]);
                $this->audit($adminId, 'transaction.shop_product_deleted', 'Deleted shop product ' . (string) $product['sku']);
            }
            $this->pdo->commit();
            return !$hasOrderHistory;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Validate current product/price/stock data and reserve inventory atomically.
     * Prices and names in the order are snapshots and cannot change with the catalog.
     *
     * @param array<string,mixed> $input
     * @return array{id:int,reference:string,name:string,email:string,phone:string,total_kobo:int}
     */
    public function createOrder(array $input, string $ipAddress = 'unknown', ?int $actorId = null): array
    {
        $name = self::formText($input['customer_name'] ?? '');
        if ($name === '' || mb_strlen($name) > 140) {
            throw new InvalidArgumentException('Enter your name (up to 140 characters).');
        }
        $email = self::formText($input['customer_email'] ?? '');
        if (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Enter a valid email address for the receipt.');
        }
        $phone = self::formText($input['customer_phone'] ?? '');
        if (mb_strlen($phone) > 40 || ($phone !== '' && preg_match('/^[+0-9() .-]+$/D', $phone) !== 1)) {
            throw new InvalidArgumentException('Enter a valid phone number (or leave it blank).');
        }
        $fulfillmentNotes = self::formText($input['fulfillment_notes'] ?? '');
        if (mb_strlen($fulfillmentNotes) > 500) {
            throw new InvalidArgumentException('Delivery or collection notes must be at most 500 characters.');
        }
        $quantities = $input['quantity'] ?? null;
        if (!is_array($quantities) || count($quantities) > 120) {
            throw new InvalidArgumentException('Choose at least one product to continue.');
        }

        $requested = [];
        foreach ($quantities as $rawId => $rawQuantity) {
            if (!is_scalar($rawId) || !is_scalar($rawQuantity)) {
                throw new InvalidArgumentException('The selected product quantities are not valid.');
            }
            $id = filter_var((string) $rawId, FILTER_VALIDATE_INT);
            $quantity = filter_var((string) $rawQuantity, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1 || $quantity === false || $quantity < 0 || $quantity > self::MAX_QUANTITY_PER_PRODUCT) {
                throw new InvalidArgumentException('Each product quantity must be a whole number from 0 to 20.');
            }
            if ($quantity > 0) {
                $requested[(int) $id] = (int) $quantity;
            }
        }
        if ($requested === [] || count($requested) > 20) {
            throw new InvalidArgumentException('Choose between 1 and 20 products to continue.');
        }
        ksort($requested, SORT_NUMERIC);

        $reference = 'YUC-S-' . gmdate('ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));
        $ipAddress = filter_var($ipAddress, FILTER_VALIDATE_IP) !== false ? substr($ipAddress, 0, 45) : 'unknown';
        $rateLimit = $this->pdo->prepare(
            "SELECT COUNT(*) FROM audit_logs WHERE event_key='transaction.shop_order_created' AND ip_address=:ip "
            . 'AND created_at >= (UTC_TIMESTAMP() - INTERVAL 15 MINUTE)'
        );
        $rateLimit->execute(['ip' => $ipAddress]);
        if ((int) $rateLimit->fetchColumn() >= 10) {
            throw new InvalidArgumentException('Too many shop checkouts came from this connection. Please wait a little and try again.');
        }
        $lines = [];
        $totalKobo = 0;

        $this->pdo->beginTransaction();
        try {
            $this->releaseExpiredReservationsWithinTransaction();
            $productQuery = $this->pdo->prepare(
                'SELECT id, sku, name, price_kobo, stock_quantity FROM shop_products '
                . "WHERE id=:id AND status='active' FOR UPDATE"
            );
            $reserve = $this->pdo->prepare(
                "UPDATE shop_products SET stock_quantity=stock_quantity-:quantity, updated_at=UTC_TIMESTAMP() "
                . "WHERE id=:id AND status='active' AND stock_quantity >= :check_quantity"
            );

            foreach ($requested as $id => $quantity) {
                $productQuery->execute(['id' => $id]);
                $product = $productQuery->fetch();
                if (!is_array($product)) {
                    throw new InvalidArgumentException('One of the selected products is no longer available. Refresh the shop and try again.');
                }
                if ((int) $product['stock_quantity'] < $quantity) {
                    throw new InvalidArgumentException('There is not enough stock for ' . (string) $product['name'] . '. Refresh the shop to see current availability.');
                }
                $unitPrice = (int) $product['price_kobo'];
                $lineTotal = $unitPrice * $quantity;
                if ($lineTotal < 1 || $lineTotal > self::MAX_PRICE_KOBO - $totalKobo) {
                    throw new InvalidArgumentException('The order total is outside the supported payment range.');
                }
                $reserve->execute(['quantity' => $quantity, 'id' => $id, 'check_quantity' => $quantity]);
                if ($reserve->rowCount() !== 1) {
                    throw new InvalidArgumentException('Stock changed while you were checking out. Please try again.');
                }
                $totalKobo += $lineTotal;
                $lines[] = [
                    'product_id' => $id,
                    'sku' => (string) $product['sku'],
                    'name' => (string) $product['name'],
                    'unit_price_kobo' => $unitPrice,
                    'quantity' => $quantity,
                    'line_total_kobo' => $lineTotal,
                ];
            }

            $amount = self::formatKobo($totalKobo);
            $transaction = $this->pdo->prepare(
                'INSERT INTO transactions (reference, user_id, recipient_email, amount, currency, status, payment_provider, description, created_at, updated_at) '
                . "VALUES (:reference, NULL, :email, :amount, 'NGN', 'pending', 'payhub', :description, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
            );
            $transaction->execute([
                'reference' => $reference,
                'email' => $email,
                'amount' => $amount,
                'description' => 'Youth Unity Cup shop order ' . $reference,
            ]);
            $transactionId = (int) $this->pdo->lastInsertId();

            $order = $this->pdo->prepare(
                'INSERT INTO shop_orders (reference, transaction_id, customer_name, customer_email, customer_phone, fulfillment_notes, '
                . 'total_kobo, status, reservation_expires_at, created_at, updated_at) '
                . "VALUES (:reference, :transaction_id, :name, :email, :phone, :notes, :total, 'pending_payment', "
                . 'DATE_ADD(UTC_TIMESTAMP(), INTERVAL ' . self::RESERVATION_MINUTES . ' MINUTE), UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            );
            $order->execute([
                'reference' => $reference,
                'transaction_id' => $transactionId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'notes' => $fulfillmentNotes,
                'total' => $totalKobo,
            ]);
            $orderId = (int) $this->pdo->lastInsertId();

            $insertLine = $this->pdo->prepare(
                'INSERT INTO shop_order_items (order_id, product_id, sku_snapshot, product_name, unit_price_kobo, quantity, line_total_kobo, created_at) '
                . 'VALUES (:order_id, :product_id, :sku, :name, :unit_price, :quantity, :line_total, UTC_TIMESTAMP())'
            );
            foreach ($lines as $line) {
                $insertLine->execute([
                    'order_id' => $orderId,
                    'product_id' => $line['product_id'],
                    'sku' => $line['sku'],
                    'name' => $line['name'],
                    'unit_price' => $line['unit_price_kobo'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total_kobo'],
                ]);
            }
            $this->writeAudit($actorId, 'transaction.shop_order_created', 'Shop order ' . $reference . ' reserved inventory', $ipAddress, [
                'reference' => $reference,
                'total_kobo' => $totalKobo,
                'item_count' => count($lines),
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return [
            'id' => $orderId,
            'reference' => $reference,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'total_kobo' => $totalKobo,
        ];
    }

    public function attachInlinePayment(string $orderReference, string $providerReference): void
    {
        if (preg_match('/^YUC-S-[0-9]{6}-[A-F0-9]{10}$/D', $orderReference) !== 1
            || preg_match('/^YUC-[A-F0-9]{32}$/D', $providerReference) !== 1) {
            throw new RuntimeException('Inline payment reference is not valid.');
        }

        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'SELECT o.id, o.status, o.transaction_id, o.reservation_expires_at '
                . 'FROM shop_orders o WHERE o.reference=:reference LIMIT 1 FOR UPDATE'
            );
            $statement->execute(['reference' => $orderReference]);
            $order = $statement->fetch();
            if (!is_array($order) || $order['status'] !== 'pending_payment'
                || strtotime((string) $order['reservation_expires_at'] . ' UTC') <= time()) {
                throw new RuntimeException('The order is no longer awaiting payment.');
            }
            $transaction = $this->pdo->prepare(
                'UPDATE transactions SET provider_reference=:provider_reference, updated_at=UTC_TIMESTAMP() '
                . "WHERE id=:id AND payment_provider='payhub' AND status='pending' AND provider_reference IS NULL"
            );
            $transaction->execute(['provider_reference' => $providerReference, 'id' => $order['transaction_id']]);
            if ($transaction->rowCount() !== 1) {
                throw new RuntimeException('The inline PayHub reference could not be attached to this order.');
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function attachPayment(string $orderReference, string $providerReference, string $checkoutUrl): void
    {
        if (preg_match('/^YUC-S-[0-9]{6}-[A-F0-9]{10}$/D', $orderReference) !== 1
            || preg_match('/^[A-Za-z0-9_-]{1,120}$/D', $providerReference) !== 1
            || !PayHubClient::isTrustedCheckoutUrl($checkoutUrl)) {
            throw new RuntimeException('Payment checkout details are not valid.');
        }

        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'SELECT o.id, o.status, o.transaction_id FROM shop_orders o WHERE o.reference=:reference LIMIT 1 FOR UPDATE'
            );
            $statement->execute(['reference' => $orderReference]);
            $order = $statement->fetch();
            if (!is_array($order) || $order['status'] !== 'pending_payment') {
                throw new RuntimeException('The order is no longer awaiting payment.');
            }
            $transaction = $this->pdo->prepare(
                'UPDATE transactions SET provider_reference=:provider_reference, updated_at=UTC_TIMESTAMP() '
                . "WHERE id=:id AND payment_provider='payhub' AND status='pending' AND provider_reference IS NULL"
            );
            $transaction->execute(['provider_reference' => $providerReference, 'id' => $order['transaction_id']]);
            if ($transaction->rowCount() !== 1) {
                throw new RuntimeException('The PayHub reference could not be attached to this order.');
            }
            $updateOrder = $this->pdo->prepare(
                'UPDATE shop_orders SET checkout_url=:checkout_url, updated_at=UTC_TIMESTAMP() WHERE id=:id'
            );
            $updateOrder->execute(['checkout_url' => $checkoutUrl, 'id' => $order['id']]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function failPaymentInitialization(string $orderReference): void
    {
        $this->pdo->beginTransaction();
        try {
            $query = $this->pdo->prepare('SELECT id, transaction_id, status FROM shop_orders WHERE reference=:reference LIMIT 1 FOR UPDATE');
            $query->execute(['reference' => $orderReference]);
            $order = $query->fetch();
            if (!is_array($order) || $order['status'] !== 'pending_payment') {
                $this->pdo->commit();
                return;
            }
            $this->releaseOrderReservationWithinTransaction((int) $order['id']);
            $this->pdo->prepare(
                "UPDATE shop_orders SET status='payment_failed', updated_at=UTC_TIMESTAMP() WHERE id=:id AND status='pending_payment'"
            )->execute(['id' => $order['id']]);
            $this->pdo->prepare(
                "UPDATE transactions SET status='failed', updated_at=UTC_TIMESTAMP() WHERE id=:id AND status='pending'"
            )->execute(['id' => $order['transaction_id']]);
            $this->writeAudit(null, 'transaction.shop_payment_initialization_failed', 'PayHub checkout could not be initialized for order ' . $orderReference, 'unknown', ['reference' => $orderReference]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** Query PayHub's authoritative verify endpoint and apply only a matching payment. */
    public function refreshPayment(string $orderReference, PayHubClient $payHub): void
    {
        $this->releaseExpiredReservations();
        $query = $this->pdo->prepare(
            'SELECT o.reference, o.total_kobo, t.provider_reference FROM shop_orders o '
            . 'INNER JOIN transactions t ON t.id=o.transaction_id WHERE o.reference=:reference LIMIT 1'
        );
        $query->execute(['reference' => $orderReference]);
        $order = $query->fetch();
        if (!is_array($order)) {
            throw new InvalidArgumentException('That order could not be found.');
        }
        $providerReference = trim((string) ($order['provider_reference'] ?? ''));
        if ($providerReference === '') {
            return;
        }

        $response = $payHub->verify($providerReference);
        $data = $response['data'] ?? null;
        if (($response['status'] ?? false) !== true || !is_array($data)) {
            throw new RuntimeException('PayHub has not returned a verifiable transaction.');
        }
        $returnedReference = self::formText($data['reference'] ?? '');
        if ($returnedReference !== $providerReference) {
            throw new RuntimeException('PayHub returned a different transaction reference.');
        }

        $providerStatus = strtolower(self::formText($data['payment_status'] ?? ''));
        if ($providerStatus === '') {
            $providerStatus = strtolower(self::formText($data['status'] ?? ''));
        }
        $paid = ($response['paid'] ?? false) === true
            || ($data['paid'] ?? false) === true
            || $providerStatus === 'success';
        if ($paid) {
            $rawAmount = $data['amount'] ?? null;
            $reportedAmount = (is_int($rawAmount) || is_string($rawAmount)) ? (string) $rawAmount : '';
            $amountIsInteger = preg_match('/^\d{1,30}$/D', $reportedAmount) === 1;
            $normalizedAmount = $amountIsInteger ? (ltrim($reportedAmount, '0') ?: '0') : '';
            $expectedAmount = (string) (int) $order['total_kobo'];
            $providerCurrency = strtoupper(self::formText($data['currency'] ?? ''));
            $detailsMatch = $amountIsInteger && $normalizedAmount === $expectedAmount && $providerCurrency === 'NGN';
            $this->applySuccessfulPayment(
                $orderReference,
                $detailsMatch,
                $providerStatus,
                $amountIsInteger ? $reportedAmount : null,
                substr($providerCurrency, 0, 3)
            );
            return;
        }

        if (in_array($providerStatus, ['failed', 'failure', 'cancelled', 'canceled'], true)) {
            $this->applyFailedPayment($orderReference, $providerStatus);
        }
    }

    public function updateOrderStatus(int $id, string $status, int $adminId): void
    {
        $this->pdo->beginTransaction();
        try {
            $query = $this->pdo->prepare('SELECT id, reference, transaction_id, status, stock_reserved, archived_at FROM shop_orders WHERE id=:id LIMIT 1 FOR UPDATE');
            $query->execute(['id' => $id]);
            $order = $query->fetch();
            if (!is_array($order)) {
                throw new InvalidArgumentException('That order no longer exists.');
            }
            if ($order['archived_at'] !== null) {
                throw new InvalidArgumentException('Restore the archived order before changing its status.');
            }

            $current = (string) $order['status'];
            $allowedNext = match ($current) {
                'pending_payment' => ['cancelled'],
                'paid_needs_review' => ['paid'],
                'paid' => ['processing'],
                'processing' => ['fulfilled'],
                default => [],
            };
            if (!in_array($status, $allowedNext, true)) {
                throw new InvalidArgumentException('That order cannot be moved to the selected status. Review payment first, then follow the fulfillment steps.');
            }

            if ($status === 'cancelled') {
                $this->releaseOrderReservationWithinTransaction((int) $order['id']);
            } elseif ($current === 'paid_needs_review' && (int) $order['stock_reserved'] !== 1) {
                $this->reserveOrderInventoryWithinTransaction((int) $order['id']);
            }
            $update = $this->pdo->prepare(
                'UPDATE shop_orders SET status=:status, '
                . "payment_review_reason=CASE WHEN :is_reviewed='yes' THEN NULL ELSE payment_review_reason END, "
                . "fulfilled_at=CASE WHEN :is_fulfilled='yes' THEN UTC_TIMESTAMP() ELSE fulfilled_at END, "
                . 'updated_at=UTC_TIMESTAMP() WHERE id=:id'
            );
            $update->execute([
                'status' => $status,
                'is_reviewed' => $current === 'paid_needs_review' ? 'yes' : 'no',
                'is_fulfilled' => $status === 'fulfilled' ? 'yes' : 'no',
                'id' => $id,
            ]);
            $description = 'Order ' . (string) $order['reference'] . ' changed from ' . $current . ' to ' . $status;
            $this->writeAudit($adminId, 'transaction.shop_order_status', $description, $this->clientIp(), ['reference' => $order['reference'], 'from' => $current, 'to' => $status]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string,mixed>|null */
    public function findOrderForAdmin(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT o.id, o.reference, o.customer_name, o.customer_email, o.customer_phone, o.fulfillment_notes, '
            . 'o.status, o.archived_at FROM shop_orders o WHERE o.id=:id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $data */
    public function updateOrderDetails(array $data, int $adminId): void
    {
        $id = filter_var(is_scalar($data['id'] ?? null) ? $data['id'] : null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new InvalidArgumentException('The selected order is not valid.');
        }
        $name = self::formText($data['customer_name'] ?? '');
        if ($name === '' || mb_strlen($name) > 140) {
            throw new InvalidArgumentException('Customer name is required and must be at most 140 characters.');
        }
        $email = self::formText($data['customer_email'] ?? '');
        if (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Enter a valid customer email address.');
        }
        $phone = self::formText($data['customer_phone'] ?? '');
        if (mb_strlen($phone) > 40 || ($phone !== '' && preg_match('/^[+0-9() .-]+$/D', $phone) !== 1)) {
            throw new InvalidArgumentException('Enter a valid customer phone number or leave it blank.');
        }
        $notes = self::formText($data['fulfillment_notes'] ?? '');
        if (mb_strlen($notes) > 500) {
            throw new InvalidArgumentException('Fulfillment notes must be at most 500 characters.');
        }
        $query = $this->pdo->prepare('SELECT reference, archived_at FROM shop_orders WHERE id=:id LIMIT 1');
        $query->execute(['id' => $id]);
        $order = $query->fetch();
        if (!is_array($order)) {
            throw new InvalidArgumentException('That order no longer exists.');
        }
        if ($order['archived_at'] !== null) {
            throw new InvalidArgumentException('Restore the archived order before editing it.');
        }
        $this->pdo->prepare(
            'UPDATE shop_orders SET customer_name=:name, customer_email=:email, customer_phone=:phone, '
            . 'fulfillment_notes=:notes, updated_at=UTC_TIMESTAMP() WHERE id=:id'
        )->execute(['name' => $name, 'email' => $email, 'phone' => $phone, 'notes' => $notes, 'id' => $id]);
        $this->audit($adminId, 'transaction.shop_order_details_updated', 'Updated customer or fulfillment details for order ' . (string) $order['reference']);
    }

    public function setOrderArchived(int $id, bool $archived, int $adminId): void
    {
        $this->pdo->beginTransaction();
        try {
            $query = $this->pdo->prepare('SELECT reference, status, archived_at FROM shop_orders WHERE id=:id LIMIT 1 FOR UPDATE');
            $query->execute(['id' => $id]);
            $order = $query->fetch();
            if (!is_array($order)) {
                throw new InvalidArgumentException('That order no longer exists.');
            }
            if ($archived && !in_array((string) $order['status'], ['fulfilled', 'payment_failed', 'cancelled'], true)) {
                throw new InvalidArgumentException('Only fulfilled, failed, or cancelled orders can be archived. Complete or cancel the order first so active payment review and stock reservations are not hidden.');
            }
            $timestamp = $archived ? gmdate('Y-m-d H:i:s') : null;
            $this->pdo->prepare('UPDATE shop_orders SET archived_at=:archived_at, updated_at=UTC_TIMESTAMP() WHERE id=:id')
                ->execute(['archived_at' => $timestamp, 'id' => $id]);
            $this->writeAudit(
                $adminId,
                $archived ? 'transaction.shop_order_archived' : 'transaction.shop_order_restored',
                ($archived ? 'Archived' : 'Restored') . ' shop order ' . (string) $order['reference'],
                $this->clientIp(),
                ['reference' => (string) $order['reference'], 'status' => (string) $order['status']]
            );
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return list<array<string,mixed>> */
    public function orders(bool $includeArchived = false, bool $releaseExpiredReservations = true): array
    {
        if ($releaseExpiredReservations) {
            $this->releaseExpiredReservations();
        }
        $archiveFilter = $includeArchived ? ' WHERE o.archived_at IS NOT NULL' : ' WHERE o.archived_at IS NULL';
        return $this->pdo->query(
            'SELECT o.id, o.reference, o.customer_name, o.customer_email, o.customer_phone, o.fulfillment_notes, o.checkout_url, '
            . 'o.total_kobo, o.status, o.reservation_expires_at, o.paid_at, o.fulfilled_at, o.created_at, o.archived_at, '
            . 'o.provider_amount_kobo, o.provider_currency, o.payment_review_reason, '
            . 't.provider_reference, t.status AS transaction_status, '
            . '(SELECT GROUP_CONCAT(CONCAT(i.quantity, \' × \', i.product_name) ORDER BY i.id SEPARATOR \', \') '
            . 'FROM shop_order_items i WHERE i.order_id=o.id) AS item_summary '
            . 'FROM shop_orders o INNER JOIN transactions t ON t.id=o.transaction_id' . $archiveFilter
            . ' ORDER BY o.created_at DESC, o.id DESC LIMIT 300'
        )->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function orderForCustomer(string $reference): ?array
    {
        if (preg_match('/^YUC-S-[0-9]{6}-[A-F0-9]{10}$/D', $reference) !== 1) {
            return null;
        }
        $statement = $this->pdo->prepare(
            'SELECT o.reference, o.total_kobo, o.status, o.reservation_expires_at, o.paid_at, o.created_at, '
            . 'o.checkout_url, t.provider_reference FROM shop_orders o '
            . 'INNER JOIN transactions t ON t.id=o.transaction_id WHERE o.reference=:reference LIMIT 1'
        );
        $statement->execute(['reference' => $reference]);
        $order = $statement->fetch();
        if (!is_array($order)) {
            return null;
        }
        $items = $this->pdo->prepare(
            'SELECT sku_snapshot, product_name, unit_price_kobo, quantity, line_total_kobo '
            . 'FROM shop_order_items WHERE order_id=(SELECT id FROM shop_orders WHERE reference=:reference) ORDER BY id'
        );
        $items->execute(['reference' => $reference]);
        $order['items'] = $items->fetchAll();
        return $order;
    }

    /** @return array{id:int,reference:string,customer_name:string,customer_email:string,total_kobo:int,status:string,reservation_expires_at:string,provider_reference:string}|null */
    public function inlineOrderForCustomer(string $reference): ?array
    {
        if (preg_match('/^YUC-S-[0-9]{6}-[A-F0-9]{10}$/D', $reference) !== 1) {
            return null;
        }
        $statement = $this->pdo->prepare(
            'SELECT o.id, o.reference, o.customer_name, o.customer_email, o.total_kobo, o.status, '
            . 'o.reservation_expires_at, t.provider_reference '
            . 'FROM shop_orders o INNER JOIN transactions t ON t.id=o.transaction_id '
            . 'WHERE o.reference=:reference AND o.status=\'pending_payment\' '
            . 'AND o.reservation_expires_at>UTC_TIMESTAMP() AND t.provider_reference IS NOT NULL LIMIT 1'
        );
        $statement->execute(['reference' => $reference]);
        $order = $statement->fetch();
        return is_array($order) ? $order : null;
    }

    /** @return array{id:int,reference:string,customer_name:string,customer_email:string,total_kobo:int,status:string,reservation_expires_at:string,provider_reference:string}|null */
    public function inlineOrderForAdmin(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $statement = $this->pdo->prepare(
            'SELECT o.id, o.reference, o.customer_name, o.customer_email, o.total_kobo, o.status, '
            . 'o.reservation_expires_at, t.provider_reference '
            . 'FROM shop_orders o INNER JOIN transactions t ON t.id=o.transaction_id '
            . 'WHERE o.id=:id AND o.archived_at IS NULL AND o.status=\'pending_payment\' '
            . 'AND o.reservation_expires_at>UTC_TIMESTAMP() AND t.provider_reference IS NOT NULL LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $order = $statement->fetch();
        return is_array($order) ? $order : null;
    }

    public function localReferenceForProvider(string $providerReference): ?string
    {
        if (preg_match('/^[A-Za-z0-9_-]{1,120}$/D', $providerReference) !== 1) {
            return null;
        }
        $statement = $this->pdo->prepare(
            'SELECT o.reference FROM shop_orders o INNER JOIN transactions t ON t.id=o.transaction_id '
            . "WHERE t.payment_provider='payhub' AND t.provider_reference=:reference LIMIT 1"
        );
        $statement->execute(['reference' => $providerReference]);
        $reference = $statement->fetchColumn();
        return is_string($reference) ? $reference : null;
    }

    public static function amountToKobo(string $amount): int
    {
        $amount = trim($amount);
        if (preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $amount) !== 1) {
            throw new InvalidArgumentException('Enter an amount in naira with no more than two decimal places.');
        }
        [$naira, $kobo] = array_pad(explode('.', $amount, 2), 2, '');
        $whole = (int) $naira;
        $fraction = (int) str_pad($kobo, 2, '0');
        if ($whole > intdiv(PHP_INT_MAX - $fraction, 100)) {
            throw new InvalidArgumentException('The amount is too large.');
        }
        return ($whole * 100) + $fraction;
    }

    public static function formatKobo(int $amount): string
    {
        return intdiv($amount, 100) . '.' . str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }

    private function applySuccessfulPayment(
        string $reference,
        bool $detailsMatch,
        string $providerStatus,
        ?string $reportedAmount,
        string $providerCurrency
    ): void {
        $notification = null;
        $this->pdo->beginTransaction();
        try {
            $this->releaseExpiredReservationsWithinTransaction();
            $query = $this->pdo->prepare(
                'SELECT o.id, o.reference, o.status, o.reservation_expires_at, o.total_kobo, o.customer_email, o.payment_review_reason, '
                . 't.id AS transaction_id, t.status AS transaction_status '
                . 'FROM shop_orders o INNER JOIN transactions t ON t.id=o.transaction_id '
                . 'WHERE o.reference=:reference LIMIT 1 FOR UPDATE'
            );
            $query->execute(['reference' => $reference]);
            $order = $query->fetch();
            if (!is_array($order)) {
                throw new RuntimeException('The payment order could not be found.');
            }

            $current = (string) $order['status'];
            $finalOrder = in_array($current, ['processing', 'fulfilled'], true);
            $review = !$detailsMatch || !in_array($current, ['pending_payment', 'paid'], true);
            $newStatus = $finalOrder ? $current : ($review ? 'paid_needs_review' : 'paid');
            $reviewReason = null;
            if (!$detailsMatch) {
                $reviewReason = $reportedAmount === null
                    ? 'amount_unreadable'
                    : ($providerCurrency !== 'NGN' ? 'currency_mismatch' : 'amount_mismatch');
            } elseif (in_array($current, ['cancelled', 'payment_failed'], true)) {
                $reviewReason = 'order_closed_before_payment';
            } elseif ($current === 'paid_needs_review') {
                $reviewReason = (string) ($order['payment_review_reason'] ?? 'manual_review');
            }
            $updateOrder = $this->pdo->prepare(
                'UPDATE shop_orders SET status=:status, provider_amount_kobo=:provider_amount, provider_currency=:provider_currency, '
                . 'payment_review_reason=:review_reason, paid_at=COALESCE(paid_at, UTC_TIMESTAMP()), updated_at=UTC_TIMESTAMP() WHERE id=:id'
            );
            $updateOrder->execute([
                'status' => $newStatus,
                'provider_amount' => $reportedAmount,
                'provider_currency' => $providerCurrency !== '' ? $providerCurrency : null,
                'review_reason' => $reviewReason,
                'id' => $order['id'],
            ]);
            $this->pdo->prepare(
                "UPDATE transactions SET status='completed', updated_at=UTC_TIMESTAMP() WHERE id=:id AND status <> 'refunded'"
            )->execute(['id' => $order['transaction_id']]);

            if ($order['transaction_status'] !== 'completed') {
                $description = $detailsMatch
                    ? 'PayHub confirmed payment for shop order ' . $reference
                    : 'PayHub reported a paid amount/currency mismatch for shop order ' . $reference;
                $this->writeAudit(null, 'transaction.shop_payment_received', $description, 'unknown', [
                    'reference' => $reference,
                    'provider_status' => $providerStatus,
                    'expected_total_kobo' => (int) $order['total_kobo'],
                    'provider_amount_kobo' => $reportedAmount,
                    'provider_currency' => $providerCurrency,
                    'details_match' => $detailsMatch,
                    'review_reason' => $reviewReason,
                    'order_status' => $newStatus,
                ]);
                $notification = [
                    'email' => (string) $order['customer_email'],
                    'status' => $newStatus,
                    'amount' => (int) $order['total_kobo'],
                    'provider_amount' => $reportedAmount ?? 'unavailable',
                    'provider_currency' => $providerCurrency,
                    'review_reason' => $reviewReason ?? '',
                ];
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        if ($notification !== null) {
            $this->notifyPayment($reference, $notification, $notification['status'] === 'paid_needs_review');
        }
    }

    private function applyFailedPayment(string $reference, string $providerStatus): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->releaseExpiredReservationsWithinTransaction();
            $query = $this->pdo->prepare(
                'SELECT o.id, o.status, o.transaction_id FROM shop_orders o WHERE o.reference=:reference LIMIT 1 FOR UPDATE'
            );
            $query->execute(['reference' => $reference]);
            $order = $query->fetch();
            if (!is_array($order)) {
                throw new RuntimeException('The payment order could not be found.');
            }
            if ($order['status'] === 'pending_payment') {
                $this->releaseOrderReservationWithinTransaction((int) $order['id']);
                $this->pdo->prepare(
                    "UPDATE shop_orders SET status='payment_failed', updated_at=UTC_TIMESTAMP() WHERE id=:id"
                )->execute(['id' => $order['id']]);
                $this->writeAudit(null, 'transaction.shop_payment_failed', 'PayHub confirmed payment failure for shop order ' . $reference, 'unknown', [
                    'reference' => $reference,
                    'provider_status' => $providerStatus,
                ]);
            }
            $this->pdo->prepare(
                "UPDATE transactions SET status='failed', updated_at=UTC_TIMESTAMP() WHERE id=:id AND status='pending'"
            )->execute(['id' => $order['transaction_id']]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array{email:string,status:string,amount:int,provider_amount:string,provider_currency:string,review_reason:string} $notification */
    private function notifyPayment(string $reference, array $notification, bool $manualReview): void
    {
        try {
            $reviewMessage = $manualReview
                ? 'Payment was received and the order needs manual review before fulfillment.'
                : 'Payment is confirmed. The tournament team will prepare your order.';
            $this->notifications->notifyActivity(
                'transaction.shop_payment_received',
                'Youth Unity Cup shop order payment update',
                'Order ' . $reference . ': ' . $reviewMessage . ' Order total: NGN ' . self::formatKobo($notification['amount'])
                    . '. PayHub reported ' . $notification['provider_amount'] . ' kobo (' . $notification['provider_currency'] . ').',
                [
                    'user_email' => $notification['email'],
                    'notify_admin' => true,
                    'include_ip' => false,
                    'record_audit' => false,
                    'context' => [
                        'order_reference' => $reference,
                        'order_status' => $notification['status'],
                        'expected_amount_ngn' => self::formatKobo($notification['amount']),
                        'provider_amount_kobo' => $notification['provider_amount'],
                        'provider_currency' => $notification['provider_currency'],
                        'review_reason' => $notification['review_reason'],
                        'manual_review' => $manualReview,
                    ],
                ]
            );
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup shop payment notification failed (' . get_class($exception) . ').');
        }
    }

    private function releaseExpiredReservations(): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->releaseExpiredReservationsWithinTransaction();
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function releaseExpiredReservationsWithinTransaction(): void
    {
        $expired = $this->pdo->query(
            "SELECT id, stock_reserved FROM shop_orders WHERE status='pending_payment' AND reservation_expires_at <= UTC_TIMESTAMP() "
            . 'ORDER BY reservation_expires_at, id LIMIT 200 FOR UPDATE'
        )->fetchAll();
        if ($expired === []) {
            return;
        }
        $ids = array_map(static fn (array $row): int => (int) $row['id'], $expired);
        $reservedIds = array_map(
            static fn (array $row): int => (int) $row['id'],
            array_filter($expired, static fn (array $row): bool => (int) $row['stock_reserved'] === 1)
        );
        if ($reservedIds !== []) {
            $reservedPlaceholders = implode(',', array_fill(0, count($reservedIds), '?'));
            $items = $this->pdo->prepare(
                'SELECT product_id, SUM(quantity) AS quantity FROM shop_order_items WHERE order_id IN (' . $reservedPlaceholders . ') '
                . 'AND product_id IS NOT NULL GROUP BY product_id ORDER BY product_id'
            );
            $items->execute($reservedIds);
            $restore = $this->pdo->prepare(
                'UPDATE shop_products SET stock_quantity=LEAST(4294967295, stock_quantity + :quantity), updated_at=UTC_TIMESTAMP() WHERE id=:id'
            );
            foreach ($items->fetchAll() as $item) {
                $restore->execute(['quantity' => (int) $item['quantity'], 'id' => (int) $item['product_id']]);
            }
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $close = $this->pdo->prepare(
            "UPDATE shop_orders SET status='cancelled', stock_reserved=0, updated_at=UTC_TIMESTAMP() "
            . "WHERE status='pending_payment' AND id IN (" . $placeholders . ')'
        );
        $close->execute($ids);
        if ($close->rowCount() > 0) {
            $this->writeAudit(null, 'transaction.shop_reservation_expired', 'Released ' . $close->rowCount() . ' expired shop stock reservation(s)', 'unknown', ['orders' => count($ids)]);
        }
    }

    private function releaseOrderReservationWithinTransaction(int $orderId): void
    {
        $reserved = $this->pdo->prepare('SELECT stock_reserved FROM shop_orders WHERE id=:id LIMIT 1 FOR UPDATE');
        $reserved->execute(['id' => $orderId]);
        if ((int) $reserved->fetchColumn() !== 1) {
            return;
        }
        $items = $this->pdo->prepare(
            'SELECT product_id, SUM(quantity) AS quantity FROM shop_order_items '
            . 'WHERE order_id=:order_id AND product_id IS NOT NULL GROUP BY product_id ORDER BY product_id'
        );
        $items->execute(['order_id' => $orderId]);
        $restore = $this->pdo->prepare(
            'UPDATE shop_products SET stock_quantity=LEAST(4294967295, stock_quantity + :quantity), updated_at=UTC_TIMESTAMP() WHERE id=:id'
        );
        foreach ($items->fetchAll() as $item) {
            $restore->execute(['quantity' => (int) $item['quantity'], 'id' => (int) $item['product_id']]);
        }
        $this->pdo->prepare('UPDATE shop_orders SET stock_reserved=0 WHERE id=:id AND stock_reserved=1')->execute(['id' => $orderId]);
    }

    private function reserveOrderInventoryWithinTransaction(int $orderId): void
    {
        $items = $this->pdo->prepare(
            'SELECT id, product_id, sku_snapshot, product_name, quantity FROM shop_order_items WHERE order_id=:order_id ORDER BY id'
        );
        $items->execute(['order_id' => $orderId]);
        $quantities = [];
        $findSku = $this->pdo->prepare('SELECT id FROM shop_products WHERE sku=:sku LIMIT 1');
        $restoreLink = $this->pdo->prepare('UPDATE shop_order_items SET product_id=:product_id WHERE id=:id AND product_id IS NULL');
        foreach ($items->fetchAll() as $item) {
            $productId = $item['product_id'] === null ? 0 : (int) $item['product_id'];
            if ($productId < 1) {
                $findSku->execute(['sku' => $item['sku_snapshot']]);
                $resolved = $findSku->fetchColumn();
                if ($resolved === false) {
                    throw new InvalidArgumentException('A product in this order is no longer in the catalog. Restore its SKU before accepting the late payment.');
                }
                $productId = (int) $resolved;
                $restoreLink->execute(['product_id' => $productId, 'id' => $item['id']]);
            }
            $quantities[$productId] = ($quantities[$productId] ?? 0) + (int) $item['quantity'];
        }
        ksort($quantities, SORT_NUMERIC);

        $lockProduct = $this->pdo->prepare('SELECT stock_quantity FROM shop_products WHERE id=:id LIMIT 1 FOR UPDATE');
        $decrease = $this->pdo->prepare(
            'UPDATE shop_products SET stock_quantity=stock_quantity-:quantity, updated_at=UTC_TIMESTAMP() '
            . 'WHERE id=:id AND stock_quantity >= :check_quantity'
        );
        foreach ($quantities as $productId => $quantity) {
            $lockProduct->execute(['id' => $productId]);
            $available = $lockProduct->fetchColumn();
            if ($available === false || (int) $available < $quantity) {
                throw new InvalidArgumentException('There is not enough available stock to accept this late payment. Add stock, then confirm the review again.');
            }
            $decrease->execute(['quantity' => $quantity, 'id' => $productId, 'check_quantity' => $quantity]);
            if ($decrease->rowCount() !== 1) {
                throw new InvalidArgumentException('Stock changed while reviewing this order. Please try again.');
            }
        }
        $this->pdo->prepare('UPDATE shop_orders SET stock_reserved=1 WHERE id=:id')->execute(['id' => $orderId]);
    }

    /** @param array<string,mixed> $context */
    private function writeAudit(?int $userId, string $event, string $description, string $ip, array $context = []): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO audit_logs (user_id, event_key, description, ip_address, context_json, created_at) '
            . 'VALUES (:user_id, :event, :description, :ip, :context, UTC_TIMESTAMP())'
        );
        $statement->execute([
            'user_id' => $userId,
            'event' => $event,
            'description' => mb_substr($description, 0, 255),
            'ip' => filter_var($ip, FILTER_VALIDATE_IP) !== false ? substr($ip, 0, 45) : 'unknown',
            'context' => $context === [] ? null : json_encode($context, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
        ]);
    }

    private function audit(int $adminId, string $event, string $description): void
    {
        try {
            $this->writeAudit($adminId, $event, $description, $this->clientIp());
        } catch (Throwable $exception) {
            error_log('Youth Unity Cup shop audit could not be saved (' . get_class($exception) . ').');
        }
    }

    private static function formText(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function clientIp(): string
    {
        return function_exists('yuc_client_ip') ? yuc_client_ip() : 'unknown';
    }
}
