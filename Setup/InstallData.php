<?php

namespace Altitude\P21\Setup;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\Set as AttributeSet;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Sales\Setup\SalesSetupFactory;
use Magento\Eav\Model\Config;

/**
 * @codeCoverageIgnore
 */
class InstallData implements InstallDataInterface
{
    /**
     * @var CustomerSetupFactory
     */
    protected $customerSetupFactory;

    /**
     * @var AttributeSetFactory
     */
    private $attributeSetFactory;

    protected $salesSetupFactory;

    private $eavSetupFactory;
    /**
     * @param CustomerSetupFactory $customerSetupFactory
     * @param AttributeSetFactory $attributeSetFactory
     */
    public function __construct(
        CustomerSetupFactory $customerSetupFactory,
        AttributeSetFactory $attributeSetFactory,
        SalesSetupFactory $salesSetupFactory,
        \Altitude\P21\Model\P21 $p21,
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory,
        Config $eavConfig

    ) 
    {
        $this->customerSetupFactory = $customerSetupFactory;
        $this->attributeSetFactory = $attributeSetFactory;
        $this->salesSetupFactory = $salesSetupFactory;
        $this->p21 = $p21;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->eavConfig = $eavConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {

        /** @var CustomerSetup $customerSetup */
        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);

        $customerEntity = $customerSetup->getEavConfig()->getEntityType('customer');
        $attributeSetId = $customerEntity->getDefaultAttributeSetId();

        /** @var $attributeSet AttributeSet */
        $attributeSet = $this->attributeSetFactory->create();
        $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

        $customerSetup->addAttribute(\Magento\Customer\Model\Customer::ENTITY, 'p21_custno', [
            'type' => 'varchar',
            'label' => 'P21 Cust No',
            'input' => 'text',
            'required' => false,
            'visible' => true,
            'user_defined' => true,
            'sort_order' => 1000,
            'position' => 1000,
            'system' => 0,
        ]);

        $attribute = $customerSetup->getEavConfig()->getAttribute(\Magento\Customer\Model\Customer::ENTITY, 'p21_custno')
            ->addData([
                'attribute_set_id' => $attributeSetId,
                'attribute_group_id' => $attributeGroupId,
                'used_in_forms' => ['adminhtml_customer'],
                'is_used_in_grid' => ['1'],
                'is_visible_in_grid' => ['1'],
                'is_filterable_in_grid' => ['1'],
                'is_searchable_in_grid' => ['1'],
            ]);

        $used_in_forms = [
            "adminhtml_customer",
            "checkout_register",
            "customer_account_create",
            "customer_account_edit",
            "adminhtml_checkout"
        ];

        $attribute->setData("used_in_forms", $used_in_forms)
            ->setData("is_used_for_customer_segment", true)
            ->setData("is_system", 0)
            ->setData("is_user_defined", 1)
            ->setData("is_visible", 1)
            ->setData("sort_order", 100);

        $attribute->save();

        $customerSetup->addAttribute(\Magento\Customer\Model\Customer::ENTITY, 'hidenegativeinvoice', [
            'type' => 'varchar',
            'label' => 'Hide Negative Invoices Y/N',
            'input' => 'text',
            'required' => false,
            'visible' => true,
            'user_defined' => true,
            'sort_order' => 1001,
            'position' => 1001,
            'system' => 0,
        ]);

        $attribute = $customerSetup->getEavConfig()->getAttribute(\Magento\Customer\Model\Customer::ENTITY, 'hidenegativeinvoice')
            ->addData([
                'attribute_set_id' => $attributeSetId,
                'attribute_group_id' => $attributeGroupId,
                'used_in_forms' => ['adminhtml_customer'],
                'is_used_in_grid' => ['1'],
                'is_visible_in_grid' => ['1'],
                'is_filterable_in_grid' => ['1'],
                'is_searchable_in_grid' => ['1'],
            ]);

        $used_in_forms = [
            "adminhtml_customer",
            "checkout_register",
            "customer_account_create",
            "customer_account_edit",
            "adminhtml_checkout"
        ];

        $attribute->setData("used_in_forms", $used_in_forms)
            ->setData("is_used_for_customer_segment", true)
            ->setData("is_system", 0)
            ->setData("is_user_defined", 1)
            ->setData("is_visible", 1)
            ->setData("sort_order", 101);
            
        $attribute->save();
       


        $salesSetup = $this->salesSetupFactory->create(['setup' => $setup]);

        /**
         * Remove previous attributes
         */
        $attributes = ['P21_OrderNo'];
        foreach ($attributes as $attr_to_remove) {
            //  $salesSetup->removeAttribute(\Magento\Sales\Model\Order::ENTITY,$attr_to_remove);
        }

        /**
         * Add 'NEW_ATTRIBUTE' attributes for order
         */
        $options = ['type' => 'varchar', 'visible' => true, 'required' => false];
        $salesSetup->addAttribute('order', 'P21_OrderNo', $options);
        $salesSetup->addAttribute('order', 'CC_AuthNo', $options);
        $salesSetup->addAttribute('order', 'P21_OrderSuf', $options);

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();

        $DBERROR = true;
        try {
            $querycheck = 'SELECT 1 FROM `gws_GreyWolfOrderQueue`';
            $query_result = $connection->query($querycheck);
            if ($query_result !== false) {
                $DBERROR = false;
            }
        } catch (\Exception $e) {
            $DBERROR = true;
        }

        if ($DBERROR == false) {
            // table exists, proceed
        } else {
            // table does not exist, create here.
            $queryCreateUsersTable = "CREATE TABLE IF NOT EXISTS `gws_GreyWolfOrderQueue` (
            `ID` int(11) unsigned NOT NULL auto_increment,
            `orderid` varchar(255) NOT NULL default '',
            `dateentered` DATETIME DEFAULT NULL,
            `dateprocessed` DATETIME DEFAULT NULL,
            PRIMARY KEY  (`ID`)
            )";
            if (!$connection->query($queryCreateUsersTable)) {
                $this->p21->gwLog("Table creation failed: " . $dbConnection->errno . ") " . $dbConnection->error);
            }
        }

