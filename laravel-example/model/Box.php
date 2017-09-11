<?php
namespace Database\Model;

use App\Services\Data\Annotations\InternalAnnotation as Internal,
    App\Services\Data\Mapping\DefaultSetterAnnotation as DefaultSetter,
    App\Services\Data\Mapping\MappingAnnotation as Mapping,
    Automation\Actions\ActionableInterface,
    Data\Money,
    Data\Size,
    Data\Weight;

/**
 * @Table(name="rr_box", uniqueConstraints={@UniqueConstraint(name="uidx_true_id_box_number", columns={"true_id", "box_number"})})
 * @Entity
 */
class Box extends AbstractEntity implements ActionableInterface {
    const CONFIRMATION_TYPE_DISABLED                    = 0;
    const CONFIRMATION_TYPE_NO_SIGNATURE                = 1;
    const CONFIRMATION_TYPE_SIGNATURE_REQUIRED          = 2;
    const CONFIRMATION_TYPE_ADULT_SIGNATURE_REQUIRED    = 3;

    const COD_CASH_ONLY                                 = 0;
    const COD_CASHIERS_CHECK_MONEY_ORDER                = 1;
    const COD_PERSONAL_CHECK_CASHIERS_CHECK_MONEY_ORDER = 2;

    private static $readyCloudDeliveryConfirmationTypes = array(
        // null would also be mapped to CONFIRMATION_TYPE_DISABLED.
        'none'                      => self::CONFIRMATION_TYPE_DISABLED,
        'no_signature_required'     => self::CONFIRMATION_TYPE_NO_SIGNATURE,
        'signature_required'        => self::CONFIRMATION_TYPE_SIGNATURE_REQUIRED,
        'adult_signature_required'  => self::CONFIRMATION_TYPE_ADULT_SIGNATURE_REQUIRED,
    );


    /**
     * @Column(name="box_id", type="integer", nullable=false)
     * @Id
     * @GeneratedValue(strategy="IDENTITY")
     * @Internal
     */
    private $boxId;
    /** @Internal */
    public function getId() { return $this->boxId; }

    /**
     * The parent @c Order to which this @c Box belongs.
     * @see getOrder(), setOrder()
     *
     * @ManyToOne(targetEntity="Order", inversedBy="boxes")
     * @JoinColumn(name="true_id", referencedColumnName="true_id", nullable=false)
     */
    protected $order;
    /**
     * @var ManyToOneOwned
     * @Internal
     */
    private $orderProxy;
    public function getOrder() { return $this->order; }

    /** @Column(name="box_number", type="integer", nullable=false) */
    public $boxNumber = 0;

    /**
     * @deprecated
     * @Column(name="ship_time", type="integer", nullable=true)
     * @Internal
     */
    public $shipTime;

    /**
     * @deprecated
     * @Column(name="ship_via", type="integer", nullable=true)
     * @Internal
     */
    public $shipVia;

    /**
     * @deprecated
     * @Column(name="ship_type", type="string", length=128, nullable=true)
     * @Internal
     */
    public $shipType;

    // TODO Add a readycloud_api mapping for this field when it's exposed in the RC API.
    /** @Column(name="package_type", type="string", length=128, nullable=true) */
    private $packageType;
    public function getPackageType() { return $this->packageType; }
    public function setPackageType($packageType) { $this->packageType = $packageType; return $this; }

    /**
     * @Column(name="tracking_number", type="string", length=128, nullable=true)
     * @Mapping(format="readycloud_api", path="tracking_number")
     * @Mapping(format="readycloud_api_2.0", path="tracking_number")
     */
    private $trackingNumber;
    public function getTrackingNumber() { return $this->trackingNumber; }
    public function setTrackingNumber($number) { $this->trackingNumber = $number; return $this; }

