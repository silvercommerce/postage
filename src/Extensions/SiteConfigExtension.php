<?php

namespace SilverCommerce\Postage\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;
use SilverCommerce\Postage\Model\PostageType;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;

/**
 * Add postage areas to config
 */
class SiteConfigExtension extends DataExtension
{
    private static $has_many = [
        'PostageTypes' => PostageType::class
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $postage_config = GridFieldConfig_RelationEditor::create();
        $postage_config
            ->removeComponentsByType(GridFieldAddExistingAutocompleter::class)
            ->removeComponentsByType(GridFieldDeleteAction::class)
            ->removeComponentsByType(GridFieldAddNewButton::class)
            ->addComponent(new GridFieldAddNewMultiClass("buttons-before-left"));

        $fields->addFieldsToTab(
            "Root.Shop",
            [
                ToggleCompositeField::create(
                    'PostageSettings',
                    _t("SilverCommerce\Postage.PostageSettings", "Postage Settings"),
                    [
                        LiteralField::create("DiscountPadding", "<br/>"),
                        GridField::create(
                            'PostageTypes',
                            '',
                            $this->owner->PostageTypes()
                        )->setConfig($postage_config)
                    ]
                )
            ]
        );
    }
}