        //fields to update later
        try {
            $querycheck = 'SELECT 1 FROM `gws_GreyWolfOrderFieldUpdate`';
            $query_result = $connection->query($querycheck);
            if ($query_result !== false) {
                $DBERROR = false;
            }
        } catch (\Exception $e) {
            $DBERROR = true;
        }

        if ($DBERROR == false) {
            // table exists, proceed
        } else {
            // table does not exist, create here.
            $queryCreateUsersTable = "CREATE TABLE IF NOT EXISTS `gws_GreyWolfOrderFieldUpdate` (
            `ID` int(11) unsigned NOT NULL auto_increment,
            `orderid` varchar(255) NOT NULL default '',
            `ERPOrderNo` varchar(255) DEFAULT NULL,
            `ERPSuffix` varchar(255) DEFAULT NULL,
            `CCAuthNo` varchar(255) DEFAULT NULL,
            `dateentered` DATETIME DEFAULT NULL,
            `dateprocessed` DATETIME DEFAULT NULL,
            PRIMARY KEY  (`ID`)
            )";
            if (!$connection->query($queryCreateUsersTable)) {
                $this->p21->gwLog("Table creation failed: " . $dbConnection->errno . ") " . $dbConnection->error);
            }
        }
        try {
            $sqlExternal = "ALTER TABLE `sales_order_grid`   ADD COLUMN `ext_order_id` VARCHAR(255) NULL DEFAULT NULL COMMENT 'External Order ID'";
            if (!$connection->query($sqlExternal)) {
                $this->p21->gwLog("Ext_order_id add failed: " . $connection->errno . ") " . $connection->error);
            }
        } catch (\Exception $e) {
            $DBERROR = false;  //this will fail if it already exists, and that's ok
        }
        try {
            $querycheck = 'SELECT 1 FROM `gws_GreyWolfLog`';
            $query_result = $connection->query($querycheck);
            if ($query_result !== false) {
                $DBERROR = false;
            }
        } catch (\Exception $e) {
            $DBERROR = true;
        }

        if ($DBERROR == false) {
            // table exists, proceed
        } else {
            // table does not exist, create here.

            $queryCreateUsersTable = "CREATE TABLE IF NOT EXISTS `gws_GreyWolfLog` (
                      `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
                      `dateentered` datetime DEFAULT NULL,
                      `user` varchar(255) DEFAULT NULL,
                      `IP` varchar(255) DEFAULT NULL,
                      `LogType` varchar(255) DEFAULT NULL,
                      `LogData` varchar(255) DEFAULT NULL,
                      `LogType2` varchar(255) DEFAULT NULL,
                      `LogData2` varchar(235) DEFAULT NULL,
                      PRIMARY KEY (`ID`)
                    ) ";
            if (!$connection->query($queryCreateUsersTable)) {
                $this->p21->gwLog("Log table creation failed: " . $connection->errno . ") " . $connection->error);
            }
        }

        $setup->startSetup();
        $eavSetup =  $this->eavSetupFactory->create(['setup' => $setup]);
        
        $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            'upc',
            [
                'group' => 'General',
                'type' => 'varchar',
                'label' => 'UPC',
                'input' => 'text',
                'source' => '',
                'frontend' => '',
                'backend' => '',
                'required' => false,
                'sort_order' => 50,
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => true,
                'visible' => true,
                'is_user_defined' => true,
                'is_html_allowed_on_front' => false,
                'visible_on_front' => true
            ]
        );
         $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            'sales_uom',
            [
                'group' => 'General',
                'type' => 'varchar',
                'label' => 'UOM',
                'input' => 'text',
                'source' => '',
                'frontend' => '',
                'backend' => '',
                'required' => false,
                'sort_order' => 50,
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => true,
                'visible' => true,
                'is_user_defined' => true,
                'user_defined' => false,
                'is_html_allowed_on_front' => false,
                'unique' => false,
                'visible_on_front' => true
            ]
        );
        $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            'erp_item_id',
                [
                'group' => 'General',
                'type' => 'varchar',
                'label' => 'ERP Item ID',
                'input' => 'text',
                'source' => '',
                'frontend' => '',
                'backend' => '',
                'required' => false,
                'sort_order' => 51,
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => false,
                'is_filterable_in_grid' => true,
                'visible' => true,
                'is_user_defined' => true,
                'user_defined' => false,
                'is_html_allowed_on_front' => false,
                'unique' => false,
                'visible_on_front' => false
            ]
        );
         $setup->endSetup();

         $setup->startSetup();

        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);
        $customerEntity = $customerSetup->getEavConfig()->getEntityType(\Magento\Customer\Model\Customer::ENTITY);
        $attributeSetId = $customerEntity->getDefaultAttributeSetId();

        /** @var \Magento\Eav\Model\Entity\Attribute\Set $attributeSet */
        $attributeSet = $this->attributeSetFactory->create();
        $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

        $eavSetup->addAttribute(
            \Magento\Customer\Model\Customer::ENTITY,
            'erpshipvia',
            [
                'type'         => 'varchar',
                'label'        => 'P21 Ship Via',
                'input'        => 'text',
                'required'     => false,
                'visible'      => true,
                'user_defined' => false,
                'position'     => 998,
                'system'       => 0,
            ]
        );

        $eavSetup->addAttribute(
            \Magento\Customer\Model\Customer::ENTITY,
            'erpshipviadesc',
            [
                'type'         => 'varchar',
                'label'        => 'P21 Ship Via Desc',
                'input'        => 'text',
                'required'     => false,
                'visible'      => true,
                'user_defined' => false,
                'position'     => 999,
                'system'       => 0,
            ]
        );

        $erpshipvia = $this->eavConfig->getAttribute(\Magento\Customer\Model\Customer::ENTITY, 'erpshipvia');
        $erpshipvia->addData([
            'used_in_forms' => ['adminhtml_customer', 'customer_account_edit'],
            'attribute_set_id' => $attributeSetId,
            'attribute_group_id' => $attributeGroupId,
        ]);
        $erpshipvia->save();

        $erpshipviadesc = $this->eavConfig->getAttribute(\Magento\Customer\Model\Customer::ENTITY, 'erpshipviadesc');
        $erpshipviadesc->addData([
            'used_in_forms' => ['adminhtml_customer', 'customer_account_edit'],
            'attribute_set_id' => $attributeSetId,
            'attribute_group_id' => $attributeGroupId,
        ]);
        $erpshipviadesc->save();

        // insert attribute
        $customerSetup->addAttribute('customer_address', 'ERPAddressID',  [
            'label' => 'External Address ID',
            'type' => 'varchar',
            'input' => 'text',
            'position' => 45,
            'visible' => true,
            'required' => false,
            'system' => 0
        ]);

        $MyAttribute = $customerSetup->getEavConfig()->getAttribute('customer_address', 'ERPAddressID');
        $MyAttribute->setData(
            'used_in_forms',
            ['adminhtml_customer_address']
        );
        $MyAttribute->save();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();

        try {
            $query = "CREATE TABLE IF NOT EXISTS `gws_GreyWolfOrderFieldUpdate` (
                `ID` int(11) unsigned NOT NULL auto_increment,
                `orderid` varchar(255) NOT NULL default '',
                `ERPOrderNo` varchar(255) DEFAULT NULL,
                `ERPSuffix` varchar(255) DEFAULT NULL,
                `CCAuthNo` varchar(255) DEFAULT NULL,
                `dateentered` DATETIME DEFAULT NULL,
                `dateprocessed` DATETIME DEFAULT NULL,
                PRIMARY KEY (`ID`)
            );
            ALTER TABLE `gws_GreyWolfOrderFieldUpdate` ADD COLUMN IF NOT EXISTS `shipping_upcharge` DECIMAL(20,4) AFTER `CCAuthNo`;";

            $connection->multiQuery($query);
        } catch (\Exception $e) {
        }

        $setup->endSetup();

    }
}
