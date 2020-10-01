
{block name='cart_summary_total'}
    <p>jhewcjas</p>
    {if !$configuration.display_prices_tax_incl && $configuration.taxes_enabled}
      <div class="cart-summary-line">
     
        <span class="label">{$cart.totals.total.label}&nbsp;{$cart.labels.tax_short}</span>
        <span class="value">{$cart.totals.total.value}</span>
      </div>
      <div class="cart-summary-line cart-total">
        <span class="label">{$cart.totals.total_including_tax.label}</span>
        <span class="value">{$cart.totals.total_including_tax.value}</span>
      </div>
    {else}
      <div class="cart-summary-line cart-total">
        <span class="label">{$cart.totals.total.label}&nbsp;{if $configuration.taxes_enabled}{$cart.labels.tax_short}{/if}</span>
        <span class="value">{$cart.totals.total.value}</span>
      </div>
    {/if}
{/block}