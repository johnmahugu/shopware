<?php
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

namespace Shopware\Tests\Unit\Bundle\CartBundle\Infrastructure\Validator\Collector;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\TestCase;
use Shopware\Bundle\CartBundle\Domain\Cart\CalculatedCart;
use Shopware\Bundle\CartBundle\Domain\LineItem\CalculatedLineItemCollection;
use Shopware\Bundle\CartBundle\Domain\Validator\Data\RuleDataCollection;
use Shopware\Bundle\CartBundle\Domain\Validator\Rule\RuleCollection;
use Shopware\Bundle\CartBundle\Infrastructure\Validator\Collector\ProductOfCategoriesRuleCollector;
use Shopware\Bundle\CartBundle\Infrastructure\Validator\Data\ProductOfCategoriesRuleData;
use Shopware\Bundle\CartBundle\Infrastructure\Validator\Rule\ProductOfCategoriesRule;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContext;
use Shopware\Tests\Unit\Bundle\CartBundle\Infrastructure\Validator\Rule\DummyProduct;

class ProductOfCategoriesRuleCollectorTest extends TestCase
{
    public function testWithoutRule()
    {
        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $connection = $this->createMock(Connection::class);

        $collector = new ProductOfCategoriesRuleCollector($connection);

        $dataCollection = new RuleDataCollection();

        $ruleCollection = new RuleCollection();

        $collector->collect($ruleCollection, $cart, $context, $dataCollection);

        $this->assertSame(0, $dataCollection->count());
    }

    public function testWithEmptyCart()
    {
        $cart = $this->createMock(CalculatedCart::class);
        $cart->method('getLineItems')
            ->will($this->returnValue(
                new CalculatedLineItemCollection([])
            ));

        $context = $this->createMock(ShopContext::class);

        $connection = $this->createConnection([]);

        $collector = new ProductOfCategoriesRuleCollector($connection);

        $dataCollection = new RuleDataCollection();

        $ruleCollection = new RuleCollection([
            new ProductOfCategoriesRule([1]),
        ]);

        $collector->collect($ruleCollection, $cart, $context, $dataCollection);

        $this->assertSame(0, $dataCollection->count());
    }

    public function testWithSingleRule()
    {
        $cart = $this->createMock(CalculatedCart::class);
        $cart->method('getLineItems')
            ->will($this->returnValue(
                new CalculatedLineItemCollection([
                    new DummyProduct('SW1'),
                ])
            ));

        $context = $this->createMock(ShopContext::class);

        $connection = $this->createConnection([
            1 => ['SW1', 'SW2'],
            2 => ['SW1', 'SW2'],
        ]);

        $collector = new ProductOfCategoriesRuleCollector($connection);

        $dataCollection = new RuleDataCollection();

        $ruleCollection = new RuleCollection([
            new ProductOfCategoriesRule([1]),
        ]);

        $collector->collect($ruleCollection, $cart, $context, $dataCollection);

        $this->assertSame(1, $dataCollection->count());

        /** @var ProductOfCategoriesRuleData $data */
        $data = $dataCollection->get(ProductOfCategoriesRuleData::class);

        $this->assertTrue($data->hasOneCategory([1]));
    }

    public function testWithMultipleRules()
    {
        $cart = $this->createMock(CalculatedCart::class);
        $cart->method('getLineItems')
            ->will($this->returnValue(
                new CalculatedLineItemCollection([
                    new DummyProduct('SW1'),
                    new DummyProduct('SW2'),
                ])
            ));

        $context = $this->createMock(ShopContext::class);

        $connection = $this->createConnection(
            [
                1 => ['SW1', 'SW2'],
                2 => ['SW1', 'SW2'],
            ],
            [1, 2, 3, 4],
            ['SW1', 'SW2']
        );

        $collector = new ProductOfCategoriesRuleCollector($connection);

        $dataCollection = new RuleDataCollection();

        $ruleCollection = new RuleCollection([
            new ProductOfCategoriesRule([1, 2]),
            new ProductOfCategoriesRule([3, 4]),
        ]);

        $collector->collect($ruleCollection, $cart, $context, $dataCollection);

        $this->assertSame(1, $dataCollection->count());

        /** @var ProductOfCategoriesRuleData $data */
        $data = $dataCollection->get(ProductOfCategoriesRuleData::class);

        $this->assertTrue($data->hasOneCategory([1]));
        $this->assertTrue($data->hasOneCategory([2]));
    }

    private function createConnection(?array $result, array $categoryIds = [], array $numbers = [])
    {
        $statement = $this->createMock(Statement::class);
        $statement->expects(static::any())
            ->method('fetchAll')
            ->will(static::returnValue($result));

        $query = $this->createMock(QueryBuilder::class);
        $query->expects(static::any())
            ->method('execute')
            ->will(static::returnValue($statement));

        if (!empty($categoryIds)) {
            $query->expects(static::exactly(2))
                ->method('setParameter')
                ->withConsecutive(
                    [':numbers', static::equalTo($numbers), Connection::PARAM_STR_ARRAY],
                    [':categoryIds', static::equalTo($categoryIds), Connection::PARAM_INT_ARRAY]
                );
        }

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::any())
            ->method('createQueryBuilder')
            ->will(static::returnValue($query));

        return $connection;
    }
}
