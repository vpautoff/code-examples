<?php
/**
 * Model for working with Book database table.
 */

class Model_Book extends App_Base_FileForAcs implements App_Interface_OnixDataSource, App_Interface_AcsDataSource, App_Interface_ApiDataSource {
    protected $_name = 'book';
    protected $_primaryKey = 'book_id';

    /**
     * Add new book. Function is calling by author or publisher.
     */
    public function addBook($values, $userId) {
        $price = '';
        if (isset($values['form_book_is_free']) && !$values['form_book_is_free']){
            if ($values['form_book_type'] == 'both') {
                $price = json_encode(
                    ["digital" => $values['form_book_price_digital'], "printed" => $values['form_book_price_printed']]
                );
            } else {
                $price = isset($values['form_book_buy_price']) ? $values['form_book_buy_price'] : '';
            }
        } 

        $isbn = '';
        if ($values['form_book_type'] == 'both') {
            $isbn = json_encode([
                "digital" => str_replace('-', '' ,$values['form_book_isbn_digital']),
                "printed" => str_replace('-', '' ,$values['form_book_isbn_printed'])
            ]);
        } else {
            $isbn = str_replace('-','', $values['form_book_isbn']);
        }

        // Remove unallowed tags from features and overview fields.
        $allowedTags = ['b', 'i', 'ul', 'li', 'br'];
        $filter = new Zend_Filter_StripTags([
            'allowTags' => $allowedTags
        ]);

        $what = [
            'title'     => $values['form_book_title'],
            'book_type' => $values['form_book_type'],
            'isbn'      => $isbn,
            'subject'   => isset($values['form_book_subject']) ? $values['form_book_subject'] : '',
            'authors'   => $values['form_book_author'],
            'publisher' => isset($values['form_book_publisher']) ? $values['form_book_publisher'] : '' ,
            'free'      => isset($values['form_book_is_free']) ? $values['form_book_is_free'] : '',
            'price_buy' => isset($price) ? $price : '',
            'overview'  => isset($values['form_book_overview']) ? $filter->filter($values['form_book_overview']) : '',
            'features'  => isset($values['form_book_features']) ? $filter->filter($values['form_book_features']) : '',
            'added_by'  => $userId,
            'weight_pound'  => isset($values['form_book_weight_pound']) ? $values['form_book_weight_pound'] : '',
            'api_only' => isset($values['form_book_api_only']) ? $values['form_book_api_only'] : ''
        ];

        $bookId = $this->insert($what);
        return $bookId;
    }

    /**
     * Get all books visible to author/publisher/assistant.
     * @param int userId The user ID.
     * @param array options Options.
     * @return bool | book Paginator - output not all results, but by page, acsPending - if true, output books which pending upload to Adobe Content Server.
     */
    public function getAllBooksByAuthorId($userId, $options = []) {
        /**
         * Zend Paginator has bug when you use DISTINCT command in SQL query. So the query
         * is constructed without it here. That's why a condition is added to the JOIN LEFT.
         */
        $select = $this->_db->select();

        if (isset($options['idOnly'])) {
            $select->from(["b"=> $this->_name], 'book_id');
        } else {
            $select->from(["b" => $this->_name]);
        }

        if (!empty($userId)){
            $select->joinLeft(
                ["d" => "user_dependence"],
                "d.user_id = b.added_by",
                []
            )
            ->joinLeft(
                ["e" => "edit_book"],
                "e.book_id = b.book_id and e.user_id = $userId",
                []
            );
        }

        if (isset($options['approvedOnly'])) {
            $select->where('b.approved = ?', 1);
        }

        if (isset($options['acsPending'])) {
            $select->where('b.book_type <> ?', 'printed')
                ->where('adobe_status = ?', 'initial');
        }

        if (isset($options['soldOnSite'])) {
            $select->where('b.api_only = ?', 0);
        }

        if (isset($options['apiAvailable'])){
            $select->where('b.api_only = ?', 0);
        }

        if (isset($options['book_type'])) {
            if (is_array($options['book_type'])) {
                $select->where('b.book_type IN (?)', $options['book_type']);
            } else {
                $select->where('b.book_type = ?', $options['book_type']);
            }
        }

        if (!empty($userId)){
            $select->where('(b.added_by = ?', $userId)
                ->orWhere('(d.publisher_id = ?', $userId)
                ->where('b.approved = ?',1)
                ->where('d.approved = ? )',1)
                ->orWhere('(e.book_id IS NOT NULL))');
        }

        $select->order('b.title');

        if (!isset($options['paginator'])) {
            if (isset($options['idOnly'])) {
                $rows = $this->_db->fetchAll($select);
                $result = [];
                foreach ($rows as $row) {
                    $result[] = $row['book_id'];
                }
                return $result;
            } else {
                return $this->_db->fetchAll($select);
            }
        } else {
            $adapter = new Zend_Paginator_Adapter_DbSelect($select);
            $paginator = new Zend_Paginator($adapter);
            return $paginator;
        }
    }

