<?php
use Automation\Actions\FilterBy,
    Automation\Actions\FilterOrders,
    Automation\Actions\GetProperty,
    Automation\Actions\SetProperty;

/**
 * Unit tests for some example rules.
 *
 * Created on Feb 6, 2015.
 *
 * @author Victor Pautoff, TrueShip, LLC.
 */
class RulesTest extends RRTestCase {
    // === Setup and Teardown === //

    protected function setUp() {
        parent::setUp();
        // Use a transaction in case temporary objects are added to the database. We don't want to keep them there.
        $this->useTransaction();
    }

    // === Tests: Category name === //

    /** @test */
    public function setCategoryNameRuleShouldAffectMatchingItems() {
        $rule = $this->createRuleToSetCategoryName('test-description', 'test-category-new-name');

        $item = new Item();
        $item->setDescription('test-description');

        $box = new Box();
        $box->addItem($item);
        $order = new Order();
        $order->addBox($box);

        $rule->run([$order]);
        self::assertEquals($item->getCategoryName(), 'test-category-new-name');
    }

    /** @test */
    public function setCategoryNameRuleShouldNotAffectNonMatchingItems() {
        $rule = $this->createRuleToSetCategoryName('test-description', 'test-category-new-name');

        $item = new Item();
        $item->setDescription('test-description-1');

        $box = new Box();
        $box->addItem($item);
        $order = new Order();
        $order->addBox($box);

        $rule->run([$order]);
        self::assertNull($item->getCategoryName());
    }

    /** @test */
    public function setCategoryNameRuleShouldNotModifyProductCategory() {
        $rule = $this->createRuleToSetCategoryName('test-description', 'test-category-new-name');

        $category = (new Category())->setName('test-category-name');
        $product = (new Product())->setSku('test-sku');
        $product->setCategory($category);
        CategoryDAO::insert($category, false);
        ProductDAO::insert($product);

        $item1 = new Item();
        $item1->setSku('test-sku');
        $item1->setProduct($product);
        $item1->setDescription('test-description');
        $box = new Box();
        $box->addItem($item1);
        $order = new Order();
        $order->addBox($box);

        $item2 = new Item();
        $item2->setSku('test-sku');
        $item2->setProduct($product);
        $item2->setDescription('test-description-1');

        $rule->run([$order]);
        // Rule must not modify product's category.
        self::assertEquals($item1->getProduct()->getCategory()->getName(), 'test-category-name');
        self::assertEquals($item1->getCategoryName(), 'test-category-new-name');
        self::assertEquals($item2->getCategoryName(), 'test-category-name');
    }

    // === Tests: Prevent return === //

    /** @test */
    public function returnIsNotPreventedByTheRuleForItemsWithoutSaleInSku() {
        $rule = $this->createRuleToPreventReturnOfSaleItems();

        // Setup item that should be returned.
        $item = new Item();
        $item->setSku('ABC-SA_2015-02-06');

        $box = new Box();
        $box->addItem($item);
        $order = new Order();
        $order->addBox($box);

        $rule->run([$order]);
        $this->assertFalse($item->getPreventReturn(), 'Return of an item was prevented.');
    }

    /** @test */
    public function returnIsPreventedByTheRuleForItemsWithSaleInSku() {
        $rule = $this->createRuleToPreventReturnOfSaleItems();

        // Setup item that shouldn't be returned.
        $item = new Item();
        $item->setSku('ABC-SALE_2015-02-06');

        $box = new Box();
        $box->addItem($item);
        $order = new Order();
        $order->addBox($box);

        $rule->run([$order]);
        $this->assertTrue($item->getPreventReturn(), 'Return of an item was not prevented.');
    }

    /** @test */
    public function returnShouldBePreventedByTheRuleWithAFilterThatMatchesAllOrders() {
        $rule = $this->createRuleToPreventReturnOfAllOrders();

        $order = new Order();

        $rule->run([$order]);
        $this->assertTrue($order->getPreventReturn(), 'Return of an order was not prevented.');
    }

    // === Tests: Return to === //

    /** @test */
    public function orderShouldBeReturnedToWarehouseByTheRuleThatSetsReturnToToWarehouseId() {
        $address = (new Address())->setZip('12345');
        $warehouse = new Warehouse();
        $warehouse->setName('Sample warehouse for test');
        $warehouse->setAddress($address);
        WarehouseDAO::insert($warehouse);

        $rule = $this->createRuleToSetReturnTo($warehouse->getName());

        // Setup order that should be returned to the warehouse.
        $order = new Order();
        $order->setMessage('Return instruction: return to origin.');

        $rule->run([$order]);
        $this->assertSame($address, $order->getReturnToAsAddress(), 'Wrong order return to address when return-to is a warehouse.');
    }

