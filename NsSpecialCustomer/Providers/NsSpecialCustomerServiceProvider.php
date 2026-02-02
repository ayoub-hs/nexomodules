<?php

namespace Modules\NsSpecialCustomer\Providers;

use App\Events\RenderFooterEvent;
use App\Events\SettingsSavedEvent;
use App\Models\Customer;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Modules\NsSpecialCustomer\Crud\CustomerTopupCrud;
use Modules\NsSpecialCustomer\Crud\OutstandingTicketCrud;
use Modules\NsSpecialCustomer\Crud\SpecialCashbackCrud;
use Modules\NsSpecialCustomer\Crud\SpecialCustomerCrud;
use Modules\NsSpecialCustomer\Listeners\RenderFooterListener;
use Modules\NsSpecialCustomer\Services\AuditService;
use Modules\NsSpecialCustomer\Services\CashbackService;
use Modules\NsSpecialCustomer\Services\SpecialCustomerService;
use Modules\NsSpecialCustomer\Services\WalletService;
use TorMorten\Eventy\Facades\Events as Hook;

class NsSpecialCustomerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register middleware
        $router = $this->app['router'];
        $router->aliasMiddleware( 'ns.special-customer.permission', \Modules\NsSpecialCustomer\Http\Middleware\CheckSpecialCustomerPermission::class );
        $router->aliasMiddleware( 'ns.special-customer.ownership', \Modules\NsSpecialCustomer\Http\Middleware\EnsureCustomerOwnership::class );
        $router->aliasMiddleware( 'ns.special-customer.balance-access', \Modules\NsSpecialCustomer\Http\Middleware\CheckBalanceAccess::class );

        // Register services as singletons for performance
        $this->app->singleton( SpecialCustomerService::class, function ( $app ) {
            return new SpecialCustomerService( $app->make( \App\Services\Options::class ) );
        } );

        $this->app->singleton( CashbackService::class, function ( $app ) {
            return new CashbackService(
                $app->make( SpecialCustomerService::class ),
                $app->make( WalletService::class )
            );
        } );

        $this->app->singleton( WalletService::class, function ( $app ) {
            return new WalletService(
                $app->make( \App\Services\CustomerService::class ),
                $app->make( AuditService::class )
            );
        } );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom( __DIR__ . '/../Database/Migrations' );
        $this->loadViewsFrom( __DIR__ . '/../Resources/Views', 'NsSpecialCustomer' );
        $this->loadRoutesFrom( __DIR__ . '/../Routes/api.php' );
        $this->loadRoutesFrom( __DIR__ . '/../Routes/web.php' );

        // Load module permissions (legacy format)
        if ( defined( 'NEXO_CREATE_PERMISSIONS' ) ) {
            include_once dirname( __FILE__ ) . '/../Database/Permissions/special-customer.php';
        }

        // View composer for all module views
        View::composer( 'NsSpecialCustomer::*', function ( $view ) {
            $view->with( 'specialCustomerConfig', app( SpecialCustomerService::class )->getConfig() );
        } );

        // Register CRUD resources
        Hook::addFilter( 'ns-crud-resource', function ( $identifier ) {
            switch ( $identifier ) {
                case 'ns.special-customers':
                    return SpecialCustomerCrud::class;
                case 'ns.special-customer-cashback':
                    return SpecialCashbackCrud::class;
                case 'ns.special-customer-topup':
                    return CustomerTopupCrud::class;
                case 'ns.outstanding-tickets':
                    return OutstandingTicketCrud::class;
            }

            return $identifier;
        } );

        // Dashboard Menu - Add Special Customer menu (same pattern as Container Management)
        Hook::addFilter( 'ns-dashboard-menus', function ( $menus ) {
            $specialCustomerMenu = [
                'label' => __( 'Special Customer' ),
                'icon' => 'la-star',
                'childrens' => [
                    'special-customer-list' => [
                        'label' => __( 'Customer List' ),
                        'href' => ns()->url( '/dashboard/special-customer/customers' ),
                    ],
                    'special-customer-topup' => [
                        'label' => __( 'Top-up Account' ),
                        'href' => ns()->url( '/dashboard/special-customer/topup' ),
                    ],
                    'special-customer-outstanding' => [
                        'label' => __( 'Outstanding Tickets' ),
                        'href' => ns()->url( '/dashboard/special-customer/outstanding-tickets' ),
                    ],
                    'special-customer-cashback' => [
                        'label' => __( 'Cashback History' ),
                        'href' => ns()->url( '/dashboard/special-customer/cashback' ),
                    ],
                    'special-customer-statistics' => [
                        'label' => __( 'Statistics' ),
                        'href' => ns()->url( '/dashboard/special-customer/statistics' ),
                    ],
                ],
            ];

            // Insert after customers menu if it exists
            if ( isset( $menus['customers'] ) ) {
                $newMenus = [];
                foreach ( $menus as $key => $value ) {
                    $newMenus[$key] = $value;
                    if ( $key === 'customers' ) {
                        $newMenus['special-customer'] = $specialCustomerMenu;
                    }
                }
                return $newMenus;
            }

            $menus['special-customer'] = $specialCustomerMenu;
            return $menus;
        } );

        Hook::addFilter( 'ns-pos-settings-tabs', function ( $tabs ) {
            $tabs['special_customer'] = include __DIR__ . '/../Settings/pos.php';

            return $tabs;
        } );

        Event::listen( RenderFooterEvent::class, RenderFooterListener::class );
        Event::listen( SettingsSavedEvent::class, function ( SettingsSavedEvent $event ) {
            $keys = array_keys( (array) $event->data );

            foreach ( $keys as $key ) {
                if ( Str::startsWith( $key, 'ns_special_' ) ) {
                    app( SpecialCustomerService::class )->clearConfigCache();
                    break;
                }
            }
        } );

        // Register POS hooks for special customer functionality
        $this->registerPOSHooks();

        // Register outstanding tickets hook-based button injection
        $this->registerOutstandingTicketsHooks();
    }

    /**
     * Register outstanding tickets hooks for hook-based button injection
     */
    private function registerOutstandingTicketsHooks(): void
    {
        // Hook-based button injection for outstanding tickets options
        Hook::addFilter(
            'ns-outstanding-tickets-options',
            function (array $options, $ticket) {
                if (
                    $ticket->customer?->is_special &&
                    ns()->allowedTo('special.customer.pay-outstanding-tickets')
                ) {
                    $options[] = [
                        'label' => __('Pay From Wallet'),
                        'icon'  => 'la-wallet',
                        'class' => 'ns-pay-wallet-ticket',
                        'attrs' => [
                            'data-ticket-id' => $ticket->id,
                        ],
                    ];
                }

                return $options;
            }
        );
    }

    /**
     * Register POS integration hooks
     * Only uses core hooks that are actually called by NexoPOS
     */
    private function registerPOSHooks(): void
    {
        // POS Options Hook - inject special customer configuration
        // This hook is called during POS initialization
        Hook::addFilter( 'ns-pos-options', function ( $options ) {
            $specialCustomerService = app( SpecialCustomerService::class );
            $config = $specialCustomerService->getConfig();

            $options['specialCustomer'] = [
                'enabled' => ! is_null( $config['groupId'] ),
                'groupId' => $config['groupId'],
                'discountPercentage' => $config['discountPercentage'],
                'cashbackPercentage' => $config['cashbackPercentage'],
                'applyDiscountStackable' => $config['applyDiscountStackable'],
            ];

            return $options;
        } );

        // Order Creation Hook - enforce special customer discount on backend
        // This is the ONLY reliable way to ensure discount is applied
        Hook::addFilter( 'ns-orders-before-create', function ( $fields ) {
            $customerId = $fields['customer_id'] ?? null;

            if ( ! $customerId ) {
                return $fields;
            }

            $customer = Customer::find( $customerId );
            if ( ! $customer ) {
                return $fields;
            }

            $specialCustomerService = app( SpecialCustomerService::class );

            // Check if customer is in special group
            if ( ! $specialCustomerService->isSpecialCustomer( $customer ) ) {
                return $fields;
            }

            $config = $specialCustomerService->getConfig();
            $discountPercentage = $config['discountPercentage'] ?? 0;

            if ( $discountPercentage <= 0 ) {
                return $fields;
            }

            // Calculate discount based on subtotal
            $subtotal = $fields['subtotal'] ?? 0;
            $discountAmount = $subtotal * ( $discountPercentage / 100 );

            // Apply discount to order fields
            $fields['discount_type'] = 'percentage';
            $fields['discount_percentage'] = $discountPercentage;
            $fields['discount_reason'] = 'Special Customer';

            \Log::info( 'Special customer discount enforced on order creation', [
                'customer_id' => $customerId,
                'subtotal' => $subtotal,
                'discount_percentage' => $discountPercentage,
                'discount_amount' => $discountAmount,
            ] );

            return $fields;
        } );
    }
}
