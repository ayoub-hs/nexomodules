<script>
/**
 * Container Management POS Integration
 */
document.addEventListener( 'DOMContentLoaded', () => {
    if ( typeof POS !== 'undefined' ) {
        
        /**
         * Component: Container POS Popup
         */
        const nsContainerPosPopup = {
            name: 'ns-container-pos-popup',
            props: [ 'popup' ],
            template: `
                <div class="ns-box shadow-xl w-[90vw] md:w-[60vw] lg:w-[40vw]">
                    <div class="border-b ns-box-header text-center font-semibold text-2xl py-2 relative">
                        <h2>@{{ __( 'Manage Containers' ) }}</h2>
                        <div class="absolute right-0 top-0 h-full flex items-center pr-4">
                            <button @click="closePopup()" class="text-gray-500 hover:text-black">
                                <i class="las la-times text-xl"></i>
                            </button>
                        </div>
                    </div>
                    <div class="h-[60vh] xl:h-[60vh] overflow-y-auto ns-scrollbar p-4 text-black">
                        <table class="w-full text-sm border-collapse text-black">
                            <thead>
                                <tr class="text-left border-b ns-box-header">
                                    <th class="py-2 px-2 text-black">@{{ __( 'Product' ) }}</th>
                                    <th class="py-2 px-2 text-black text-center">@{{ __( 'Required' ) }}</th>
                                    <th class="py-2 px-2 text-center text-black">@{{ __( 'Override' ) }}</th>
                                </tr>
                            </thead>
                            <tbody class="ns-box-body text-black">
                                <template v-for="(product, index) in products" :key="index">
                                    <tr v-if="product.container_type" class="border-b hover:bg-gray-50">
                                        <td class="py-3 px-2 text-black">
                                            <p class="font-semibold text-black">@{{ getName(product) }}</p>
                                            <p class="text-xs text-gray-500">@{{ product.container_type.name }}</p>
                                        </td>
                                        <td class="py-3 px-2 text-center text-black">
                                            @{{ product.container_quantity }}
                                        </td>
                                        <td class="py-3 px-2 text-center text-black text-font">
                                             <div class="input-group flex-auto border-2 rounded">
                                                <input 
                                                    type="number" 
                                                    class="outline-hidden w-full p-2 text-center text-black bg-white" 
                                                    :disabled="product.container_tracking_enabled === false"
                                                    :value="getOverrideValue(product)"
                                                    @input="setOverride(product, $event.target.value)"
                                                    min="0"
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-if="!hasContainerProducts">
                                    <td colspan="4" class="py-10 text-center text-gray-500">
                                        @{{ __( 'No products in cart require containers.' ) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t ns-box-footer flex justify-end">
                        <button class="rounded px-6 py-2 shadow-sm font-semibold transition-all bg-primary text-white hover:bg-primary-dark" @click="closePopup()">@{{ __( 'Done' ) }}</button>
                    </div>
                </div>
            `,
            data() {
                return {
                    products: [],
                    subscriber: null,
                }
            },
            computed: {
                hasContainerProducts() {
                    return this.products.some( p => p.container_type );
                }
            },
            mounted() {
                this.subscriber = POS.products.subscribe( products => {
                    this.products = products;
                });
            },
            unmounted() {
                if ( this.subscriber ) {
                    this.subscriber.unsubscribe();
                }
            },
            methods: {
                __( text ) { return typeof window.__ === 'function' ? window.__( text ) : text; },
                getName( product ) {
                    const name = product.name || '';
                    return name.split(' (Incl.')[0].split(' (No Container)')[0];
                },
                closePopup() {
                    if ( this.popup ) {
                        this.popup.close();
                    }
                    POS.refreshCart();
                },
                getOverrideValue( product ) {
                    if ( product.container_quantity_override !== undefined && product.container_quantity_override !== null ) {
                        return product.container_quantity_override;
                    }
                    return product.container_quantity;
                },
                setOverride( product, value ) {
                    product.container_quantity_override = value === '' ? null : parseInt( value );
                    this.$forceUpdate();
                    POS.refreshCart();
                }
            }
        };

        const nsContainerPosButton = {
            name: 'ns-container-pos-button',
            props: [ 'order', 'settings', 'options' ],
            template: `
                <div class="px-2 border-r last:border-r-0">
                    <button @click="openPopup()" class="flex items-center hover:text-primary transition-colors py-2 text-black">
                        <i class="las la-box-open text-xl mr-1"></i>
                        <span class="text-sm font-semibold text-black">@{{ __( 'Containers' ) }}</span>
                    </button>
                </div>
            `,
            methods: {
                __( text ) { return typeof window.__ === 'function' ? window.__( text ) : text; },
                openPopup() {
                    Popup.show( nsContainerPosPopup );
                }
            }
        };

        const Vue = window.Vue || (window.app ? window.app.__vue_app__ : null);
        const markRaw = ( component ) => {
            return ( Vue && typeof Vue.markRaw === 'function' ) ? Vue.markRaw( component ) : component;
        };

        POS.cartHeaderButtons.subscribe( buttons => {
            if ( ! buttons.nsContainerPosButton ) {
                const updatedButtons = {
                    ...buttons,
                    nsContainerPosButton: markRaw( nsContainerPosButton )
                };
                setTimeout( () => {
                    POS.cartHeaderButtons.next( updatedButtons );
                }, 100 );
            }
        });

        const updateContainerLabels = ( products ) => {
            const options = POS.options.value;
            if ( ! options || ! options.container_management || ! options.container_management.links ) {
                return;
            }

            const links = options.container_management.links;

            products.forEach( p => {
                const productId = parseInt( p.product_id || p.product.id );
                const unitId = parseInt( p.unit_quantity_id || p.unit_id );
                
                let link = links.find( l => l.product_id === productId && l.unit_id === unitId );
                if ( ! link && unitId ) {
                    link = links.find( l => l.product_id === productId && ( l.unit_id === null || l.unit_id === undefined ) );
                }

                if ( link ) {
                    p.container_type = {
                        id: link.container_type_id,
                        name: link.container_type_name
                    };
                    
                    p.container_quantity = Math.floor( parseFloat( p.quantity ) / parseFloat( link.capacity ) );
                    
                    if ( p.container_tracking_enabled === undefined ) {
                        p.container_tracking_enabled = false;
                    }

                    // CAUTION: Modifying p.name might trigger reactive loops if not careful.
                    // We only modify it if we have a stable base name.
                    if ( ! p.base_name ) {
                        p.base_name = p.name.split( ' (Incl.' )[0].split( ' (No Container)' )[0];
                    }
                    
                    if ( p.container_tracking_enabled ) {
                        const qty = ( p.container_quantity_override !== undefined && p.container_quantity_override !== null ) 
                            ? p.container_quantity_override 
                            : p.container_quantity;
                        
                        const newName = `${p.base_name} (Incl. ${qty}x ${p.container_type.name})`;
                        if ( p.name !== newName ) p.name = newName;
                    } else {
                        const newName = `${p.base_name} (No Container)`;
                        if ( p.name !== newName ) p.name = newName;
                    }
                } else {
                    p.container_type = null;
                }
            });
        };

        let injectionTimeout = null;

        const injectContainerToggle = () => {
            const productOptions = document.querySelectorAll('.product-item .product-options');
            productOptions.forEach( ( container ) => {
                const productItem = container.closest( '.product-item' );
                if ( ! productItem ) return;
                
                const index = parseInt( productItem.getAttribute( 'product-index' ) );
                const products = POS.products.getValue();
                const product = products[index];

                if ( ! product || ! product.container_type ) {
                    const existing = container.querySelector( '.ns-container-toggle' );
                    if (existing) existing.remove();
                    return;
                }

                let toggleDiv = container.querySelector( '.ns-container-toggle' );
                if ( ! toggleDiv ) {
                    toggleDiv = document.createElement( 'div' );
                    toggleDiv.className = 'px-1 ns-container-toggle';
                    const a = document.createElement( 'a' );
                    toggleDiv.appendChild( a );
                    
                    const wholesaleToggle = container.querySelector( 'i.la-award' )?.closest( '.px-1' );
                    if ( wholesaleToggle ) {
                        wholesaleToggle.parentNode.insertBefore( toggleDiv, wholesaleToggle.nextSibling );
                    } else {
                        container.appendChild( toggleDiv );
                    }
                }

                const a = toggleDiv.querySelector( 'a' );
                const isActive = product.container_tracking_enabled === true;
                const newClass = 'cursor-pointer outline-hidden border-dashed py-1 border-b text-sm transition-all ' + 
                                ( isActive ? 'text-green-600 border-green-600 font-bold' : 'border-secondary text-gray-400 opacity-50' );
                const newIcon = `<i class="las ${isActive ? 'la-box-open' : 'la-box'} text-xl"></i>`;

                if ( a.className !== newClass ) a.className = newClass;
                if ( a.innerHTML !== newIcon ) a.innerHTML = newIcon;
                
                a.onclick = ( e ) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const currentProducts = POS.products.getValue();
                    currentProducts[index].container_tracking_enabled = ! currentProducts[index].container_tracking_enabled;
                    POS.products.next( [...currentProducts] );
                    POS.refreshCart();
                };
            });
        };

        POS.products.subscribe( products => {
            updateContainerLabels( products );
            
            // Clear any pending injection
            if ( injectionTimeout ) clearTimeout( injectionTimeout );
            
            // Use a simple polling-style injection every time products change, 
            // but debounced to let Vue finish its job.
            injectionTimeout = setTimeout( () => {
                injectContainerToggle();
                // Run a second time shortly after just in case Vue re-rendered again
                setTimeout( injectContainerToggle, 500 );
            }, 300 );
        });

        // Periodic check every few seconds instead of MutationObserver 
        // to be 100% safe from infinite loops.
        setInterval( injectContainerToggle, 3000 );
    }
});
</script>
