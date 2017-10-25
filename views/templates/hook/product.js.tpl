{*
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
*}
<script type="text/javascript">
  (function () {
    if (typeof window.point_rate !== 'undefined') {
      // Might have been initialized already by a theme
      return;
    }

    window.point_rate = {$point_rate|floatval};
    window.point_value = {$point_value|floatval};
    window.points_in_cart = {$points_in_cart|intval};
    window.none_award = {if $none_award}true{else}false{/if};

    function updatePoints() {
      if (typeof window.priceWithDiscountsDisplay === 'undefined'
          || typeof window.productPriceWithoutReduction === 'undefined'
          || typeof window.productPrice === 'undefined') {
        return;
      }

      var currentPrice = window.priceWithDiscountsDisplay;
      var points = parseInt(currentPrice / window.point_rate, 10);
      var total_points = window.points_in_cart + points;
      var voucher = total_points * window.point_value;
      if (!window.none_award && parseFloat(productPriceWithoutReduction) !== parseFloat(productPrice)) {
        $('#loyalty').html("{l s='No reward points for this product because there\'s already a discount.' mod='loyalty'}");
      } else if (!points) {
        $('#loyalty').html("{l s='No reward points for this product.' mod='loyalty'}");
      } else {
        var content = "{l s='By buying this product you can collect up to' mod='loyalty'} <b><span id=\"loyalty_points\">" + points + '</span> ';
        if (points > 1) {
          content += "{l s='loyalty points' mod='loyalty'}</b>. ";
        } else {
          content += "{l s='loyalty point' mod='loyalty'}</b>. ";
        }

        content += "{l s='Your cart will total' mod='loyalty'} <b><span id=\"total_loyalty_points\">" + total_points + '</span> ';
        if (total_points > 1) {
          content += "{l s='points' mod='loyalty'}";
        } else {
          content += "{l s='point' mod='loyalty'}";
        }

        content += "</b> {l s='that can be converted into a voucher of' mod='loyalty'} ";
        content += '<span id="loyalty_price">' + formatCurrency(voucher, currencyFormat, currencySign, currencyBlank) + '</span>.';
        $('#loyalty').html(content);
      }
    }

    $(document).ready(function () {
      // Catch all attribute changes of the product
      $(document).on('change', '.product_attributes input, .product_attributes select', function () {
        setTimeout(updatePoints, 100); // Schedule last
      });

      // Force color "button" to fire event change
      $('#color_to_pick_list').click(function () {
        setTimeout(updatePoints, 100); // Schedule last
      });
      updatePoints();
    });
  }());
</script>
