<?php

class BookController extends App_Controller_Action {

    public function init() {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('is-book-sold-on-site', array('json'))
            ->initContext();
    }

    public function searchBookAction() {
        $searchFor = $this->_getParam('search_for', null);

        $this->_helper->layout->disableLayout();

        $page = intval($this->_getParam("page") ? intval($this->_getParam("page")) : 1);
        $perPage = intval($this->_getParam("perPage") ? intval($this->_getParam("perPage")) : 10);

        $bookPaginator = (new Model_Book())->searchBook($searchFor, ['title'], ['paginator' => true]);

        $bookPaginator->setCurrentPageNumber($page);
        $bookPaginator->setItemCountPerPage($perPage);
        $bookPaginator->setPageRange(5);

        $this->view->books = $bookPaginator;
    }

    public function getOneTimeLinkAction() {
        $this->_helper->layout->disableLayout();

        $step = $this->_getParam('step',1);

        $form = new Form_OneTimeLink();

        if ($step == 3) {
            // Get template of instructions for downloading.
            $instructionView= new Zend_View();
            $instructionView->setScriptPath(APPLICATION_PATH.'/views/mail-templates/');
            $bookDownloadingInstruction = $instructionView->render("instruction-for-one-time-link.phtml");

            $bookDownloadingInstruction = str_replace("\n", "", $bookDownloadingInstruction);

            $this->view->instruction = $bookDownloadingInstruction;
            $this->view->link = $this->_getParam("link", null);
            $this->view->title= $this->_getParam("title", null);
        }

        if($postData = $this->getRequest()->getPost()){
            if($form->isValidPartial($postData) && $step == 2) {
                $acsManager = new Model_Manager_ACSRequest($postData['form_book_select'], 'book');
                $oneTimeLink = $acsManager->getOneTimeLink($postData['form_book_select']);

                $this->view->book = (new Model_Book())->getBookInfoById($postData['form_book_select']);
                $this->view->link = $oneTimeLink;
            } else if($step == 3 && $form->isValidPartial($postData)){
                $subject = "Book Downloading Link";
                $to      = $postData['form_email'];

                $headers  = 'MIME-Version: 1.0' . "\r\n";
                $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
                $headers .= 'From: AuthorCloudware <support@authorcloudware.com>' . "\r\n";
                $headers .= 'Reply-To: AuthorCloudware <support@authorcloudware.com>'. "\r\n";

                $this->view->emailSent = mail($to, $subject, $postData['form_email_text'], $headers);
            }
        }

        $this->view->form = $form;

        $html = $this->view->render('book/get-one-time-link-step' . $step . '.phtml');

        $this->_helper->json(["success" => true, "html" => $html]);
    }

    public function isBookSoldOnSiteAction() {
        $bookId = $this->_getParam("bookId", 0);

        $soldThroughAPIOnly = (new Model_Book())->getBookInfoById($bookId, 'api_only');

        $this->view->soldOnSite = (int)!$soldThroughAPIOnly;
        $this->view->success = true;
    }

    public function booksTypeAheadAction() {
        $query = $this->_getParam('query');
        $bookType = $this->_getParam('bookType');

        $books = (new Model_Book())->searchBook($query, 'title', [
            'approved_only' => 1,
            'type' => $bookType
        ]);

        $options = [];
        foreach($books as $book) {
            $options[] = ['title' => $book['title'], 'id' => $book['book_id']];
        }

        $this->_helper->json(['options' => $options]);
    }
}
