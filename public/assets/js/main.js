/* ==========================================================================
   Daily Dose Export — storefront scripts
   ========================================================================== */
(function () {
  'use strict';

  var DDE = window.DDE || {};
  var toastEl = null;
  var toastTimer = null;

  function $(selector, scope) { return (scope || document).querySelector(selector); }
  function $$(selector, scope) { return Array.prototype.slice.call((scope || document).querySelectorAll(selector)); }

  function toast(message, isError) {
    toastEl = toastEl || $('[data-toast]');
    if (!toastEl) { if (isError) console.error(message); return; }
    toastEl.textContent = message;
    toastEl.classList.toggle('is-error', !!isError);
    toastEl.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.hidden = true; }, 3800);
  }

  function api(action, payload) {
    var headers = {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json'
    };
    if (DDE.csrf) headers['X-CSRF-Token'] = DDE.csrf;
    return fetch(DDE.apiUrl + '?action=' + encodeURIComponent(action), {
      method: 'POST',
      headers: headers,
      credentials: 'same-origin',
      body: JSON.stringify(payload || {})
    }).then(function (response) {
      return response.json().catch(function () {
        throw new Error('Unexpected server response. Please try again.');
      });
    }).then(function (data) {
      if (!data || data.ok !== true) {
        var err = new Error((data && data.message) || 'Something went wrong. Please try again.');
        err.data = data || {};
        throw err;
      }
      return data.data || {};
    });
  }

  function handleApiError(error) {
    var data = error && error.data;
    if (data && data.require_login && data.login_url) {
      window.location.href = data.login_url;
      return;
    }
    toast((error && error.message) || 'Something went wrong. Please try again.', true);
  }

  function setBadge(selector, value) {
    $$(selector).forEach(function (el) {
      el.textContent = value;
      if (el.classList.contains('count-badge')) {
        el.classList.toggle('is-empty', Number(value) <= 0);
      }
    });
  }

  function applyCounts(data) {
    if (!data) return;
    if (typeof data.cart_count !== 'undefined') setBadge('[data-cart-count]', data.cart_count);
    if (typeof data.wishlist_count !== 'undefined') setBadge('[data-wishlist-count]', data.wishlist_count);
  }

  /* ---------------- Theme ---------------- */
  var themeRoot = document.documentElement;
  var themeToggles = $$('[data-theme-toggle]');

  function currentTheme() {
    return themeRoot.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  }

  function syncThemeControls() {
    var isDark = currentTheme() === 'dark';
    var label = isDark ? themeRoot.getAttribute('data-theme-light-label') : themeRoot.getAttribute('data-theme-dark-label');
    themeToggles.forEach(function (button) {
      button.setAttribute('aria-label', label);
      button.setAttribute('title', label);
      button.setAttribute('aria-pressed', isDark ? 'true' : 'false');
    });
    var meta = $('meta[data-theme-color]');
    if (meta) meta.setAttribute('content', isDark ? '#0D1713' : '#173F32');
  }

  function setTheme(theme, persist) {
    themeRoot.setAttribute('data-theme', theme === 'dark' ? 'dark' : 'light');
    if (persist !== false) {
      try { localStorage.setItem('dde-theme', theme); } catch (e) {}
    }
    syncThemeControls();
  }

  document.addEventListener('click', function (event) {
    if (!event.target.closest('[data-theme-toggle]')) return;
    event.preventDefault();
    setTheme(currentTheme() === 'dark' ? 'light' : 'dark');
  });

  if (window.matchMedia) {
    var themeQuery = window.matchMedia('(prefers-color-scheme: dark)');
    var onSystemTheme = function (event) {
      var saved = null;
      try { saved = localStorage.getItem('dde-theme'); } catch (e) {}
      if (!saved) setTheme(event.matches ? 'dark' : 'light', false);
    };
    if (themeQuery.addEventListener) themeQuery.addEventListener('change', onSystemTheme);
    else if (themeQuery.addListener) themeQuery.addListener(onSystemTheme);
  }
  syncThemeControls();

  /* ---------------- Navigation drawer ---------------- */
  var nav = null, navBackdrop = null, navToggle = null;

  function setNav(open) {
    nav = nav || $('[data-nav]') || $('#mobile-nav');
    navBackdrop = navBackdrop || $('[data-nav-backdrop]');
    navToggle = navToggle || $('[data-nav-toggle]');
    if (!nav) return;
    nav.hidden = !open;
    if (navBackdrop) navBackdrop.hidden = !open;
    document.body.classList.toggle('nav-open', open);
    if (navToggle) navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      var first = nav.querySelector('[data-nav-close]');
      if (first) first.focus();
    } else if (navToggle) {
      navToggle.focus();
    }
  }

  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-nav-toggle]')) {
      event.preventDefault();
      setNav(true);
    } else if (event.target.closest('[data-nav-close]') || event.target.closest('[data-nav-backdrop]')) {
      event.preventDefault();
      setNav(false);
    }
  });

  /* ---------------- Search panel ---------------- */
  var searchPanel = null;

  function setSearch(open) {
    searchPanel = searchPanel || $('[data-search-panel]');
    var toggles = $$('[data-search-toggle]');
    if (!searchPanel) return;
    searchPanel.hidden = !open;
    toggles.forEach(function (t) { t.setAttribute('aria-expanded', open ? 'true' : 'false'); });
    if (open) {
      var input = searchPanel.querySelector('input[type="search"]');
      if (input) setTimeout(function () { input.focus(); }, 30);
    }
  }

  document.addEventListener('click', function (event) {
    var toggle = event.target.closest('[data-search-toggle]');
    if (toggle) {
      event.preventDefault();
      setSearch(!searchPanel || searchPanel.hidden);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    if (nav && !nav.hidden) setNav(false);
    if (searchPanel && !searchPanel.hidden) setSearch(false);
    var filters = $('#shop-filters');
    if (filters && filters.classList.contains('is-open')) closeFilters();
  });

  window.addEventListener('resize', function () {
    if (window.innerWidth > 820) {
      if (nav && !nav.hidden) setNav(false);
      var filters = $('#shop-filters');
      if (filters && filters.classList.contains('is-open')) closeFilters();
    }
  });

  /* ---------------- Add to cart ---------------- */
  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-add-to-cart]');
    if (!button) return;
    event.preventDefault();
    var variantId = button.getAttribute('data-add-to-cart');
    var qty = 1;
    var scope = button.closest('[data-product-form]');
    if (scope) {
      var qtyInput = scope.querySelector('input[name="quantity"]');
      if (qtyInput) qty = Math.max(1, parseInt(qtyInput.value, 10) || 1);
      var checked = scope.querySelector('input[name="variant_id"]:checked');
      if (checked) variantId = checked.value;
    }
    if (!variantId) return;

    var original = button.innerHTML;
    button.disabled = true;
    button.classList.add('is-loading');
    button.innerHTML = 'Adding…';

    api('cart.add', { variant_id: Number(variantId), quantity: qty }).then(function (data) {
      applyCounts(data);
      toast(data.message || 'Added to your cart.');
      button.innerHTML = 'Added';
      setTimeout(function () {
        button.innerHTML = original;
        button.disabled = false;
        button.classList.remove('is-loading');
      }, 1400);
    }).catch(function (error) {
      button.innerHTML = original;
      button.disabled = false;
      button.classList.remove('is-loading');
      handleApiError(error);
    });
  });

  /* ---------------- Wishlist ---------------- */
  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-wishlist]');
    if (!button) return;
    event.preventDefault();
    var productId = button.getAttribute('data-wishlist');
    if (!productId) return;
    button.disabled = true;

    api('wishlist.toggle', { product_id: Number(productId) }).then(function (data) {
      var active = !!data.active;
      $$('[data-wishlist="' + productId + '"]').forEach(function (el) {
        el.classList.toggle('is-active', active);
        el.setAttribute('aria-pressed', active ? 'true' : 'false');
        el.setAttribute('aria-label', active ? 'Remove from wishlist' : 'Add to wishlist');
        var label = el.querySelector('span');
        if (label) label.textContent = active ? 'Saved to wishlist' : 'Save to wishlist';
      });
      setBadge('[data-wishlist-count]', data.wishlist_count);
      toast(active ? 'Saved to your wishlist.' : 'Removed from your wishlist.');
      button.disabled = false;
    }).catch(function (error) {
      button.disabled = false;
      handleApiError(error);
    });
  });

  /* ---------------- Quantity steppers ---------------- */
  document.addEventListener('click', function (event) {
    var minus = event.target.closest('[data-qty-minus]');
    var plus = event.target.closest('[data-qty-plus]');
    if (!minus && !plus) return;
    event.preventDefault();
    var wrap = (minus || plus).closest('[data-qty]');
    var input = wrap ? wrap.querySelector('input') : null;
    if (!input) return;
    var min = parseInt(input.getAttribute('min'), 10) || 1;
    var max = parseInt(input.getAttribute('max'), 10) || 99;
    var value = parseInt(input.value, 10) || min;
    value = plus ? Math.min(max, value + 1) : Math.max(min, value - 1);
    input.value = value;
    input.dispatchEvent(new Event('change', { bubbles: true }));
  });

  /* ---------------- Cart page ajax updates ---------------- */
  function syncCart(action, payload, message) {
    api(action, payload).then(function (data) {
      applyCounts(data);
      if (message) toast(message);
      window.location.reload();
    }).catch(handleApiError);
  }

  $$('[data-cart-update]').forEach(function (form) {
    var debounceTimer = null;
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var itemKey = form.getAttribute('data-cart-update');
      var input = form.querySelector('input[name="quantity"]');
      syncCart('cart.update', { item_key: itemKey, quantity: parseInt(input && input.value, 10) || 1 });
    });
    var qtyInput = form.querySelector('input[name="quantity"]');
    if (qtyInput) {
      qtyInput.addEventListener('change', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
          if (form.requestSubmit) form.requestSubmit(); else form.dispatchEvent(new Event('submit', { cancelable: true }));
        }, 450);
      });
    }
  });

  $$('[data-cart-remove]').forEach(function (button) {
    button.addEventListener('click', function (event) {
      event.preventDefault();
      syncCart('cart.remove', { item_key: button.getAttribute('data-cart-remove') }, 'Item removed from your cart.');
    });
  });

  $$('[data-coupon-remove]').forEach(function (button) {
    button.addEventListener('click', function (event) {
      event.preventDefault();
      syncCart('coupon.remove', {}, 'Coupon removed.');
    });
  });

  /* ---------------- Newsletter ---------------- */
  $$('[data-newsletter-form]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var email = form.querySelector('input[name="email"]');
      var message = form.querySelector('[data-form-message]');
      var button = form.querySelector('button[type="submit"]');
      if (!email || !email.value.trim()) return;
      if (button) button.disabled = true;
      if (message) { message.textContent = 'Subscribing…'; message.className = 'form-message'; }

      api('newsletter.subscribe', { email: email.value.trim() }).then(function (data) {
        if (message) {
          message.textContent = data.message || 'Thank you for subscribing.';
          message.className = 'form-message ok';
          message.style.color = '#E9D8B4';
        }
        form.reset();
        if (button) button.disabled = false;
      }).catch(function (error) {
        if (message) {
          message.textContent = error.message || 'We could not subscribe you. Please try again.';
          message.className = 'form-message is-error';
          message.style.color = '#F3C7BE';
        }
        if (button) button.disabled = false;
      });
    });
  });

  /* ---------------- Product gallery ---------------- */
  $$('[data-gallery-thumb]').forEach(function (thumb) {
    thumb.addEventListener('click', function (event) {
      event.preventDefault();
      var main = $('[data-gallery-main] img');
      var full = thumb.getAttribute('data-full');
      if (main && full) {
        main.src = full;
        main.removeAttribute('srcset');
      }
      $$('[data-gallery-thumb]').forEach(function (t) { t.classList.remove('is-active'); t.setAttribute('aria-current', 'false'); });
      thumb.classList.add('is-active');
      thumb.setAttribute('aria-current', 'true');
    });
  });

  /* ---------------- Product variants ---------------- */
  var variantInputs = $$('input[name="variant_id"][data-variant]');
  if (variantInputs.length) {
    var priceEl = $('[data-product-price]');
    var mrpEl = $('[data-product-mrp]');
    var saveEl = $('[data-product-save]');
    var stockEl = $('[data-product-stock]');
    var addBtn = $('[data-product-add]');
    var buyBtn = $('[data-product-buy]');
    var stickyPrice = $('[data-sticky-price]');
    var stickyBtn = $('[data-sticky-add]');

    var formatMoney = function (value) {
      return '₹' + Number(value).toLocaleString('en-IN', { maximumFractionDigits: 0 });
    };

    var syncVariant = function (input) {
      if (!input) return;
      var price = input.getAttribute('data-price');
      var mrp = input.getAttribute('data-mrp');
      var stock = parseInt(input.getAttribute('data-stock'), 10) || 0;
      var label = input.getAttribute('data-label') || '';

      if (priceEl) priceEl.textContent = price !== '' ? formatMoney(price) : 'Price on request';
      if (mrpEl) {
        if (mrp && price && Number(mrp) > Number(price)) { mrpEl.textContent = formatMoney(mrp); mrpEl.hidden = false; }
        else { mrpEl.hidden = true; mrpEl.textContent = ''; }
      }
      if (saveEl) {
        if (mrp && price && Number(mrp) > Number(price)) {
          var pct = Math.round(((Number(mrp) - Number(price)) / Number(mrp)) * 100);
          saveEl.textContent = pct + '% off';
          saveEl.hidden = false;
        } else { saveEl.hidden = true; saveEl.textContent = ''; }
      }
      if (stockEl) {
        stockEl.classList.remove('out', 'request');
        if (price === '') {
          stockEl.textContent = 'Price on request — contact us for a quotation.';
          stockEl.classList.add('request');
        } else if (stock > 0) {
          stockEl.textContent = stock > 10 ? 'In stock — ready to dispatch' : 'Only ' + stock + ' left in stock';
        } else {
          stockEl.textContent = 'Out of stock — enquire for availability';
          stockEl.classList.add('out');
        }
      }
      if (addBtn) addBtn.disabled = !(price !== '' && stock > 0);
      if (buyBtn) buyBtn.disabled = !(price !== '' && stock > 0);
      if (stickyPrice) {
        stickyPrice.innerHTML = (price !== '' ? '<strong>' + formatMoney(price) + '</strong>' : '<strong>Price on request</strong>') + '<small>' + (label ? label.replace(/"/g, '') : '') + '</small>';
      }
      if (stickyBtn) stickyBtn.disabled = !(price !== '' && stock > 0);
    };

    variantInputs.forEach(function (input) {
      input.addEventListener('change', function () { syncVariant(input); });
      if (input.checked) syncVariant(input);
    });

    if (stickyBtn) {
      stickyBtn.addEventListener('click', function (event) {
        event.preventDefault();
        if (addBtn) addBtn.click();
      });
    }
  }

  /* ---------------- Shop filters drawer ---------------- */
  var filterPanel = $('#shop-filters');
  var filterBackdrop = null;

  function openFilters() {
    if (!filterPanel) return;
    filterPanel.classList.add('is-open');
    filterBackdrop = filterBackdrop || $('[data-filter-backdrop]');
    if (!filterBackdrop) {
      filterBackdrop = document.createElement('div');
      filterBackdrop.className = 'nav-backdrop';
      filterBackdrop.setAttribute('data-filter-backdrop', '');
      document.body.appendChild(filterBackdrop);
      filterBackdrop.addEventListener('click', closeFilters);
    }
    filterBackdrop.hidden = false;
    document.body.classList.add('nav-open');
    var close = filterPanel.querySelector('[data-filter-close]');
    if (close) close.focus();
  }

  function closeFilters() {
    if (filterPanel) filterPanel.classList.remove('is-open');
    if (filterBackdrop) filterBackdrop.hidden = true;
    document.body.classList.remove('nav-open');
    $$('[data-filter-toggle]').forEach(function (t) { t.setAttribute('aria-expanded', 'false'); });
  }

  $$('[data-filter-toggle]').forEach(function (button) {
    button.addEventListener('click', function (event) {
      event.preventDefault();
      openFilters();
      button.setAttribute('aria-expanded', 'true');
    });
  });
  $$('[data-filter-close]').forEach(function (button) {
    button.addEventListener('click', function (event) {
      event.preventDefault();
      closeFilters();
    });
  });

  /* ---------------- Saved address autofill ---------------- */
  $$('[data-address-select]').forEach(function (select) {
    select.addEventListener('change', function () {
      var option = select.options[select.selectedIndex];
      if (!option || !option.value) return;
      var map = {
        shipping_full_name: option.getAttribute('data-name'),
        shipping_phone: option.getAttribute('data-phone'),
        shipping_address_type: option.getAttribute('data-type'),
        shipping_house: option.getAttribute('data-house'),
        shipping_street: option.getAttribute('data-street'),
        shipping_landmark: option.getAttribute('data-landmark'),
        shipping_city: option.getAttribute('data-city'),
        shipping_state: option.getAttribute('data-state'),
        shipping_pincode: option.getAttribute('data-pincode'),
        shipping_country: option.getAttribute('data-country')
      };
      Object.keys(map).forEach(function (name) {
        if (map[name] === null) return;
        var field = document.querySelector('[name="' + name + '"]');
        if (field) field.value = map[name];
      });
    });
  });

  /* ---------------- Auto-submit selects (sorting) ---------------- */
  document.addEventListener('change', function (event) {
    var select = event.target.closest('[data-auto-submit]');
    if (!select) return;
    var form = select.form;
    if (!form) return;
    if (form.requestSubmit) {
      form.requestSubmit();
    } else {
      form.submit();
    }
  });

  /* ---------------- Confirmation + print ---------------- */
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form || !form.getAttribute) return;
    var message = form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
      event.preventDefault();
    }
  });

  document.addEventListener('click', function (event) {
    var printBtn = event.target.closest('[data-print]');
    if (printBtn) {
      event.preventDefault();
      window.print();
    }
  });

  /* ---------------- Flash dismiss ---------------- */
  $$('.flash-close').forEach(function (button) {
    button.addEventListener('click', function () {
      var flash = button.closest('.flash');
      if (flash) flash.remove();
    });
  });
})();
