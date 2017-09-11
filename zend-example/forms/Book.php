<?php

/**
 * Form for adding new book and edit information about book.
 */

class Form_Book extends Zend_Form {
    private $_hideFields;
    private $_showPrices;

    private $_shippingFields = ['form_book_weight_pound'];

    public function init() {
        $this->_hideFields =[];
        $this->_showPrices = false;

        // form_book_api_only: init
        $element = new Zend_Form_Element_Radio('form_book_api_only');
        $element->setMultiOptions([
            '0' => 'No',
            '1' => 'Yes'
        ]);
        $element->setLabel('Sold Through API Only');
        $element->setSeparator('');
        $validator = new Zend_Validate_NotEmpty();
        $validator->setMessages(
            ['isEmpty' => 'Please choose how you want to sell your book']
        );
        $element->addValidator($validator);
        $element->setRequired(true);
        $element->setRegisterInArrayValidator(false);

        // form_book_api_only: add
        $this->addElement($element);

        $element = new Zend_Form_Element_Radio('form_code_access_only');
        $element->setMultiOptions([
            '0' => 'No',
            '1' => 'Yes'
        ]);
        $element->setSeparator('');
        $element->setLabel('Code Access Only');

        $this->addElement($element);

        // form book_type: init
        $element = new Zend_Form_Element_Radio('form_book_type');
        $element->setMultiOptions(['digital' => 'digital', 'printed' => 'printed', 'both' => 'both']);
        $element->setLabel('Book type');
        $element->setSeparator('');
        $validator = new Zend_Validate_NotEmpty();
        $validator->setMessages(['isEmpty' => 'Please choose a book type']);
        $element->addValidator($validator);
        $element->setRequired(true);
        $element->setRegisterInArrayValidator(false);

        // form book_type: add

        $this->addElement($element);

        // form book_file: init
        $element = new Zend_Form_Element_Text('form_book_file');
        $element->setLabel('Book file');

        // form book_file: add

        $this->addElement($element);

        // form book_title: init
        $element = new Zend_Form_Element_Text('form_book_title');

        $validator = new Zend_Validate_NotEmpty();
        $validator->setMessages(['isEmpty' => 'Please enter a book title']);
        $element->addValidator($validator);
        $element->setRequired(true);
        $element->setLabel('Book title');

        // form book_title: add
        $this->addElement($element);

        // form book_author: init
        $element = new Zend_Form_Element_Text('form_book_author');

        $validator = new Zend_Validate_NotEmpty();
        $validator->setMessages(['isEmpty' => 'Please enter a book author(s)']);
        $element->addValidator($validator);
        $element->setRequired(true);
        $element->setLabel('Author(s)');

        // form book_author: add

        $this->addElement($element);

        // form book_publisher: init
        $element = new Zend_Form_Element_Text('form_book_publisher');

        $validator = new Zend_Validate_NotEmpty();
        $validator->setMessages(['isEmpty' => 'Please enter your publisher']);
        $element->addValidator($validator);
        $element->setRequired(true);
        $element->setLabel('Publisher');

        // form book_publisher: add

        $this->addElement($element);

        // form book_can_edit:init

        $element = new Zend_Form_Element_Text('form_book_can_edit');
        $element->setLabel('Can edit book');
        $element->setAttrib('placeholder', 'For Publisher Only Leave Empty');

        // form book_can_edit: add

        $this->addElement($element);

        // form book_authors_edit:init
        $element = new Zend_Form_Element_Hidden('form_book_authors_edit');

        // form book_authors_edit: add

        $this->addElement($element);

        // form book_available: init

        $element = new Zend_Form_Element_Radio('form_book_is_free');
        $validator = new Zend_Validate_NotEmpty();
        $validator->setMessages(['isEmpty' => 'Please choose price of your book']);
        $element->addValidator($validator);
        $element->setRequired(true);
        $element->setMultiOptions(['1'=>'free', '0' => 'paid']);
        $element->setLabel('Access');
        $element->setSeparator('');
        $element->setRegisterInArrayValidator(false);

        // form book_available: add

        $this->addElement($element);

        // If book has type "digital" or "printed" use this price field
        // form book_buy_price: init

        $element = new Zend_Form_Element_Text('form_book_buy_price');
        $validator = new Zend_Validate_NotEmpty();
        $validator->setMessages(['isEmpty' => 'Please set price for your book']);
        $element->addValidator($validator);
        $validator = new App_Validate_CustomFloatValidator();
        $validator->setMessages(
            ['notFloat' => 'Invalid value for book price', 'badFormat' => 'Only 2 decimal digits allowed']
        );
        $element->addValidator($validator);
        $element->setRequired(true);
        $element->setLabel('Price($)');

        // form book_buy_price: add

        $this->addElement($element);

        // If book has type "both" use 2 fields for prices
        // form_book_price_digital: init

        $element = new Zend_Form_Element_Text('form_book_price_digital');
        $validator = new Zend_Validate_NotEmpty();
        $validator->setMessages(['isEmpty' => 'Please set price for digital book']);
        $element->addValidator($validator);
        $validator = new App_Validate_CustomFloatValidator();
        $validator->setMessages([
            'notFloat' => 'Invalid value for book price',
            'badFormat' => 'Only 2 decimal digits allowed'
        ]);
        $element->addValidator($validator);
        $element->setRequired(true);
        $element->setLabel('Price digital($)');

        // form_book_price_digital: add
        $this->addElement($element);

        // form_book_price_printed: init

        $element = new Zend_Form_Element_Text('form_book_price_printed');
        $validator = new Zend_Validate_NotEmpty();
        $validator->setMessages(['isEmpty' => 'Please set price for printed book']);
        $element->addValidator($validator);
        $validator = new App_Validate_CustomFloatValidator();
        $validator->setMessages([
            'notFloat' => 'Invalid value for book price',
            'badFormat' => 'Only 2 decimal digits allowed'
        ]);
        $element->addValidator($validator);
        $element->setRequired(true);
        $element->setLabel('Price printed($)');

        // form_book_price_printed: add
        $this->addElement($element);

        // If book has 'printed' or 'digital' type use this one field.
        // form book_isbn: init
        $element = new Zend_Form_Element_Text('form_book_isbn');

        $validator = new Zend_Validate_NotEmpty();
        $validator->setMessages(['isEmpty' => 'Please enter ISBN']);
        $element->addValidator($validator);
        $element->setRequired(true);
        $element->setLabel('ISBN');

        // form book_isbn: add
        $this->addElement($element);

        // If book is in 2 formats - use 2 fields for digital book ISBN and printed book ISBN.
        $element = new Zend_Form_Element_Text('form_book_isbn_digital');

        $validator = new Zend_Validate_NotEmpty();
        $validator->setMessages(['isEmpty' => 'Please enter ISBN']);
        $element->addValidator($validator);
        $element->setRequired(true);
        $element->setLabel('ISBN digital');

        // form book_isbn: add
        $this->addElement($element);

        $element = new Zend_Form_Element_Text('form_book_isbn_printed');

        $validator = new Zend_Validate_NotEmpty();
        $validator->setMessages(['isEmpty' => 'Please enter ISBN']);
        $element->addValidator($validator);
        $element->setRequired(true);
        $element->setLabel('ISBN printed');

        // form book_isbn: add
        $this->addElement($element);

        // form book_subject: init
        $element = new App_Form_Element_SubjectSelect('form_book_subject');
        $element->setRequired(true);
        $element->setAttrib("class", "combobox");

        $validator = new Zend_Validate_NotEmpty();
        $validator->setMessages(['isEmpty' => 'Please enter book subject']);
        $element->addValidator($validator);

        $validator = new Zend_Validate_InArray($element->getMultiOptions());
        $validator->setMessages(['notInArray' => 'Please choose subjects from the list']);
        $element->addValidator($validator);
        $element->setRequired(true);
        $element->setLabel('Subject');

        // form book_subject: add
        $this->addElement($element);

        // form book_weight_pound: init
        $element = new Zend_Form_Element_Text('form_book_weight_pound');
        $element->setRequired(true);
        $element->setLabel('Weight (lb)');
        $element->setValue(1);
        $validator = new Zend_Validate_NotEmpty();
        $validator->setMessages(['isEmpty' => 'Please enter book weight in pounds']);
        $element->addValidator($validator);

        // form book_weight: add
        $this->addElement($element);

        // form book_subject:add

        $this->addElement($element);

        // form book_overview:init
        $element = new Zend_Form_Element_Textarea('form_book_overview');
        $element->setAttribs(['cols' => '35','rows' => '15', 'spellcheck' => 'false']);
        $element->setLabel('Overview');

        // form book_overview:add
        $this->addElement($element);

        // form book_features: init

        $element = new Zend_Form_Element_Textarea('form_book_features');
        $element->setAttribs(['cols' => '35', 'rows' => '15', 'spellcheck' => 'false']);
        $element->setLabel('Features');

        // form book_features: add

        $this->addElement($element);

        $this->clearDecorators();

        $this->setElementDecorators(['ViewHelper']);
    }