    /**
     * Get book info by book id.
     */
    public function getBookInfoById($bookId, $fields = ["*"]) {
        $select = $this->_db->select()
            ->from($this->_name, $fields)
            ->where('book_id = ?', $bookId);

        if (count($fields) == 1 && $fields[0] != '*') {
            return $this->_db->fetchOne($select);
        } else {
            return $this->_db->fetchRow($select);
        }
    }

    public function getBookInfoByISBN($isbn, $userId, $type = 'digital') {
        $select = $this->_db->select()
            ->from(["b" => $this->_name], ['id' => 'book_id', 'adobeId' => 'adobe_resource_id','adobe_status'])
            ->joinLeft(
                ["d" => "user_dependence"],
                "d.user_id = b.added_by",
                []
            )
            ->joinLeft(
                ["e" => "edit_book"],
                "e.book_id = b.book_id and e.user_id = $userId",
                []
            )
            ->where('(b.added_by = ?', $userId)
            ->orWhere('(d.publisher_id = ?', $userId)
            ->where('b.approved = ?', 1)
            ->where('d.approved = ? )', 1)
            ->orWhere('(e.book_id IS NOT NULL))')
            ->where('b.approved = ?', '1');

        $select->where('((b.isbn = ?', $isbn)
            ->where('b.book_type = ?)', $type)
            ->orWhere('(b.isbn LIKE ?', '%"' . $type . '":"' . $isbn . '"%')
            ->where('b.book_type = ?))', 'both');

        return $this->_db->fetchAll($select);
    }

    /**
     * Get book info and corresponding bookshelf item's info.
     */
    public function getBookOnShelfInfo($bookId, $userId) {
        $select = $this->_db->select()
            ->from(["b" => $this->_name])
            ->joinLeft(
                ["bs" => "bookshelf"], 
                "b.book_id = bs.book_id" 
            )
            ->where("bs.user_id = ?", $userId)
            ->where("b.book_id = ?", $bookId);
        return $this->_db->fetchRow($select);
    }

    /**
     * Upload cover for a book.
     */
    public function uploadCover($bookId, $coverPath) {
        $coverFilename = pathinfo($coverPath, PATHINFO_FILENAME);
        $coverExtension = pathinfo($coverPath, PATHINFO_EXTENSION);

        return $this->update(
            ['cover_path' => $coverFilename, 'cover_extension' => $coverExtension],
            ['book_id = ?' => $bookId]
        );
    }

    /**
     * Get book cover by book id.
     */
    public function getCover($bookId) {
        $select = $this->_db->select()
            ->from($this->_name, ['cover_path', 'cover_extension'])
            ->where('book_id = ?', $bookId);
        $result =  $this->_db->fetchRow($select);
        return [$result['cover_path'], $result['cover_extension']];
    }
    
