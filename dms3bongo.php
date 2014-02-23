<?php

/**
 * @author Marc Queralt <marc@demomentsomtres.com>
 */
if (!defined('_PS_VERSION_'))
    exit;

// Module Dir
define('DMS3_BONGO_MODULE_DIR', dirname(__FILE__));

// WSDL
define('DMS3_BONGO_WSDL', 'https://api.bongous.com/services/v4?wsdl');

// Option names
define('DMS3_BONGO_CHECKOUT_URL', 'dms3bongo-checkoutUrl');
define('DMS3_BONGO_PARTNER_KEY', 'dms3bongo-partnerKey');
define('DMS3_BONGO_LANGUAGE', 'dms3bongo-language');
define('DMS3_BONGO_CONTINUE_SHOPPING_MESSAGE', 'dms3bongo-continueShopping');
define('DMS3_BONGO_DOMESTIC_SHIPPING_CALCULATION', 'dms3bongo-shippingCostCalc');
define('DMS3_BONGO_DOMESTIC_SHIPPING_CALCULATION_FREE', 'free');
define('DMS3_BONGO_DOMESTIC_SHIPPING_CALCULATION_DEFAULT', 'psDefault');
define('DMS3_BONGO_DOMESTIC_SHIPPING_PROVIDER', 'dms3bongo-shippingCarrierId');
define('DMS3_BONGO_DEFAULT_MANUFACTURE_COUNTRY', 'dms3bongo-manufactureCountryId');
define('DMS3_BONGO_TRANSFER_TYPE', 'dms3bongo-transferType');
define('DMS3_BONGO_TRANSFER_TYPE_INTERNATIONAL_CHECKOUT_BUTTON', 'checkoutButton');
define('DMS3_BONGO_TRANSFER_TYPE_AUTO_REDIRECT', 'autoRedirect');
define('DMS3_BONGO_TRANSFER_TYPE_INTERNATIONAL_AND_REDIRECT', 'checkoutButtonAndAutoRedirect');
define('DMS3_BONGO_DC_ADDRESS_1', 'dms3bongo-dc-address');
define('DMS3_BONGO_DC_ADDRESS_2', 'dms3bongo-dc-address2');
define('DMS3_BONGO_DC_ADDRESS_CITY', 'dms3bongo-dc-city');
define('DMS3_BONGO_DC_ADDRESS_REGION', 'dms3bongo-dc-region');
define('DMS3_BONGO_DC_ADDRESS_ZIPCODE', 'dms3bongo-dc-zipCode');
define('DMS3_BONGO_DC_ADDRESS_COUNTRY', 'dms3bongo-dc-countryId');
define('DMS3_BONGO_PS_CALLBACK_URL', 'dms3bongo-ps-callbackUrl');
define('DMS3_BONGO_PS_CONTINUE_SHOPPING_URL', 'dms3bongo-ps-continueShoppingUrl');
define('DMS3_BONGO_CRON_SECUREKEY', 'dms3bongo-cron-key');
define('DMS3_BONGO_CRON_URL', 'dms3bongo-cron-url');
define('DMS3_BONGO_UNIT_FACTOR', 'dms3bongo-unit-factor');
define('DMS3_BONGO_WEIGHT_FACTOR', 'dms3bongo-weight-factor');
define('DMS3_BONGO_CSV_DELIMITER', ',');

/**
 * dms3bongo class
 * @since 1.0
 */
class DMS3Bongo extends PaymentModule {

    private $_html = '';
    private $_postErrors = array();

