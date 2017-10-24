<?php
/**
 * 2007-2016 PrestaShop
 *
 * Thirty Bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017 Thirty Bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    Thirty Bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017 Thirty Bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

use LoyaltyModule\LoyaltyModule;
use LoyaltyModule\LoyaltyStateModule;

if (!defined('_TB_VERSION_')) {
    exit;
}

require_once __DIR__.'/vendor/autoload.php';

/**
 * Class Loyalty
 */
class Loyalty extends Module
{
    protected $html = '';

    public $loyaltyStateDefault;
    public $loyaltyStateValidation;
    public $loyaltyStateCancel;
    public $loyaltyStateConvert;
    public $loyaltyStateNoneAward;

    /**
     * Loyalty constructor.
     */
    public function __construct()
    {
        $this->name = 'loyalty';
        $this->tab = 'pricing_promotion';
        $this->version = '2.0.2';
        $this->author = 'thirty bees';
        $this->need_instance = 0;

        $this->controllers = ['default'];

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Customer loyalty and rewards');
        $this->description = $this->l('Provide a loyalty program to your customers.');
        $this->confirmUninstall = $this->l('Are you sure you want to delete all loyalty points and customer history?');
    }

    /**
     * Install this module
     *
     * @return bool
     */
    public function install()
    {
        if (!parent::install() || !$this->installDB() || !$this->registerHook('extraRight') || !$this->registerHook('updateOrderStatus')
            || !$this->registerHook('newOrder') || !$this->registerHook('adminCustomers') || !$this->registerHook('shoppingCart')
            || !$this->registerHook('orderReturn') || !$this->registerHook('cancelProduct') || !$this->registerHook('customerAccount')
            || !Configuration::updateValue('PS_LOYALTY_POINT_VALUE', '0.20') || !Configuration::updateValue('PS_LOYALTY_MINIMAL', 0)
            || !Configuration::updateValue('PS_LOYALTY_POINT_RATE', '10') || !Configuration::updateValue('PS_LOYALTY_NONE_AWARD', '1')
            || !Configuration::updateValue('PS_LOYALTY_TAX', '0') || !Configuration::updateValue('PS_LOYALTY_VALIDITY_PERIOD', '0')
        ) {
            return false;
        }

        $defaultTranslations = ['en' => 'Loyalty reward', 'fr' => 'Récompense fidélité'];
        $conf = [(int) Configuration::get('PS_LANG_DEFAULT') => $this->l('Loyalty reward')];
        foreach (Language::getLanguages() as $language) {
            if (isset($defaultTranslations[$language['iso_code']])) {
                $conf[(int) $language['id_lang']] = $defaultTranslations[$language['iso_code']];
            }
        }
        Configuration::updateValue('PS_LOYALTY_VOUCHER_DETAILS', $conf);

        $categoryConfig = '';
        $categories = Category::getSimpleCategories((int) Configuration::get('PS_LANG_DEFAULT'));
        foreach ($categories as $category) {
            $categoryConfig .= (int) $category['id_category'].',';
        }
        $categoryConfig = rtrim($categoryConfig, ',');
        Configuration::updateValue('PS_LOYALTY_VOUCHER_CATEGORY', $categoryConfig);

        /* This hook is optional */
        $this->registerHook('displayMyAccountBlock');
        if (!LoyaltyStateModule::insertDefaultData()) {
            return false;
        }

        return true;
    }