    /**
     * @Column(name="actual_shipping_cost", type="decimal", nullable=true)
     * @Mapping(format="readycloud_api", path="charges/actual_ship_cost", formatter="\Data\Money", selfFormatting=true)
     * @Mapping(format="readycloud_api_2.0", path="ship_cost", formatter="\Data\Money", selfFormatting=true)
     * @Mapping(format="rules_editor", type="\Data\Money")
     * @DefaultSetter("setActualShippingCostFromReadyCloud")
     */
    private $actualShippingCost;
    private $actualShippingCostProxy;
    public function getActualShippingCost() { return $this->actualShippingCostProxy; }
    public function setActualShippingCost(Money $cost) { $this->actualShippingCostProxy->setFrom($cost); return $this; }
    // TODO Maybe move the custom setters' logic to one or more formatter classes, or to parseX() methods in the importer class.
    /** @Internal */
    public function setActualShippingCostFromReadyCloud(Money $cost) { return $this->setActualShippingCost($cost->defaultUnitTo(\ReadyCloudImporter::DEFAULT_DATA_CURRENCY)); }

    /** @Column(name="actual_shipping_cost_currency", type="string", length=5, nullable=true) */
    private $actualShippingCostCurrency;

    /**
     * @Column(name="weight", type="decimal", nullable=true)
     * @Mapping(format="readycloud_api", path="weight", formatter="\Data\Weight", selfFormatting=true)
     * @Mapping(format="readycloud_api_2.0", path="weight", formatter="\Data\Weight", selfFormatting=true)
     * @Mapping(format="rules_editor", type="\Data\Weight")
     * @DefaultSetter("setWeightFromReadyCloud")
     */
    private $weight;
    private $weightProxy;
    public function getWeight() { return $this->weightProxy; }
    public function setWeight(Weight $weight) { $this->weightProxy->setFrom($weight)->convertTo(Weight::G); return $this; }
    /** @Internal */
    public function setWeightFromReadyCloud(Weight $weight) { return $this->setWeight($weight->defaultUnitTo(\ReadyCloudImporter::DEFAULT_DATA_WEIGHT_UNIT)); }

    /** @Column(name="weight_unit", type="string", length=5, nullable=true) */
    private $weightUnit;

    // TODO Add readycloud_api mapping for the container fields when the containers etc are properly implemented in the RC API.
    /** @Column(name="container_name", type="string", length=255, nullable=true) */
    public $containerName;

    /** @Column(name="container_description", type="string", length=255, nullable=true) */
    public $containerDescription;

    // TODO Update this field's mapping when it's properly (re)exposed in the RC API.
    /**
     * @Column(name="length", type="decimal", nullable=true)
     * @Mapping(format="readycloud_api", path="length", formatter="\Data\Size", selfFormatting=true)
     * @Mapping(format="readycloud_api_2.0", path="length", formatter="\Data\Size", selfFormatting=true)
     * @Mapping(format="rules_editor", type="\Data\Size")
     * @DefaultSetter("setLengthFromReadyCloud")
     */
    private $length;
    private $lengthProxy;
    public function getLength() { return $this->lengthProxy; }
    public function setLength(Size $length) { $this->lengthProxy->setFrom($length)->convertTo(Size::M); return $this; }
    /** @Internal */
    public function setLengthFromReadyCloud(Size $length) { return $this->setLength($length->defaultUnitTo(\ReadyCloudImporter::DEFAULT_DATA_SIZE_UNIT)); }

    /** @Column(name="length_unit", type="string", length=5, nullable=true) */
    private $lengthUnit;

    // TODO Update this field's mapping when it's properly (re)exposed in the RC API.
    /**
     * @Column(name="width", type="decimal", nullable=true)
     * @Mapping(format="readycloud_api", path="width", formatter="\Data\Size", selfFormatting=true)
     * @Mapping(format="readycloud_api_2.0", path="width", formatter="\Data\Size", selfFormatting=true)
     * @Mapping(format="rules_editor", type="\Data\Size")
     * @DefaultSetter("setWidthFromReadyCloud")
     */
    private $width;
    private $widthProxy;
    public function getWidth() { return $this->widthProxy; }
    public function setWidth(Size $width) { $this->widthProxy->setFrom($width)->convertTo(Size::M); return $this; }
    /** @Internal */
    public function setWidthFromReadyCloud(Size $width) { return $this->setWidth($width->defaultUnitTo(\ReadyCloudImporter::DEFAULT_DATA_SIZE_UNIT)); }

