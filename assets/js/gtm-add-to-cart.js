/**
 * Q1 Shop GTM Enhanced Ecommerce Add to Cart Tracking
 * 
 * Tracks "add to cart" events for both normal and sticky buttons
 * using Google Tag Manager Enhanced Ecommerce format
 */

// Log immediately when script file is loaded (before jQuery check)
console.log('Q1 Shop GTM: Script file loaded and executing...');

(function($) {
    'use strict';

    console.log('Q1 Shop GTM: Script wrapper function executing...');

    // Ensure dataLayer exists
    window.dataLayer = window.dataLayer || [];
    
    // Verify jQuery is available
    if (typeof $ === 'undefined' || typeof jQuery === 'undefined') {
        console.error('Q1 Shop GTM: jQuery is not available!', {
            $: typeof $,
            jQuery: typeof jQuery
        });
        return;
    }
    
    console.log('Q1 Shop GTM: Script wrapper executed', {
        jQuery: typeof $ !== 'undefined',
        jQueryVersion: typeof $ !== 'undefined' ? $.fn.jquery : 'N/A'
    });

    /**
     * Get product data from the form or page
     */
    function getProductData($button) {
        var productData = {
            id: '',
            name: '',
            sku: '',
            price: '',
            category: '',
            variant: '',
            quantity: 1
        };

        // Check if this is a loop/archive button (has data-product_id attribute)
        var productId = $button.data('product_id') || $button.attr('data-product_id');
        var quantity = $button.data('quantity') || $button.attr('data-quantity') || 1;

        // If it's a loop button, get data from the product card
        if (productId && $button.hasClass('ajax_add_to_cart')) {
            productData.id = productId;
            productData.quantity = parseInt(quantity, 10) || 1;

            // Get product card container - try multiple selectors to find the card
            // First try closest li.product (works for menu items and regular loops)
            var $productCard = $button.closest('li.product, li.type-product');
            if (!$productCard.length) {
                // Try other product containers
                $productCard = $button.closest('.woocommerce-card, .product, .type-product, .wc-block-grid__product');
            }
            if (!$productCard.length) {
                // Fallback: go up through parents to find product container
                $productCard = $button.parents('.product, .type-product, .woocommerce-card').first();
            }
            if (!$productCard.length) {
                // Last resort: find the closest element with product classes in the same context
                $productCard = $button.closest('.woocommerce').find('li.product').first();
            }

            // Get product name from card - try multiple selectors
            var $productTitle = $productCard.find('.woocommerce-loop-product__title a, .woocommerce-LoopProduct-link');
            if (!$productTitle.length) {
                $productTitle = $productCard.find('h2 a, h3 a, .product-title a, a.woocommerce-LoopProduct-link');
            }
            if ($productTitle.length) {
                productData.name = $productTitle.text().trim() || $productTitle.attr('aria-label') || '';
            }

            // Get price from card - check multiple locations
            var $price = $productCard.find('.price .woocommerce-Price-amount.amount, .price .amount');
            if (!$price.length) {
                $price = $productCard.find('.woocommerce-Price-amount, .price');
            }
            if ($price.length) {
                var priceText = $price.first().text().replace(/[^\d,.-]/g, '').replace(',', '.');
                productData.price = parseFloat(priceText) || '';
            }

            // Get categories from card - check for .product__categories first (from your HTML structure)
            var $categories = $productCard.find('.product__categories a');
            if (!$categories.length) {
                $categories = $productCard.find('.posted_in a, .product_meta .posted_in a');
            }
            if ($categories.length) {
                var categories = [];
                $categories.each(function() {
                    var catText = $(this).text().trim();
                    if (catText) {
                        categories.push(catText);
                    }
                });
                productData.category = categories[0] || '';
                if (categories.length > 1) {
                    productData.categories = categories;
                }
            }

            // Try to get SKU if available
            var $sku = $productCard.find('.sku, [itemprop="sku"]');
            if ($sku.length) {
                productData.sku = $sku.text().trim();
            }

            // If we still don't have name or price, try to find them in the same container as the button
            if (!productData.name || !productData.price) {
                // Get the immediate parent container (woocommerce-card__header)
                var $header = $button.closest('.woocommerce-card__header');
                if ($header.length) {
                    if (!productData.name) {
                        var $title = $header.find('.woocommerce-loop-product__title a');
                        if ($title.length) {
                            productData.name = $title.text().trim() || $title.attr('aria-label') || '';
                        }
                    }
                    if (!productData.price) {
                        var $headerPrice = $header.find('.price .amount');
                        if ($headerPrice.length) {
                            var priceText = $headerPrice.first().text().replace(/[^\d,.-]/g, '').replace(',', '.');
                            productData.price = parseFloat(priceText) || '';
                        }
                    }
                    if (!productData.category) {
                        var $headerCategories = $header.find('.product__categories a');
                        if ($headerCategories.length) {
                            var categories = [];
                            $headerCategories.each(function() {
                                categories.push($(this).text().trim());
                            });
                            productData.category = categories[0] || '';
                            if (categories.length > 1) {
                                productData.categories = categories;
                            }
                        }
                    }
                }
            }

            return productData;
        }

        // Try to get product data from the button's form (single product page)
        var $form = $button.closest('form.cart');
        
        if ($form.length) {
            // Get product ID from form or button
            var productId = $form.find('input[name="add-to-cart"]').val() || 
                           $form.find('button[name="add-to-cart"]').val() ||
                           $form.data('product_id') ||
                           $button.val() ||
                           $button.data('product_id');

            // Get quantity - check both normal and sticky forms
            var quantity = $form.find('input[name="quantity"]').val() || 
                          $form.find('.qty').val();
            if (!quantity) {
                var $stickyForm = $('.commercekit_sticky-atc form.cart');
                if ($stickyForm.length) {
                    quantity = $stickyForm.find('input[name="quantity"]').val() || 
                              $stickyForm.find('.qty').val();
                }
            }
            quantity = quantity || 1;

            // Get variation data if it's a variable product
            var variationId = $form.find('input[name="variation_id"]').val();
            var variationData = {};

            // Also check in sticky form if variation_id not found
            if (!variationId) {
                var $stickyForm = $('.commercekit_sticky-atc form.cart');
                if ($stickyForm.length) {
                    variationId = $stickyForm.find('input[name="variation_id"]').val();
                    if (variationId) {
                        $form = $stickyForm;
                    }
                }
            }

            if (variationId) {
                productData.id = variationId;
                
                // Get variation attributes from both forms
                var $allForms = $form.add($('.commercekit_sticky-atc form.cart, form.commercekit_sticky-atc-origin'));
                
                $allForms.find('select[name^="attribute_"]').each(function() {
                    var $select = $(this);
                    var attrName = $select.attr('name').replace('attribute_', '').replace('pa_', '');
                    var attrValue = $select.find('option:selected').text() || $select.val();
                    if (attrValue && attrValue !== '' && attrValue !== 'Choose an option') {
                        variationData[attrName] = attrValue;
                    }
                });

                // Also check for swatches or other variation inputs
                $allForms.find('.cgkit-attribute-swatches .cgkit-swatch-selected, .variation-selected').each(function() {
                    var $swatch = $(this);
                    var attrName = $swatch.closest('tr, .variation').find('label, th').text().trim();
                    var attrValue = $swatch.text().trim() || $swatch.data('value') || $swatch.attr('title');
                    if (attrValue && attrValue !== '') {
                        variationData[attrName] = attrValue;
                    }
                });

                // Build variant string
                var variantParts = [];
                for (var key in variationData) {
                    if (variationData.hasOwnProperty(key)) {
                        variantParts.push(variationData[key]);
                    }
                }
                productData.variant = variantParts.join(' / ');
            } else {
                productData.id = productId;
            }

            productData.quantity = parseInt(quantity, 10) || 1;

            // Try to get product name from page
            var $productTitle = $('.product_title, .product .entry-title, h1.product_title, .content-title');
            if (!$productTitle.length) {
                $productTitle = $('.product-info .content-title, .commercekit-pdp-sticky-inner .content-title');
            }
            if ($productTitle.length) {
                productData.name = $productTitle.first().text().trim();
            }

            // Try to get price - check variation price first, then regular price
            var $price = $form.find('.single_variation_wrap .price .amount, .woocommerce-variation-price .amount, .cgkit-as-variation-price .price .amount');
            if (!$price.length) {
                $price = $form.find('.price .amount, .woocommerce-Price-amount');
            }
            // Also check in the sticky ATC form
            if (!$price.length) {
                $price = $('.commercekit_sticky-atc .price .amount, .commercekit-pdp-sticky-inner .price .amount');
            }
            // Check main product price
            if (!$price.length) {
                $price = $('.product .price .amount, .summary .price .amount');
            }
            if ($price.length) {
                var priceText = $price.first().text().replace(/[^\d,.-]/g, '').replace(',', '.');
                productData.price = parseFloat(priceText) || '';
            }

            // Try to get SKU
            var $sku = $('.sku, [itemprop="sku"]');
            if ($sku.length) {
                productData.sku = $sku.text().trim();
            }

            // Try to get category
            var $category = $('.posted_in a, .product_meta .posted_in a');
            if ($category.length) {
                productData.category = $category.first().text().trim();
            }

            // Use localized data if available
            if (typeof q1ShopGTM !== 'undefined' && q1ShopGTM.product) {
                if (!productData.name && q1ShopGTM.product.name) {
                    productData.name = q1ShopGTM.product.name;
                }
                if (!productData.sku && q1ShopGTM.product.sku) {
                    productData.sku = q1ShopGTM.product.sku;
                }
                if (!productData.price && q1ShopGTM.product.price) {
                    productData.price = q1ShopGTM.product.price;
                }
                if (!productData.category && q1ShopGTM.product.category) {
                    productData.category = q1ShopGTM.product.category;
                }
                if (!productData.id && q1ShopGTM.product.id) {
                    productData.id = q1ShopGTM.product.id;
                }
            }
        }

        return productData;
    }

    /**
     * Send Enhanced Ecommerce event to GTM using gtag format
     */
    function sendGTMAddToCartEvent(productData) {
        // Ensure we have required data
        if (!productData.id || !productData.name) {
            console.warn('Q1 Shop GTM: Missing required product data', productData);
            return;
        }

        var currency = 'EUR';
        if (typeof q1ShopGTM !== 'undefined' && q1ShopGTM.currency) {
            currency = q1ShopGTM.currency;
        }

        // Calculate total value
        var price = parseFloat(productData.price) || 0;
        var quantity = parseInt(productData.quantity, 10) || 1;
        var value = (price * quantity).toFixed(2);

        // Build item object for gtag event
        var item = {
            item_id: productData.sku || String(productData.id),
            item_name: productData.name,
            price: parseFloat(price.toFixed(2)),
            quantity: quantity,
            index: 0
        };

        // Add optional fields
        if (productData.category) {
            item.item_category = productData.category;
            
            // If we have multiple categories, add them as item_category2, item_category3, etc.
            if (productData.categories && Array.isArray(productData.categories)) {
                productData.categories.forEach(function(cat, index) {
                    if (index > 0) {
                        item['item_category' + (index + 1)] = cat;
                    }
                });
            }
        }

        if (productData.variant) {
            item.item_variant = productData.variant;
        }

        if (productData.brand) {
            item.item_brand = productData.brand;
        }

        // Build event data object
        var eventData = {
            currency: currency,
            value: parseFloat(value),
            items: [item]
        };

        // Send event using gtag if available, otherwise use dataLayer
        console.log('Q1 Shop GTM: Preparing to send event', eventData);
        
        if (typeof gtag !== 'undefined') {
            console.log('Q1 Shop GTM: Using gtag to send event');
            gtag('event', 'add_to_cart', eventData);
        } else {
            // Fallback to dataLayer for GTM
            console.log('Q1 Shop GTM: gtag not available, using dataLayer');
            window.dataLayer = window.dataLayer || [];
            var dataLayerEvent = {
                'event': 'add_to_cart',
                'currency': currency,
                'value': parseFloat(value),
                'items': [item]
            };
            console.log('Q1 Shop GTM: Pushing to dataLayer', dataLayerEvent);
            window.dataLayer.push(dataLayerEvent);
        }

        // Debug log
        console.log('Q1 Shop GTM: add_to_cart event sent successfully', {
            event: 'add_to_cart',
            item: item,
            value: value,
            currency: currency,
            hasGtag: typeof gtag !== 'undefined',
            hasDataLayer: typeof window.dataLayer !== 'undefined'
        });
    }

    /**
     * Handle added_to_cart event from WooCommerce
     */
    function handleAddedToCart(event, fragments, cartHash, $button) {
        console.log('Q1 Shop GTM: handleAddedToCart called', { event, fragments, cartHash, button: $button });
        
        // Get the button that triggered the event
        // WooCommerce passes the button as third parameter
        var $triggerButton = $button;
        
        // If button not provided, try to find it
        if (!$triggerButton || $triggerButton.length === 0) {
            console.log('Q1 Shop GTM: Button not provided, searching...');
            // Look for buttons with loading class (just clicked)
            $triggerButton = $('.add_to_cart_button.loading, .single_add_to_cart_button.loading');
            
            // If still not found, try to find the last clicked button
            if (!$triggerButton || $triggerButton.length === 0) {
                $triggerButton = $('.add_to_cart_button:last, .single_add_to_cart_button:last');
            }
            
            // If still not found, try to find form submit button
            if (!$triggerButton || $triggerButton.length === 0) {
                $triggerButton = $('form.cart button[type="submit"]:last');
            }
        }

        console.log('Q1 Shop GTM: Using button for tracking', $triggerButton);

        // Remove loading class if present
        if ($triggerButton && $triggerButton.length) {
            $triggerButton.removeClass('loading');
            // Mark as tracked
            $triggerButton.data('gtm-tracked', true);
        }

        // Get product data
        var productData = getProductData($triggerButton);
        
        console.log('Q1 Shop GTM: Product data retrieved', productData);

        // Only send event if we have required data
        if (productData.id && productData.name) {
            // Send GTM event
            console.log('Q1 Shop GTM: Sending event to GTM', productData);
            sendGTMAddToCartEvent(productData);
        } else {
            console.warn('Q1 Shop GTM: Could not retrieve product data for tracking', {
                button: $triggerButton,
                productData: productData
            });
        }
    }

    /**
     * Initialize tracking
     */
    function initGTMTracking() {
        console.log('Q1 Shop GTM: Initializing tracking...');
        
        // Listen for WooCommerce added_to_cart event
        $(document.body).on('added_to_cart', function(event, fragments, cartHash, $button) {
            console.log('Q1 Shop GTM: added_to_cart event received', { fragments, cartHash, button: $button });
            handleAddedToCart(event, fragments, cartHash, $button);
        });

        // Also listen for wc_fragment_refresh which happens after AJAX add to cart
        // This is a fallback in case added_to_cart event is not triggered
        $(document.body).on('wc_fragment_refresh', function(event, fragments) {
            // Check if this was triggered by an add to cart action
            // by looking for the button that was clicked
            var $button = $('.single_add_to_cart_button.loading, .add_to_cart_button.loading');
            if ($button.length) {
                setTimeout(function() {
                    handleAddedToCart(event, fragments, null, $button);
                }, 100);
            }
        });

        // Also add a more generic listener for any element with data-product_id
        // This catches buttons that might not have the exact classes we expect
        $(document).on('click', '[data-product_id]', function(e) {
            var $element = $(this);
            var productId = $element.data('product_id') || $element.attr('data-product_id');
            var hasAjaxClass = $element.hasClass('ajax_add_to_cart') || $element.hasClass('add_to_cart_button');
            
            // Only process if it looks like an add to cart button
            if (productId && (hasAjaxClass || $element.attr('href') === '#')) {
                console.log('Q1 Shop GTM: Element with data-product_id clicked (generic listener)', {
                    element: $element,
                    productId: productId,
                    classes: $element.attr('class'),
                    href: $element.attr('href')
                });
            }
        });

        // Direct click listener as additional fallback for AJAX buttons
        // This ensures we catch the event even if WooCommerce events don't fire
        // This is especially important for menu items where events might not propagate correctly
        // Use document instead of document.body for better event capture
        $(document).on('click', '.add_to_cart_button.ajax_add_to_cart', function(e) {
            var $button = $(this);
            var productId = $button.data('product_id') || $button.attr('data-product_id');
            
            console.log('Q1 Shop GTM: Button clicked (direct listener)', { 
                button: $button, 
                productId: productId,
                hasAjaxClass: $button.hasClass('ajax_add_to_cart'),
                classes: $button.attr('class'),
                href: $button.attr('href')
            });
            
            // Only track if it's an AJAX button with product_id
            if (productId && $button.hasClass('ajax_add_to_cart')) {
                // Store button reference and mark as not yet tracked
                $button.data('gtm-tracked', false);
                $button.data('gtm-click-time', Date.now());
                
                console.log('Q1 Shop GTM: Setting up tracking for button', $button);
                
                // Track immediately as primary method, then verify if event fires
                var trackedImmediately = false;
                
                // Try to track immediately
                setTimeout(function() {
                    if (!$button.data('gtm-tracked')) {
                        console.log('Q1 Shop GTM: Tracking immediately (before WooCommerce event)', $button);
                        handleAddedToCart(null, null, null, $button);
                        trackedImmediately = true;
                    }
                }, 100);
                
                // Set a timeout to verify if the event fires
                // WooCommerce usually fires added_to_cart within 200-500ms
                setTimeout(function() {
                    if (!$button.data('gtm-tracked') && !trackedImmediately) {
                        // Event didn't fire, track manually
                        console.log('Q1 Shop GTM: Tracking manually - added_to_cart event did not fire', $button);
                        handleAddedToCart(null, null, null, $button);
                        $button.data('gtm-tracked', true);
                    } else if ($button.data('gtm-tracked')) {
                        console.log('Q1 Shop GTM: Already tracked via WooCommerce event');
                    }
                }, 800);
            }
        });

        // Fallback: Listen for form submission on single product pages
        $('form.cart').on('submit', function(e) {
            var $form = $(this);
            var $button = $form.find('button[type="submit"], .single_add_to_cart_button');
            
            // Store button reference for later use
            $form.data('submit-button', $button);
        });
    }

    // Initialize immediately and also on DOM ready
    function initializeTracking() {
        console.log('Q1 Shop GTM: Initializing tracking...', {
            jQuery: typeof jQuery !== 'undefined',
            dataLayer: typeof window.dataLayer !== 'undefined',
            gtag: typeof gtag !== 'undefined',
            buttonsCount: $('.add_to_cart_button.ajax_add_to_cart').length
        });
        initGTMTracking();
        
        // Log all buttons found
        var $allButtons = $('.add_to_cart_button.ajax_add_to_cart');
        console.log('Q1 Shop GTM: Found ' + $allButtons.length + ' AJAX add to cart buttons');
        $allButtons.each(function(index) {
            var $btn = $(this);
            console.log('Q1 Shop GTM: Button ' + (index + 1), {
                productId: $btn.data('product_id') || $btn.attr('data-product_id'),
                classes: $btn.attr('class'),
                inMenu: $btn.closest('.menu-item, nav').length > 0
            });
        });
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        console.log('Q1 Shop GTM: DOM ready, initializing...');
        initializeTracking();
    });

    // Also initialize if DOM is already ready
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        console.log('Q1 Shop GTM: DOM already ready, initializing immediately...');
        setTimeout(initializeTracking, 1);
    }

    // Log script load - this should appear immediately when script loads
    console.log('Q1 Shop GTM: Script loaded', {
        jQuery: typeof jQuery !== 'undefined',
        dataLayer: typeof window.dataLayer !== 'undefined',
        gtag: typeof gtag !== 'undefined',
        readyState: document.readyState
    });
    
    // Force immediate initialization check
    console.log('Q1 Shop GTM: Checking for buttons immediately...');
    setTimeout(function() {
        var $buttons = $('.add_to_cart_button.ajax_add_to_cart, [data-product_id]');
        console.log('Q1 Shop GTM: Immediate check found ' + $buttons.length + ' potential buttons');
        if ($buttons.length > 0) {
            $buttons.each(function(i) {
                var $btn = $(this);
                console.log('Q1 Shop GTM: Button ' + (i+1), {
                    productId: $btn.data('product_id') || $btn.attr('data-product_id'),
                    classes: $btn.attr('class'),
                    inMenu: $btn.closest('.menu-item, nav, .menu').length > 0
                });
            });
        }
    }, 500);

})(jQuery);

// Log after script execution
console.log('Q1 Shop GTM: Script execution completed');