    /**
     * Install DB stuff
     *
     * @return bool
     */
    public function installDB()
    {
        Db::getInstance()->execute(
            '
        CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'loyalty` (
            `id_loyalty`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_loyalty_state` INT UNSIGNED NOT NULL DEFAULT 1,
            `id_customer`      INT UNSIGNED NOT NULL,
            `id_order`         INT UNSIGNED DEFAULT NULL,
            `id_cart_rule`     INT UNSIGNED DEFAULT NULL,
            `points`           INT NOT NULL DEFAULT 0,
            `date_add`         DATETIME NOT NULL,
            `date_upd`         DATETIME NOT NULL,
            PRIMARY KEY (`id_loyalty`),
            INDEX index_loyalty_loyalty_state (`id_loyalty_state`),
            INDEX index_loyalty_order (`id_order`),
            INDEX index_loyalty_discount (`id_cart_rule`),
            INDEX index_loyalty_customer (`id_customer`)
        ) DEFAULT CHARSET=utf8mb4 ;'
        );

        Db::getInstance()->execute(
            '
        CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'loyalty_history` (
            `id_loyalty_history` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_loyalty` INT UNSIGNED DEFAULT NULL,
            `id_loyalty_state` INT UNSIGNED NOT NULL DEFAULT 1,
            `points` INT NOT NULL DEFAULT 0,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_loyalty_history`),
            INDEX `index_loyalty_history_loyalty` (`id_loyalty`),
            INDEX `index_loyalty_history_loyalty_state` (`id_loyalty_state`)
        ) DEFAULT CHARSET=utf8mb4;'
        );

