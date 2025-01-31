<?php

namespace SilverCommerce\Postage\Forms;

use Locale;
use SilverStripe\i18n\i18n;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Control\RequestHandler;
use SilverStripe\ORM\ValidationException;
use SilverCommerce\Postage\Helpers\Parcel;
use SilverCommerce\Postage\Helpers\PostageOption;
use SilverCommerce\GeoZones\Forms\RegionSelectionField;
use SilverCommerce\Postage\Extensions\PostageExtension;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\DataObject;

/**
 * Generate a form that lists available postage options and then saves them
 * to the provided object
 *
 */
class PostageForm extends Form
{
    const DEFAULT_NAME = "PostageForm";

    /**
     * The current country as a 2 character string
     *
     * @var string
     */
    protected $country;

    /**
     * The current region, in ISO-3166-2 format
     *
     * @var string
     */
    protected $region;

    /**
     * Total value to calculate postage against
     *
     * @var float
     */
    protected $value;

    /**
     * Total weight to calculate postage against
     *
     * @var float
     */
    protected $weight;

    /**
     * Total number of items to calculate postage against
     *
     * @var int
     */
    protected $items;

    /**
     * The current object to apply postage to
     *
     * @var DataObject
     */
    protected $object;

    /**
     * Should this form redirect back if no postage options are available?
     *
     * @var boolean
     */
    protected $back_on_no_options = true;

        /**
     * Get should this form redirect back if no postage options are available?
     *
     * @return boolean
     */
    public function getBackOnNoOptions()
    {
        return $this->back_on_no_options;
    }

    /**
     * Set should this form redirect back if no postage options are available?
     *
     * @param boolean $back_on_no_options
     * @return self
     */
    public function setBackOnNoOptions(bool $back_on_no_options)
    {
        $this->back_on_no_options = $back_on_no_options;

        return $this;
    }

    /**
     * Is the provided object suitable for use (does it exist and
     * does it implement PostageExtension)
     *
     * @return bool
     */
    protected function isValidObject($object)
    {
        if (empty($object)) {
            return false;
        };

        if (!in_array(PostageExtension::class, $object->config()->extensions)) {
            return false;
        }

        return true;
    }

    /**
     * Construct this form
     *
     * This form will generate dropdowns to select country and region, unless
     * these are provided via params (in which case these are converted to
     * hidden fields).
     *
     * @param RequestHandler $controller Optional parent request handler
     * @param string $name The method on the controller that will return this form object.
     * @param DataObject $object An object that implements PostageExtension
     * @param float $value Monitary value
     * @param float $weight Total weight
     * @param int $items Total number of items
     * @param string $country 2 character ISO-3166 country code (EG "GB")
     * @param string $region ISO-3166-2 subdivision/region code (EG "GLS")
     * @param bool $country_region_dropdown Show dropdown selections for country and region
     *
     * @throws ValidationException
     */
    public function __construct(
        RequestHandler $controller = null,
        string $name = self::DEFAULT_NAME,
        DataObject $object = null,
        int $value = 0,
        int $weight = 0,
        int $items = 0,
        string $country = null,
        string $region = null
    ) {
        if (!$this->isValidObject($object)) {
            throw new ValidationException("Your object must extend " . PostageExtension::class);
        }

        // Initial setup
        $this->setController($controller);
        $this->setName($name);

        $session = $this->getSession();
        $available_postage = null;
        $curr_postage = $object->getPostage();
        $add_dropdowns = false;

        $this->value = $value;
        $this->weight = $weight;
        $this->items = $items;
        $this->object = $object;

        if (empty($country) && empty($region)) {
            $add_dropdowns = true;
        }

        if (empty($country)) {
            $country = $session->get("Form.{$this->FormName()}.Country");
        }

        if (empty($region)) {
            $region = $session->get("Form.{$this->FormName()}.Region");
        }

        if (empty($country)) {
            $country = $this->getDefaultLocale();
        }

        if (isset($country)) {
            $this->country = $country;
            $session->set("Form.{$this->FormName()}.Country", $country);
        }

        if (isset($region)) {
            $this->region = $region;
            $session->set("Form.{$this->FormName()}.Region", $region);
        }

        if (isset($country) && isset($region)) {
            $available_postage = $this->getPossiblePostage();
        }

        $fields = FieldList::create();
        $actions = FieldList::create(
            FormAction::create(
                "doSetPostage",
                _t('SilverCommerce\Postage.Search', "Search")
            )->addExtraClass('btn btn-secondary')
        );
        $validator = RequiredFields::create();

        // If we have not pre-selected country and region, add
        // Validator dropdowns
        if ($add_dropdowns) {
            $fields->push(DropdownField::create(
                'Country',
                _t('SilverCommerce\Postage.Country', 'Country'),
                array_change_key_case(
                    i18n::getData()->getCountries(),
                    CASE_UPPER
                ),
                $this->getDefaultLocale()
            )->setForm($this));

            $fields->push(RegionSelectionField::create(
                "Region",
                _t('SilverCommerce\Postage.Region', "County/State"),
                "Country"
            )->setForm($this));

            $validator->addRequiredField("Country");
            $validator->addRequiredField("Region");
        }

        // If we have stipulated a search, then see if we have any results
        // otherwise load empty fieldsets
        if (isset($available_postage) && $available_postage->exists()) {
            // Loop through all postage areas and generate a new list
            $postage_array = [];

            foreach ($available_postage as $area) {
                $postage_array[$area->getKey()] = $area->getSummary();
            }

            $fields->add(OptionsetField::create(
                "PostageKey",
                _t('SilverCommerce\Postage.SelectPostage', "Select Postage"),
                $postage_array
            )->setForm($this));

            $actions
                ->dataFieldByName("action_doSetPostage")
                ->setTitle(_t('SilverCommerce\Postage.Update', "Update"));

            if (!$add_dropdowns) {
                $validator->addRequiredField("PostageKey");
            }
        } elseif (isset($available_postage)) {
            $fields->add(ReadonlyField::create(
                "NoPostage",
                "",
                _t(
                    'SilverCommerce\Postage.CannotPost',
                    'Unfortunatley we cannot post to this location'
                )
            )->addExtraClass("text-danger"));

            // If we do not have selection dropdowns, remove search action
            if (!$add_dropdowns) {
                $actions->removeByName("action_doSetPostage");
            }
        }

        // Setup form
        parent::__construct(
            $controller,
            $name,
            $fields,
            $actions,
            $validator
        );

        // Check if the form has been re-posted and load data
        $data = $session->get("Form.{$this->FormName()}.data");

        if (!is_array($data)) {
            $data = [];
        }
        
        // Set postage key from session
        if ($curr_postage instanceof PostageOption) {
            $data["PostageKey"] = $curr_postage->getKey();
        }

        // Set data to form
        $this->loadDataFrom($data);

        // Extension call
        $this->extend("updatePostageForm");
    }