    /** @Column(name="width_unit", type="string", length=5, nullable=true) */
    private $widthUnit;

    // TODO Update this field's mapping when it's properly (re)exposed in the RC API.
    /**
     * @Column(name="height", type="decimal", nullable=true)
     * @Mapping(format="readycloud_api", path="height", formatter="\Data\Size", selfFormatting=true)
     * @Mapping(format="readycloud_api_2.0", path="height", formatter="\Data\Size", selfFormatting=true)
     * @Mapping(format="rules_editor", type="\Data\Size")
     * @DefaultSetter("setHeightFromReadyCloud")
     */
    private $height;
    private $heightProxy;
    public function getHeight() { return $this->heightProxy; }
    public function setHeight(Size $height) { $this->heightProxy->setFrom($height)->convertTo(Size::M); }
    /** @Internal */
    public function setHeightFromReadyCloud(Size $height) { return $this->setHeight($height->defaultUnitTo(\ReadyCloudImporter::DEFAULT_DATA_SIZE_UNIT)); }

    /** @Column(name="height_unit", type="string", length=5, nullable=true) */
    private $heightUnit;

    // TODO Add readycloud_api mapping for the COD fields when the they're (re?)exposed in the RC API.
    /** @Column(name="is_cod_enabled", type="boolean", nullable=false) */
    private $isCodEnabled = false;
    public function isCodEnabled() { return (bool)$this->isCodEnabled; }
    public function setIsCodEnabled($isCodEnabled) { $this->isCodEnabled = (bool)$isCodEnabled; return $this; }

    /** @Column(name="cod_type", type="integer", nullable=false) */
    private $codType = self::COD_CASH_ONLY;
    public function getCodType() { return $this->codType; }
    public function setCodType($codType) { $this->codType = $codType; return $this; }

    /** @Column(name="cod_add_shipping_cost", type="boolean", nullable=false) */
    private $codAddShippingCost = false;
    public function getCodAddShippingCost() { return (bool)$this->codAddShippingCost; }
    public function setCodAddShippingCost($codAddShippingCost) { $this->codAddShippingCost = (bool)$codAddShippingCost; return $this; }

    /** @Column(name="cod_amount", type="decimal", nullable=true) */
    private $codAmount;
    private $codAmountProxy;
    public function getCodAmount() { return $this->codAmountProxy; }
    public function setCodAmount(Money $amount) { $this->codAmountProxy->setFrom($amount); return $this; }

    /** @Column(name="cod_amount_currency", type="string", length=5, nullable=true) */
    private $codAmountCurrency;

    /**
     * @Column(name="is_shipper_release", type="boolean", nullable=false)
     * @Mapping(format="readycloud_api", path="shipper_release")
     * @Mapping(format="readycloud_api_2.0", path="shipper_release")
     */
    private $isShipperRelease = false;
    public function isShipperRelease() { return (bool)$this->isShipperRelease; }
    public function setIsShipperRelease($isShipperRelease) { $this->isShipperRelease = (bool)$isShipperRelease; return $this; }

    /**
     * @Column(name="delivery_confirmation_type", type="integer", nullable=false)
     * @Mapping(format="readycloud_api", path="delivery_confirmation", getter="getDeliveryConfirmationTypeForReadyCloud", setter="setDeliveryConfirmationTypeFromReadyCloud")
     * @Mapping(format="readycloud_api_2.0", path="confirmation_type", getter="getDeliveryConfirmationTypeForReadyCloud", setter="setDeliveryConfirmationTypeFromReadyCloud")
     */
    public $deliveryConfirmationType = 0;
    public function getDeliveryConfirmationType() { return $this->deliveryConfirmationType; }
    /** @Internal */
    public function getDeliveryConfirmationTypeForReadyCloud() { return array_search($this->deliveryConfirmationType, self::$readyCloudDeliveryConfirmationTypes); }
    public function setDeliveryConfirmationType($type) { $this->deliveryConfirmationType = $type; return $this; }
    /** @Internal */
    public function setDeliveryConfirmationTypeFromReadyCloud($type) { $this->deliveryConfirmationType = getval(self::$readyCloudDeliveryConfirmationTypes, $type, 0); return $this; }

