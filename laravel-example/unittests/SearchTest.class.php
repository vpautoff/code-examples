<?php
/**
 * Unit tests for search functions (Order and ReturnInvoice classes).
 */
class SearchTest extends RRTestCase {
    /** Test data. */
    protected $importId;
    protected $nonMatchingOrder;
    protected $nonMatchingReturnInvoice;
    protected $order;
    protected $returnInvoice;

    // === Setup and Teardown === //

    protected function setUp() {
        parent::setUp();
        $this->useTransaction();
        $this->insertSampleData();
    }

    // === Tests: OrderDAO::searchForCustomerService() === //

    /**
     * @test
     * @dataProvider dataProviderForSearchForCustomerService
     */
    public function orderDaoSearchForCustomerServiceShouldReturnCorrectData($field, $term, $exactMatch) {
        $results = OrderDAO::searchForCustomerService($term);
        $this->checkSearch($results, $field, $term, $exactMatch);
    }

    // === Tests: OrderDAO::searchByOrderNumberOrTrackingNumberOrCustomer() === //

    /**
     * @test
     * @dataProvider dataProviderForOrderDaoSearchByOrderNumberOrTrackingNumberOrCustomerUsingFromAddress
     */
    public function orderDaoSearchByOrderNumberOrTrackingNumberOrCustomerInCashForGoldModeShouldReturnCorrectData($field, $term, $exactMatch) {
        $results = OrderDAO::searchByOrderNumberOrTrackingNumberOrCustomer($term, '', $this->order->getSource(), 'from');
        $this->checkSearch($results, $field, $term, $exactMatch);
    }

    /**
     * @test
     * @dataProvider dataProviderForOrderDaoSearchByOrderNumberOrTrackingNumberOrCustomerUsingShippingAddress
     */
    public function orderDaoSearchByOrderNumberOrTrackingNumberOrCustomerInPixarModeShouldReturnCorrectData($field, $term, $exactMatch) {
        $results = OrderDAO::searchByOrderNumberOrTrackingNumberOrCustomer($term, '', $this->order->getSource(), 'shipping');
        $this->checkSearch($results, $field, $term, $exactMatch);
    }

    // === Tests: OrderDAO::searchImported() === //

    /**
     * @test
     * @dataProvider dataProviderForOrderDaoSearchImported
     */
    public function orderDaoSearchImportedShouldReturnCorrectData($field, $term, $exactMatch) {
        $results = OrderDAO::searchImported($this->importId, $term);
        $this->checkSearch($results, $field, $term, $exactMatch);
    }

    // === Tests: OrderDAO::searchOrdersInProcess() === //

    /**
     * @test
     * @dataProvider dataProviderForOrderDaoSearchOrdersInProcessUsingFromAddress
     */
    public function orderDaoSearchOrdersInProcessInCashForGoldModeShouldReturnCorrectData($field, $term, $exactMatch) {
        $results = OrderDAO::searchOrdersInProcess($term, 1, '', $this->order->getSource(), 'from');
        $this->checkSearch($results, $field, $term, $exactMatch);
    }

    /**
     * @test
     * @dataProvider dataProviderForOrderDaoSearchOrdersInProcessUsingShippingAddress
     */
    public function orderDaoSearchOrdersInProcessInPixarModeShouldReturnCorrectData($field, $term, $exactMatch) {
        $results = OrderDAO::searchOrdersInProcess($term, 1, '', $this->order->getSource(), 'shipping');
        $this->checkSearch($results, $field, $term, $exactMatch);
    }

    // === Tests: ReturnInvoiceDAO::search() === //

    /**
     * @test
     * @dataProvider dataProviderForReturnInvoiceSearch
     */
    public function returnInvoiceDaoSearchShouldReturnCorrectData($field, $term, $exactMatch) {
        $results = ReturnInvoiceDAO::search($term);
        $this->checkSearch($results, $field, $term, $exactMatch);
    }

    // === Tests: ReturnInvoiceDAO::searchByAgingReport() === //

    /**
     * @test
     * @dataProvider dataProviderForReturnInvoiceSearch
     */
    public function returnInvoiceDaoSearchByAgingReportShouldReturnCorrectData($field, $term, $exactMatch) {
        $results = ReturnInvoiceDAO::searchByAgingReport($term);
        $this->checkSearch($results, $field, $term, $exactMatch);
    }

