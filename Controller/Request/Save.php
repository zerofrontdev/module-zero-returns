<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Returns\Controller\Request;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Zero\Returns\Model\Config;
use Zero\Returns\Model\ReturnRequest;
use Zero\Returns\Model\Returns;
use Zero\Returns\ViewModel\Storefront;

/**
 * The request form's POST: the lines asked for (items[<order item id>] =
 * qty, reason[<order item id>]), the resolution and a comment become a
 * pending return; the store and the customer get an email. The form key is
 * checked by Magento's CSRF validator.
 */
class Save implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly Storefront $storefront,
        private readonly Config $config,
        private readonly ReturnRequest $returnRequest
    ) {
    }

    public function execute()
    {
        $redirect = $this->redirectFactory->create();
        $order = $this->storefront->order();
        if (!$this->config->isEnabled() || !$order) {
            return $redirect->setPath('sales/order/history');
        }
        try {
            $quantities = [];
            foreach ((array) $this->request->getParam('items', []) as $orderItemId => $qty) {
                $quantities[(int) $orderItemId] = (float) str_replace(',', '.', is_scalar($qty) ? (string) $qty : '0');
            }
            $resolution = $this->request->getParam('resolution', Returns::REFUND);
            $comment = $this->request->getParam('comment');
            $return = $this->returnRequest->create(
                $order,
                $quantities,
                array_map('strval', array_filter((array) $this->request->getParam('reason', []), 'is_scalar')),
                is_scalar($resolution) ? (string) $resolution : Returns::REFUND,
                is_scalar($comment) ? (string) $comment : ''
            );
            $this->messageManager->addSuccessMessage(__('Your return request %1 is sent. We review it and email you.', Returns::number($return)));

            return $redirect->setPath('returns/view/index', ['id' => (int) $return['return_id']]);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $redirect->setPath('returns/request/index', ['_query' => ['order' => (int) $order->getEntityId()]]);
        }
    }
}