    /**
     * @Column(name="declared_value", type="decimal", nullable=true)
     * @Mapping(format="readycloud_api", path="charges/declared_value", formatter="\Data\Money", selfFormatting=true)
     * @Mapping(format="readycloud_api_2.0", path="declared_value", formatter="\Data\Money", selfFormatting=true)
     * @Mapping(format="rules_editor", type="\Data\Money")
     * @DefaultSetter("setDeclaredValueFromReadyCloud")
     */
    private $declaredValue;
    private $declaredValueProxy;
    public function getDeclaredValue() { return $this->declaredValueProxy; }
    public function setDeclaredValue(Money $value) { $this->declaredValueProxy->setFrom($value); return $this; }
    /** @Internal */
    public function setDeclaredValueFromReadyCloud(Money $value) { return $this->setDeclaredValue($value->defaultUnitTo(\ReadyCloudImporter::DEFAULT_DATA_CURRENCY)); }

    /** @Column(name="declared_value_currency", type="string", length=5, nullable=true) */
    private $declaredValueCurrency;

    /**
     * @Column(name="general_description", type="string", length=255, nullable=true)
     * @Mapping(format="readycloud_api", path="general_description")
     * @Mapping(format="readycloud_api_2.0", path="description")
     */
    public $generalDescription;
    public function getGeneralDescription() { return $this->generalDescription; }
    public function setGeneralDescription($description) { $this->generalDescription = $description; return $this; }

    /**
     * @Column(name="insured_value", type="decimal", nullable=true)
     * @Mapping(format="readycloud_api", path="charges/insured_value", formatter="\Data\Money", selfFormatting=true)
     * @Mapping(format="readycloud_api_2.0", path="insured_value", formatter="\Data\Money", selfFormatting=true)
     * @Mapping(format="rules_editor", type="\Data\Money")
     * @DefaultSetter("setInsuredValueFromReadyCloud")
     */
    private $insuredValue;
    private $insuredValueProxy;
    public function getInsuredValue() { return $this->insuredValueProxy; }
    public function setInsuredValue(Money $value) { $this->insuredValueProxy->setFrom($value); return $this; }
    /** @Internal */
    public function setInsuredValueFromReadyCloud(Money $value) { return $this->setInsuredValue($value->defaultUnitTo(\ReadyCloudImporter::DEFAULT_DATA_CURRENCY)); }

    /** @Column(name="insured_value_currency", type="string", length=5, nullable=true) */
    private $insuredValueCurrency;

    /**
     * @Column(name="is_saturday_delivery_enabled", type="boolean", nullable=false)
     * @Mapping(format="readycloud_api", path="saturday_delivery")
     * @Mapping(format="readycloud_api_2.0", path="saturday_delivery")
     */
    private $isSaturdayDeliveryEnabled = false;
    public function isSaturdayDeliveryEnabled() { return (bool)$this->isSaturdayDeliveryEnabled; }
    public function setIsSaturdayDeliveryEnabled($isSaturdayDeliveryEnabled) { $this->isSaturdayDeliveryEnabled = (bool)$isSaturdayDeliveryEnabled; return $this; }

    /**
     * @Column(name="created_at", type="datetime", nullable=true)
     * @Internal
     */
    public $createdAt;
    public function getCreatedAt() { return $this->createdAt; }
    public function setCreatedAt(\DateTime $createdAt = null) { $this->createdAt = $createdAt; return $this; }

    /**
     * @Column(name="updated_at", type="datetime", nullable=true)
     * @Internal
     */
    public $updatedAt;
    public function getUpdatedAt() { return $this->updatedAt; }
    public function setUpdatedAt(\DateTime $updatedAt = null) { $this->updatedAt = $updatedAt; return $this; }

    /** @Internal */
    private $label;
    public function getLabel() { return $this->label; }
    public function setLabel($label) {
        $this->label = $label;
        $amazonS3Client = new \AmazonS3Client();
        $this->setLabelFileName($amazonS3Client->getFileName($label));
    }

