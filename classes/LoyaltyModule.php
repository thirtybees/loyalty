<?php
/**
 * 2007-2016 PrestaShop
 *
 * Thirty Bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017-2024 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    Thirty Bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017-2024 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

namespace LoyaltyModule;

use Cart;
use CartRule;
use Configuration;
use Context;
use Currency;
use Customer;
use Db;
use Language;
use ObjectModel;
use Order;
use PrestaShopException;
use Product;
use Shop;
use Tools;
use Validate;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class LoyaltyModule
 */
class LoyaltyModule extends ObjectModel
{
    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table'   => 'loyalty',
        'primary' => 'id_loyalty',
        'fields'  => [
            'id_loyalty_state' => ['type' => self::TYPE_INT, 'validate' => 'isInt'],
            'id_customer'      => ['type' => self::TYPE_INT, 'validate' => 'isInt', 'required' => true],
            'id_order'         => ['type' => self::TYPE_INT, 'validate' => 'isInt'],
            'id_cart_rule'     => ['type' => self::TYPE_INT, 'validate' => 'isInt'],
            'points'           => ['type' => self::TYPE_INT, 'validate' => 'isInt', 'required' => true],
            'date_add'         => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd'         => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];

    /**
     * @var int
     */
    public $id_loyalty_state;

    /**
     * @var int
     */
    public $id_customer;

    /**
     * @var int
     */
    public $id_order;

    /**
     * @var int
     */
    public $id_cart_rule;

    /**
     * @var int
     */
    public $points;

    /**
     * @var string
     */
    public $date_add;

    /**
     * @var string
     */
    public $date_upd;

    /**
     * @param int $idOrder
     *
     * @return int
     *
     * @throws PrestaShopException
     */
    public static function getByOrderId($idOrder)
    {
        return (int) Db::getInstance()->getValue(
            '
		SELECT f.id_loyalty
		FROM `'._DB_PREFIX_.'loyalty` f
		WHERE f.id_order = '.(int) ($idOrder)
        );
    }

    /**
     * @param Order $order
     *
     * @return int
     *
     * @throws PrestaShopException
     */
    public static function getOrderNbPoints($order)
    {
        if (!Validate::isLoadedObject($order)) {
            return 0;
        }

        return static::getCartNbPoints(new Cart((int) $order->id_cart));
    }

    /**
     * @param Cart $cart
     * @param Product|null $newProduct
     *
     * @return int
     *
     * @throws PrestaShopException
     */
    public static function getCartNbPoints($cart, $newProduct = null)
    {
        $total = 0;
        if (Validate::isLoadedObject($cart)) {
            $currentContext = Context::getContext();
            $context = clone $currentContext;
            $context->cart = $cart;
            // if customer is logged we do not recreate it
            if (!$context->customer->isLogged(true)) {
                $context->customer = new Customer($context->cart->id_customer);
            }
            $context->language = new Language($context->cart->id_lang);
            $context->shop = new Shop($context->cart->id_shop);
            $context->currency = new Currency($context->cart->id_currency, null, $context->shop->id);

            $cartProducts = $cart->getProducts();
            $taxesEnabled = Product::getTaxCalculationMethod();
            if (isset($newProduct) && !empty($newProduct)) {
                $cartProductsNew['id_product'] = (int) $newProduct->id;
                if ($taxesEnabled == PS_TAX_EXC) {
                    $cartProductsNew['price'] = number_format($newProduct->getPrice(false, (int) $newProduct->getIdProductAttributeMostExpensive()), 2, '.', '');
                } else {
                    $cartProductsNew['price_wt'] = number_format($newProduct->getPrice(true, (int) $newProduct->getIdProductAttributeMostExpensive()), 2, '.', '');
                }
                $cartProductsNew['cart_quantity'] = 1;
                $cartProducts[] = $cartProductsNew;
            }
            foreach ($cartProducts as $product) {
                if (!(int) (Configuration::get('PS_LOYALTY_NONE_AWARD')) && Product::isDiscounted((int) $product['id_product'])) {
                    if (isset(Context::getContext()->smarty) && is_object($newProduct) && $product['id_product'] == $newProduct->id) {
                        Context::getContext()->smarty->assign('no_pts_discounted', 1);
                    }
                    continue;
                }
                $total += ($taxesEnabled == PS_TAX_EXC ? $product['price'] : $product['price_wt']) * (int) ($product['cart_quantity']);
            }
            foreach ($cart->getCartRules(false) as $cartRule) {
                if (!$cartRule['free_shipping']) {
                    if ($taxesEnabled == PS_TAX_EXC) {
                        $total -= $cartRule['value_tax_exc'];
                    } else {
                        $total -= $cartRule['value_real'];
                    }
                }
            }
        }

        return static::getNbPointsByPrice($total);
    }

    /**
     * @param float $price
     *
     * @return int
     * @throws PrestaShopException
     */
    public static function getNbPointsByPrice($price)
    {
        if (Configuration::get('PS_CURRENCY_DEFAULT') != Context::getContext()->currency->id) {
            if (Context::getContext()->currency->conversion_rate) {
                $price = $price / Context::getContext()->currency->conversion_rate;
            }
        }

        /* Prevent division by zero */
        $points = 0;
        if ($pointRate = (float) (Configuration::get('PS_LOYALTY_POINT_RATE'))) {
            $points = floor(number_format($price, 2, '.', '') / $pointRate);
        }

        return (int) $points;
    }

    /**
     * @param int $nbPoints
     * @param int|null $idCurrency
     *
     * @return float
     * @throws PrestaShopException
     */
    public static function getVoucherValue($nbPoints, $idCurrency = null)
    {
        $currency = $idCurrency ? new Currency($idCurrency) : Context::getContext()->currency->id;

        return (int)$nbPoints * (float)Tools::convertPrice(Configuration::get('PS_LOYALTY_POINT_VALUE'), $currency);
    }

    /**
     * @param int $idCustomer
     *
     * @return int
     *
     * @throws PrestaShopException
     */
    public static function getPointsByCustomer($idCustomer)
    {

        $validityPeriod = Configuration::get('PS_LOYALTY_VALIDITY_PERIOD');
        $sqlPeriod = '';
        if ((int) $validityPeriod > 0) {
            $sqlPeriod = ' AND datediff(NOW(),f.date_add) <= '.$validityPeriod;
        }

        $conn = Db::getInstance(_PS_USE_SQL_SLAVE_);

        return (int)$conn->getValue(
                '
		SELECT SUM(f.points) points
		FROM `'._DB_PREFIX_.'loyalty` f
		WHERE f.id_customer = '.(int) ($idCustomer).'
		AND f.id_loyalty_state IN ('.(int) (LoyaltyStateModule::getValidationId()).', '.(int) (LoyaltyStateModule::getNoneAwardId()).')
		'.$sqlPeriod
            )
            +
            (int)$conn->getValue(
                '
		SELECT SUM(f.points) points
		FROM `'._DB_PREFIX_.'loyalty` f
		WHERE f.id_customer = '.(int) ($idCustomer).'
		AND f.id_loyalty_state = '.(int) LoyaltyStateModule::getCancelId().'
		AND points < 0
		'.$sqlPeriod
            );
    }

    /**
     * @param int $idCustomer
     *
     * @return int[]
     *
     * @throws PrestaShopException
     */
    public static function getDiscountByIdCustomer($idCustomer)
    {
        $query = '
		SELECT DISTINCT f.id_cart_rule
		FROM `'._DB_PREFIX_.'loyalty` f
		LEFT JOIN `'._DB_PREFIX_.'orders` o ON (f.`id_order` = o.`id_order`)
		INNER JOIN `'._DB_PREFIX_.'cart_rule` cr ON (cr.`id_cart_rule` = f.`id_cart_rule`)
		WHERE f.`id_customer` = '.(int) ($idCustomer).'
		AND f.`id_cart_rule` > 0
		AND o.`valid` = 1';

        $result = Db::getInstance()->executeS($query);
        return is_array($result)
            ? array_column($result , 'id_cart_rule')
            : [];
    }

    /**
     * @param CartRule $cartRule
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public static function registerDiscount($cartRule)
    {
        if (!Validate::isLoadedObject($cartRule)) {
            return false;
        }
        $context = Context::getContext();
        $items = static::getAllByIdCustomer((int) $cartRule->id_customer, (int)$context->language->id , true);
        $associated = false;
        foreach ($items AS $item) {
            $lm = new LoyaltyModule((int) $item['id_loyalty']);

            /* Check for negative points for this order */
            $negativePoints = (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT SUM(points) points FROM '._DB_PREFIX_.'loyalty WHERE id_order = '.(int) $item['id'].' AND id_loyalty_state = '.(int) LoyaltyStateModule::getCancelId().' AND points < 0');

            if ($lm->points + $negativePoints <= 0) {
                continue;
            }

            $lm->id_cart_rule = (int) $cartRule->id;
            $lm->id_loyalty_state = (int) LoyaltyStateModule::getConvertId();
            $lm->save();
            $associated = true;
        }

        return $associated;
    }

