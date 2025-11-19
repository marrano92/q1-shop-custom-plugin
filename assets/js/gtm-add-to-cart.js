/**
 * Q1 Shop GTM Enhanced Ecommerce Add to Cart Tracking
 * 
 * Tracks "add to cart" events for both normal and sticky buttons
 * using Google Tag Manager Enhanced Ecommerce format
 */

(function($) {
    'use strict';

    // Ensure dataLayer exists
    window.dataLayer = window.dataLayer || [];
    
    // Verify jQuery is available
    if (typeof $ === 'undefined' || typeof jQuery === 'undefined') {
        return;
    }

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
     * Get product data from wishlist button
     */
    function getWishlistProductData($button) {
        var productData = {
            id: '',
            name: '',
            sku: '',
            price: '',
            category: '',
            variant: '',
            quantity: 1
        };

        // Get product ID from button
        var productId = $button.attr('data-product-id') || $button.data('product_id');
        if (!productId) {
            // Try to get from parent
            var $parent = $button.closest('.commercekit-wishlist');
            if ($parent.length) {
                productId = $parent.attr('data-product-id') || $parent.data('product_id');
            }
        }

        if (!productId) {
            return productData;
        }

        productData.id = productId;

        // Get product card container - try multiple selectors
        var $productCard = $button.closest('li.product, li.type-product');
        if (!$productCard.length) {
            $productCard = $button.closest('.woocommerce-card, .product, .type-product, .wc-block-grid__product');
        }
        if (!$productCard.length) {
            $productCard = $button.parents('.product, .type-product, .woocommerce-card').first();
        }
        if (!$productCard.length) {
            // Try to find product on single product page
            $productCard = $('.product');
        }

        // Get product name
        var $productTitle = $productCard.find('.woocommerce-loop-product__title a, .woocommerce-LoopProduct-link');
        if (!$productTitle.length) {
            $productTitle = $productCard.find('h2 a, h3 a, .product-title a, a.woocommerce-LoopProduct-link');
        }
        if (!$productTitle.length) {
            $productTitle = $('.product_title, .product .entry-title, h1.product_title');
        }
        if ($productTitle.length) {
            productData.name = $productTitle.first().text().trim() || $productTitle.first().attr('aria-label') || '';
        }

        // Get price
        var $price = $productCard.find('.price .woocommerce-Price-amount.amount, .price .amount');
        if (!$price.length) {
            $price = $productCard.find('.woocommerce-Price-amount, .price');
        }
        if (!$price.length) {
            $price = $('.product .price .amount, .summary .price .amount');
        }
        if ($price.length) {
            var priceText = $price.first().text().replace(/[^\d,.-]/g, '').replace(',', '.');
            productData.price = parseFloat(priceText) || '';
        }

        // Get categories
        var $categories = $productCard.find('.product__categories a');
        if (!$categories.length) {
            $categories = $productCard.find('.posted_in a, .product_meta .posted_in a');
        }
        if (!$categories.length) {
            $categories = $('.posted_in a, .product_meta .posted_in a');
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

        // Get SKU
        var $sku = $productCard.find('.sku, [itemprop="sku"]');
        if (!$sku.length) {
            $sku = $('.sku, [itemprop="sku"]');
        }
        if ($sku.length) {
            productData.sku = $sku.text().trim();
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

        return productData;
    }

    /**
     * Send Enhanced Ecommerce add_to_wishlist event to GTM using gtag format
     */
    function sendGTMAddToWishlistEvent(productData) {
        // Ensure we have required data
        if (!productData.id || !productData.name) {
            return;
        }

        // Prevent duplicate events - check if this product was already tracked
        var lastTrackedProductId = window.sessionStorage.getItem('last_gtm_add_to_wishlist_product_id');
        var lastTrackedTime = parseInt(window.sessionStorage.getItem('last_gtm_add_to_wishlist_time') || '0');
        
        // If the same product was tracked within the last 3 seconds, skip
        if (lastTrackedProductId === String(productData.id) && (Date.now() - lastTrackedTime < 3000)) {
            return; // Skip duplicate event
        }
        
        // Store this event to prevent duplicates
        window.sessionStorage.setItem('last_gtm_add_to_wishlist_product_id', String(productData.id));
        window.sessionStorage.setItem('last_gtm_add_to_wishlist_time', Date.now().toString());

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
        if (typeof gtag !== 'undefined') {
            gtag('event', 'add_to_wishlist', eventData);
        } else {
            // Fallback to dataLayer for GTM
            window.dataLayer = window.dataLayer || [];
            var dataLayerEvent = {
                'event': 'add_to_wishlist',
                'currency': currency,
                'value': parseFloat(value),
                'items': [item]
            };
            window.dataLayer.push(dataLayerEvent);
        }
    }

    /**
     * Send Enhanced Ecommerce event to GTM using gtag format
     */
    function sendGTMAddToCartEvent(productData, $button) {
        // Ensure we have required data
        if (!productData.id || !productData.name) {
            return;
        }

        // Prevent duplicate events using sessionStorage
        // Create a unique key for this product add to cart event
        var eventKey = 'gtm_atc_' + productData.id;
        var lastEventTime = parseInt(window.sessionStorage.getItem(eventKey) || '0');
        var currentTime = Date.now();
        
        // If the same product was tracked within the last 3 seconds, skip
        if (lastEventTime && (currentTime - lastEventTime) < 3000) {
            return; // Skip duplicate event
        }
        
        // Store this event timestamp to prevent duplicates
        window.sessionStorage.setItem(eventKey, currentTime.toString());

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
        if (typeof gtag !== 'undefined') {
            gtag('event', 'add_to_cart', eventData);
        } else {
            // Fallback to dataLayer for GTM
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({
                'event': 'add_to_cart',
                'currency': currency,
                'value': parseFloat(value),
                'items': [item]
            });
        }
    }

    /**
     * Handle added_to_cart event from WooCommerce
     */
    function handleAddedToCart(event, fragments, cartHash, $button) {
        // Get the button that triggered the event
        // WooCommerce passes the button as third parameter
        var $triggerButton = $button;
        
        // If button not provided, try to find it
        if (!$triggerButton || $triggerButton.length === 0) {
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

        // Remove loading class if present
        if ($triggerButton && $triggerButton.length) {
            $triggerButton.removeClass('loading');
        }

        // Check if already tracked to prevent duplicates
        if ($triggerButton && $triggerButton.length && $triggerButton.data('gtm-tracked')) {
            return; // Already tracked, skip
        }

        // Get product data
        var productData = getProductData($triggerButton);

        // Only send event if we have required data
        if (productData.id && productData.name) {
            // Send GTM event
            sendGTMAddToCartEvent(productData, $triggerButton);
            // Mark as tracked
            if ($triggerButton && $triggerButton.length) {
                $triggerButton.data('gtm-tracked', true);
            }
        }
    }

    /**
     * Initialize tracking
     */
    function initGTMTracking() {
        // Listen for WooCommerce added_to_cart event
        // This is the primary method - WooCommerce triggers this after successful add to cart
        $(document.body).on('added_to_cart', function(event, fragments, cartHash, $button) {
            // Mark button as tracked immediately to prevent click listener from also tracking
            if ($button && $button.length) {
                $button.data('gtm-tracked', true);
            }
            handleAddedToCart(event, fragments, cartHash, $button);
        });

        // Also listen for wc_fragment_refresh which happens after AJAX add to cart
        // This is a fallback in case added_to_cart event is not triggered
        // But we'll skip this if added_to_cart already fired
        $(document.body).on('wc_fragment_refresh', function(event, fragments) {
            // Check if this was triggered by an add to cart action
            // by looking for the button that was clicked
            var $button = $('.single_add_to_cart_button.loading, .add_to_cart_button.loading');
            if ($button.length && !$button.data('gtm-tracked')) {
                setTimeout(function() {
                    // Double check it's still not tracked
                    if (!$button.data('gtm-tracked')) {
                        handleAddedToCart(event, fragments, null, $button);
                    }
                }, 100);
            }
        });


        // Direct click listener as additional fallback for AJAX buttons
        // This ensures we catch the event even if WooCommerce events don't fire
        // This is especially important for menu items where events might not propagate correctly
        // Use document instead of document.body for better event capture
        $(document).on('click', '.add_to_cart_button.ajax_add_to_cart', function(e) {
            var $button = $(this);
            var productId = $button.data('product_id') || $button.attr('data-product_id');
            
            // Only track if it's an AJAX button with product_id
            if (productId && $button.hasClass('ajax_add_to_cart')) {
                // Store button reference and mark as not yet tracked
                $button.data('gtm-tracked', false);
                $button.data('gtm-click-time', Date.now());
                
                // Set a timeout to track if WooCommerce event doesn't fire
                // WooCommerce usually fires added_to_cart within 200-500ms
                // We'll wait a bit longer to give WooCommerce time to fire the event
                setTimeout(function() {
                    // Only track if WooCommerce event didn't fire
                    if (!$button.data('gtm-tracked')) {
                        handleAddedToCart(null, null, null, $button);
                    }
                }, 600);
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

    /**
     * Initialize wishlist tracking
     */
    function initWishlistTracking() {
        // Track clicks on wishlist buttons and monitor DOM changes
        // CommerceKit updates the DOM when wishlist is successfully added
        
        // Listen for clicks on wishlist save buttons
        $(document).on('click', '.commercekit-save-wishlist', function(e) {
            var $button = $(this);
            var productId = $button.attr('data-product-id') || $button.data('product_id');
            
            if (!productId) {
                return;
            }
            
            // Mark button as clicked to prevent duplicate tracking
            if ($button.data('gtm-wishlist-tracked')) {
                return;
            }
            
            // Store initial button HTML to detect changes
            var initialHtml = $button.closest('.commercekit-wishlist').html();
            var $wishlistContainer = $button.closest('.commercekit-wishlist');
            
            // Get product data immediately (before DOM changes)
            var productData = getWishlistProductData($button);
            
            // Wait for DOM update (CommerceKit updates the container HTML on success)
            // Check multiple times to catch the update
            var checkCount = 0;
            var maxChecks = 20; // Check for up to 2 seconds (20 * 100ms)
            
            var checkInterval = setInterval(function() {
                checkCount++;
                
                // Check if DOM has been updated (wishlist was added)
                var currentHtml = $wishlistContainer.html();
                if (currentHtml !== initialHtml) {
                    // DOM was updated, wishlist add was successful
                    clearInterval(checkInterval);
                    
                    // Mark as tracked to prevent duplicates
                    $button.data('gtm-wishlist-tracked', true);
                    
                    // Send GTM event
                    if (productData.id && productData.name) {
                        sendGTMAddToWishlistEvent(productData);
                    }
                    
                    // Reset tracking flag after a delay to allow future clicks
                    setTimeout(function() {
                        $button.data('gtm-wishlist-tracked', false);
                    }, 3000);
                } else if (checkCount >= maxChecks) {
                    // Timeout - stop checking
                    clearInterval(checkInterval);
                    $button.data('gtm-wishlist-tracked', false);
                }
            }, 100); // Check every 100ms
        });
        
        // Alternative approach: Intercept fetch calls for wishlist
        // This is a backup in case the DOM monitoring doesn't work
        if (typeof window.fetch !== 'undefined') {
            var originalFetch = window.fetch;
            var wishlistClickData = null;
            
            // Store click data when wishlist button is clicked
            $(document).on('click', '.commercekit-save-wishlist', function(e) {
                var $button = $(this);
                var productId = $button.attr('data-product-id') || $button.data('product_id');
                if (productId) {
                    wishlistClickData = {
                        productId: productId,
                        button: $button,
                        timestamp: Date.now()
                    };
                }
            });
            
            // Intercept fetch calls
            window.fetch = function() {
                var url = arguments[0];
                var options = arguments[1] || {};
                
                // Check if this is a wishlist save action
                if (typeof url === 'string' && url.indexOf('commercekit_save_wishlist') !== -1) {
                    // Call original fetch
                    return originalFetch.apply(this, arguments).then(function(response) {
                        // Clone response to read it
                        var clonedResponse = response.clone();
                        
                        // Parse JSON response
                        clonedResponse.json().then(function(json) {
                            // If wishlist add was successful and we have click data
                            if (json.status == 1 && wishlistClickData && 
                                (Date.now() - wishlistClickData.timestamp < 5000)) {
                                
                                var $button = wishlistClickData.button;
                                var productData = getWishlistProductData($button);
                                
                                if (productData.id && productData.name) {
                                    // Check if already tracked via DOM monitoring
                                    if (!$button.data('gtm-wishlist-tracked')) {
                                        sendGTMAddToWishlistEvent(productData);
                                        $button.data('gtm-wishlist-tracked', true);
                                        setTimeout(function() {
                                            $button.data('gtm-wishlist-tracked', false);
                                        }, 3000);
                                    }
                                }
                                
                                // Clear click data
                                wishlistClickData = null;
                            }
                        }).catch(function() {
                            // Ignore JSON parse errors
                        });
                        
                        return response;
                    });
                }
                
                // For all other fetch calls, use original function
                return originalFetch.apply(this, arguments);
            };
        }
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        initGTMTracking();
        initWishlistTracking();
    });

    // Also initialize if DOM is already ready
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(function() {
            initGTMTracking();
            initWishlistTracking();
        }, 1);
    }

})(jQuery);

