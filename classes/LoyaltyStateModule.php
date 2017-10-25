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

namespace LoyaltyModule;

use Loyalty;
use Module;

if (!defined('_TB_VERSION_')) {
    exit;
}

require_once __DIR__.'/../loyalty.php';

/**
 * Class LoyaltyStateModule
 *
 * @package LoyaltyModule
 */
class LoyaltyStateModule extends \ObjectModel
{
    // @codingStandardsIgnoreStart
    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table'     => 'loyalty_state',
        'primary'   => 'id_loyalty_state',
        'multilang' => true,
        'fields'    => [
            'id_order_state' => ['type' => self::TYPE_INT, 'validate' => 'isInt'],

            // Lang fields
            'name'           => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'required' => true, 'size' => 128],
        ],
    ];
    public $name;
    public $id_order_state;
    // @codingStandardsIgnoreEnd

    /**
     * @return bool
     */
    public static function insertDefaultData()
    {
        $loyaltyModule = new \Loyalty();
        $languages = \Language::getLanguages();

        $defaultTranslations = ['default' => ['id_loyalty_state' => (int) LoyaltyStateModule::getDefaultId(), 'default' => $loyaltyModule->getL('Awaiting validation'), 'en' => 'Awaiting validation', 'fr' => 'En attente de validation']];
        $defaultTranslations['validated'] = ['id_loyalty_state' => (int) LoyaltyStateModule::getValidationId(), 'id_order_state' => \Configuration::get('PS_OS_DELIVERED'), 'default' => $loyaltyModule->getL('Available'), 'en' => 'Available', 'fr' => 'Disponible'];
        $defaultTranslations['cancelled'] = ['id_loyalty_state' => (int) LoyaltyStateModule::getCancelId(), 'id_order_state' => \Configuration::get('PS_OS_CANCELED'), 'default' => $loyaltyModule->getL('Cancelled'), 'en' => 'Cancelled', 'fr' => 'Annulés'];
        $defaultTranslations['converted'] = ['id_loyalty_state' => (int) LoyaltyStateModule::getConvertId(), 'default' => $loyaltyModule->getL('Already converted'), 'en' => 'Already converted', 'fr' => 'Déjà convertis'];
        $defaultTranslations['none_award'] = ['id_loyalty_state' => (int) LoyaltyStateModule::getNoneAwardId(), 'default' => $loyaltyModule->getL('Unavailable on discounts'), 'en' => 'Unavailable on discounts', 'fr' => 'Non disponbile sur produits remisés'];

        foreach ($defaultTranslations as $loyaltyState) {
            $state = new LoyaltyStateModule((int) $loyaltyState['id_loyalty_state']);
            if (isset($loyaltyState['id_order_state'])) {
                $state->id_order_state = (int) $loyaltyState['id_order_state'];
            }
            $state->name[(int) \Configuration::get('PS_LANG_DEFAULT')] = $loyaltyState['default'];
            foreach ($languages as $language) {
                if (isset($loyaltyState[$language['iso_code']])) {
                    $state->name[(int) $language['id_lang']] = $loyaltyState[$language['iso_code']];
                }
            }
            $state->save();
        }

        return true;
    }

    /**
     * @return int
     */
    public static function getDefaultId()
    {
        return 1;
    }

    /**
     * @return int
     */
    public static function getValidationId()
    {
        return 2;
    }

    /**
     * @return int
     */
    public static function getCancelId()
    {
        return 3;
    }

    /**
     * @return int
     */
    public static function getConvertId()
    {
        return 4;
    }

    /**
     * @return int
     */
    public static function getNoneAwardId()
    {
        return 5;
    }

    /**
     * Get the states that can be linked to an order + translations
     *
     * @return array
     *
     * @since 3.0.0
     */
    public static function getStates()
    {
        /** @var Loyalty $module */
        $module = Module::getInstanceByName('loyalty');

        return [
            static::getDefaultId()    => $module->getL('Awaiting validation'),
            static::getValidationId() => $module->getL('Available'),
            static::getCancelId()     => $module->getL('Cancelled'),
            static::getConvertId()    => $module->getL('Already converted'),
        ];
    }
}
