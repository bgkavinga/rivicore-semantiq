<?php

declare(strict_types=1);

namespace Rivicore\SemantiQ\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Setup\CategorySetupFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddProductExcludeAttribute implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CategorySetupFactory $categorySetupFactory
    ) {}

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();
        $setup = $this->categorySetupFactory->create(['setup' => $this->moduleDataSetup]);

        $setup->addAttribute(
            Product::ENTITY,
            'semantiq_exclude',
            [
                'group'                   => 'Search Engine Optimization',
                'type'                    => 'int',
                'backend'                 => '',
                'frontend'                => '',
                'input'                   => 'select',
                'source'                  => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,
                'label'                   => 'Exclude from SemantiQ Index',
                'global'                  => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible'                 => true,
                'required'                => false,
                'user_defined'            => false,
                'default'                 => '0',
                'searchable'              => false,
                'filterable'              => false,
                'comparable'              => false,
                'visible_on_front'        => false,
                'used_in_product_listing' => false,
                'unique'                  => false,
                'apply_to'                => '',
                'sort_order'              => 200,
            ]
        );

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