    public function editBook($bookId, $values){
        $price = '';
        if (!$values['form_book_is_free']){
            if ($values['form_book_type'] == 'both')
                $price = json_encode(
                    ["digital" => $values['form_book_price_digital'], "printed" => $values['form_book_price_printed']]
                );
            else 
                $price = $values['form_book_buy_price'];
        }

        $isbn = '';
        if ($values['form_book_type'] == 'both') {
            $isbn = json_encode(
                ["digital" => $values['form_book_isbn_digital'], "printed" => $values['form_book_isbn_printed']]
            );
        } else {
            $isbn = $values['form_book_isbn'];
        }

        return $this->update(
            [
                'title'     => $values['form_book_title'],
                'isbn'      => $isbn,
                'subject'     => $values['form_book_subject'],
                'authors'     => $values['form_book_author'],
                'publisher' => isset($values['form_book_publisher']) ? $values['form_book_publisher'] : '',
                'overview'  => isset($values['form_book_overview']) ? $values['form_book_overview'] : '',
                'features'  => isset($values['form_book_features']) ? $values['form_book_features'] : '',
                'free'      => $values['form_book_is_free'],
                'price_buy' => ($values['form_book_is_free']) ? '' : $price,
                'weight_pound' => isset($values['form_book_weight_pound']) ? $values['form_book_weight_pound'] : '',
                'api_only' => isset($values['form_book_api_only']) ? $values['form_book_api_only'] : '',
                'access_code_only' => isset($values['form_code_access_only']) ? $values['form_code_access_only'] : ''
            ],
            ['book_id = ?' => $bookId]
        );
    }

    /**
     * Search books by titles, authors, subject and ISBN. If $paginator is not null return Zend_Paginator object.
     */
    public function searchBook($searchStr, $searchIn, $options = []) {
        $select = $this->_db->select()
            ->from($this->_name)
            ->distinct(true)
            ->order('title');

        if (isset($options['approved_only'])) {
            $select->where('approved = ?', 1);
        }

        // Show books available for purchase.
        if (isset($options['access_code_only'])) {
            $select->where('access_code_only = ?', $options['access_code_only']);
        }

        if (isset($options['type'])) {
            $select->where('book_type IN (?)', (array)$options['type']);
        }

        if (is_string($searchIn)) {
            if ($searchIn == 'title'){
                $titleQuery = $this->getFulltextSearchQuery($searchStr, 'title');
                $select->where($titleQuery);
            } else {
                $select->where("`".$searchIn."` LIKE ?", "%".$searchStr."%");
            }
        } elseif (is_array($searchIn)) {
            foreach ($searchIn as $index => $field) {
                if ($field == 'title'){
                    $titleQuery = $this->getFulltextSearchQuery($searchStr, 'title');
                    if ($index == 0)
                        $select->where('('.$titleQuery);
                    else 
                        $select->orWhere($titleQuery);    
                } else {
                    if ($index == 0)
                        $select->where("(`".$field."` LIKE ?", "%".$searchStr."%");
                    else 
                        $select->orWhere("`".$field."` LIKE ?", "%".$searchStr."%");
                }    
            }
            $select->where(true.")");
        }
        
        
        if (isset($options['category']) && !empty($options['category'])){
            $select->where('subject = ?', $options['category']);
        }

        if (isset($options['api_only'])) {
            $select->where('api_only = ?', (int)$options['api_only']);
        }

        if (!isset($options['paginator'])) {
            return $this->_db->fetchAll($select);
        } else {
            $adapter     = new Zend_Paginator_Adapter_DbSelect($select);
            $paginator  = new Zend_Paginator($adapter);
            return $paginator;
            
        }
    }

    protected function getFulltextSearchQuery($searchStr, $field) {
        $searchArr = explode(" ", $searchStr);
        
        $query = "";
        $likes = [];
        foreach ($searchArr as $word) {
            $likes[] = "`" . $field . "` LIKE '%" . $word . "%'";
        }
        $query .= implode(' OR ', $likes);
        
        return $query;
    }