    /** @test */
    public function orderShouldBeReturnedToTheShippingAddressOfOriginByTheRuleThatSetsReturnToToOrigin() {
        $rule = $this->createRuleToSetReturnTo(Order::RETURN_TO_ORIGIN);

        // Setup order that should be returned to the shipping address of origin.
        $order = new Order();
        $order->setMessage('Return instruction: return to origin.');
        $address = (new Address())->setZip('12345');
        $order->setFromAddress($address);

        $rule->run([$order]);
        $this->assertSame($address, $order->getReturnToAsAddress(), 'Wrong order return to address when return-to is "origin".');
    }

    // === Tests: Return via === //

    /** @test */
    public function returnViaRuleForOrdersShouldSetCorrectOrderValues() {
        $shipVia = Order::SHIP_VIA_ENDICIA;
        $shipType = 'First Class Mail';

        $rule = $this->createReturnViaRuleForOrders($shipVia, $shipType);

        // Setup an order that should be affected.
        $order = new Order();
        $order->setMessage("Return instruction: return via $shipVia.");

        $rule->run([$order]);
        $this->assertEquals($shipVia, $order->getReturnShipVia(), 'Wrong order return ship via is set when return-via helper is used.');
        $this->assertEquals($shipType, $order->getReturnShipType(), 'Wrong order return ship type is set when return-via helper is used.');

    }

    /** @test */
    public function returnViaRuleForItemsShouldSetCorrectItemValues() {
        $shipVia = Order::SHIP_VIA_ENDICIA;
        $shipType = 'First Class Mail';

        $rule = $this->createReturnViaRuleForItems($shipVia, $shipType, '-SALE');

        // Setup an item that should be affected.
        $item = new Item();
        $item->setSku('ABC-SALE_2015-02-06');

        $box = new Box();
        $box->addItem($item);
        $order = new Order();
        $order->addBox($box);

        $rule->run([$order]);
        $this->assertEquals($shipVia, $item->getReturnShipVia(), 'Wrong ReturnShipVia is set for an item when return-via helper is used.');
        $this->assertEquals($shipType, $item->getReturnShipType(), 'Wrong ReturnShipType is set for an item when return-via helper is used.');
    }

    /**
     * @test
     * @expectedException Exception
     * @expectedExceptionMessage The selected items cannot be returned together. Create separate returns for these items.
     */
    public function orderShouldNotBeReturnedIfItemsHaveDifferentReturnShipVia() {
        $this->setUpConfigMock([
            'READYRETURNS_MODE' => 'standard',
        ]);

        $rule = $this->createReturnViaRuleForItems(Order::SHIP_VIA_ENDICIA, 'First Class Mail', '-SALE');
        $rule2 = $this->createReturnViaRuleForItems(Order::SHIP_VIA_UPS, UpsShipping::getShipTypeName('03'), '-CLOSEOUT');

        list($returnInvoiceForm, $order) = $this->createTestItems();

        $rule->run([$order]);
        $rule2->run([$order]);
        $returnProcessor = new ReturnProcessor();
        $returnProcessor->validate($returnInvoiceForm, $order);
    }

    /**
     * @test
     * @expectedException Exception
     * @expectedExceptionMessage The selected items cannot be returned together. Create separate returns for these items.
     */
    public function orderShouldNotBeReturnedIfItemsHaveDifferentReturnShipType() {
        $this->setUpConfigMock([
            'READYRETURNS_MODE' => 'standard',
        ]);

        $rule = $this->createReturnViaRuleForItems(Order::SHIP_VIA_ENDICIA, 'First Class Mail', '-SALE');
        $rule2 = $this->createReturnViaRuleForItems(Order::SHIP_VIA_ENDICIA, 'Priority Mail Express', '-CLOSEOUT');

        list($returnInvoiceForm, $order) = $this->createTestItems();

        $rule->run([$order]);
        $rule2->run([$order]);
        $returnProcessor = new ReturnProcessor();
        $returnProcessor->validate($returnInvoiceForm, $order);
    }

    // === Private Methods === //

    /**
     * Creates the @c Rule that prevents return for all orders.
     * @return Rule The @c Rule.
     */
    private function createRuleToPreventReturnOfAllOrders() {
        return Rule::of([
            'orders.with.dots' => Action::of('OrdersInput', []),
            'filter1.with.dots' => Action::of('FilterOrders', [
                FilterOrders::PARAMETER_PREDICATE => 'TRUEPREDICATE',
            ]),
            'setProperty1' => Action::of('SetProperty', [
                SetProperty::PARAMETER_PROPERTY_NAME => 'preventReturn',
                SetProperty::PARAMETER_PROPERTY_VALUE => true,
            ]),
        ],
        [
            'filter1.with.dots.orders' => 'orders.with.dots.orders',
            'setProperty1.input' => 'filter1.with.dots.matching-orders',
        ]);
    }