    /**
     * @deprecated
     *
     * Box (shipping) options for this @c Box.
     * Up to one @c BoxOption record is allowed for each @c Box (a 1:0-1 relationship).
     * @see deleteOptions(), getOptions(), setOptions()
     *
     * @OneToOne(targetEntity="BoxOption", mappedBy="box", cascade={"detach"})
     */
    private $options;

    /**
     * @OneToMany(targetEntity="BoxCustomField", mappedBy="boxId", cascade={"detach"})
     * @Mapping(format="readycloud_api", path="custom_fields")
     * @Mapping(format="readycloud_api_2.0", path="custom_fields")
     */
    private $customFields;
    public function addCustomField($field) {
        $this->customFields->add($field);
        $field->setBoxId($this);
    }
    public function getCustomFields() { return $this->customFields->toArray(); }

    /**
     * The items for this @c Box.
     * @OneToMany(targetEntity="Item", mappedBy="box", cascade={"detach"}, fetch="EXTRA_LAZY")
     * @OrderBy({"itemId" = "ASC"})
     * @Mapping(format="readycloud_api", path="items")
     * @Mapping(format="readycloud_api_2.0", path="items")
     */
    protected $items;
    /**
     * @var OneToManyOwner
     * @Internal
     */
    private $itemsProxy;
    public function getItems() { return $this->itemsProxy; }

    // === Constructor === //

    public function __construct() {
        parent::__construct();
        $this->customFields = new \SetCollection();
    }

    // === Public Methods === //

    /**
     * Deletes a @c BoxCustomField.
     * @param string $name The name of the custom field.
     * @attention This method DOES NOT automatically flush the changes to the database!
     */
    public function deleteCustomField($name) {
        foreach ($this->customFields as $field) {
            if ($field->getName() == $name) {
                // Schedule the custom field for deletion from the DB, but don't flush the changes yet! (Let the client code decide when to flush changes.)
                BoxCustomFieldDAO::delete($field, false);
                $this->customFields->removeElement($field);
                break;
            }
        }
    }

    /**
     * Detaches the box (shipping) options from this @c Box, and schedules the detached @c BoxOption object for deletion. If there are no box options
     * set, does nothing.
     * @attention This method DOES NOT automatically flush the changes to the database!
     * @see getOptions(), setOptions()
     */
    public function deleteOptions() {
        if (!isset($this->options)) {
            // Nothing to do here.
            return;
        }

        // Schedule the box options for deletion from the DB, but don't flush the changes yet! (Let the client code decide when to flush changes.)
        BoxOptionDAO::delete($this->options, false);
        $this->options = null;
    }

    /**
     * Returns the list of the associated @c Box objects.
     * @return Box[] The boxes.
     * @Internal
     */
    public function getBoxes() {
        return [$this];
    }

    /**
     * Retrieves a @c BoxCustomField value.
     * @param string $name The name of the custom field.
     * @return string|null The custom field's value, or @c null if not found.
     * @see getCustomFields(), setCustomField()
     * @Internal
     */
    public function getCustomField($name) {
        foreach ($this->getCustomFields() as $field) {
            if ($field->getName() == $name) {
                return $field->getValue();
            }
        }
        return null;
    }

    /**
     * Gets the shipping label of this @c Box.
     * @return string|null The label (base64 encoded), or @c null if not found.
     * @throws \Exception if the parent @c Order has an unsupported ship-via.
     * @see setLabelOld()
     * @deprecated
     * @Internal
     */
    public function getLabelOld() {
        $shipVia = $this->getOrder()->getShipVia();
        switch ($shipVia) {
        case Order::SHIP_VIA_ENDICIA:
            return $this->getCustomField(BoxCustomField::_ENDICIA_LABEL);
        case Order::SHIP_VIA_MAIL_INNOVATIONS:
        case Order::SHIP_VIA_UPS:
            $label = $this->getCustomField(BoxCustomField::_UPS_LABEL_PNG);
            if ($label === null) {
                $label = $this->getCustomField(BoxCustomField::_UPS_LABEL);
            }
            return $label;
        default:
            // This will alert us if we forget to add support for a new carrier.
            throw new \Exception("Cannot retrieve label for ship-via '$shipVia'.");
        }
    }