    public function getBookIdByTitle($title) {
        $select = $this->_db->select()
            ->from($this->_name,'book_id')
            ->where("title = ?", $title);
        return $this->_db->fetchCol($select);
    }

    /**
     * Admin should approve books of only independent authors.
     */
    public function getUnapprovedAdminBooks() {
        $select = $this->_db->select()
            ->from(["b" => $this->_name], ['id' => 'book_id', 'title' => 'title'])
            ->joinLeft(
                ["u" => "user"], 
                "u.user_id = b.added_by"
            )
            ->where("b.approved = ?", 0)
            ->where("u.user_type = ?", "author")
            ->where("u.is_independent = ?", 1);
        return $this->_db->fetchAll($select);
    }

    public function approveBookById($bookId) {
        return $this->update(
            ['approved' => true],
            ['book_id = ?' => $bookId]
        );
    }

    /**
     * Get UUID value set by Aobe Content Server.
     */
    public function getUUID($bookId) {
        $select = $this->_db->select()
            ->from($this->_name, 'adobe_resource_id')
            ->where("book_id = ?", $bookId);
        $result =  $this->_db->fetchCol($select);
        return $result['0'];
    }

    /**
     * Mark that book uploading to the ACS is completed.
     */
    public function completeAdobeUpload($bookId) {
        return $this->update(
            ['adobe_status' => 'assigned'],
            ['book_id = ?' => $bookId]
        );
    }

    /**
     * Check if book belong to the certain author or publisher.
     */
    public function checkAccessRights($userId, $bookId) {
        $select = $this->_db->select()
            ->from(["b" => $this->_name])
            ->joinLeft(
                ["d" => "user_dependence"],
                "b.added_by = d.user_id"
            )
            ->joinLeft(
                ["e" => "edit_book"],
                "e.book_id = b.book_id"
            )
            ->where("b.book_id = ?", $bookId)
            ->where("(b.added_by = ?", $userId)
            ->orWhere("(d.publisher_id = ?", $userId)
            ->where("d.approved = ?))", 1)
            ->orWhere("e.user_id = ?", $userId);
        $result = $this->_db->fetchRow($select);
        return (!empty($result));
    }

    /**
     * Get number unapproved book for publisher.
     */
    public function getNumberOfUnapprovedPublisherBooks($authorsId) {
        $select = $this->_db->select()
            ->from($this->_name, ["count" => "COUNT(*)"])
            ->where("approved = ?", 0)
            ->where("added_by IN (?)", $authorsId);
        $result = $this->_db->fetchCol($select);
        return $result[0];
    }

    /**
     * Get unapproved book of publisher's authors.
     */
    public function getUnapprovedPublisherBooks($authorsId) {
        $select = $this->_db->select()
            ->from($this->_name)
            ->where("approved = ?", 0)
            ->where("added_by IN (?)", $authorsId);
        return $this->_db->fetchAll($select);
    }

    public function isBookApproved($bookId) {
        $select = $this->_db->select()
            ->from($this->_name, 'approved')
            ->where("book_id = ?", $bookId);
        $result =  $this->_db->fetchCol($select);
        return $result['0'];
    }

    public function getAdobeBookStatus($bookId) {
        $select = $this->_db->select()
            ->from($this->_name, 'adobe_status')
            ->where("book_id = ?", $bookId);
        $result =  $this->_db->fetchCol($select);
        return $result['0'];
    }

    public function getBookType($bookId) {
        $select = $this->_db->select()
            ->from($this->_name, 'book_type')
            ->where("book_id = ?", $bookId);
        $result =  $this->_db->fetchCol($select);
        return $result['0'];
    }

    public function getBookIsbn($bookId) {
        $select = $this->_db->select()
            ->from($this->_name, 'isbn')
            ->where("book_id = ?", $bookId);
        $result =  $this->_db->fetchCol($select);
        return $result['0'];
    }

