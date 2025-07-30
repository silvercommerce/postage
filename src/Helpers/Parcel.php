<?php

namespace SilverCommerce\Postage\Helpers;

use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Core\Injector\Injectable;
use SilverCommerce\Postage\Model\PostageType;

/**
 * A parcel is a generic object that can be handed to the postage calculator
 * to find the best applicable shipping rate.
 *
 * NOTE Thanks to SilverShop for this idea!
 *
 */
class Parcel
{
    use Injectable;

    /**
     * Total width of the package
     *
     * @var int
     */
    protected $width  = 0;

    public function getWidth()
    {
        return $this->width;
    }

    public function setWidth($width)
    {
        $this->width = $width;
        return $this;
    }

    /**
     * Total height of the package
     *
     * @var int
     */
    protected $height = 0;

    public function getHeight()
    {
        return $this->height;
    }

    public function setHeight($height)
    {
        $this->height = $height;
        return $this;
    }

    /**
     * Total depth of the package
     *
     * @var int
     */
    protected $depth = 0;

    public function getDepth()
    {
        return $this->depth;
    }

    public function setDepth($depth)
    {
        $this->depth = $depth;
        return $this;
    }

    /**
     * Total weight of the package
     *
     * @var int
     */
    protected $weight = 0;

    public function getWeight()
    {
        return $this->weight;
    }

    public function setWeight($weight)
    {
        $this->weight = $weight;
        return $this;
    }

    /**
     * Total number of items in the package
     *
     * @var int
     */
    protected $items = 0;

    public function getItems()
    {
        return $this->items;
    }

    public function setItems($items)
    {
        $this->items = $items;
        return $this;
    }

    /**
     * The total monitary value of this parcel
     *
     * @var float
     */
    protected $value = 0;

    /**
     * Get the value of value
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Set the value of value
     *
     * @return  self
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * The country this parcel is to be delivered to
     *
     * Expects the ISO-3166 2 character country code
     *
     * @var string
     */
    protected $country = null;

    public function getCountry()
    {
        return $this->country;
    }

    public function setCountry($country)
    {
        $this->country = $country;
        return $this;
    }

    /**
     * The region witin a country this parcel is to be delivered to
     *
     * Expects the ISO-3166-2 3 character "subdivision" code
     *
     * @var string
     */
    protected $region = null;

    public function getRegion()
    {
        return $this->region;
    }

    public function setRegion($region)
    {
        $this->region = $region;
        return $this;
    }

    /**
     * Optional full address that this parcel needs to be delivered to
     *
     * @var string
     */
    protected $address = null;

    public function getAddress()
    {
        return $this->address;
    }

    public function setAddress(string $address)
    {
        $this->address = $address;
        return $this;
    }

    /**
     * Optional latitude of the address that this parcel needs to be delivered
     * to (useful for things like distance based shipping using geo-location)
     *
     * @var string
     */
    protected $latitude = null;

    public function getLatitude()
    {
        return $this->latitude;
    }

    public function setLatitude(string $latitude)
    {
        $this->latitude = $latitude;
        return $this;
    }

    /**
     * Optional longditude of the address that this parcel needs to be delivered
     * to (useful for things like distance based shipping using geo-location)
     *
     * @var string
     */
    protected $longditude = null;

    public function getLongditude()
    {
        return $this->longditude;
    }

    public function setLongditude(string $longditude)
    {
        $this->longditude = $longditude;
        return $this;
    }

    /**
     * Calculate total volume
     *
     * @var int
     */
    public function getVolume()
    {
        return $this->height * $this->width * $this->depth;
    }

    /**
     * Initialise this Package
     *
     * @param $country Set the country for this Parcel
     * @param $region Set the region for this parcel
     */
    public function __construct($country, $region)
    {
        $this->country = $country;
        $this->region = $region;
    }

    /**
     * Generate a list of availabe postage options, from the current
     * available Postage Types.
     *
     * @return ArrayList
     */
    public function getPostageOptions()
    {
        $config = SiteConfig::current_site_config();
        $types = $config->PostageTypes()->filter("Enabled", true);
        $options = ArrayList::create();

        foreach ($types as $type) {
            /** @var PostageType $type */
            $options->merge($type->getPossiblePostage($this));
        }

        return $options;
    }
}
