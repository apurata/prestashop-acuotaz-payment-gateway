{$response nofilter}
{* Leo MixFashion (and similar) use display:flex on .product-prices.
   The addon is injected inside that row and is not visible. Move it below
   the price block so the widget keeps its normal width (API max-width ~27em).
   Re-place on prestashop updatedProduct only (DOM move; no extra addon fetch). *}
<style>
#acuotaz-add-on {
  display: block !important;
  margin: 0.75rem 0 !important;
  clear: both;
}
</style>
<script>
(function () {
  var placing = false;

  function placeApurataAddon() {
    if (placing) {
      return;
    }
    var prices = document.querySelector('.product-prices');
    if (!prices || !prices.parentNode) {
      return;
    }

    placing = true;
    try {
      var inside = prices.querySelector('#acuotaz-add-on');
      var outside = null;
      var sib = prices.nextElementSibling;
      while (sib) {
        if (sib.id === 'acuotaz-add-on') {
          outside = sib;
          break;
        }
        sib = sib.nextElementSibling;
      }

      if (inside) {
        if (outside) {
          outside.parentNode.removeChild(outside);
        }
        prices.parentNode.insertBefore(inside, prices.nextSibling);
        return;
      }

      if (outside) {
        return;
      }

      var el = document.getElementById('acuotaz-add-on');
      if (el) {
        prices.parentNode.insertBefore(el, prices.nextSibling);
      }
    } finally {
      placing = false;
    }
  }

  function bindApurataAddonPlacement() {
    placeApurataAddon();

    // Combination/price AJAX from PrestaShop core. Does not call Apurata itself;
    // only repositions HTML already returned in the product refresh.
    if (window.prestashop && typeof window.prestashop.on === 'function') {
      window.prestashop.on('updatedProduct', placeApurataAddon);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindApurataAddonPlacement);
  } else {
    bindApurataAddonPlacement();
  }
})();
</script>