    /**
     * Creates the @c Rule that prevents return for items with '-SALE' in their SKU.
     * @return Rule The @c Rule.
     */
    private function createRuleToPreventReturnOfSaleItems() {
        return Rule::of([
            'orders.with.dots' => Action::of('OrdersInput', []),
            'getProperty' => Action::of('GetProperty', [
                GetProperty::PARAMETER_PROPERTY_NAME => 'items',
            ]),
            'filter1.with.dots' => Action::of('FilterBy', [
                FilterBy::PARAMETER_PROPERTY_NAME => 'sku',
                FilterBy::PARAMETER_OPERATOR => FilterBy::OPERATOR_CONTAINS,
                FilterBy::PARAMETER_REFERENCE_VALUE => '-SALE',
            ]),
            'setProperty1' => Action::of('SetProperty', [
                SetProperty::PARAMETER_PROPERTY_NAME => 'preventReturn',
                SetProperty::PARAMETER_PROPERTY_VALUE => true,
            ]),
        ],
        [
            'getProperty.input' => 'orders.with.dots.orders',
            'filter1.with.dots.input' => 'getProperty.output',
            'setProperty1.input' => 'filter1.with.dots.matching',
        ]);
    }

    /**
     * Creates the @c Rule that set category name for items.
     * @param string $description Items' description.
     * @param string $categoryName Category name that should be applied.
     * @return Rule
     */
    private function createRuleToSetCategoryName($description, $categoryName) {
        $rule = new Rule();

        $filters = [
            [
                'object' => 'Item',
                'property' => 'description',
                'operator' => '==',
                'value' => $description,
            ],
        ];

        $actions = [
            [
                'object' => 'Item',
                'type' => 'set-property',
                'property' => 'categoryName',
                'value' => $categoryName,
            ],
        ];

        $rule->build($filters, $actions);

        return $rule;
    }

    /**
     * Creates the @c Rule that sets returnTo property to the given value for items with 'return to origin' text in the message.
     * @param string|int $returnTo The warehouse id, or @c Order::RETURN_TO_ORIGIN if the order should be returned to the shipping address of origin.
     * @return Rule The @c Rule.
     */
    private function createRuleToSetReturnTo($returnTo) {
        return Rule::of([
            'orders' => Action::of('OrdersInput', []),
            'filter1' => Action::of('FilterBy', [
                FilterBy::PARAMETER_PROPERTY_NAME => 'message',
                FilterBy::PARAMETER_OPERATOR => FilterBy::OPERATOR_CONTAINS,
                FilterBy::PARAMETER_REFERENCE_VALUE => 'return to origin',
            ]),
            'setProperty1' => Action::of('SetProperty', [
                SetProperty::PARAMETER_PROPERTY_NAME => 'returnTo',
                SetProperty::PARAMETER_PROPERTY_VALUE => $returnTo,
            ]),
        ],
        [
            'filter1.input' => 'orders.orders',
            'setProperty1.input' => 'filter1.matching',
        ]);
    }

    /**
     * Creates a 'return-via' rule on the @c Item level.
     * @param string $returnShipVia Return ship via.
     * @param string $returnShipType Return ship type.
     * @param string $skuText Sku text.
     * @return Rule The @c Rule.
     */
    private function createReturnViaRuleForItems($returnShipVia, $returnShipType, $skuText) {
        $rule = new Rule();

        $filters = [
            [
                'object' => 'Item',
                'property' => 'sku',
                'operator' => 'contains',
                'value' => $skuText
            ]
        ];

        $actions = [
            [
                'object' => 'Item',
                'type' => 'return-via',
                'ship-via' => $returnShipVia,
                'ship-type' => $returnShipType
            ]
        ];

        $rule->build($filters, $actions);

        return $rule;
    }

    /**
     * Creates a 'return-via' rule on the @c Order level.
     * @param string $returnShipVia Return ship via.
     * @param string $returnShipType Return ship type.
     * @return Rule The @c Rule.
     */
    private function createReturnViaRuleForOrders($returnShipVia, $returnShipType) {
        $rule = new Rule();

        $filters = [
            [
                'object' => 'Order',
                'property' => 'message',
                'operator' => 'contains',
                'value' => 'return via'
            ]
        ];

        $actions = [
            [
                'object' => 'Order',
                'type' => 'return-via',
                'ship-via' => $returnShipVia,
                'ship-type' => $returnShipType
            ]
        ];

        $rule->build($filters, $actions);

        return $rule;
    }

    /**
     * Creates an order with two test items and an array with return invoice data.
     * @return array Data.
     */
    private function createTestItems() {
        $item = new Item();
        $item->setId(12345);
        $item->setSku('ABC-SALE_2015-02-06');

        $item2 = new Item();
        $item2->setId(23456);
        $item2->setSku('ABC-CLOSEOUT_2015-02-06');

        $box = new Box();
        $box->addItem($item);
        $box->addItem($item2);
        $order = new Order();
        $order->addBox($box);

        $returnInvoiceForm = [
            "item_12345" => 'ABC-SALE_2015-02-06',
            "quantity_12345" => 1,
            "returnType_12345" => ReturnItem::TYPE_REFUND,
            "item_23456" => 'ABC-CLOSEOUT_2015-02-06',
            "quantity_23456" => 1,
            "returnType_23456" => ReturnItem::TYPE_REFUND
        ];

        return [$returnInvoiceForm, $order];
    }
}
