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

        // Try to get product data from the button's form
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
     * Send Enhanced Ecommerce event to GTM
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

        // Build product object for Enhanced Ecommerce
        var product = {
            id: String(productData.id),
            name: productData.name,
            price: price.toFixed(2),
            quantity: quantity
        };

        if (productData.sku) {
            product.sku = productData.sku;
        }

        if (productData.category) {
            product.category = productData.category;
        }

        if (productData.variant) {
            product.variant = productData.variant;
        }

        // Push event to dataLayer
        window.dataLayer.push({
            'event': 'addToCart',
            'ecommerce': {
                'currencyCode': currency,
                'add': {
                    'products': [product]
                },
                'value': value
            }
        });

        // Debug log (remove in production if needed)
        if (window.console && console.log) {
            console.log('Q1 Shop GTM: addToCart event sent', {
                event: 'addToCart',
                product: product,
                value: value,
                currency: currency
            });
        }
    }

    /**
     * Handle added_to_cart event from WooCommerce
     */
    function handleAddedToCart(event, fragments, cartHash, $button) {
        // Get the button that triggered the event
        var $triggerButton = $button || $('.single_add_to_cart_button:last, .add_to_cart_button:last');
        
        // If no button found, try to find the form
        if (!$triggerButton || $triggerButton.length === 0) {
            $triggerButton = $('form.cart button[type="submit"]:last');
        }

        // Get product data
        var productData = getProductData($triggerButton);

        // Send GTM event
        sendGTMAddToCartEvent(productData);
    }

    /**
     * Initialize tracking
     */
    function initGTMTracking() {
        // Listen for WooCommerce added_to_cart event
        $(document.body).on('added_to_cart', function(event, fragments, cartHash, $button) {
            handleAddedToCart(event, fragments, cartHash, $button);
        });

        // Also listen for wc_fragment_refresh which happens after AJAX add to cart
        $(document.body).on('wc_fragment_refresh', function(event, fragments) {
            // Check if this was triggered by an add to cart action
            // by looking for the button that was clicked
            var $button = $('.single_add_to_cart_button.loading, .add_to_cart_button.loading');
            if ($button.length) {
                // Remove loading class and trigger our handler
                $button.removeClass('loading');
                setTimeout(function() {
                    handleAddedToCart(event, fragments, null, $button);
                }, 100);
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

    // Initialize when DOM is ready
    $(document).ready(function() {
        initGTMTracking();
    });

    // Also initialize if DOM is already ready
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(initGTMTracking, 1);
    }

})(jQuery);