    public function getBookTitle($bookId) {
        $select = $this->_db->select()
            ->from($this->_name, 'title')
            ->where("book_id = ?", $bookId);
        $result =  $this->_db->fetchCol($select);
        return $result['0'];
    }

    public function getAddedByField($bookId) {
        $select = $this->_db->select()
            ->from($this->_name, 'added_by')
            ->where("book_id = ?", $bookId);
        $result =  $this->_db->fetchCol($select);
        return $result['0'];
    }

    public function isBookFree($bookId) {
        $select = $this->_db->select()
            ->from($this->_name, 'price_buy')
            ->where("book_id = ?", $bookId);
        $result = $this->_db->fetchCol($select);
        return ($result['0'] == 0);
    }

    /**
     * Get data necessary for ACS request.
     */
    public function getAcsFileData($bookId) {
        $select = $this->_db->select()
            ->from($this->_name, ['publisher', 'title', 'authors', 'adobe_resource_id'])
            ->where('book_id = ?', $bookId);
        return $this->_db->fetchRow($select);
    }

    /**
     * Get random covers for landing page.
     */
    public function getRandomCovers() {
        $select = $this->_db->select()
            ->from($this->_name, ['book_id', 'cover_path', 'cover_extension'])
            ->where('cover_path <> ""')
            ->where('approved = ?', 1)
            ->joinLeft(
                ['u' => 'user'],
                'book.added_by = u.user_id',
                'u.sell_on_site'
            )
            ->where('u.sell_on_site = ?', 1)
            ->order('RAND()')
            ->limit('10');
        return $this->_db->fetchAll($select);    
    }

    /**
     * Function for getting all possible values of field "Subject".
     */
    public function getAllAvailableSubjects() {
        $sql = "SHOW COLUMNS FROM `book` LIKE 'subject'";
        $row = $this->_db->fetchRow($sql);
        $type = $row['Type'];
        preg_match('/enum\((.*)\)$/', $type, $matches);
        $vals = explode(',', $matches[1]);
        foreach ($vals as &$val) {
            $val = trim($val, "'");
        }
        return $vals;    
    }

    public function getDistributorIdByBookId($bookId) {
        $select = $this->_db->select()
            ->from($this->_name, ['book_id', 'added_by'])
            ->joinLeft(
                ['ad' => 'user_dependence'],
                'ad.user_id = added_by',
                ['publisher_id']
            )
            ->where('book.book_id = ?', $bookId);
        $result = $this->_db->fetchRow($select);

        return is_null($result['publisher_id']) ? $result['added_by'] : $result['publisher_id'];
    }

    public function getArrayOfItemsId($options = []) {
        $select = $this->_db->select()
            ->from($this->_name, ['book_id'])
            ->where('approved = ?', 1)
            ->order('book_id');


        return $this->_db->fetchCol($select);
    }

    public function bookAddedByIndiAuthor($bookId) {
        $select = $this->_db->select()
            ->from(['book' => $this->_name])
            ->joinLeft(
                'user',
                'user.user_id = book.added_by'
            )
            ->where('book.book_id = ?', $bookId)
            ->where('user.user_type = ?', 'author')
            ->where('user.is_independent = ?', 1);

        $result = $this->_db->fetchRow($select);
        return !empty($result);
    }

    public function getListOfAvailableDigitalBooks() {
        $select = $this->_db->select()
            ->from(['book' => $this->_name], ['book_id', 'title', 'book_type','adobe_resource_id'])
            ->where('book_type IN (?)', ['digital', 'both'])
            ->where('adobe_status = ?', 'assigned')
            ->order('title');

        return $this->_db->fetchAll($select);
    }

    public function makeUserBooksApiOnly($userId) {
        $booksId = $this->getAllBooksByAuthorId($userId, ["idOnly" => true]);

        if (!empty($booksId)) {
            $where = $this->getAdapter()->quoteInto('book_id IN (?)', $booksId);
            return $this->update(
                ['api_only' =>  1],
                $where
            );
        }
    }
}