    /**
     * Gets the label file name of this @c Box.
     * @return string|null The label file name, or @c null if not found.
     * @see setLabelFileName()
     * @Internal
     */
    public function getLabelFileName() {
        return $this->getCustomField(BoxCustomField::LABEL_FILE_NAME);
    }

    /**
     * Retrieves the box (shipping) options for this @c Box.
     * @return mixed The box options (a @c BoxOption object) if set, or @c null otherwise.
     * @see deleteOptions(), setOptions()
     */
    public function getOptions() {
        return $this->options;
    }

    /**
     * Get the package type of this @c Box.
     * @return string|null The package type, or @c null if not set.
     */
    public function getEffectivePackageType() {
        if (!is_null($this->packageType)) {
            return $this->packageType;
        }
        // Get the package type from custom field if available.
        $packageType = $this->getCustomField(BoxCustomField::SO_PKG_TYPE);
        if (!is_null($packageType)) {
            return $packageType;
        }
        // No package type in the custom field. Use the default package type as specified in admin settings.
        try {
            $shipperClass = \Shipper::getClassNameFromShipVia($this->getOrder()->getShipVia());
        } catch (\Exception $e) {
            return null;
        }
        return $shipperClass::getDefaultPackageTypeName();
    }

    /**
     * Gets the tracking status from this @c Box.
     * @return string|null The tracking status, or @c null if not found.
     */
    public function getTrackingStatus() {
        return $this->getCustomField(BoxCustomField::TRACKING_STATUS);
    }

    /**
     * Sets a @c BoxCustomField value. If a @c BoxCustomField with the given name exists, its value is overwritten. Otherwise, a new @c BoxCustomField
     * is added.
     * @param string $name The name of the custom field.
     * @param string $value The new value of the custom field.
     * @return BoxCustomField The added or updated @c BoxCustomField.
     * @see addCustomField(), getCustomField()
     * @Internal
     */
    public function setCustomField($name, $value) {
        // If there already exists a custom field with the same name, overwrite it.
        foreach ($this->getCustomFields() as $field) {
            if ($field->getName() == $name) {
                $field->setValue($value);
                return $field;
            }
        }

        // If there is no existing field with the same name, create a new one.
        $field = new BoxCustomField();
        $field->setName($name);
        $field->setValue($value);
        $this->addCustomField($field);

        return $field;
    }

    /**
     * Sets the shipping label of this @c Box.
     * @param string $label The base64 encoded shipping label.
     * @throws \Exception if the parent @c Order has an unsupported ship-via.
     * @see getLabelOld()
     * @deprecated
     * @Internal
     */
    public function setLabelOld($label) {
        $amazonS3Client = new \AmazonS3Client();
        $this->setLabelFileName($amazonS3Client->getFileName($label));
        $shipVia = $this->getOrder()->getShipVia();
        switch ($shipVia) {
        case Order::SHIP_VIA_ENDICIA:
            $this->setCustomField(BoxCustomField::_ENDICIA_LABEL, $label);
            break;
        case Order::SHIP_VIA_MAIL_INNOVATIONS:
        case Order::SHIP_VIA_UPS:
            $this->setCustomField(BoxCustomField::_UPS_LABEL_PNG, $label);
            break;
        default:
            // This will alert us if we forget to add support for a new carrier.
            throw new \Exception("Cannot set label for ship-via '$shipVia'.");
        }
    }

    /**
     * Sets the label file name of this @c Box.
     * @param string $labelFileName The label file name.
     * @see getLabelFileName()
     * @Internal
     */
    public function setLabelFileName($labelFileName) {
        $this->setCustomField(BoxCustomField::LABEL_FILE_NAME, $labelFileName);
    }

