<div class="col-lg-12">
  <div class="panel">
    <div class="panel-heading">{l s='Loyalty points (%d points)' sprintf=[$points] mod='loyalty'}</div>
    <div class="panel-body">
      {if (!isset($points) || count($details) == 0)}
        {l s='This customer has no points' mod='loyalty'}
      {/if}

      <table cellspacing="0" cellpadding="0" class="table">
        <thead>
          <tr style="background-color:#F5E9CF; padding: 0.3em 0.1em;">
            <th>{l s='Order' mod='loyalty'}</th>
            <th>{l s='Date' mod='loyalty'}</th>
            <th>{l s='Total (without shipping)' mod='loyalty'}</th>
            <th>{l s='Points' mod='loyalty'}</th>
            <th>{l s='Points Status' mod='loyalty'}</th>
          </tr>
        </thead>
        <tfoot>
          <tr>
            <td>&nbsp;</td>
            <td colspan="2" class="bold" style="text-align: right;">{l s='Total points available:' mod='loyalty'}</td>
            <td>{$points|escape:'htmlall':'UTF-8'}</td>
            <td>{l s='Voucher value:' mod='loyalty'} {displayPrice price=$voucher_value}</td>
          </tr>
        </tfoot>
        <tbody>
          {foreach $details as $key => $loyalty}
            <tr style="background-color: {if $key % 2 != 0}#FFF6CF{else}#FFFFFF{/if}">
              <td>{if $loyalty['id'] > 0}<a style="color: #268CCD; font-weight: bold; text-decoration: underline;" href="{$loyalty['url']|escape:'htmlall':'UTF-8'}">{l s='#%d' sprintf=[$loyalty['id']]}</a>{else}--{/if}</td>
              <td>{$loyalty['date']|date_format:'d-m-Y H:i'}</td>
              <td>{if $loyalty['id'] > 0}{$loyalty['total_without_shipping']}{else}--{/if}</td>
              <td>{$loyalty['points']|intval}</td>
              <td>{$loyalty['state']|escape:'htmlall':'UTF-8'}</td>
            </tr>
          {/foreach}
        </tbody>
      </table>
    </div>
  </div>
</div>
