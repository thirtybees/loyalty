<div class="col-lg-12">
  <div class="panel">
    <div class="panel-heading">{l s='Loyalty points (%d points)' sprintf=[$points] mod='loyalty'}</div>
    <div class="panel-body">
      {if (!isset($points) || count($details) == 0)}
        {l s='This customer has no points' mod='loyalty'}
      {/if}

      <table id="loyalty-table" cellspacing="0" cellpadding="0" class="table">
        <thead>
          <tr style="background-color:#F5E9CF; padding: 0.3em 0.1em;">
            <th>{l s='Order' mod='loyalty'}</th>
            <th>{l s='Date' mod='loyalty'}</th>
            <th>{l s='Total (without shipping)' mod='loyalty'}</th>
            <th>{l s='Points' mod='loyalty'}</th>
            <th>{l s='Points Status' mod='loyalty'}</th>
            <th></th>
          </tr>
        </thead>
        <tfoot>
          <tr>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td colspan="2" class="bold" style="text-align: right;">{l s='Total points available:' mod='loyalty'}</td>
            <td>{$points|escape:'htmlall':'UTF-8'}</td>
            <td>{l s='Voucher value:' mod='loyalty'} {displayPrice price=$voucher_value}</td>
          </tr>
        </tfoot>
        <tbody>
          {foreach $details as $key => $loyalty}
            <tr style="background-color: {if $key % 2 != 0}#FFF6CF{else}#FFFFFF{/if}" data-id-loyalty="{$loyalty['id']|intval}" data-id-loyalty-state="{$loyalty['id_loyalty_state']|intval}">
              <td>{if $loyalty['id'] > 0}<a style="color: #268CCD; font-weight: bold; text-decoration: underline;" href="{$loyalty['url']|escape:'htmlall':'UTF-8'}">{l s='#%d' sprintf=[$loyalty['id']]}</a>{else}--{/if}</td>
              <td>{$loyalty['date']|date_format:'d-m-Y H:i'}</td>
              <td>{if $loyalty['id'] > 0}{$loyalty['total_without_shipping']}{else}--{/if}</td>
              <td>{$loyalty['points']|intval}</td>
              <td id="voucher_state_{$loyalty['id']|intval}">{$loyalty['state']|escape:'htmlall':'UTF-8'}</td>
              <td></td>
            </tr>
          {/foreach}
        </tbody>
      </table>
    </div>
  </div>
</div>

<script type="text/javascript">
  (function () {
    function initStateChanger() {
      if (typeof $ === 'undefined') {
        setTimeout(initStateChanger, 10);

        return;
      }
    }
    var availableStates = {$available_states|json_encode};
    var buttonBackups = {ldelim}{rdelim};

    function showChanger(idLoyalty, idLoyaltyState) {
      var $loyalty = $('#voucher_state_' + idLoyalty);
      var $stateSelect = $('<select />');

      $.each(availableStates, function (id, text) {
        $stateSelect.append($('<option>', {
          value: id,
          text : text,
          selected: (parseInt(id, 10) === parseInt(idLoyaltyState, 10) ? 'selected' : false),
        }));
      });

      $stateSelect.change(function (event) {
        changeHandler(idLoyalty, parseInt(event.target.value, 10), idLoyaltyState);
      });

      var $submitChangeBtn = $('<a />', {
        id: 'loyalty-change-btn-' + idLoyalty,
        click: function (event) {
          submitHandler(idLoyalty, parseInt($(event.target).data('id-loyalty-state'), 10), event.target);
        },
        'class': 'btn btn-info',
        disabled: 'disabled',
        html: '{l s='Update status' mod='loyalty' js=1} <i class="icon icon-chevron-right"></i>'
      });

      $loyalty.html($stateSelect);
      var $btnContainer = $loyalty.next();
      buttonBackups[idLoyalty] = $btnContainer.html();
      $btnContainer.html($submitChangeBtn);
    }

    function changeHandler(idLoyalty, idLoyaltyState, previousIdLoyaltyState) {
      $targetButton = $('#loyalty-change-btn-' + idLoyalty);

      // Disable if the status does not change
      if (idLoyaltyState === previousIdLoyaltyState) {
        $targetButton.attr('disabled', 'disabled');
      } else {
        $targetButton.removeAttr('disabled');
      }

      $targetButton.data('id-loyalty-state', idLoyaltyState);
    }

    function submitHandler(idLoyalty, idLoyaltyState, elem) {
      $.ajax({
        url: window.loyalty_endpoint,
        method: 'POST',
        dataType: 'json',
        data: {
          action: 'ChangeState',
          idLoyalty: idLoyalty,
          idLoyaltyState: idLoyaltyState
        },
        success: function (response) {
          if (response && response.success) {
            showSuccessMessage('Successfully changed the status');

            var $showChangeButton = $('<a class="btn btn-default"><i class="icon icon-refresh"></i> {l s='Change state' mod='loyalty' js=1}</a>');

            $showChangeButton.click(function () {
              showChanger(idLoyalty, idLoyaltyState)
            });

            $(elem).replaceWith($showChangeButton);
            $('#voucher_state_' + idLoyalty).text(availableStates[idLoyaltyState]);
          } else {
            showErrorMessage('Unable to change the status');
          }
        },
        error: function () {
          showErrorMessage('Unable to change the status. Check your server log for errors.');
        }
      });
    }

    var $table = $('#loyalty-table');
    var $loyalties = $table.find('tbody > tr');

    $loyalties.each(function (index, tr) {
      $tr = $(tr);
      var idLoyalty = parseInt($tr.data('id-loyalty'), 10);
      var idLoyaltyState = parseInt($tr.data('id-loyalty-state'), 10);

      var $showChangeButton = $('<a class="btn btn-default"><i class="icon icon-refresh"></i> {l s='Change state' mod='loyalty' js=1}</a>');

      $showChangeButton.click(function () {
        showChanger(idLoyalty, idLoyaltyState)
      });

      $tr.find('td').last().html($showChangeButton);
    });

    initStateChanger();
  }());
</script>
