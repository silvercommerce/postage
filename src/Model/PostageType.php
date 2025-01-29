<?php

namespace SilverCommerce\Postage\Model;

use ReflectionClass;
use SilverStripe\ORM\DataObject;
use SilverCommerce\GeoZones\Model\Zone;
use SilverStripe\SiteConfig\SiteConfig;
use SilverCommerce\Postage\Helpers\Parcel;
use SilverCommerce\TaxAdmin\Model\TaxCategory;
use SilverCommerce\Postage\Tasks\PostageUpgradeTask;
use SilverStripe\Forms\FormField;

/**
 * Postage Types are a base class for creating differnt types of postage.
 *
 * Custom postage types need to provide their own implementation of `getPossiblePostage`.
 * This is the method that will be called when trying to determine a list of
 * possible postage options for the current order.
 *
 */
class PostageType extends DataObject
{
    private static $table_name = 'PostageType';

    private static $db = [
        "Name" => "Varchar",
        "Enabled" => "Boolean"
    ];

    private static $has_one = [
        "Tax" => TaxCategory::class,
        "Site" => SiteConfig::class
    ];

    private static $many_many = [
        "Locations" => Zone::class,
        "Exclusions" => Zone::class
    ];

    private static $casting = [
        'ShortClassName' => 'Varchar'
    ];

    private static $summary_fields = [
        'ShortClassName',
        'Name',
        'Enabled'
    ];

    private static $field_labels = [
        'ShortClassName' => 'Type'
    ];

    public function getTitle()
    {
        return $this->Name;
    }

    /**
     * Get an unqualified verson of this object's classname
     *
     * @return string
     */
    public function getShortClassName()
    {
        $reflect = new ReflectionClass($this);
        return FormField::name_to_label($reflect->getShortName());
    }

    /**
     * Return a list of possible postage options that can be rendered into the postage
     * form.
     *
     * NOTE Even if you have one option, you need to return a list, containing one item.
     *
     * The list can be any implementation of an SSList
     *
     * @param Parcel
     * @return SSList
     */
    public function getPossiblePostage(Parcel $parcel)
    {
        user_error("You must implement your own 'getPossiblePostage' method");
    }

    /**
     * {@inheritdoc}
     */
    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        if (PostageUpgradeTask::config()->run_during_dev_build) {
            $task = new PostageUpgradeTask();
            $task->up();
        }
    }

    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function($fields) {
            /** @var FormField */
            $site_field = $fields->dataFieldByName('SiteID');

            if (!empty($site_field)) {
                $site_field
                    ->setReadonly(true)
                    ->performReadonlyTransformation();
            }
        });

        return parent::getCMSFields();
    }
}
