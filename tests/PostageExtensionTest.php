<?php

namespace SilverCommerce\Postage\Tests;

use SilverStripe\i18n\i18n;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Security;
use SilverCommerce\TaxAdmin\Model\TaxRate;
use SilverCommerce\Postage\Helpers\PostageOption;
use SilverCommerce\Postage\Tests\Model\ExtendableObject;
use SilverStripe\ORM\FieldType\DBCurrency;
use SilverStripe\Core\Config\Config;

/**
 * Test functionality of postage extension
 *
 */
class PostageExtensionTest extends SapphireTest
{

    protected static $fixture_file = 'PostageExtensionTest.yml';

    protected static $extra_dataobjects = [
        ExtendableObject::class
    ];

    public function setUp(): void
    {
        parent::setUp();
        
        // Setup default locale
        i18n::set_locale("en_GB");
        $member = Security::getCurrentUser();
        $member->Locale = "en_GB";

        // Set default currency
        Config::inst()->set(DBCurrency::class, "currency_symbol", "£");
    }

    protected function createPostageOption()
    {
        $tax = $this->objFromFixture(TaxRate::class, "vat");

        return PostageOption::create(
            "Postage",
            10,
            $tax
        );
    }

    /**
     * Test possible postage results for flat rate shipping
     */
    public function testSetGetPostage()
    {
        $postage = $this->createPostageOption();
        $obj = $this->objFromFixture(ExtendableObject::class, "test");
        $obj->setPostage($postage);

        $this->assertInstanceOf(PostageOption::class, $obj->getPostage());
        $this->assertEquals($postage, $obj->getPostage());
    }

    /**
     * Test that clearing postage emptys relevent data
     */
    public function testClearPostage()
    {
        $obj = $this->objFromFixture(ExtendableObject::class, "test");
        $obj->clearPostage();

        $this->assertEquals("", $obj->PostageTitle);
        $this->assertEquals(0, $obj->PostagePrice);
        $this->assertEquals(null, $obj->PostageTaxID);
    }

    /**
     * Test the postage details are correctly rendered
     */
    public function testPostageDetails()
    {
        $postage = $this->createPostageOption();
        $obj = $this->objFromFixture(ExtendableObject::class, "test");
        $obj->setPostage($postage);

        $this->assertEquals("Postage (£12.00)", $obj->PostageDetails);
    }

    /**
     * Test the postage total is correct
     */
    public function testPostageTotal()
    {
        $postage = $this->createPostageOption();
        $obj = $this->objFromFixture(ExtendableObject::class, "test");
        $obj->setPostage($postage);

        $this->assertEquals(12.0, $obj->PostageTotal);
    }

    /**
     * Test the postage tax price is correct
     */
    public function testPostageTaxPrice()
    {
        $postage = $this->createPostageOption();
        $obj = $this->objFromFixture(ExtendableObject::class, "test");
        $obj->setPostage($postage);

        $this->assertEquals(2.0, $obj->PostageTaxPrice);
    }
}