    /**
     * Return the default locale of the site (as a 2 character code)
     *
     * @return string
     */
    protected function getDefaultLocale()
    {
        return strtoupper(Locale::getRegion(i18n::get_locale()));
    }

    /**
     * Create a parcel from the current data
     *
     * @return Parcel
     */
    protected function getParcel()
    {
        $parcel = Parcel::create(
            $this->country,
            $this->region
        );

        $parcel
            ->setValue($this->value)
            ->setWeight($this->weight)
            ->setItems($this->items);
        
        return $parcel;
    }

    /**
     * Get postage that is available based on the country and region submitted
     *
     * @param $country 2 character country code
     * @param $region 3 character region/subdivision code
     * @return ArrayList
     */
    protected function getPossiblePostage()
    {
        $parcel = $this->getParcel();
        $postage_areas = $parcel->getPostageOptions();

        $this->extend('updatePossiblePostage', $postage_areas);

        return $postage_areas;
    }

    /**
     * Method that deals with get postage details and setting the
     * postage
     *
     * @param $data
     */
    public function doSetPostage($data)
    {
        $this->extend("beforeSetPostage", $data);

        $session = $this->getSession();

        if (array_key_exists("Country", $data)) {
            $this->country = $data["Country"];
        } else {
            $this->country = $session->get("Form.{$this->FormName()}.Country");
        }

        if (array_key_exists("Region", $data)) {
            $this->region = $data["Region"];
        } else {
            $this->region = $session->get("Form.{$this->FormName()}.Region");
        }

        // If no country or region data was submitted, return an error
        if (empty($this->country) || empty($this->region)) {
            $this->sessionMessage(_t(
                "SilverCommerce\Postage.InvalidCountryRegion",
                "Invalid country or region"
            ));

            return $this
                ->getController()
                ->redirectBack();
        }

        $session->set("Form.{$this->FormName()}.Country", $this->country);
        $session->set("Form.{$this->FormName()}.Region", $this->region);

        $areas = $this->getPossiblePostage();
        $postage = null;

        // If no postage areas are available and we want to redirect back,
        // do so now.
        if (!$areas->exists() && $this->getBackOnNoOptions()) {
            $session->set("Form.{$this->FormName()}.data", $data);
            $this->object->clearPostage();
            $this->object->write();

            return $this->getController()->redirectBack();
        }

        // Check that postage is set, if not, see if we can set a default
        if (array_key_exists("PostageKey", $data) && $data["PostageKey"]) {
            // First is the current postage ID in the list of postage areas
            foreach ($areas as $area) {
                if ($data["PostageKey"] == $area->getKey()) {
                    $postage = $area;
                }
            }
        }
        
        // If current postage is not set and areas are available, then default
        // to using the first area
        if (!$postage && $areas->exists()) {
            $postage = $areas->first();
            $data["PostageKey"] = $postage->getKey();
        }

        if ($postage) {
            $this->object->setPostage($postage);
            $this->object->write();
        }

        // Set the form pre-populate data before redirecting
        $session->set("Form.{$this->FormName()}.data", $data);

        $this->extend("afterSetPostage", $data);
        
        if (array_key_exists("CompleteURL", $data)) {
            $url = $data["CompleteURL"];
        } else {
            $url = Controller::join_links(
                $this->getController()->Link(),
                "#{$this->FormName()}"
            );
        }

        return $this
            ->getController()
            ->redirect($url);
    }

    /**
     * Get the current object to apply postage to
     *
     * @return  DataObject
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * Set the current object to apply postage to
     *
     * @param  DataObject  $object  The current object to apply postage to
     *
     * @return  self
     */
    public function setObject(DataObject $object)
    {
        $this->object = $object;

        return $this;
    }
}