    /**
     * Sets the box (shipping) options for this @c Box. Also sets the reverse reference (from the @c BoxOption to this @c Box).
     * @param BoxOption $options The box options.
     * @throws \LogicException if the box options are already set to a different @c BoxOption instance. The previous box options must be explicitly
     *   deleted first, using the <tt>deleteOptions()</tt> method. This is done to avoid hidden side-effects in the code.
     * @note The box options cannot be set to @c null with this method. Box options cannot exist without a parent @c Box, except before the parent
     *   @c Box is assigned. The only way to detach the box options from a @c Box is to delete the @c BoxOption object (and then optionally create
     *   and attach a new one). Use the <tt>deleteOptions()</tt> method to delete the box options.
     * @attention This method DOES NOT automatically persist the new association! For the association to be saved, the @c BoxOption object must be
     *   persisted by the client code.
     * @see deleteOptions(), getOptions()
     */
    public function setOptions(BoxOption $options) {
        // If the box options are already set to the same instance, do nothing. This also helps avoid infinite recursion.
        if ($this->options === $options) {
            return;
        }

        // Check if we're allowed to set the options. The type-hint prevents null from being passed in, so no need to check that explicitly.
        if (isset($this->options)) {
            throw new \LogicException('Cannot set box options. Delete the previous options first.');
        }

        // Set our reference before calling BoxOption::setBox(), to avoid infinite recursion if the BoxOption calls this method again.
        $this->options = $options;
        // Make sure to update the reverse reference. This is especially important here, since the BoxOption is the "owning" side from Doctrine's
        // perspective (it's the side with the FK column).
        $options->setBox($this);
    }

    public function toDomElement($doc) {
        $box = $doc->createElement("box");

        if ($this->getWeight()->hasValue()) {
            $weight = $doc->createElement('weight');
            $weight->appendChild($this->getWeight()->toDomElement($doc, 'weight', 'units'));
            $box->appendChild($weight);
        }

        if (isset($this->trackingNumber)) {
            $box->appendChild($doc->createElement("tracking_number", $this->trackingNumber));
        }

        $container = $doc->createElement("container");

        if (isset($this->containerName)) {
            $container->appendChild($doc->createElement("name", $this->containerName));
        }

        if (isset($this->containerDescription)) {
            $container->appendChild($doc->createElement("description", $this->containerDescription));
        }

        if ($this->getLength()->hasValue()) {
            $length = $doc->createElement('length');
            $length->appendChild($this->getLength()->toDomElement($doc, 'length', 'units'));
            $container->appendChild($length);
        }

        if ($this->getWidth()->hasValue()) {
            $width = $doc->createElement('width');
            $width->appendChild($this->getWidth()->toDomElement($doc, 'length', 'units'));
            $container->appendChild($width);
        }

        if ($this->getHeight()->hasValue()) {
            $height = $doc->createElement('height');
            $height->appendChild($this->getHeight()->toDomElement($doc, 'length', 'units'));
            $container->appendChild($height);
        }

        $box->appendChild($container);

        if ($this->getActualShippingCost()->hasValue()) {
            $shipCost = $doc->createElement('actual_shipcost');
            $shipCost->appendChild($this->getActualShippingCost()->toDomElement($doc, 'money', 'currency'));
            $box->appendChild($shipCost);
        }

        $options = $this->getOptions();
        if (isset($options)) {
            foreach ($options->toDomElements($doc) as $option) {
                $box->appendChild($option);
            }
        }

        foreach ($this->items as $item) {
            $box->appendChild($item->toDomElement($doc));
        }

        return $box;
    }

    // === Public Static Methods === //

    /**
     * Convert camel case property names to human readable names.
     * @param string $propertyName The lowerCamelCase property name.
     * @return string The human-friendly property name.
     */
    public static function getPropertyDisplayName($propertyName) {
        switch ($propertyName) {
        case 'isCodEnabled':
            return 'COD';
        default:
            return parent::getPropertyDisplayName($propertyName);
        }
    }

    // === Protected Methods === //

