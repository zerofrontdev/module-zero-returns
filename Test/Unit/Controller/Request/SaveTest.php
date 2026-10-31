<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Returns\Test\Unit\Controller\Request;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zero\Returns\Controller\Request\Save;
use Zero\Returns\Model\Config;
use Zero\Returns\Model\ReturnRequest;
use Zero\Returns\Model\Returns;
use Zero\Returns\ViewModel\Storefront;

/**
 * The return request form's POST (A5-1: the controller used an undeclared ReturnRequest and every submit was a 500).
 */
class SaveTest extends TestCase
{
    private ReturnRequest&MockObject $returnRequest;
    private ManagerInterface&MockObject $messages;
    /** @var array{0: string, 1: array<string, mixed>}|null */
    private ?array $redirectedTo = null;

    public function testSubmitCreatesTheReturnAndOpensIt(): void
    {
        $order = $this->order(4002);
        $this->returnRequest->expects($this->once())->method('create')
            ->with($order, [11 => 1.5, 12 => 2.0], [11 => 'damaged'], Returns::REFUND, 'Torn seam')
            ->willReturn(['return_id' => 13]);
        $this->messages->expects($this->once())->method('addSuccessMessage')
            ->with($this->callback(static fn ($m) => str_contains((string) $m, 'R000013')));
        $this->messages->expects($this->never())->method('addErrorMessage');

        $this->controller($order, true, [
            'items' => ['11' => '1,5', '12' => '2'],
            'reason' => ['11' => 'damaged'],
            'resolution' => Returns::REFUND,
            'comment' => 'Torn seam',
        ])->execute();

        $this->assertSame(['returns/view/index', ['id' => 13]], $this->redirectedTo);
    }

    public function testRefusalGoesBackToTheForm(): void
    {
        $order = $this->order(4002);
        $this->returnRequest->expects($this->once())->method('create')
            ->willThrowException(new LocalizedException(__('Choose at least one item to return.')));
        $this->messages->expects($this->once())->method('addErrorMessage')->with('Choose at least one item to return.');
        $this->messages->expects($this->never())->method('addSuccessMessage');

        $this->controller($order, true, ['items' => []])->execute();

        $this->assertSame(['returns/request/index', ['_query' => ['order' => 4002]]], $this->redirectedTo);
    }

    public function testDisabledGoesToOrderHistory(): void
    {
        $this->returnRequest->expects($this->never())->method('create');
        $this->messages->expects($this->never())->method('addSuccessMessage');

        $this->controller($this->order(4002), false, ['items' => ['11' => '1']])->execute();

        $this->assertSame(['sales/order/history', []], $this->redirectedTo);
    }

    public function testNoOrderGoesToOrderHistory(): void
    {
        $this->returnRequest->expects($this->never())->method('create');
        $this->messages->expects($this->never())->method('addSuccessMessage');

        $this->controller(null, true, ['items' => ['11' => '1']])->execute();

        $this->assertSame(['sales/order/history', []], $this->redirectedTo);
    }

    protected function setUp(): void
    {
        $this->returnRequest = $this->createMock(ReturnRequest::class);
        $this->messages = $this->createMock(ManagerInterface::class);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function controller(?Order $order, bool $enabled, array $params): Save
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $key, $default = null) => $params[$key] ?? $default
        );
        $redirect = $this->createStub(Redirect::class);
        $redirect->method('setPath')->willReturnCallback(function (string $path, array $args = []) use ($redirect) {
            $this->redirectedTo = [$path, $args];
            return $redirect;
        });
        $redirectFactory = $this->createStub(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);
        $storefront = $this->createStub(Storefront::class);
        $storefront->method('order')->willReturn($order);
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn($enabled);

        return new Save($request, $redirectFactory, $this->messages, $storefront, $config, $this->returnRequest);
    }

    private function order(int $id): Order
    {
        $order = $this->createStub(Order::class);
        $order->method('getEntityId')->willReturn($id);

        return $order;
    }
}
