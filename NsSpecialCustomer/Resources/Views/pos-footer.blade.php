<script>
/**
 * Special Customer POS Integration
 */
document.addEventListener('DOMContentLoaded', () => {
    if (typeof POS === 'undefined') {
        return;
    }

    const getOptions = () => {
        if (POS.options) {
            if (typeof POS.options.getValue === 'function') {
                return POS.options.getValue();
            }

            if (Object.prototype.hasOwnProperty.call(POS.options, 'value')) {
                return POS.options.value;
            }
        }

        return {};
    };

    const getSpecialConfig = () => {
        const options = getOptions();
        return options?.specialCustomer || {};
    };

    let processing = false;
    let debounceHandle = null;

    const applySpecialPricing = (order) => {
        const config = getSpecialConfig();

        if (!config?.enabled || !config?.groupId) {
            return;
        }

        const customer = order?.customer || null;
        // Check if customer is in special group by comparing group_id
        const customerGroupId = customer?.group_id || null;
        const isSpecial = !!customerGroupId && parseInt(customerGroupId) === parseInt(config.groupId);

        const products = typeof POS.products?.getValue === 'function'
            ? POS.products.getValue()
            : (POS.products?.value || []);

        let productsUpdated = false;
        let discountChanged = false;

        products.forEach((product) => {
            if (product.product_type !== 'product' || typeof product.$quantities !== 'function') {
                return;
            }

            const quantities = product.$quantities();
            const wholesalePrice = parseFloat(quantities?.wholesale_price_edit || 0);
            const salePrice = parseFloat(quantities?.sale_price_edit || 0);
            const canApplyWholesale = wholesalePrice > 0 && wholesalePrice < salePrice;

            if (isSpecial && product.mode === 'normal' && product.wholesale_applied && !product.special_wholesale_override) {
                product.special_wholesale_override = true;
                productsUpdated = true;
            }

            if (product.mode === 'wholesale' && product.special_wholesale_override) {
                product.special_wholesale_override = false;
                productsUpdated = true;
            }

            if (isSpecial && canApplyWholesale && !product.special_wholesale_override) {
                if (product.mode !== 'wholesale') {
                    product.mode = 'wholesale';
                    product.wholesale_applied = true;
                    productsUpdated = true;
                }
                return;
            }

            if (!isSpecial && product.wholesale_applied && product.mode === 'wholesale') {
                product.mode = 'normal';
                product.wholesale_applied = false;
                product.special_wholesale_override = false;
                productsUpdated = true;
            }

            if (!isSpecial && product.wholesale_applied && product.mode === 'normal') {
                product.wholesale_applied = false;
                product.special_wholesale_override = false;
                productsUpdated = true;
            }
        });

        if (config.discountPercentage > 0) {
            if (isSpecial) {
                if (!order.special_discount_applied) {
                    order.special_discount_previous = {
                        discount_type: order.discount_type,
                        discount_percentage: order.discount_percentage,
                        discount: order.discount,
                    };
                }

                if (order.discount_type !== 'percentage' || order.discount_percentage !== config.discountPercentage || !order.special_discount_applied) {
                    order.discount_type = 'percentage';
                    order.discount_percentage = config.discountPercentage;
                    order.special_discount_applied = true;
                    discountChanged = true;
                }
            } else if (order.special_discount_applied) {
                const previous = order.special_discount_previous || {};
                const previousType = previous.discount_type ?? null;
                const previousPercentage = previous.discount_percentage ?? 0;
                const previousDiscount = previous.discount ?? 0;

                if (order.discount_type !== previousType || order.discount_percentage !== previousPercentage || order.discount !== previousDiscount || order.special_discount_applied) {
                    order.discount_type = previousType;
                    order.discount_percentage = previousPercentage;
                    order.discount = previousDiscount;
                    order.special_discount_applied = false;
                    delete order.special_discount_previous;
                    discountChanged = true;
                }
            }
        }

        if (productsUpdated && typeof POS.recomputeProducts === 'function') {
            POS.recomputeProducts(products);
        }

        if (typeof POS.products?.next === 'function' && productsUpdated) {
            POS.products.next([...products]);
        }

        if ((productsUpdated || discountChanged) && typeof POS.order?.next === 'function') {
            POS.order.next(order);
        }

        if ((productsUpdated || discountChanged) && typeof POS.refreshCart === 'function') {
            POS.refreshCart();
        }
    };

    const queueApply = (order) => {
        if (processing) {
            return;
        }

        processing = true;
        clearTimeout(debounceHandle);

        debounceHandle = setTimeout(() => {
            try {
                applySpecialPricing(order);
            } finally {
                processing = false;
            }
        }, 200);
    };

    if (POS.order && typeof POS.order.subscribe === 'function') {
        POS.order.subscribe((order) => {
            queueApply(order);
        });
    }
});
</script>