    /**
     * @param int $idCustomer
     * @param int $idLang
     * @param bool $onlyValidate
     * @param bool $pagination
     * @param int $nb
     * @param int $page
     *
     * @return array
     *
     * @throws PrestaShopException
     */
    public static function getAllByIdCustomer($idCustomer, $idLang, $onlyValidate = false, $pagination = false, $nb = 10, $page = 1)
    {

        $validityPeriod = Configuration::get('PS_LOYALTY_VALIDITY_PERIOD');
        $sqlPeriod = '';
        if ((int) $validityPeriod > 0) {
            $sqlPeriod = ' AND datediff(NOW(),f.date_add) <= '.$validityPeriod;
        }

        $query = '
		SELECT f.id_order AS id, f.date_add AS date, (o.total_paid - o.total_shipping) total_without_shipping, f.points, f.id_loyalty, f.id_loyalty_state, fsl.name state, o.`id_currency`
		FROM `'._DB_PREFIX_.'loyalty` f
		LEFT JOIN `'._DB_PREFIX_.'orders` o ON (f.id_order = o.id_order)
		LEFT JOIN `'._DB_PREFIX_.'loyalty_state_lang` fsl ON (f.id_loyalty_state = fsl.id_loyalty_state AND fsl.id_lang = '.(int) ($idLang).')
		WHERE f.id_customer = '.(int) ($idCustomer).$sqlPeriod;
        if ($onlyValidate === true) {
            $query .= ' AND f.id_loyalty_state = '.(int) LoyaltyStateModule::getValidationId();
        }
        $query .= ' GROUP BY f.id_loyalty '.($pagination ? 'LIMIT '.(((int) ($page) - 1) * (int) ($nb)).', '.(int) ($nb) : '');

        $result = Db::getInstance()->executeS($query);
        return is_array($result) ? $result : [];
    }

