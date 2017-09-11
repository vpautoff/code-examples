<?php
namespace Database\Model;

/**
 * @Table(name="rr_warehouse")
 * @Entity
 */
class Warehouse extends AbstractEntity {
    /**
     * @Column(name="id", type="integer", nullable=false)
     * @Id
     * @GeneratedValue(strategy="IDENTITY")
     */
    private $id;
    public function getId() { return $this->id; }

    /**
     * @OneToOne(targetEntity="Address", inversedBy="warehouse")
     * @JoinColumn(name="address_id", referencedColumnName="address_id", nullable=false)
     * @var Address
     * @see getAddress(), deleteAddress(), setAddress()
     */
    protected $address;
    /**
     * @var OneToOneOwner
     * @Internal
     */
    private $addressProxy;

    /** @Column(name="name", type="string", length=64, nullable=false, unique=true) */
    private $name;
    public function getName() { return $this->name; }
    public function setName($name) { $this->name = $name; return $this; }

    /** @Column(name="created_at", type="datetime", nullable=true) */
    private $createdAt;
    public function getCreatedAt() { return $this->createdAt; }
    public function setCreatedAt(\DateTime $createdAt = null) { $this->createdAt = $createdAt; return $this; }

    /** @Column(name="updated_at", type="datetime", nullable=true) */
    private $updatedAt;
    public function getUpdatedAt() { return $this->updatedAt; }
    public function setUpdatedAt(\DateTime $updatedAt = null) { $this->updatedAt = $updatedAt; return $this; }

    /** @OneToMany(targetEntity="Product", mappedBy="warehouse", fetch="EXTRA_LAZY") */
    protected $products;
    /**
     * @var OneToManyNotOwner
     * @Internal
     */
    private $productsProxy;

    /** @ManyToMany(targetEntity="User", mappedBy="warehouses") */
    protected $users;
    /**
     * @var ManyToMany
     * @Internal
     */
    private $usersProxy;

    /** @OneToMany(targetEntity="ReturnInvoice", mappedBy="warehouse") */
    protected $returnInvoices;
    /**
     * @var OneToManyNotOwner
     * @Internal
     */
    private $returnInvoicesProxy;

    // === Public Methods === //

    public function addProduct(Product $product) {
        $this->productsProxy->add($product, $this);
        RuleDAO::createProductWarehouseRule($product, $this);
        return $this;
    }
    public function detachProduct(Product $product) {
        $this->productsProxy->detach($product, $this);
        RuleDAO::deleteProductWarehouseRule($product, $this);
        return $this;
    }

    public function getLocation() {
        return $this->getAddress()->getLocation();
    }

    // === Protected Methods === //

    // Initializes proxies and other internal objects.
    protected function initialize() {
        parent::initialize();
        $this->addressProxy = $this->setAssociation('address', 'OneToOneOwner');
        $this->productsProxy = $this->setAssociation('products', 'OneToManyNotOwner');
        $this->usersProxy = $this->setAssociation('users', 'ManyToMany');
        $this->returnInvoicesProxy = $this->setAssociation('returnInvoices', 'OneToManyNotOwner');
    }
}

class WarehouseDAO extends BaseDAO {
    public static function bulkDelete(array $ids, $pageSize = 20) {
        global $em;
        $i = 0;
        $warehouses = $em->getRepository('Warehouse')->findById($ids);
        $warehousesCount = count($warehouses);
        foreach ($warehouses as $warehouse) {
            self::delete($warehouse, false);
            if ((++$i % $pageSize) == 0 || $i == $warehousesCount) {
                $em->flush();
                $em->clear();
                gc_collect_cycles();
            }
        }
    }

    /** @return int The total count of all the Warehouses. */
    public static function countAll() {
        global $em;
        $query = $em->createQuery("SELECT COUNT(w) FROM Warehouse w");
        return (int)$query->getSingleScalarResult();
    }

    public static function delete($warehouse, $flush = true) {
        $warehouse->deleteAddress();
        parent::delete($warehouse, $flush);
    }

    public static function findAll() {
        global $em;
        return $em->getRepository("Warehouse")->findBy(array(), array("name" => "ASC"));
    }

    public static function getByName($name) {
        global $em;
        return $em->getRepository('Warehouse')->findOneBy(array('name' => $name));
    }

    public static function insert($warehouse, $flush = true) {
        $address = $warehouse->getAddress();
        if (isset($address)) {
            AddressDAO::insert($address, false);
        }
        return parent::insert($warehouse, $flush);
    }

    /**
     * Check if the warehouse is unique.
     * @param string $name The warehouse name.
     * @param int|null $id The warehouse id or @c null if the warehouse is new. Default is @c null.
     * @return bool @c true if the warehouse is unique, @c false otherwise.
     */
    public static function isUnique($name, $id = null) {
        global $em;
        $warehouse = $em->getRepository('Warehouse')->findOneBy(array('name' => $name));
        return !$warehouse || $warehouse->getId() == $id;
    }

    /**
     * Returns a sorted subset of @c Warehouse objects.
     * @param int $offset The offset of the first record to return, counting from 0.
     * @param int $rowCount The number of records to return.
     * @param string $customSort A custom sort string. Default is an empty string (no custom sort). The records will be further sorted by the @c Warehosue IDs.
     * @return Warehouse[] The array of @c Warehouse objects.
     */
    public static function selectRange($offset, $rowCount, $customSort = '') {
        global $em;
        if (!empty($customSort)) {
            $customSort .= ',';
        }
        $query = $em->createQuery("SELECT w FROM Warehouse w JOIN w.address a ORDER BY $customSort w.id");
        return $query->setFirstResult($offset)->setMaxResults($rowCount)->getResult();
    }
}