    // === Data Providers === //

    public function dataProviderForSearchForCustomerService() {
        return [
            // Fields: matching field, search term, is exact match?
            ['orderNumber', '11111111', true],
            ['primaryId', 'test-pid-aaaaaaaa', true],
            ['rma', 22222222, true],
            ['orderNumber', ' 11111111 ', true],
            ['primaryId', ' test-pid-aaaaaaaa ', true],
            ['rma', ' 22222222 ', true],
            ['shippingAddress.email', 'st-email-aaaaa', false],
            ['shippingAddress.name', 'st-first-name-aaaaaaaa test-last-name-aaaaa', false]
        ];
    }

    public function dataProviderForOrderDaoSearchByOrderNumberOrTrackingNumberOrCustomerUsingFromAddress() {
        return [
            // Fields: matching field, search term, is exact match?
            ['orderNumber', '11111111', true],
            ['fromAddress.email', 'st-email-aaaaa', false],
            ['fromAddress.name', 'st-first-name-aaaaaaaa test-last-name-aaaaa', false],
            ['trackingNumber', 'st-tracking-number-aaaaa', false]
        ];
    }

    public function dataProviderForOrderDaoSearchByOrderNumberOrTrackingNumberOrCustomerUsingShippingAddress() {
        return [
            // Fields: matching field, search term, is exact match?
            ['orderNumber', '11111111', true],
            ['shippingAddress.email', 'st-email-aaaaa', false],
            ['shippingAddress.name', 'st-first-name-aaaaaaaa test-last-name-aaaaa', false],
            ['trackingNumber', 'st-tracking-number-aaaaa', false]
        ];
    }

    public function dataProviderForOrderDaoSearchImported() {
        return [
            // Fields: matching field, search term, is exact match?
            ['orderNumber', '11111111', true],
            ['primaryId', 'test-pid-aaaaaaaa', true],
            ['shippingAddress.email', 'st-email-aaaaa', false],
            ['shippingAddress.name', 'st-first-name-aaaaaaaa test-last-name-aaaaa', false]
        ];
    }

    public function dataProviderForOrderDaoSearchOrdersInProcessUsingFromAddress() {
        return [
            // Fields: matching field, search term, is exact match?
            ['orderNumber', '11111111', true],
            ['primaryId', 'test-pid-aaaaaaaa', true],
            ['fromAddress.email', 'st-email-aaaaa', false],
            ['fromAddress.name', 'st-first-name-aaaaaaaa test-last-name-aaaaa', false],
            ['trackingNumber', 'st-tracking-number-aaaaa', false]
        ];
    }

    public function dataProviderForOrderDaoSearchOrdersInProcessUsingShippingAddress() {
        return [
            // Fields: matching field, search term, is exact match?
            ['orderNumber', '11111111', true],
            ['primaryId', 'test-pid-aaaaaaaa', true],
            ['shippingAddress.email', 'st-email-aaaaa', false],
            ['shippingAddress.name', 'st-first-name-aaaaaaaa test-last-name-aaaaa', false],
            ['trackingNumber', 'st-tracking-number-aaaaa', false]
        ];
    }

    public function dataProviderForReturnInvoiceSearch() {
        return [
            // Fields: matching field, search term, is exact match?
            ['orderNumber', '11111111', true],
            ['primaryId', 'test-pid-aaaaaaaa', true],
            ['rma', 22222222, true],
            ['fromAddress.email', 'st-email-aaaaa', false],
            ['fromAddress.name', 'st-first-name-aaaaaaaa test-last-name-aaaaa', false],
            ['trackingNumber', 'st-tracking-number-aaaaa', false]
        ];
    }

    // === Private Methods === //