    /**
     * @param bool $nullValues
     * @param bool $autodate
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function save($nullValues = false, $autodate = true)
    {
        parent::save($nullValues, $autodate);
        $this->historize();

        return true;
    }

    /**
     * @return void
     *
     * @throws PrestaShopException
     */
    private function historize()
    {
        Db::getInstance()->execute(
            '
		INSERT INTO `'._DB_PREFIX_.'loyalty_history` (`id_loyalty`, `id_loyalty_state`, `points`, `date_add`)
		VALUES ('.(int) ($this->id).', '.(int) ($this->id_loyalty_state).', '.(int) ($this->points).', NOW())'
        );
    }

    /**
     * @param int $idCartRule
     *
     * @return array
     *
     * @throws PrestaShopException
     */
    public static function getOrdersByIdDiscount($idCartRule)
    {
        $items = Db::getInstance()->executeS(
            '
		SELECT f.id_order AS id_order, f.points AS points, f.date_upd AS date
		FROM `'._DB_PREFIX_.'loyalty` f
		WHERE f.id_cart_rule = '.(int) $idCartRule.' AND f.id_loyalty_state = '.(int) LoyaltyStateModule::getConvertId()
        );

        if (!empty($items) && is_array($items)) {
            foreach ($items as $key => $item) {
                $order = new Order((int) $item['id_order']);
                $items[$key]['id_currency'] = (int) $order->id_currency;
                $items[$key]['id_lang'] = (int) $order->id_lang;
                $items[$key]['total_paid'] = $order->total_paid;
                $items[$key]['total_shipping'] = $order->total_shipping;
            }

            return $items;
        }

        return [];
    }
}