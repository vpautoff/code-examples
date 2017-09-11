<?php

class OrderController extends App_Controller_Action {
    public function getNotShippedOrdersAction() {
        $orders = (new Model_OrderShipment())->getAllUnshippedOrders();
        $this->_helper->json(['success' => true, 'items' => $orders]);
    }

    public function getOrderDetailsAction() {
        $this->_helper->layout->disableLayout();

        $orderId = $this->_getParam('id', 0);

        $modelOrder = new Model_DbTable_AmazonOrder();
        $modelPurchaseDetails = new Model_PurchaseDetails();
        $modelAmazonTransaction = new Model_DbTable_AmazonTransaction();

        $orderInfo = $modelOrder->getInfoAboutOrder($orderId);

        if ($orderInfo) {
            $lastTransaction = $modelAmazonTransaction->getLastOrderTransaction($orderId);

            $orderInfo['last_transaction_date'] = $lastTransaction['date'];

            $purchaseInfo = $modelPurchaseDetails->getProductsByOrderId($orderId);

            $itemList = [];
            foreach($purchaseInfo as $purchaseItem) {
                $itemList[$purchaseItem['code']] = $purchaseItem['qty'];
            }

            $orderInfo['items_list'] = Model_NewCart::itemsDescriptionStatic($itemList, $orderInfo['user_type'] == 'professor' && $orderInfo['active'] && !$orderInfo['is_banned']);
        }

        $this->view->order = $orderInfo;
        $this->view->customerMode = ($this->_user->user_type == 'admin') ? false : true;
        $this->view->physicalItems = !empty($orderInfo['shipping_status']);
        $this->view->parcelSent = !empty($orderInfo['shipping_status']) ? ($orderInfo['shipping_status'] == 'sent') : false;
    }

    public function markOrderAsSentAction() {
        $orderId = $this->_getParam('id', 0);
        (new Model_OrderShipment())->markAsSent($orderId);
        $this->_helper->json(['success' => true]);
    }

    public function addTrackingNumberAction() {
        $orderId = $this->_getParam('id',0);
        $trackingNumber = $this->_getParam('trackingNumber',0);

        $orderShipment = new Model_OrderShipment();
        $orderShipment->addTrackingNumber($trackingNumber, $orderId);

        $this->_helper->json(['success' => true]);
    }

    /**
     * Mark their orders as informed about when user go to the order list page.
     */
    public function markOrdersAsInformedAction() {
        (new Model_DbTable_AmazonOrder())->markAsInformed($this->_user->user_id);
        $this->_helper->json(['success' => true]);
    }

    public function checkOrderNotificationsAction() {
        $modelOrder = new Model_DbTable_AmazonOrder();
        $notInformedOrderChanges = $modelOrder->getNotInformedOf($this->_user->user_id);
        $this->_helper->json(['success' => true, 'count' => $notInformedOrderChanges]);
    }

    public function orderListAction() {
        $this->view->hideSearch = true;

        $modelAmazonOrder = new Model_DbTable_AmazonOrder();

        $orders = $modelAmazonOrder->getAllUserOrders($this->_user->user_id);
        $modelAmazonOrder->markAsInformed($this->_user->user_id);

        $this->view->orders = $orders;
        $this->view->orderNotifications = 0;
    }

    public function adminOrderListAction() {
        $this->_helper->layout->disableLayout();
        $this->view->hideSearch = true;

        $page = $this->_getParam('page', 1);
        $perPage = $this->_getParam('perPage', 10);

        $modelAmazonOrder = new Model_DbTable_AmazonOrder();
        $ordersPaginator = $modelAmazonOrder->getAllOrders($paginator = true);

        $ordersPaginator->setCurrentPageNumber($page);
        $ordersPaginator->setItemCountPerPage($perPage);
        $ordersPaginator->setPageRange(3);

        $this->view->orders = $ordersPaginator;
    }

    public function amazonIpnAction() {
        $amazonFpsModel = new Model_AmazonFps();
        $amazonTransactionModel = new Model_DbTable_AmazonTransaction();
        $purchaseManager = new Model_Manager_Purchase();
        $amazonOrderModel = new Model_DbTable_AmazonOrder();

        $orderId = false;
        if ($amazonFpsModel->checkIpnSignature($_POST)) {
            // For processing PAY operations we will use transactionId to get order info.
            if (!isset($_POST['parentTransactionId'])) {
                $orderId = $amazonTransactionModel->getOrderId($_POST['transactionId']);
            } else {
                // In case of REFUND operation we need to process parentTransactionId - value which is referring to initial PAY request.
                $orderId = $amazonTransactionModel->getOrderId($_POST['parentTransactionId']);
            }

            if ($orderId) {
                $ipnData = array_merge($_POST, ['orderId' => $orderId]);
                $userInfo = $amazonOrderModel->getUserByOrderId($orderId);
                $purchaseManager->processIPN($ipnData, $userInfo);
            }
        } else {
            $amazonLogger = Zend_Registry::get('logger');
            $amazonLogger->log('Invalid IPN message', 7);
            $amazonLogger->log(var_export($_POST, true), 7);
        }
        $this->_helper->json(['success' => true]);
    }
}