    /** 
     * This function hide prices fields according to book type (printed, digital, both)
     * and book access (free or true, paid).
     */
    public function hidePricesFields($bookType, $freeBookAccess) {
        if ( $freeBookAccess == 0 ) {
            $this->_showPrices = true;

            if ($bookType == 'printed' || $bookType == 'digital') {
                $this->getElement('form_book_price_digital')->setRequired(false);
                $this->getElement('form_book_price_printed')->setRequired(false);
                $this->_hideFields[] = 'form_book_price_digital';
                $this->_hideFields[] = 'form_book_price_printed';
            } else {
                $this->getElement('form_book_buy_price')->setRequired(false);
                $this->_hideFields[] = 'form_book_buy_price';
            }
        } else {
            $this->_showPrices = false;
            $this->getElement('form_book_buy_price')->setRequired(false);
            $this->getElement('form_book_price_digital')->setRequired(false);
            $this->getElement('form_book_price_printed')->setRequired(false);
        }
    }

    /**
     * This function mark fields as hide according to bookType. 
     * Because 'both' type has 2 fields for ISBN and 'printed'/'digital' only one.
     * @param string $bookType
     * @param bool $newBookMode
     */
    public function hideISBNFields($bookType, $newBookMode) {
        if ($bookType == 'printed' || $bookType == 'digital' || $newBookMode || empty($bookType)) {
            $this->getElement('form_book_isbn_digital')->setRequired(false);
            $this->getElement('form_book_isbn_printed')->setRequired(false);
            $this->_hideFields[] = 'form_book_isbn_digital';
            $this->_hideFields[] = 'form_book_isbn_printed';
        } else {
            $this->getElement('form_book_isbn')->setRequired(false);
            $this->_hideFields[] = 'form_book_isbn';
        }
    }

