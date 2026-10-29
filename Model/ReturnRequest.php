<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Returns\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

/**
 * A customer's return request for an order: the theme's form
 * (Controller\Request\Save) and the GraphQL mutation share the checks.
 */
class ReturnRequest
{
    public function __construct(
        private readonly Config $config,
        private readonly Eligibility $eligibility,
        private readonly Returns $returns,
        private readonly Mailer $mailer
    ) {
    }

    /**
     * @param array<int, float> $quantities by order item id
     * @param array<int, string> $reasons by order item id
     * @return array<string, mixed> the return
     * @throws LocalizedException
     */
    public function create(Order $order, array $quantities, array $reasons, string $resolution, string $comment): array
    {
        if (!$this->eligibility->canRequest($order)) {
            throw new LocalizedException(__('This order has nothing left to return, or its return window has passed.'));
        }
        $returnable = $this->eligibility->returnable($order);
        $allowed = $this->config->reasons((int) $order->getStoreId());
        $items = [];
        foreach ($quantities as $orderItemId => $qty) {
            $orderItemId = (int) $orderItemId;
            $qty = (float) $qty;
            if ($qty <= 0 || !isset($returnable[$orderItemId])) {
                continue;
            }
            $line = $returnable[$orderItemId];
            if ($qty > $line['qty']) {
                throw new LocalizedException(__('Only %1 of %2 can be returned.', $line['qty'] + 0, $line['item']->getName()));
            }
            $reason = trim((string) ($reasons[$orderItemId] ?? ''));
            if ($allowed && !in_array($reason, $allowed, true)) {
                throw new LocalizedException(__('Pick a reason for %1.', $line['item']->getName()));
            }
            $items[] = [
                'order_item_id' => $orderItemId,
                'sku' => (string) $line['item']->getSku(),
                'name' => (string) $line['item']->getName(),
                'qty' => $qty,
                'reason' => $reason,
            ];
        }
        if (!$items) {
            throw new LocalizedException(__('Pick at least one item to return.'));
        }
        $return = $this->returns->create([
            'order_id' => (int) $order->getEntityId(),
            'order_increment_id' => (string) $order->getIncrementId(),
            'store_id' => (int) $order->getStoreId(),
            'customer_id' => (int) $order->getCustomerId(),
            'customer_name' => trim($order->getCustomerFirstname() . ' ' . $order->getCustomerLastname()),
            'customer_email' => (string) $order->getCustomerEmail(),
            'resolution' => in_array($resolution, Returns::RESOLUTIONS, true) ? $resolution : Returns::REFUND,
            'comment' => mb_substr(trim($comment), 0, 4000) ?: null,
        ], $items);
        $this->mailer->newRequest($return);

        return $return;
    }
}