        Db::getInstance()->execute(
            '
        CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'loyalty_state` (
            `id_loyalty_state` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_order_state` INT UNSIGNED DEFAULT NULL,
            PRIMARY KEY (`id_loyalty_state`),
            INDEX index_loyalty_state_order_state (`id_order_state`)
        ) DEFAULT CHARSET=utf8mb4;'
        );

        Db::getInstance()->execute(
            '
        CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'loyalty_state_lang` (
            `id_loyalty_state` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_lang` INT UNSIGNED NOT NULL,
            `name` varchar(64) NOT NULL,
            UNIQUE KEY `index_unique_loyalty_state_lang` (`id_loyalty_state`,`id_lang`)
        ) DEFAULT CHARSET=utf8mb4;'
        );

        return true;
    }

    /**
     * Uninstall this module
     *
     * @return bool
     */
    public function uninstall()
    {
        if (!parent::uninstall() || !$this->uninstallDB() || !Configuration::deleteByName('PS_LOYALTY_POINT_VALUE') || !Configuration::deleteByName('PS_LOYALTY_POINT_RATE')
            || !Configuration::deleteByName('PS_LOYALTY_NONE_AWARD') || !Configuration::deleteByName('PS_LOYALTY_MINIMAL') || !Configuration::deleteByName('PS_LOYALTY_VOUCHER_CATEGORY')
            || !Configuration::deleteByName('PS_LOYALTY_VOUCHER_DETAILS') || !Configuration::deleteByName('PS_LOYALTY_TAX') || !Configuration::deleteByName('PS_LOYALTY_VALIDITY_PERIOD')
        ) {
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function uninstallDB()
    {
        Db::getInstance()->execute('DROP TABLE `'._DB_PREFIX_.'loyalty`;');
        Db::getInstance()->execute('DROP TABLE `'._DB_PREFIX_.'loyalty_state`;');
        Db::getInstance()->execute('DROP TABLE `'._DB_PREFIX_.'loyalty_state_lang`;');
        Db::getInstance()->execute('DROP TABLE `'._DB_PREFIX_.'loyalty_history`;');

        return true;
    }

    /**
     * Module configuration page
     *
     * @return string
     */
    public function getContent()
    {
        $this->instanceDefaultStates();
        $this->_postProcess();

        $this->html .= $this->renderForm();

        return $this->html;
    }

    private function instanceDefaultStates()
    {
        /* Recover default loyalty status save at module installation */
        $this->loyaltyStateDefault = new LoyaltyStateModule(LoyaltyStateModule::getDefaultId());
        $this->loyaltyStateValidation = new LoyaltyStateModule(LoyaltyStateModule::getValidationId());
        $this->loyaltyStateCancel = new LoyaltyStateModule(LoyaltyStateModule::getCancelId());
        $this->loyaltyStateConvert = new LoyaltyStateModule(LoyaltyStateModule::getConvertId());
        $this->loyaltyStateNoneAward = new LoyaltyStateModule(LoyaltyStateModule::getNoneAwardId());
    }

    private function _postProcess()
    {
        if (Tools::isSubmit('submitLoyalty')) {
            $idLangDefault = (int) Configuration::get('PS_LANG_DEFAULT');
            $languages = Language::getLanguages();

            $this->_errors = [];
            if (!is_array(Tools::getValue('categoryBox')) || !count(Tools::getValue('categoryBox'))) {
                $this->_errors[] = $this->l('You must choose at least one category for voucher\'s action');
            }
            if (!count($this->_errors)) {
                Configuration::updateValue('PS_LOYALTY_VOUCHER_CATEGORY', $this->voucherCategories(Tools::getValue('categoryBox')));
                Configuration::updateValue('PS_LOYALTY_POINT_VALUE', (float) (Tools::getValue('point_value')));
                Configuration::updateValue('PS_LOYALTY_POINT_RATE', (float) (Tools::getValue('point_rate')));
                Configuration::updateValue('PS_LOYALTY_NONE_AWARD', (int) (Tools::getValue('PS_LOYALTY_NONE_AWARD')));
                Configuration::updateValue('PS_LOYALTY_MINIMAL', (float) (Tools::getValue('minimal')));
                Configuration::updateValue('PS_LOYALTY_TAX', (int) (Tools::getValue('PS_LOYALTY_TAX')));
                Configuration::updateValue('PS_LOYALTY_VALIDITY_PERIOD', (int) (Tools::getValue('validity_period')));

                $this->loyaltyStateValidation->id_order_state = (int) (Tools::getValue('id_order_state_validation'));
                $this->loyaltyStateCancel->id_order_state = (int) (Tools::getValue('id_order_state_cancel'));

                $arrayVoucherDetails = [];
                foreach ($languages as $language) {
                    $arrayVoucherDetails[(int) ($language['id_lang'])] = Tools::getValue('voucher_details_'.(int) ($language['id_lang']));
                    $this->loyaltyStateDefault->name[(int) ($language['id_lang'])] = Tools::getValue('default_loyalty_state_'.(int) ($language['id_lang']));
                    $this->loyaltyStateValidation->name[(int) ($language['id_lang'])] = Tools::getValue('validation_loyalty_state_'.(int) ($language['id_lang']));
                    $this->loyaltyStateCancel->name[(int) ($language['id_lang'])] = Tools::getValue('cancel_loyalty_state_'.(int) ($language['id_lang']));
                    $this->loyaltyStateConvert->name[(int) ($language['id_lang'])] = Tools::getValue('convert_loyalty_state_'.(int) ($language['id_lang']));
                    $this->loyaltyStateNoneAward->name[(int) ($language['id_lang'])] = Tools::getValue('none_award_loyalty_state_'.(int) ($language['id_lang']));
                }
                if (empty($arrayVoucherDetails[$idLangDefault])) {
                    $arrayVoucherDetails[$idLangDefault] = ' ';
                }
                Configuration::updateValue('PS_LOYALTY_VOUCHER_DETAILS', $arrayVoucherDetails);

                if (empty($this->loyaltyStateDefault->name[$idLangDefault])) {
                    $this->loyaltyStateDefault->name[$idLangDefault] = ' ';
                }
                $this->loyaltyStateDefault->save();

                if (empty($this->loyaltyStateValidation->name[$idLangDefault])) {
                    $this->loyaltyStateValidation->name[$idLangDefault] = ' ';
                }
                $this->loyaltyStateValidation->save();

                if (empty($this->loyaltyStateCancel->name[$idLangDefault])) {
                    $this->loyaltyStateCancel->name[$idLangDefault] = ' ';
                }
                $this->loyaltyStateCancel->save();

                if (empty($this->loyaltyStateConvert->name[$idLangDefault])) {
                    $this->loyaltyStateConvert->name[$idLangDefault] = ' ';
                }
                $this->loyaltyStateConvert->save();

                if (empty($this->loyaltyStateNoneAward->name[$idLangDefault])) {
                    $this->loyaltyStateNoneAward->name[$idLangDefault] = ' ';
                }
                $this->loyaltyStateNoneAward->save();

                $this->html .= $this->displayConfirmation($this->l('Settings updated.'));
            } else {
                $errors = '';
                foreach ($this->_errors as $error) {
                    $errors .= $error.'<br />';
                }
                $this->html .= $this->displayError($errors);
            }
        }
    }

    private function voucherCategories($categories)
    {
        $cat = '';
        if ($categories && is_array($categories)) {
            foreach ($categories as $category) {
                $cat .= $category.',';
            }
        }

        return rtrim($cat, ',');
    }

    /* Hook display on product detail */

    public function renderForm()
    {
        $orderStates = OrderState::getOrderStates($this->context->language->id);
        $currency = new Currency((int) (Configuration::get('PS_CURRENCY_DEFAULT')));

        $rootCategory = Category::getRootCategory();
        $rootCategory = ['id_category' => $rootCategory->id, 'name' => $rootCategory->name];

        if (Tools::getValue('categoryBox')) {
            $selectedCategories = Tools::getValue('categoryBox');
        } else {
            $selectedCategories = explode(',', Configuration::get('PS_LOYALTY_VOUCHER_CATEGORY'));
        }

        $fieldsForm1 = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon'  => 'icon-cogs',
                ],
                'input'  => [
                    [
                        'type'   => 'text',
                        'label'  => $this->l('Ratio'),
                        'name'   => 'point_rate',
                        'prefix' => $currency->sign,
                        'suffix' => $this->l('= 1 reward point.'),
                    ],
                    [
                        'type'   => 'text',
                        'label'  => $this->l('1 point ='),
                        'name'   => 'point_value',
                        'prefix' => $currency->sign,
                        'suffix' => $this->l('for the discount.'),
                    ],
                    [
                        'type'   => 'text',
                        'label'  => $this->l('Validity period of a point'),
                        'name'   => 'validity_period',
                        'suffix' => $this->l('days'),
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Voucher details'),
                        'name'  => 'voucher_details',
                        'lang'  => true,
                    ],
                    [
                        'type'   => 'text',
                        'label'  => $this->l('Minimum amount in which the voucher can be used'),
                        'name'   => 'minimal',
                        'prefix' => $currency->sign,
                        'class'  => 'fixed-width-sm',
                    ],
                    [
                        'type'    => 'switch',
                        'is_bool' => true, //retro-compat
                        'label'   => $this->l('Apply taxes on the voucher'),
                        'name'    => 'PS_LOYALTY_TAX',
                        'values'  => [
                            [
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type'    => 'select',
                        'label'   => $this->l('Points are awarded when the order is'),
                        'name'    => 'id_order_state_validation',
                        'options' => [
                            'query' => $orderStates,
                            'id'    => 'id_order_state',
                            'name'  => 'name',
                        ],
                    ],
                    [
                        'type'    => 'select',
                        'label'   => $this->l('Points are cancelled when the order is'),
                        'name'    => 'id_order_state_cancel',
                        'options' => [
                            'query' => $orderStates,
                            'id'    => 'id_order_state',
                            'name'  => 'name',
                        ],
                    ],
                    [
                        'type'    => 'switch',
                        'is_bool' => true, //retro-compat
                        'label'   => $this->l('Give points on discounted products'),
                        'name'    => 'PS_LOYALTY_NONE_AWARD',
                        'values'  => [
                            [
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type'   => 'categories',
                        'label'  => $this->l('Vouchers created by the loyalty system can be used in the following categories:'),
                        'name'   => 'categoryBox',
                        'desc'   => $this->l('Mark the boxes of categories in which loyalty vouchers can be used.'),
                        'tree'   => [
                            'use_search'          => false,
                            'id'                  => 'categoryBox',
                            'use_checkbox'        => true,
                            'selected_categories' => $selectedCategories,
                        ],
                        //retro compat 1.5 for category tree
                        'values' => [
                            'trads'               => [
                                'Root'         => $rootCategory,
                                'selected'     => $this->l('Selected'),
                                'Collapse All' => $this->l('Collapse All'),
                                'Expand All'   => $this->l('Expand All'),
                                'Check All'    => $this->l('Check All'),
                                'Uncheck All'  => $this->l('Uncheck All'),
                            ],
                            'selected_cat'        => $selectedCategories,
                            'input_name'          => 'categoryBox[]',
                            'use_radio'           => false,
                            'use_search'          => false,
                            'disabled_categories' => [],
                            'top_category'        => Category::getTopCategory(),
                            'use_context'         => true,
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        $fieldsForm2 = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Loyalty points progression'),
                    'icon'  => 'icon-cogs',
                ],
                'input'  => [
                    [
                        'type'  => 'text',
                        'label' => $this->l('Initial'),
                        'name'  => 'default_loyalty_state',
                        'lang'  => true,
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Unavailable'),
                        'name'  => 'none_award_loyalty_state',
                        'lang'  => true,
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Converted'),
                        'name'  => 'convert_loyalty_state',
                        'lang'  => true,
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Validation'),
                        'name'  => 'validation_loyalty_state',
                        'lang'  => true,
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Cancelled'),
                        'name'  => 'cancel_loyalty_state',
                        'lang'  => true,
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitLoyalty';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        ];

        return $helper->generateForm([$fieldsForm1, $fieldsForm2]);
    }

    /* Hook display on customer account page */

    public function getConfigFieldsValues()
    {
        $fieldsValues = [
            'point_rate'                => Tools::getValue('PS_LOYALTY_POINT_RATE', Configuration::get('PS_LOYALTY_POINT_RATE')),
            'point_value'               => Tools::getValue('PS_LOYALTY_POINT_VALUE', Configuration::get('PS_LOYALTY_POINT_VALUE')),
            'PS_LOYALTY_NONE_AWARD'     => Tools::getValue('PS_LOYALTY_NONE_AWARD', Configuration::get('PS_LOYALTY_NONE_AWARD')),
            'minimal'                   => Tools::getValue('PS_LOYALTY_MINIMAL', Configuration::get('PS_LOYALTY_MINIMAL')),
            'validity_period'           => Tools::getValue('PS_LOYALTY_VALIDITY_PERIOD', Configuration::get('PS_LOYALTY_VALIDITY_PERIOD')),
            'id_order_state_validation' => Tools::getValue('id_order_state_validation', $this->loyaltyStateValidation->id_order_state),
            'id_order_state_cancel'     => Tools::getValue('id_order_state_cancel', $this->loyaltyStateCancel->id_order_state),
            'PS_LOYALTY_TAX'            => Tools::getValue('PS_LOYALTY_TAX', Configuration::get('PS_LOYALTY_TAX')),
        ];

        $languages = Language::getLanguages(false);

        foreach ($languages as $lang) {
            $fieldsValues['voucher_details'][$lang['id_lang']] = Tools::getValue('voucher_details_'.(int) $lang['id_lang'], Configuration::get('PS_LOYALTY_VOUCHER_DETAILS', (int) $lang['id_lang']));
            $fieldsValues['default_loyalty_state'][$lang['id_lang']] = Tools::getValue('default_loyalty_state_'.(int) $lang['id_lang'], $this->loyaltyStateDefault->name[(int) ($lang['id_lang'])]);
            $fieldsValues['validation_loyalty_state'][$lang['id_lang']] = Tools::getValue('validation_loyalty_state_'.(int) $lang['id_lang'], $this->loyaltyStateValidation->name[(int) ($lang['id_lang'])]);
            $fieldsValues['cancel_loyalty_state'][$lang['id_lang']] = Tools::getValue('cancel_loyalty_state_'.(int) $lang['id_lang'], $this->loyaltyStateCancel->name[(int) ($lang['id_lang'])]);
            $fieldsValues['convert_loyalty_state'][$lang['id_lang']] = Tools::getValue('convert_loyalty_state_'.(int) $lang['id_lang'], $this->loyaltyStateConvert->name[(int) ($lang['id_lang'])]);
            $fieldsValues['none_award_loyalty_state'][$lang['id_lang']] = Tools::getValue('none_award_loyalty_state_'.(int) $lang['id_lang'], $this->loyaltyStateNoneAward->name[(int) ($lang['id_lang'])]);
        }

        return $fieldsValues;
    }

    public function hookExtraRight($params)
    {
        $product = new Product((int) Tools::getValue('id_product'));
        if (Validate::isLoadedObject($product)) {
            if (Validate::isLoadedObject($params['cart'])) {
                $pointsBefore = (int) LoyaltyModule::getCartNbPoints($params['cart']);
                $pointsAfter = (int) LoyaltyModule::getCartNbPoints($params['cart'], $product);
                $points = (int) ($pointsAfter - $pointsBefore);
            } else {
                if (!(int) Configuration::get('PS_LOYALTY_NONE_AWARD') && Product::isDiscounted((int) $product->id)) {
                    $points = 0;
                    $this->smarty->assign('no_pts_discounted', 1);
                } else {
                    $points = (int) LoyaltyModule::getNbPointsByPrice(
                        $product->getPrice(
                            Product::getTaxCalculationMethod() == PS_TAX_EXC ? false : true,
                            (int) $product->getIdProductAttributeMostExpensive()
                        )
                    );
                }

                $pointsAfter = $points;
                $pointsBefore = 0;
            }
            $this->smarty->assign(
                [
                    'points'         => (int) $points,
                    'total_points'   => (int) $pointsAfter,
                    'point_rate'     => Configuration::get('PS_LOYALTY_POINT_RATE'),
                    'point_value'    => Configuration::get('PS_LOYALTY_POINT_VALUE'),
                    'points_in_cart' => (int) $pointsBefore,
                    'voucher'        => LoyaltyModule::getVoucherValue((int) $pointsAfter),
                    'none_award'     => Configuration::get('PS_LOYALTY_NONE_AWARD'),
                ]
            );
            Media::addJsDef([
                'loyalty_already' => $this->l('No reward points for this product because there\'s already a discount.'),
                'loyalty_nopoints'  => $this->l('No reward points for this product.'),
            ]);

            $this->context->controller->addJS(($this->_path).'js/loyalty.js');

            return $this->display(__FILE__, 'product.tpl');
        }

        return false;
    }

    /* Catch product returns and substract loyalty points */

    public function hookDisplayMyAccountBlock($params)
    {
        return $this->hookCustomerAccount($params);
    }

    /* Hook display on shopping cart summary */

    public function hookCustomerAccount($params)
    {
        return $this->display(__FILE__, 'my-account.tpl');
    }

    /* Hook called when a new order is created */

    public function hookOrderReturn($params)
    {
        $totalPrice = 0;
        $taxesEnabled = Product::getTaxCalculationMethod();
        $details = OrderReturn::getOrdersReturnDetail((int) $params['orderReturn']->id);
        foreach ($details as $detail) {
            if ($taxesEnabled == PS_TAX_EXC) {
                $totalPrice += Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                    '
                SELECT ROUND(total_price_tax_excl, 2)
                FROM '._DB_PREFIX_.'order_detail od
                WHERE id_order_detail = '.(int) $detail['id_order_detail']
                );
            } else {
                $totalPrice += Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                    '
                SELECT ROUND(total_price_tax_incl, 2)
                FROM '._DB_PREFIX_.'order_detail od
                WHERE id_order_detail = '.(int) $detail['id_order_detail']
                );
            }
        }

        $loyaltyNew = new LoyaltyModule();
        $loyaltyNew->points = (int) (-1 * LoyaltyModule::getNbPointsByPrice($totalPrice));
        $loyaltyNew->id_loyalty_state = (int) LoyaltyStateModule::getCancelId();
        $loyaltyNew->id_order = (int) $params['orderReturn']->id_order;
        $loyaltyNew->id_customer = (int) $params['orderReturn']->id_customer;
        $loyaltyNew->save();
    }

    /* Hook called when an order change its status */

    public function hookShoppingCart($params)
    {
        if (Validate::isLoadedObject($params['cart'])) {
            $points = LoyaltyModule::getCartNbPoints($params['cart']);
            $this->smarty->assign(
                [
                    'points'         => (int) $points,
                    'voucher'        => LoyaltyModule::getVoucherValue((int) $points),
                    'guest_checkout' => (int) Configuration::get('PS_GUEST_CHECKOUT_ENABLED'),
                ]
            );
        } else {
            $this->smarty->assign(['points' => 0]);
        }

        return $this->display(__FILE__, 'shopping-cart.tpl');
    }

    /* Hook display in tab AdminCustomers on BO */

    public function hookNewOrder($params)
    {
        if (!Validate::isLoadedObject($params['customer']) || !Validate::isLoadedObject($params['order'])) {
            die($this->l('Missing parameters'));
        }
        $loyalty = new LoyaltyModule();
        $loyalty->id_customer = (int) $params['customer']->id;
        $loyalty->id_order = (int) $params['order']->id;
        $loyalty->points = LoyaltyModule::getOrderNbPoints($params['order']);
        if (!Configuration::get('PS_LOYALTY_NONE_AWARD') && (int) $loyalty->points == 0) {
            $loyalty->id_loyalty_state = LoyaltyStateModule::getNoneAwardId();
        } else {
            $loyalty->id_loyalty_state = LoyaltyStateModule::getDefaultId();
        }

        return $loyalty->save();
    }

    public function hookUpdateOrderStatus($params)
    {
        if (!Validate::isLoadedObject($params['newOrderStatus'])) {
            die($this->l('Missing parameters'));
        }
        $newOrder = $params['newOrderStatus'];
        $order = new Order((int) $params['id_order']);
        if ($order && !Validate::isLoadedObject($order)) {
            die($this->l('Incorrect Order object.'));
        }
        $this->instanceDefaultStates();

        if ($newOrder->id == $this->loyaltyStateValidation->id_order_state || $newOrder->id == $this->loyaltyStateCancel->id_order_state) {
            if (!Validate::isLoadedObject($loyalty = new LoyaltyModule(LoyaltyModule::getByOrderId($order->id)))) {
                return false;
            }
            if ((int) Configuration::get('PS_LOYALTY_NONE_AWARD') && $loyalty->id_loyalty_state == LoyaltyStateModule::getNoneAwardId()) {
                return true;
            }

            if ($newOrder->id == $this->loyaltyStateValidation->id_order_state) {
                $loyalty->id_loyalty_state = LoyaltyStateModule::getValidationId();
                if ((int) $loyalty->points < 0) {
                    $loyalty->points = abs((int) $loyalty->points);
                }
            } elseif ($newOrder->id == $this->loyaltyStateCancel->id_order_state) {
                $loyalty->id_loyalty_state = LoyaltyStateModule::getCancelId();
                $loyalty->points = 0;
            }

            return $loyalty->save();
        }

        return true;
    }

    public function hookAdminCustomers($params)
    {
        $customer = new Customer((int) $params['id_customer']);
        if ($customer && !Validate::isLoadedObject($customer)) {
            die($this->l('Incorrect Customer object.'));
        }

        $details = LoyaltyModule::getAllByIdCustomer((int) $params['id_customer'], (int) $params['cookie']->id_lang);
        $points = (int) LoyaltyModule::getPointsByCustomer((int) $params['id_customer']);

        $html = '<div class="col-lg-12"><div class="panel">
            <div class="panel-heading">'.sprintf($this->l('Loyalty points (%d points)'), $points).'</div>';

        if (!isset($points) || count($details) == 0) {
            return $html.' '.$this->l('This customer has no points').'</div></div>';
        }

        $html .= '
        <div class="panel-body">
        <table cellspacing="0" cellpadding="0" class="table">
            <tr style="background-color:#F5E9CF; padding: 0.3em 0.1em;">
                <th>'.$this->l('Order').'</th>
                <th>'.$this->l('Date').'</th>
                <th>'.$this->l('Total (without shipping)').'</th>
                <th>'.$this->l('Points').'</th>
                <th>'.$this->l('Points Status').'</th>
            </tr>';
        foreach ($details as $key => $loyalty) {
            $url = 'index.php?tab=AdminOrders&id_order='.$loyalty['id'].'&vieworder&token='.Tools::getAdminToken('AdminOrders'.(int) Tab::getIdFromClassName('AdminOrders').(int) $params['cookie']->id_employee);
            $html .= '
            <tr style="background-color: '.($key % 2 != 0 ? '#FFF6CF' : '#FFFFFF').';">
                <td>'.((int) $loyalty['id'] > 0 ? '<a style="color: #268CCD; font-weight: bold; text-decoration: underline;" href="'.$url.'">'.sprintf($this->l('#%d'), $loyalty['id']).'</a>' : '--').'</td>
                <td>'.Tools::displayDate($loyalty['date']).'</td>
                <td>'.((int) $loyalty['id'] > 0 ? $loyalty['total_without_shipping'] : '--').'</td>
                <td>'.(int) $loyalty['points'].'</td>
                <td>'.$loyalty['state'].'</td>
            </tr>';
        }
        $html .= '
            <tr>
                <td>&nbsp;</td>
                <td colspan="2" class="bold" style="text-align: right;">'.$this->l('Total points available:').'</td>
                <td>'.$points.'</td>
                <td>'.$this->l('Voucher value:').' '.Tools::displayPrice(
                LoyaltyModule::getVoucherValue((int) $points, (int) Configuration::get('PS_CURRENCY_DEFAULT')),
                new Currency((int) Configuration::get('PS_CURRENCY_DEFAULT'))
            ).'</td>
            </tr>
        </table>
        </div>
        </div></div>';

        return $html;
    }

    public function hookCancelProduct($params)
    {
        if (!Validate::isLoadedObject($params['order']) || !Validate::isLoadedObject($order_detail = new OrderDetail((int) $params['id_order_detail']))
            || !Validate::isLoadedObject($loyalty = new LoyaltyModule((int) LoyaltyModule::getByOrderId((int) $params['order']->id)))
        ) {
            return false;
        }

        $taxesEnabled = Product::getTaxCalculationMethod();
        $loyaltyNew = new LoyaltyModule();
        if ($taxesEnabled == PS_TAX_EXC) {
            $loyaltyNew->points = -1 * LoyaltyModule::getNbPointsByPrice(number_format($order_detail->total_price_tax_excl, 2, '.', ''));
        } else {
            $loyaltyNew->points = -1 * LoyaltyModule::getNbPointsByPrice(number_format($order_detail->total_price_tax_incl, 2, '.', ''));
        }
        $loyaltyNew->id_loyalty_state = (int) LoyaltyStateModule::getCancelId();
        $loyaltyNew->id_order = (int) $params['order']->id;
        $loyaltyNew->id_customer = (int) $loyalty->id_customer;
        $loyaltyNew->add();

        return;
    }

    public function getL($key)
    {
        $translations = [
            'Awaiting validation'         => $this->l('Awaiting validation'),
            'Available'                   => $this->l('Available'),
            'Cancelled'                   => $this->l('Cancelled'),
            'Already converted'           => $this->l('Already converted'),
            'Unavailable on discounts'    => $this->l('Unavailable on discounts'),
            'Not available on discounts.' => $this->l('Not available on discounts.'),
        ];

        return (array_key_exists($key, $translations)) ? $translations[$key] : $key;
    }
}
