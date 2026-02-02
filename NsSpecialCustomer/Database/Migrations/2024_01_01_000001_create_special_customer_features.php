<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\CustomerGroup;
use App\Models\Role;

/**
 * Create Special Customer Features Migration
 * 
 * This migration sets up the infrastructure for the Special Customer module:
 * - Creates the "Special" customer group
 * - Creates the cashback history table with proper financial tracking
 * - Sets up permissions and default configuration
 * - Ensures data integrity with proper constraints and indexes
 */
return new class extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        try {
            DB::transaction(function () {
                $this->createCashbackHistoryTable();
                $this->createSpecialCustomerGroup();
                $this->initializeDefaultConfiguration();
            });
        } catch (\Exception $e) {
            // Log the error but don't fail the migration
            \Log::error('NsSpecialCustomer migration error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create the "Special" customer group.
     */
    private function createSpecialCustomerGroup(): void
    {
        // Check if customer groups table exists
        if (!Schema::hasTable('nexopos_customers_groups')) {
            \Log::warning('NsSpecialCustomer: Customer groups table does not exist yet. Skipping group creation.');
            return;
        }

        try {
            $specialGroup = CustomerGroup::where('name', 'Special')->first();
            
            if (!$specialGroup) {
                $specialGroup = new CustomerGroup();
                $specialGroup->name = 'Special';
                $specialGroup->description = 'Premium customer group with wholesale pricing, special discounts, and yearly cashback rewards';
                $specialGroup->author = 1; // Set author to admin user (ID 1)
                $specialGroup->save();

                // Store the group ID in options for the service
                $this->saveOption('ns_special_customer_group_id', $specialGroup->id);
            }
        } catch (\Exception $e) {
            \Log::warning('NsSpecialCustomer: Failed to create customer group: ' . $e->getMessage());
        }
    }

    /**
     * Create the cashback history table with proper financial tracking.
     */
    private function createCashbackHistoryTable(): void
    {
        if (!Schema::hasTable('ns_special_cashback_history')) {
            Schema::create('ns_special_cashback_history', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Customer and period information
            $table->unsignedBigInteger('customer_id');
            $table->year('year')->comment('The year for which cashback is calculated');
            
            // Financial amounts with proper precision
            $table->decimal('total_purchases', 15, 5)->default(0)->comment('Total purchases for the year');
            $table->decimal('total_refunds', 15, 5)->default(0)->comment('Total refunds for the year');
            $table->decimal('cashback_percentage', 5, 2)->default(0)->comment('Cashback percentage applied');
            $table->decimal('cashback_amount', 15, 5)->default(0)->comment('Cashback amount awarded');

            // Transaction references for audit trail
            $table->unsignedBigInteger('transaction_id')->nullable()->comment('Reference to customer account history transaction');
            $table->unsignedBigInteger('reversal_transaction_id')->nullable()->comment('Reference to reversal transaction if applicable');

            // Status and workflow tracking
            $table->enum('status', ['pending', 'processing', 'processed', 'reversed', 'failed'])->default('pending')->comment('Current status of the cashback');
            $table->timestamp('processed_at')->nullable()->comment('When the cashback was processed');
            $table->timestamp('reversed_at')->nullable()->comment('When the cashback was reversed');
            
            // Audit trail
            $table->unsignedBigInteger('author')->nullable()->comment('User who processed the cashback');
            $table->unsignedBigInteger('reversal_author')->nullable()->comment('User who reversed the cashback');
            $table->text('reversal_reason')->nullable()->comment('Reason for reversal');
            $table->text('description')->nullable()->comment('Additional notes or description');

            // Timestamps
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('customer_id')
                  ->references('id')
                  ->on('nexopos_users')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            $table->foreign('transaction_id')
                  ->references('id')
                  ->on('nexopos_customers_account_history')
                  ->onDelete('set null')
                  ->onUpdate('cascade');

            $table->foreign('reversal_transaction_id')
                  ->references('id')
                  ->on('nexopos_customers_account_history')
                  ->onDelete('set null')
                  ->onUpdate('cascade');

            $table->foreign('author')
                  ->references('id')
                  ->on('nexopos_users')
                  ->onDelete('set null')
                  ->onUpdate('cascade');

            $table->foreign('reversal_author')
                  ->references('id')
                  ->on('nexopos_users')
                  ->onDelete('set null')
                  ->onUpdate('cascade');

            // Indexes for performance
            // Use unique constraint for customer_id + year to prevent duplicate cashback entries
            $table->unique(['customer_id', 'year'], 'ns_special_cashback_customer_year_unique');
            $table->index(['status'], 'ns_special_cashback_status');
            $table->index(['year'], 'ns_special_cashback_year');
            $table->index(['processed_at'], 'ns_special_cashback_processed_at');
            $table->index(['transaction_id'], 'ns_special_cashback_transaction');
            $table->index(['created_at'], 'ns_special_cashback_created_at');
            });
        }
    }

    /**
     * Initialize default configuration.
     */
    private function initializeDefaultConfiguration(): void
    {
        // Check if options table exists
        if (!Schema::hasTable('nexopos_options')) {
            \Log::warning('NsSpecialCustomer: Options table does not exist yet. Skipping configuration.');
            return;
        }

        try {
            $defaults = $this->getDefaultConfig();
            
            foreach ($defaults as $key => $value) {
                $this->saveOption($key, $value, false); // Don't overwrite existing values
            }
        } catch (\Exception $e) {
            \Log::warning('NsSpecialCustomer: Failed to initialize configuration: ' . $e->getMessage());
        }
    }

    /**
     * Save an option value.
     * 
     * @param string $key The option key
     * @param mixed $value The option value
     * @param bool $overwrite Whether to overwrite existing values
     */
    private function saveOption(string $key, mixed $value, bool $overwrite = true): void
    {
        $optionClass = config('nexopos.options', 'App\Models\Option');
        
        $existingOption = $optionClass::where('key', $key)->first();
        
        if (!$existingOption) {
            $option = new $optionClass();
            $option->key = $key;
            $option->value = $value;
            $option->array = false;
            $option->save();
        } elseif ($overwrite) {
            $existingOption->value = $value;
            $existingOption->save();
        }
    }

    /**
     * Get default configuration values.
     */
    private function getDefaultConfig(): array
    {
        return [
            'ns_special_discount_percentage' => 7.0,
            'ns_special_cashback_percentage' => 2.0,
            'ns_special_apply_discount_stackable' => false,
            'ns_special_min_order_amount' => 0,
            'ns_special_max_topup_amount' => 10000,
            'ns_special_min_topup_amount' => 1,
            'ns_special_enable_auto_cashback' => false,
            'ns_special_cashback_processing_month' => 1, // January
        ];
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        DB::transaction(function () {
            // Drop the cashback history table
            Schema::dropIfExists('ns_special_cashback_history');

            // Remove configuration options
            $optionClass = config('nexopos.options', 'App\Models\Option');
            $configKeys = [
                'ns_special_customer_group_id',
                'ns_special_discount_percentage',
                'ns_special_cashback_percentage',
                'ns_special_apply_discount_stackable',
                'ns_special_min_order_amount',
                'ns_special_max_topup_amount',
                'ns_special_min_topup_amount',
                'ns_special_enable_auto_cashback',
                'ns_special_cashback_processing_month',
            ];
            
            $optionClass::whereIn('key', $configKeys)->delete();

            // Note: We don't delete the customer group itself as it might contain customers
            // The group can be manually deleted if needed after ensuring no customers are assigned
        });
    }
};