    public function hideShippingFields($bookType) {
        if ($bookType == 'digital' || empty($bookType)){
            foreach($this->_shippingFields as $field){
                $this->getElement($field)->setRequired(false);
                $this->_hideFields[] = $field;
            }
        }
    }

    public function hideApiFields() {
        $this->_hideFields[] = 'form_book_api_only';
    }

    public function getHideFields() {
        return $this->_hideFields;
    }

    public function showPrices() {
        return $this->_showPrices;
    }

    public function isValid($data) {
        // If book is free then price fields are not required.
        if (isset($data['form_book_is_free']) && $data['form_book_is_free'] == 1) {
            $this->getElement('form_book_buy_price')->clearValidators();
            $this->getElement('form_book_price_digital')->clearValidators();
            $this->getElement('form_book_price_printed')->clearValidators();
        }

        return parent::isValid($data);
    }

    public function makeFieldsReadOnly() {
        foreach ($this->getElementsAndSubFormsOrdered() as $element){
            $element->setAttrib("readOnly", true);
        }
        $this->getElement('form_book_is_free')->setAttrib("disable", "disable");
    }

    public function adjustFormForUserType($userInfo) {
        $userInfo = (array)$userInfo;

        if ($userInfo['user_type'] !== 'admin')
            $this->removeElement('form_code_access_only');

        if ($userInfo['user_type'] == 'author') {

            $this->removeElement('form_book_can_edit');
            $this->removeElement('form_book_authors_edit');
        }

        // For independent authors "Publisher" field should be empty and disabled.
        // For not independent - publisher is set and can not be changed.
        if ($userInfo['is_independent']) {
            $this->removeElement('form_book_publisher');
        } else {
            $this->getElement('form_book_publisher')->setValue($userInfo['company'])->setAttrib("readOnly", "true");
        }

        // Users who sell books only through API can add only digital books.
        if (!$userInfo['sell_on_site'] && $userInfo['api_access']) {
            $this->getElement('form_book_type')->setValue('digital')->setAttrib('disabled', 'disabled');
        }

        if (!($userInfo['api_access'] && $userInfo['sell_on_site'])) {
            $this->getElement('form_book_api_only')->clearValidators()->setRequired(false);
        }

        if (!$userInfo['api_access'] || ($userInfo['api_access'] && !$userInfo['sell_on_site'])) {
            $this->hideApiFields();
        }
    }
}