    /**
     * @since 1.0
     */
    function __construct() {
        $this->name = 'dms3bongo';
        $this->tab = 'payments_gateways';
        $this->version = '0.1';
        $this->author = 'DeMomentSomTres';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = array(
            'min' => '1.5',
            'max' => '1.6.99',
        );
        $this->currencies = true;
        $this->currencies_mode = 'radio';
        
        parent::__construct();

        $this->page = basename(__FILE__, '.php');
        $this->displayName = $this->l('Bongo Checkout');
        $this->description = $this->l('Manages shipping and payment using Bongo Checkout');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    /**
     * @since 1.0
     * @return boolean install status
     */
    public function install() {
        if (Shop::isFeatureActive())
            Shop::setContext(Shop::CONTEXT_ALL);
        if (parent::install() == false ||
                $this->createBongoPaymentTable() == false ||
                //$this->registerHook('actionAdminControllerSetMedia') == false || // As we dont need to addd js neither css to the tab
                $this->registerHook('actionProductUpdate') == false ||
                $this->registerHook('displayAdminProductsExtra') == false ||
                $this->registerHook('invoice') == false ||
                $this->registerHook('payment') == false ||
                $this->registerHook('paymentReturn') == false)
            return false;
        return true;
    }

    /**
     * @since 1.0
     * @return boolean uninstall status
     */
    public function uninstall() {
        if (parent::uninstall() == false || $this->destroyBongoPaymentTable() == false)
            return false;
        return true;
    }

    /**
     * @since 1.0
     * @return string
     */
    public function getContent() {
        $output = null;
        $error = false;
        if (Tools::isSubmit('submit' . $this->name)):
            $checkOutUrl = Tools::getValue(DMS3_BONGO_CHECKOUT_URL);
            $partnerKey = Tools::getValue(DMS3_BONGO_PARTNER_KEY);
            $language = Tools::getValue(DMS3_BONGO_LANGUAGE);
            $key = Tools::getValue(DMS3_BONGO_CRON_SECUREKEY);
            $factor = Tools::getValue(DMS3_BONGO_UNIT_FACTOR);
            $wfactor = Tools::getValue(DMS3_BONGO_WEIGHT_FACTOR);
            $messageContinue = Tools::getValue(DMS3_BONGO_CONTINUE_SHOPPING_MESSAGE);
            $shippingCalculation = Tools::getValue(DMS3_BONGO_DOMESTIC_SHIPPING_CALCULATION);
            $carrier = Tools::getValue(DMS3_BONGO_DOMESTIC_SHIPPING_PROVIDER);
            $manufactureCountry = Tools::getValue(DMS3_BONGO_DEFAULT_MANUFACTURE_COUNTRY);
            $transferType = Tools::getValue(DMS3_BONGO_TRANSFER_TYPE);
            $address1 = Tools::getValue(DMS3_BONGO_DC_ADDRESS_1);
            $address2 = Tools::getValue(DMS3_BONGO_DC_ADDRESS_2);
            $city = Tools::getValue(DMS3_BONGO_DC_ADDRESS_CITY);
            $region = Tools::getValue(DMS3_BONGO_DC_ADDRESS_REGION);
            $zipCode = Tools::getValue(DMS3_BONGO_DC_ADDRESS_ZIPCODE);
            $country = Tools::getValue(DMS3_BONGO_DC_ADDRESS_COUNTRY);
            if (!$checkOutUrl || empty($checkOutUrl)):
                $error = true;
                $output .= $this->displayError($this->l('Checkout URL is required. Please contact Bongo International to get yours.'));
            endif;
            if (!$partnerKey || empty($partnerKey)):
                $error = true;
                $output .= $this->displayError($this->l('Invalid Partner Key. Please contact Bongo International to get yours.'));
            endif;
            if (!$language || empty($language)):
                $language = 'en';
            endif;
            if (!$key || empty($key)):
                $key = substr(md5(date()), 2, 23);
            endif;
            if (!$factor || empty($factor)):
                $factor = 1;
            endif;
            if (!$wfactor || empty($wfactor)):
                $wfactor = 1;
            endif;
            if (!$shippingCalculation):
                $error = true;
                $output .= $this->displayError($this->l('Select a shipping cost calculation method'));
            endif;
            if (!$carrier || $carrier == 0):
                $error = true;
                $output .= $this->displayError($this->l('Select a valid carrier to send orders to Bongo DC'));
            endif;
            if (!$manufactureCountry || empty($manufactureCountry)):
                $error = true;
                $output .= $this->displayError($this->l('Select a valid country as the default manufacturer country'));
            endif;
            if (!$transferType):
                $error = true;
                $output .= $this->displayError($this->l('Select a transfer type'));
            endif;
            if (!$address1 || empty($address1) || !$city || empty($city) || !$region || empty($region) || !$zipCode || empty($zipCode) || !$country || empty($country)):
                $error = true;
                $output .= $this->displayError($this->l('Some fields of Bongo DC address are missing'));
            endif;
            if (!$error):
                Configuration::updateValue(DMS3_BONGO_CHECKOUT_URL, $checkOutUrl);
                Configuration::updateValue(DMS3_BONGO_PARTNER_KEY, $partnerKey);
                Configuration::updateValue(DMS3_BONGO_LANGUAGE, $language);
                Configuration::updateValue(DMS3_BONGO_CRON_SECUREKEY, $key);
                Configuration::updateValue(DMS3_BONGO_UNIT_FACTOR, $factor);
                Configuration::updateValue(DMS3_BONGO_WEIGHT_FACTOR, $wfactor);
                Configuration::updateValue(DMS3_BONGO_CONTINUE_SHOPPING_MESSAGE, $messageContinue);
                Configuration::updateValue(DMS3_BONGO_DOMESTIC_SHIPPING_CALCULATION, $shippingCalculation);
                Configuration::updateValue(DMS3_BONGO_DOMESTIC_SHIPPING_PROVIDER, $carrier);
                Configuration::updateValue(DMS3_BONGO_DEFAULT_MANUFACTURE_COUNTRY, $manufactureCountry);
                Configuration::updateValue(DMS3_BONGO_TRANSFER_TYPE, $transferType);
                Configuration::updateValue(DMS3_BONGO_DC_ADDRESS_1, $address1);
                Configuration::updateValue(DMS3_BONGO_DC_ADDRESS_2, $address2);
                Configuration::updateValue(DMS3_BONGO_DC_ADDRESS_CITY, $city);
                Configuration::updateValue(DMS3_BONGO_DC_ADDRESS_ZIPCODE, $zipCode);
                Configuration::updateValue(DMS3_BONGO_DC_ADDRESS_REGION, $region);
                Configuration::updateValue(DMS3_BONGO_DC_ADDRESS_COUNTRY, $country);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            endif;
        endif;
        return $output . $this->displayForm();
    }

    /**
     * @since 1.0
     * @return string
     */
    public function displayForm() {

        // Get default language
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        // Get Carriers
        $carriers = Carrier::getCarriers($default_lang, true);
        $carriers = array_merge(
                array(array(
                'id_carrier' => 0,
                'name' => $this->l('-- Please select a carrier --'),
            )), $carriers
        );

        // Get Countries
        $countries = Country::getCountries($default_lang);
        $countries = array_merge(
                array(array(
                'id_country' => 0,
                'name' => $this->l('-- Please select a country --'),
            )), $countries
        );

        // Init fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('General Settings'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Bongo Checkout URL'),
                    'name' => DMS3_BONGO_CHECKOUT_URL,
                    'desc' => $this->l('URL provided by Bongo International'),
                    'size' => 150,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Prestashop Callback URL'),
                    'name' => DMS3_BONGO_PS_CALLBACK_URL,
                    'desc' => $this->l('Copy the content and paste it to configure your account at Bongo International Partners website'),
                    'size' => 150,
                    'required' => false,
//                    'disabled' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Prestashop continue shopping URL'),
                    'name' => DMS3_BONGO_PS_CONTINUE_SHOPPING_URL,
                    'desc' => $this->l('Copy the content and paste it to configure your account at Bongo International Partners website'),
                    'size' => 150,
                    'required' => false,
//                    'disabled' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Bongo Partner Key'),
                    'name' => DMS3_BONGO_PARTNER_KEY,
                    'desc' => $this->l('The partner key provided by Bongo International'),
                    'size' => 50,
                    'required' => true
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Language'),
                    'desc' => $this->l('Language in the internal communications with Bongo International'),
                    'name' => DMS3_BONGO_LANGUAGE,
                    'options' => array(
                        'query' => array(
                            array(
                                'lang_id' => 'en',
                                'name' => $this->l('English')
                            ),
                            array(
                                'lang_id' => 'es',
                                'name' => $this->l('Spanish')
                            ),
                        ),
                        'id' => 'lang_id',
                        'name' => 'name'
                    ),
                    'required' => true
                ),
            ),
        );
        $fields_form[1]['form'] = array(
            'legend' => array(
                'title' => $this->l('Product Management'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Cron Secure Key'),
                    'desc' => $this->l('Grants the authorized user access of cron and csv files') . '<br/>'
                    . $this->l('If the field is left empty a new secure random key will be generated on save.'),
                    'name' => DMS3_BONGO_CRON_SECUREKEY,
                    'size' => 50,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Cron URL'),
                    'desc' => $this->l('Setup a cron job based calling this sentence in order to be able to sync products with Bongo International'),
                    'name' => DMS3_BONGO_CRON_URL,
                    'size' => 100,
//                    'disabled' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Inches per unit'),
                    'desc' => $this->l('Length unit to inches conversion factor'),
                    'name' => DMS3_BONGO_UNIT_FACTOR,
                    'size' => 10
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Pounds per unit'),
                    'desc' => $this->l('Weigth unit to pounds conversion factor'),
                    'name' => DMS3_BONGO_WEIGHT_FACTOR,
                    'size' => 10
                )
            ),
        );
        $fields_form[2]['form'] = array(
            'legend' => array(
                'title' => $this->l('Order Management'),
            ),
            'input' => array(
                array(
                    'type' => 'textarea',
                    'label' => $this->l('Continue shopping message'),
                    'name' => DMS3_BONGO_CONTINUE_SHOPPING_MESSAGE,
                    'desc' => $this->l('Message to be shown after the user clicks on continue shopping in Bongo International'),
                    'rows' => 5,
                    'cols' => 100,
                    'required' => false
                ),
                array(
                    'type' => 'radio',
                    'label' => $this->l('Bongo domestic shipping calculation'),
                    'name' => DMS3_BONGO_DOMESTIC_SHIPPING_CALCULATION,
                    'desc' => $this->l('Defines the way shipping to Bongo is calculated.') . '<br/><b>' .
                    $this->l('Free') . '</b>: ' .
                    $this->l('No domestic shipping cost will ever be added to any order') . '<br/><b>' .
                    $this->l('Shipping Carrier - Method') . '</b>: ' .
                    $this->l('The shipping carrier/method selected on Prestashop will be used to calculate a real-time domestic shipping cost.'),
                    'required' => true,
                    'class' => 't',
                    'values' => array(
                        array(
                            'id' => 'free',
                            'value' => DMS3_BONGO_DOMESTIC_SHIPPING_CALCULATION_FREE,
                            'label' => $this->l('Free')
                        ),
                        array(
                            'id' => 'default',
                            'value' => DMS3_BONGO_DOMESTIC_SHIPPING_CALCULATION_DEFAULT,
                            'label' => $this->l('Shipping Carrier - Method')
                        ),
                    )
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Bongo domestic shipping carrier'),
                    'desc' => $this->l('The carrier that ships to Bongo International DC'),
                    'name' => DMS3_BONGO_DOMESTIC_SHIPPING_PROVIDER,
                    'options' => array(
                        'query' => $carriers,
                        'id' => 'id_carrier',
                        'name' => 'name'
                    ),
                    'required' => true
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Default manufacture country'),
                    'name' => DMS3_BONGO_DEFAULT_MANUFACTURE_COUNTRY,
                    'desc' => $this->l('Manufacture country if no country is identified at product level'),
                    'options' => array(
                        'query' => $countries,
                        'id' => 'iso_code',
                        'name' => 'name'
                    ),
                    'required' => true
                ),
                array(
                    'type' => 'radio',
                    'label' => $this->l('Bongo transfer type'),
                    'name' => DMS3_BONGO_TRANSFER_TYPE,
                    'desc' => '<b>' . $this->l('International Checkout Button') . '</b>: '
                    . $this->l('A button will be displayed below the regular Checkout button in the customer\'s shopping cart that will take them to Bongo to enter their billing/shipping details and complete payment.')
                    . '<br/><b>' . $this->l('Auto Redirect') . '</b>: '
                    . $this->l('If you are using the Prestashop one-page checkout, the customer will be automatically redirected to Bongo to complete payment after entering their billing/shipping detais if the country they choose is selected as an allowed countries for the selected carrier.') . '<br/><br/>'
                    . $this->l('Please note that Bongo does not support checking out with multiple address at this time.'),
                    'required' => true,
                    'class' => 't',
                    'values' => array(
                        array(
                            'id' => 'international',
                            'value' => DMS3_BONGO_TRANSFER_TYPE_INTERNATIONAL_CHECKOUT_BUTTON,
                            'label' => $this->l('International Checkout Button')
                        ),
                        array(
                            'id' => 'auto',
                            'value' => DMS3_BONGO_TRANSFER_TYPE_AUTO_REDIRECT,
                            'label' => $this->l('Auto Redirect')
                        ),
                        array(
                            'id' => 'international-and-redirect',
                            'value' => DMS3_BONGO_TRANSFER_TYPE_INTERNATIONAL_AND_REDIRECT,
                            'label' => $this->l('International Checkout Button and Auto Redirect')
                        )
                    )
                )
            )
        );
        $fields_form[3]['form'] = array(
            'legend' => array(
                'title' => $this->l('Bongo DC Address'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Address 1'),
                    'name' => DMS3_BONGO_DC_ADDRESS_1,
                    'desc' => $this->l('The address of Bongo DC'),
                    'size' => 150,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Address 2'),
                    'name' => DMS3_BONGO_DC_ADDRESS_2,
                    'desc' => $this->l('Complementary address of Bongo DC'),
                    'size' => 150,
                    'required' => false
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('City'),
                    'name' => DMS3_BONGO_DC_ADDRESS_CITY,
                    'desc' => $this->l('City where Bongo DC is placed'),
                    'size' => 150,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Zip Code'),
                    'name' => DMS3_BONGO_DC_ADDRESS_ZIPCODE,
                    'desc' => $this->l('The Zip Code'),
                    'size' => 10,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('State/Region'),
                    'name' => DMS3_BONGO_DC_ADDRESS_REGION,
                    'desc' => $this->l('State, Province or region'),
                    'size' => 50,
                    'required' => true
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Country'),
                    'name' => DMS3_BONGO_DC_ADDRESS_COUNTRY,
                    'desc' => $this->l('Country where Bongo DC is based'),
                    'options' => array(
                        'query' => $countries,
                        'id' => 'iso_code',
                        'name' => 'name'
                    ),
                    'required' => true
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'button'
            )
        );

        $helper = new HelperForm();

        // Module, token and current index
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and Toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' => array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ),
            'export-all' => array(
                'desc' => $this->l('Export'),
                'href' => $this->ExportCsvURL(),
            ),
            'back' => array(
                'desc' => $this->l('Back to list'),
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules')
            )
        );

        // Load current values
        $helper->fields_value[DMS3_BONGO_CHECKOUT_URL] = Configuration::get(DMS3_BONGO_CHECKOUT_URL);
        $helper->fields_value[DMS3_BONGO_PARTNER_KEY] = Configuration::get(DMS3_BONGO_PARTNER_KEY);
        $helper->fields_value[DMS3_BONGO_LANGUAGE] = Configuration::get(DMS3_BONGO_LANGUAGE);
        $helper->fields_value[DMS3_BONGO_CRON_SECUREKEY] = Configuration::get(DMS3_BONGO_CRON_SECUREKEY);
        $helper->fields_value[DMS3_BONGO_CRON_URL] = $this->prestashopCronFileURL();
        $helper->fields_value[DMS3_BONGO_UNIT_FACTOR] = Configuration::get(DMS3_BONGO_UNIT_FACTOR);
        $helper->fields_value[DMS3_BONGO_WEIGHT_FACTOR] = Configuration::get(DMS3_BONGO_WEIGHT_FACTOR);
        $helper->fields_value[DMS3_BONGO_CONTINUE_SHOPPING_MESSAGE] = Configuration::get(DMS3_BONGO_CONTINUE_SHOPPING_MESSAGE);
        $helper->fields_value[DMS3_BONGO_DOMESTIC_SHIPPING_CALCULATION] = Configuration::get(DMS3_BONGO_DOMESTIC_SHIPPING_CALCULATION);
        $helper->fields_value[DMS3_BONGO_DOMESTIC_SHIPPING_PROVIDER] = Configuration::get(DMS3_BONGO_DOMESTIC_SHIPPING_PROVIDER);
        $helper->fields_value[DMS3_BONGO_DEFAULT_MANUFACTURE_COUNTRY] = Configuration::get(DMS3_BONGO_DEFAULT_MANUFACTURE_COUNTRY);
        $helper->fields_value[DMS3_BONGO_TRANSFER_TYPE] = Configuration::get(DMS3_BONGO_TRANSFER_TYPE);
        $helper->fields_value[DMS3_BONGO_DC_ADDRESS_1] = Configuration::get(DMS3_BONGO_DC_ADDRESS_1);
        $helper->fields_value[DMS3_BONGO_DC_ADDRESS_2] = Configuration::get(DMS3_BONGO_DC_ADDRESS_2);
        $helper->fields_value[DMS3_BONGO_DC_ADDRESS_CITY] = Configuration::get(DMS3_BONGO_DC_ADDRESS_CITY);
        $helper->fields_value[DMS3_BONGO_DC_ADDRESS_ZIPCODE] = Configuration::get(DMS3_BONGO_DC_ADDRESS_ZIPCODE);
        $helper->fields_value[DMS3_BONGO_DC_ADDRESS_REGION] = Configuration::get(DMS3_BONGO_DC_ADDRESS_REGION);
        $helper->fields_value[DMS3_BONGO_DC_ADDRESS_COUNTRY] = Configuration::get(DMS3_BONGO_DC_ADDRESS_COUNTRY);
        $helper->fields_value[DMS3_BONGO_PS_CALLBACK_URL] = $this->prestashopCallbackURL();
        $helper->fields_value[DMS3_BONGO_PS_CONTINUE_SHOPPING_URL] = $this->prestashopContinueShoppingURL();
        return $helper->generateForm($fields_form);
    }

    /**
     * Returns the prestashop callback url for this install
     * @since 1.0
     * @return string
     * @TODO acabar-ho
     */
    function prestashopCallbackURL() {
        return 'This will show the Callback URL';
    }

    /**
     * Returns the prestashop continue shopping url for this install
     * @since 1.0
     * @return string
     * @TODO acabar-ho
     */
    function prestashopContinueShoppingURL() {
        return 'This will show the Continue Shopping URL';
    }

    /**
     * Returns the prestashop cron file url
     * @since 1.0
     * @return string
     * @TODO acabar-ho
     */
    function prestashopCronFileURL() {
        return _PS_BASE_URL_ . _MODULE_DIR_ . 'dms3bongo/cron.php'
                . '?secure_key='
                . Configuration::get(DMS3_BONGO_CRON_SECUREKEY);
    }

    /**
     * Returns the URL to call CSV export
     * @since 1.0
     * @return string
     */
    function exportCsvURL() {
        return _PS_BASE_URL_ . _MODULE_DIR_ . '/dms3bongo/csv.php'
                . '?secure_key='
                . Configuration::get(DMS3_BONGO_CRON_SECUREKEY);
    }

    /**
     * Returns a super admin employee
     * @since 1.0
     * @return Employee
     */
    public static function getFirstSuperAdmin() {
        $employees = Employee::getEmployeesByProfile(1, true);
        return $employees[0];
    }

    /**
     * Generates a CSV file containing all products new or changed products
     * marking that their state in dms3bongo files
     * @since 1.0
     */
    public static function exportCSV() {

        header('Content-type: text/csv');
        header('Content-Type: application/force-download; charset=UTF-8');
        header('Cache-Control: no-store, no-cache');
        header('Content-disposition: attachment; filename="DMS3-products.csv"');

        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        // Context Init
        $context = Context::getContext();
        $context->employee = self::getFirstSuperAdmin();

        // Get the products
        $products = Product::getProducts($default_lang, 0, 9999999, 'id_product', 'ASC');

        // Extend products
        $products = Product::getProductsProperties($default_lang, $products);

        $language = Configuration::get(DMS3_BONGO_LANGUAGE);
        $originCountry = Configuration::get(DMS3_BONGO_DEFAULT_MANUFACTURE_COUNTRY);
        $factor = strval(Configuration::get(DMS3_BONGO_UNIT_FACTOR));
        $wfactor = strval(Configuration::get(DMS3_BONGO_WEIGHT_FACTOR));


        $output = 'language,productId,productDescription,url,imageUrl,price,originCountry,hsCode,ECCN,haz,licenseFlag,importFlag,productType,L1,W1,H1,WT1,L2,W2,H2,WT2,L3,W3,H3,WT3,L4,W4,H4,WT4' . "\n";
        foreach ($products as $p):
            $output .= $language . DMS3_BONGO_CSV_DELIMITER
                    . $p['id_product'] . DMS3_BONGO_CSV_DELIMITER
                    . $p['name'] . DMS3_BONGO_CSV_DELIMITER                                     // Optional - Description
                    . /* $p->getLink() . */ DMS3_BONGO_CSV_DELIMITER                            // Optional - URL
                    . DMS3_BONGO_CSV_DELIMITER                                                  // Optional - Image URL
                    . $p['price'] . DMS3_BONGO_CSV_DELIMITER                                    // Price
                    . $originCountry . DMS3_BONGO_CSV_DELIMITER                                 // Country of origin
                    . DMS3_BONGO_CSV_DELIMITER                                                  // hsCode
                    . DMS3_BONGO_CSV_DELIMITER                                                  // ECCN
                    . DMS3_BONGO_CSV_DELIMITER                                                  // haz
                    . DMS3_BONGO_CSV_DELIMITER                                                  // licenseFlag
                    . DMS3_BONGO_CSV_DELIMITER                                                  // importFlag
                    . DMS3_BONGO_CSV_DELIMITER                                                  // productType
                    . strval($p['height']) * $factor . DMS3_BONGO_CSV_DELIMITER                 // length
                    . strval($p['width']) * $factor . DMS3_BONGO_CSV_DELIMITER                  // width
                    . strval($p['depth']) * $factor . DMS3_BONGO_CSV_DELIMITER                  // height
                    . strval($p['weight']) * $wfactor . DMS3_BONGO_CSV_DELIMITER                // weight
                    . DMS3_BONGO_CSV_DELIMITER
                    . DMS3_BONGO_CSV_DELIMITER
                    . DMS3_BONGO_CSV_DELIMITER
                    . DMS3_BONGO_CSV_DELIMITER
                    . DMS3_BONGO_CSV_DELIMITER
                    . DMS3_BONGO_CSV_DELIMITER
                    . DMS3_BONGO_CSV_DELIMITER
                    . DMS3_BONGO_CSV_DELIMITER
                    . DMS3_BONGO_CSV_DELIMITER
                    . DMS3_BONGO_CSV_DELIMITER
                    . DMS3_BONGO_CSV_DELIMITER
                    . DMS3_BONGO_CSV_DELIMITER
                    . "\n";
        endforeach;

        echo $output;
    }

    public static function ProductsCheck($debug = false) {
        include_once './classes/ProductDMS3BongoSync.php';

        // Get the products
        $productIds = self::productsToCheck();

        $partnerKey = Configuration::get(DMS3_BONGO_PARTNER_KEY);
        $language = Configuration::get(DMS3_BONGO_LANGUAGE);

        $items = array();
        foreach ($productIds as $id):
            $items[] = array(
                'productID' => $id
            );
        endforeach;

        $webClient = new SoapClient(DMS3_BONGO_WSDL);
        $request = (object) array(
                    'partnerKey' => $partnerKey,
                    'language' => $language,
                    'items' => $items,
        );
        try {
            $response = $webClient->connectSkuStatus($request);
        } catch (SoapFault $e) {
            echo 'Error:' . "\n";
            echo $e->getMessage();
            exit;
        }

        if ($response->error == 0):
            if ($debug):
                echo '<pre>';
                print_r($response);
                echo '</pre>';
            endif;
            foreach ($response->items as $p):
                $ps = new ProductDMS3BongoSync($p->productID);
                $ps->id_product = $p->productID;
                if (isset($ps->statusCode)):
                    $ps->previousStatus = $ps->statusCode;
                endif;
                // Update only if there are changes
                if ($ps->statusCode != $p->productStatus):
                    $ps->statusCode = $p->productStatus;
                    $ps->save();
                endif;
            endforeach;
            echo sizeof($productIds) . ' Products Checked with Bongo International' . "\n";
        else:
            echo 'Error:<br/>';
            print_r($response);
        endif;
    }

    public static function ProductsSend($debug = false) {
        include_once './classes/ProductDMS3BongoSync.php';

        // Context Init
        $context = Context::getContext();
        $context->employee = self::getFirstSuperAdmin();

        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        // Get the products
        $products = self::productsToSync();

        // Extend products
        $products = Product::getProductsProperties($default_lang, $products);

        $partnerKey = Configuration::get(DMS3_BONGO_PARTNER_KEY);
        $language = Configuration::get(DMS3_BONGO_LANGUAGE);
        $originCountry = Configuration::get(DMS3_BONGO_DEFAULT_MANUFACTURE_COUNTRY);
        $factor = strval(Configuration::get(DMS3_BONGO_UNIT_FACTOR));
        $wfactor = strval(Configuration::get(DMS3_BONGO_WEIGHT_FACTOR));

        $items = array();
        foreach ($products as $p):
            $items[] = array(
                'productID' => $p['id'],
                'description' => $p['name'][$default_lang],
                'url' => null,
                'imageUrl' => null,
                'price' => $p['price'],
                'countryOfOrigin' => $originCountry,
                'hsCode' => null,
                'eccn' => null,
                'hazFlag' => 0,
                'licenseFlag' => null,
                'importFlag' => null,
                'productType' => null,
                'itemInformation' => array(
                    'l' => $p['depth'] * $factor,
                    'w' => $p['width'] * $factor,
                    'h' => $p['height'] * $factor,
                    'wt' => $p['weight'] * $wfactor
                ),
            );
        endforeach;

        $webClient = new SoapClient(DMS3_BONGO_WSDL);
        $request = (object) array(
                    'partnerKey' => $partnerKey,
                    'language' => $language,
                    'items' => $items,
        );
        try {
            $response = $webClient->connectProductInfo($request);
        } catch (SoapFault $e) {
            echo 'Error:' . "\n";
            echo $e->getMessage();
            exit;
        }
        if ($response->error == 0):
            foreach ($products as $p):
                $ps = new ProductDMS3BongoSync($p['id']);
                $ps->id_product = $p['id'];
                if (isset($ps->statusCode)):
                    $ps->previousStatus = $ps->statusCode;
                endif;
                $ps->statusCode = ProductDMS3BongoSync::STATUS_UNPROCESSED;
                $ps->save();
            endforeach;
            if ($debug):
                echo '<pre>';
                print_r($response);
                echo '</pre>';
            endif;
            echo sizeof($products) . ' Products Sent to Bongo International' . "\n";
        else:
            echo 'Error:<br/>';
            print_r($response);
        endif;
    }

    /**
     * Gets the product id that should be synched to Bongo because they are new or have been updated
     * @since 1.0
     * @return array
     */
    static function productsToSync() {
        $result = array();
        $db = Db::getInstance();
        $query = 'SELECT p.id_product FROM `' . _DB_PREFIX_ . 'product` p LEFT JOIN `'
                . _DB_PREFIX_ . 'dms3bongo_product` d ON p.id_product = d.id_product'
                . ' WHERE d.id_product IS NULL'
                . ' OR d.statusCode = 0'
                . ' OR d.date_upd < p.date_upd';
        //echo $query;exit;
        $rows = $db->ExecuteS($query);
        foreach ($rows as $row):
            $p = new Product($row['id_product']);
            $result[] = array_merge($row, get_object_vars($p));
        endforeach;
        return $result;
    }

    /**
     * Gets the product id's whose status at Bongo should be checked
     * @since 1.0
     * @return array
     */
    static function productsToCheck() {
        $result = array();
        $db = Db::getInstance();
        $query = 'SELECT d.id_product FROM `' . _DB_PREFIX_ . 'dms3bongo_product` d'
                . ' WHERE d.statusCode = ' . ProductDMS3BongoSync::STATUS_UNPROCESSED;
        $rows = $db->ExecuteS($query);
        foreach ($rows as $row):
            $result[] = $row['id_product'];
        endforeach;
        return $result;
    }

    /**
     * 
     * @param type $params
     * @return type
     */
    public function hookDisplayAdminProductsExtra($params) {
        include_once dirname(__FILE__) . '/classes/ProductDMS3BongoSync.php';

        if (Validate::isLoadedObject($product = new Product((int) Tools::getValue('id_product')))):
            $ps = new ProductDMS3BongoSync($product->id);
            $this->context->smarty->assign(array(
                'dms3bongo_currentStatus' => $ps->statusCode,
                'dms3bongo_previousStatus' => $ps->previousStatus,
                'dms3bongo_lastUpdated' => $ps->date_upd
            ));
            return $this->display(__FILE__, '/views/templates/admin/dms3bongoProduct.tpl');
        endif;
    }

    /**
     * 
     * @param type $params
     */
    public function hookActionProductUpdate($params) {
        include_once dirname(__FILE__) . '/classes/ProductDMS3BongoSync.php';

        $id_product = (int) Tools::getValue('id_product');
        $currentStatus = (int) Tools::getValue('dms3bongo_currentStatus');
        $oldCurrentStatus = (int) Tools::getValue('dms3bongo_oldCurrentStatus');

        if ($currentStatus != $oldCurrentStatus):
            $ps = new ProductDMS3BongoSync($id_product);
            $ps->statusCode = $currentStatus;
            $ps->previousStatus = $oldCurrentStatus;
            $ps->save();
        endif;
    }
    
    public function hookPayment($params) {
        global $smarty;
        
        if(!$this->active):
            return;
        endif;
        
        $smarty->assign(array(
            'dms3bongo_path' => $this->_path,
            'dms3bongo_path_ssl' => Configuration::get('PS_FO_PROTOCOL').$_SERVER['HTTP_HOST'].__PS_BASE_URI__."modules/{$this->name}/"
        ));
            
       return $this->display(__FILE__,'payment.tpl');
    }

    /**
     * Creates de dms3bongo database table
     * since @1.0
     */
    function createBongoPaymentTable() {
        $db = Db::getInstance();
        $query = 'CREATE TABLE `' . _DB_PREFIX_ . 'dms3bongo_payment` (
`id_payment` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
`id_order` INT NOT NULL,
`error`INT,
`error_message` VARCHAR(255),
`tracking_link` VARCHAR(255)
) ENGINE ' . _MYSQL_ENGINE_;
        $query2 = 'CREATE TABLE `' . _DB_PREFIX_ . 'dms3bongo_product` (
