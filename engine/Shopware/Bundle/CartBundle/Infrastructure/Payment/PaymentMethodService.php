<?php
declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Bundle\CartBundle\Infrastructure\Payment;

use Shopware\Bundle\CartBundle\Domain\Cart\CalculatedCart;
use Shopware\Bundle\CartBundle\Domain\Payment\PaymentMethod;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class PaymentMethodService
{
    /**
     * @var PaymentMethodGateway
     */
    private $gateway;

    /**
     * @var RiskManagementPaymentFilter
     */
    private $riskManagementFilter;

    public function __construct(
        PaymentMethodGateway $gateway,
        RiskManagementPaymentFilter $paymentRiskManagementFilter
    ) {
        $this->gateway = $gateway;
        $this->riskManagementFilter = $paymentRiskManagementFilter;
    }

    /**
     * @param CalculatedCart       $calculatedCart
     * @param ShopContextInterface $context
     *
     * @return PaymentMethod[]
     */
    public function getAvailable(
        CalculatedCart $calculatedCart,
        ShopContextInterface $context
    ): array {
        $payments = $this->gateway->getAll($context->getTranslationContext());

        $actives = array_filter($payments, function (PaymentMethod $paymentMethod) {
            return $paymentMethod->isActive();
        });

        return $this->riskManagementFilter->filter($actives, $calculatedCart, $context);
    }
}