    // Initializes proxies and other internal objects.
    protected function initialize() {
        parent::initialize();
        $this->orderProxy = $this->setAssociation("order", "ManyToOneOwned");
        $this->itemsProxy = $this->setAssociation("items", "OneToManyOwner");
        $this->actualShippingCostProxy = Money::proxyFor($this->actualShippingCost, $this->actualShippingCostCurrency);
        $this->codAmountProxy = Money::proxyFor($this->codAmount, $this->codAmountCurrency);
        $this->declaredValueProxy = Money::proxyFor($this->declaredValue, $this->declaredValueCurrency);
        $this->heightProxy = Size::proxyFor($this->height, $this->heightUnit);
        $this->insuredValueProxy = Money::proxyFor($this->insuredValue, $this->insuredValueCurrency);
        $this->lengthProxy = Size::proxyFor($this->length, $this->lengthUnit);
        $this->widthProxy = Size::proxyFor($this->width, $this->widthUnit);
        $this->weightProxy = Weight::proxyFor($this->weight, $this->weightUnit);
    }
}

class BoxDAO extends BaseDAO {
    public static function delete($box, $flush = true) {
        global $em;

        $box->getItems()->deleteAll();
        $box->deleteOptions();
        parent::delete($box, $flush);
    }

    public static function fromSimpleXml($xml) {
        syslog(LOG_DEBUG, "processing xml for order box");

        $box = new Box();
        $box->setWeight(\XmlFileImporter::parseWeight($xml->weight));
        $box->setActualShippingCost(\XmlFileImporter::parseMoney($xml->actual_shipcost));
        if (isset($xml->tracking_number)) {
            $box->setTrackingNumber((string)$xml->tracking_number);
        }

        if (isset($xml->container)) {
            $container = $xml->container;

            if (isset($container->name)) {
                $box->containerName = (string)$container->name;
            }
            if (isset($container->description)) {
                $box->containerDescription = (string)$container->description;
            }
            $box->setLength(\XmlFileImporter::parseSize($container->length));
            $box->setWidth(\XmlFileImporter::parseSize($container->width));
            $box->setHeight(\XmlFileImporter::parseSize($container->height));
        }

        // Shipping options.
        $box->setOptions(BoxOptionDAO::fromSimpleXml($xml));

        // Items.
        syslog(LOG_DEBUG, "Attempting to parse " . count($xml->item) . " items.");
        foreach ($xml->item as $itemXml) {
            $box->addItem(ItemDAO::fromSimpleXml($itemXml));
        }

        return $box;
    }

    public static function getBoxes($trueId) {
        global $em;

        $dql = 'SELECT b FROM Box b JOIN b.order o WHERE (o.trueId = :trueId) ORDER BY b.boxNumber ASC';
        $params = array('trueId' => $trueId);
        return $em->createQuery($dql)->setParameters($params)->getResult();
    }

    public static function getConfirmationTypeDisplayText($confirmationType) {
        switch ($confirmationType) {
            case Box::CONFIRMATION_TYPE_DISABLED:
                return 'No Delivery Confirmation';
            case Box::CONFIRMATION_TYPE_NO_SIGNATURE:
                return 'No Signature';
            case Box::CONFIRMATION_TYPE_SIGNATURE_REQUIRED:
                return 'Signature Required';
            case Box::CONFIRMATION_TYPE_ADULT_SIGNATURE_REQUIRED:
                return 'Adult Signature Required';
            default:
                throw new \Exception('Invalid confirmation type: ' . $confirmationType);
        }
    }

    public static function insert($box, $flush = true) {
        // Insert the custom fields.
        foreach ($box->getCustomFields() as $field) {
            BoxCustomFieldDAO::insert($field, false);
        }

        // Insert items.
        foreach ($box->getItems() as $item) {
            ItemDAO::insert($item, false);
        }

        // Insert the box options.
        $options = $box->getOptions();
        if (isset($options)) {
            BoxOptionDAO::insert($options, false);
        }

        return parent::insert($box, $flush);
    }

    /**
     * Updates the tracking status of a @c Box.
     * @param Box $box The @c Box to update.
     * @param string $status The new tracking status.
     * @param boolean $flush Should the changes be flushed to the database immediately? Default is @c true.
     */
    public static function updateTrackingStatus(Box $box, $status, $flush = true) {
        $field = $box->setCustomField(BoxCustomField::TRACKING_STATUS, $status);
        BoxCustomFieldDAO::insert($field, $flush);
    }
}