    private function checkSearch($results, $field, $term, $exactMatch) {
        $term = trim($term);
        $matchingObjectFound = false;
        foreach ($results as $i => $object) {
            // Make sure all returned records match the search term.
            $value = $this->getFieldValue($object, $field);
            $nth = nth($i + 1);
            if ($exactMatch) {
                $this->assertEquals($term, $value, "The $nth record doesn't match the search term '{$term}'.");
            } else {
                $this->assertContains($term, $value, "The $nth record doesn't match the search term '{$term}'.");
            }

            // The search results should contain our sample matching object.
            $matchingId = $object instanceof Order ? $this->order->getId() : $this->returnInvoice->getId();
            if ($matchingId === $object->getId()) {
                $matchingObjectFound = true;
            }

            // The search results must not contain our sample non-matching object.
            $nonMatchingId = $object instanceof Order ? $this->nonMatchingOrder->getId() : $this->nonMatchingReturnInvoice->getId();
            $this->assertNotEquals($nonMatchingId, $object->getId(), "The non-matching sample record was included in the search results.");
        }
        $this->assertTrue($matchingObjectFound, "The matching record was not included in the search results.");
    }

    private function getFieldValue($object, $field) {
        switch ($field) {
        case 'fromAddress.email':
            return $object->getFromAddress()->getEmail();
        case 'fromAddress.name':
            return $object->getFromAddress()->getFirstName() . ' ' . $object->getFromAddress()->getLastName();
        case 'shippingAddress.email':
            return $object->getShippingAddress()->getEmail();
        case 'shippingAddress.name':
            return $object->getShippingAddress()->getFirstName() . ' ' . $object->getShippingAddress()->getLastName();
        case 'rma':
            return $object instanceof ReturnInvoice ? $object->rmaNumber : $object->matchedRmaNumber;
        default:
            $getter = 'get' . ucfirst($field);
            return $object->$getter();
        }
    }

    private function insertSampleData() {
        $address = (new Address())
            ->setFirstName('test-first-name-aaaaaaaa')
            ->setLastName('test-last-name-aaaaaaaa')
            ->setEmail('test-email-aaaaaaaa');

        $box = new Box();
        $box->setTrackingNumber('test-tracking-number-aaaaaaaa');

        $this->order = new Order();
        $this->order->setOrderNumber(11111111);
        $this->order->setPrimaryId('test-pid-aaaaaaaa');
        $this->order->setSource(Import::SOURCE_READYRETURNS);
        $this->order->setOrderedAt(new \DateTime());
        $this->order->setImportId(ImportDAO::getLastImport());
        $this->order->setFromAddress($address);
        $this->order->setShippingAddress($address);
        $this->order->addBox($box);
        OrderDAO::insert($this->order, false);

        $this->returnInvoice = new ReturnInvoice();
        $this->returnInvoice->setTrackingNumber('test-tracking-number-aaaaaaaa');
        $this->returnInvoice->time = time();
        $this->returnInvoice->setFromAddress($address);
        $this->returnInvoice->setOrder($this->order);
        $this->returnInvoice->rmaNumber = 22222222;
        ReturnInvoiceDAO::insert($this->returnInvoice, false);

        $this->importId = $this->order->getImportId()->getId();

        $address = (new Address())
            ->setFirstName('test-first-name-zzzzzzzz')
            ->setLastName('test-last-name-zzzzzzzz')
            ->setEmail('test-email-zzzzzzzz');

        $box = new Box();
        $box->setTrackingNumber('test-tracking-number-zzzzzzzz');

        $this->nonMatchingOrder = new Order();
        $this->nonMatchingOrder->setOrderNumber(99999999);
        $this->nonMatchingOrder->setPrimaryId('test-pid-zzzzzzzz');
        $this->nonMatchingOrder->setSource(Import::SOURCE_READYRETURNS);
        $this->nonMatchingOrder->setOrderedAt(new \DateTime());
        $this->nonMatchingOrder->setImportId(ImportDAO::getLastImport());
        $this->nonMatchingOrder->setFromAddress($address);
        $this->nonMatchingOrder->setShippingAddress($address);
        $this->nonMatchingOrder->addBox($box);
        OrderDAO::insert($this->nonMatchingOrder, false);

        $this->nonMatchingReturnInvoice = new ReturnInvoice();
        $this->nonMatchingReturnInvoice->setTrackingNumber('test-tracking-number-zzzzzzzz');
        $this->nonMatchingReturnInvoice->time = time();
        $this->nonMatchingReturnInvoice->setFromAddress($address);
        $this->nonMatchingReturnInvoice->setOrder($this->nonMatchingOrder);
        $this->nonMatchingReturnInvoice->rmaNumber = 33333333;
        ReturnInvoiceDAO::insert($this->nonMatchingReturnInvoice);
    }
}
