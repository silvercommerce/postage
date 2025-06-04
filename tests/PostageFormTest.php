<?php

namespace SilverCommerce\Postage\Tests;

use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\OptionsetField;
use SilverCommerce\GeoZones\Model\Zone;
use SilverStripe\SiteConfig\SiteConfig;
use SilverCommerce\GeoZones\Model\Region;
use SilverCommerce\Postage\Forms\PostageForm;
use SilverCommerce\GeoZones\Forms\RegionSelectionField;
use SilverCommerce\Postage\Tests\Model\ExtendableObject;
use SilverCommerce\Postage\Tests\Control\PostageController;
use SilverCommerce\Postage\Helpers\Parcel;
use SilverCommerce\Postage\Helpers\PostageOption;

class PostageFormTest extends FunctionalTest
{
    protected static $fixture_file = 'PostageFormTest.yml';

    protected static $extra_dataobjects = [
        ExtendableObject::class
    ];

    public function setUp(): void
    {
        parent::setUp();
        Config::inst()->set(Region::class, "create_on_build", false);
        Director::config()->set(
            "rules",
            [
                'postagetest//$Action/$ID/$OtherID' => PostageController::class
            ]
        );
    }

    /**
     * Test that the form generates dropdowns when no country provided
     */
    public function testDropdowns()
    {
        $object = $this->objFromFixture(ExtendableObject::class, "uk");

        $form = PostageForm::create(
            Controller::curr(),
            "PostageForm",
            $object,
            $object->SubTotal,
            $object->TotalWeight,
            $object->TotalItems
        );

        $this->assertInstanceOf(
            DropdownField::class,
            $form->Fields()->dataFieldByName("Country")
        );

        $this->assertInstanceOf(
            RegionSelectionField::class,
            $form->Fields()->dataFieldByName("Region")
        );
    }

    /**
     * Test that the form generates a correct list of postage
     */
    public function testFixed()
    {
        $object = $this->objFromFixture(ExtendableObject::class, "uk");

        $form = PostageForm::create(
            Controller::curr(),
            "PostageForm",
            $object,
            $object->SubTotal,
            $object->TotalWeight,
            $object->TotalItems,
            $object->DeliveryCountry,
            $object->DeliveryCounty
        );

        $this->assertInstanceOf(
            OptionsetField::class,
            $form->Fields()->dataFieldByName("PostageKey")
        );
    }


    /**
     * Test that the form generates a no postage error
     */
    public function testNoPostage()
    {
        $object = $this->objFromFixture(ExtendableObject::class, "us");

        $form = PostageForm::create(
            Controller::curr(),
            "PostageForm",
            $object,
            $object->SubTotal,
            $object->TotalWeight,
            $object->TotalItems,
            $object->DeliveryCountry,
            $object->DeliveryCounty
        );

        $field = $form->Fields()->dataFieldByName("NoPostage");

        $this->assertInstanceOf(ReadonlyField::class, $field);
        $this->assertEquals(
            "Unfortunatley we cannot post to this location",
            $field->Value()
        );
    }

    /**
     * Test that the form updated the object with the new postage
     *
     */
    public function testObjectChanged()
    {
        $object = $this->objFromFixture(ExtendableObject::class, "uk");
        $id = $object->ID;
        $page = $this->get('postagetest/index/' . $id);
        $postage_key = $object->getPostage()->getKey();

        // Page should load..
        $this->assertEquals(200, $page->getStatusCode());

        // Submit the postage form
        $result = $this->submitForm(
            "PostageForm_PostageForm",
            null,
            ["PostageKey" => $postage_key]
        );

        // Reload the page and check result
        $page = $this->get('postagetest/index/' . $id);

        // Check the rendered key
        $this->assertExactMatchBySelector("p.postage-key", [
            $postage_key
        ]);
    }
}