`id_product`INT(10) NOT NULL PRIMARY KEY,
`statusCode` INT,
`previousStatus` INT,
`date_add` DATETIME,
`date_upd`DATETIME
) ENGINE ' . _MYSQL_ENGINE_;
        ;
        return $db->Execute($query) && $db->Execute($query2);
    }

    /**
     * Creates all the OrderStates corresponding to Bongo Checkout
     */
    public function createOrderStates() {
//        if (!Configuration::get(DMS3_BONGO_OS_SENT_TO_BONGO)) {
//            $orderState = new OrderState();
//            $orderState->name = array();
//
//            foreach (Language::getLanguages() as $language) {
//                if (Tools::strtolower($language['iso_code']) == 'es')
//                    $orderState->name[$language['id_lang']] = 'Enviado a Bongo - pendiente de respuesta';
//                else
//                    $orderState->name[$language['id_lang']] = 'Sent to Bongo - waiting confirmation';
//            }
//
//            $orderState->send_email = false;
//            $orderState->color = '#DDEEFF';
//            $orderState->hidden = false;
//            $orderState->delivery = false;
//            $orderState->logable = true;
//            $orderState->invoice = true;
//
//            if ($orderState->add()) {
//                $source = dirname(__FILE__) . '/../../img/os/' . Configuration::get('PS_OS_PAYPAL') . '.gif';
//                $destination = dirname(__FILE__) . '/../../img/os/' . (int) $orderState->id . '.gif';
//                copy($source, $destination);
//            }
//            Configuration::updateValue('PAYPAL_OS_AUTHORIZATION', (int) $orderState->id);
//        }
    }

    /**
     * Destroys de dms3bongo database table
     * since @1.0
     */
    function destroyBongoPaymentTable() {
        $db = Db::getInstance();
        $query = 'DROP TABLE `' . _DB_PREFIX_ . 'dms3bongo_payment`';
        $query2 = 'DROP TABLE `' . _DB_PREFIX_ . 'dms3bongo_product`';
        return $db->Execute($query) && $db->Execute($query2);
    }

}

?>