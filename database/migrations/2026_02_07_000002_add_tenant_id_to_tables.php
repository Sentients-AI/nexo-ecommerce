<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that need tenant_id added (with standard id column).
     *
     * @var array<string>
     */
    private array $tables = [
        'users',
        'products',
        'categories',
        'stocks',
        'stock_movements',
        'orders',
        'order_items',
        'carts',
        'cart_items',
        'payment_intents',
        'refunds',
        'refund_events',
        'promotions',
        'promotion_usages',
        'price_histories',
        'feature_flags',
        'system_configs',
        'idempotency_keys',
        'refund_projections',
        'audit_logs',
    ];

    /**
     * Tables with non-standard primary keys that need tenant_id.
     *
     * @var array<string, string>
     */
    private array $specialTables = [
        'order_financial_projections' => 'order_id',
    ];

    /**
     * Unique constraints that need to be dropped and recreated with tenant_id.
     *
     * @var array<string, array{drop: string, columns: array<string>}>
     */
    private array $uniqueConstraints = [
        'products' => ['drop' => 'products_sku_unique', 'columns' => ['tenant_id', 'sku']],
        'categories' => ['drop' => 'categories_slug_unique', 'columns' => ['tenant_id', 'slug']],
        'promotions' => ['drop' => 'promotions_code_unique', 'columns' => ['tenant_id', 'code']],
        'feature_flags' => ['drop' => 'feature_flags_key_unique', 'columns' => ['tenant_id', 'key']],
    ];

    public function up(): void
    {
        // Add tenant_id column to all tables with standard id column
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->foreignId('tenant_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('tenants')
                        ->cascadeOnDelete();
                });
            }
        }

        // Add tenant_id to tables with non-standard primary keys
        foreach ($this->specialTables as $table => $afterColumn) {
            if (! Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $table) use ($afterColumn) {
                    $table->foreignId('tenant_id')
                        ->nullable()
                        ->after($afterColumn)
                        ->constrained('tenants')
                        ->cascadeOnDelete();
                });
            }
        }

        // Drop old unique constraints and create new composite ones
        foreach ($this->uniqueConstraints as $table => $constraint) {
            Schema::table($table, function (Blueprint $table) use ($constraint) {
                $table->dropUnique($constraint['drop']);
                $table->unique($constraint['columns']);
            });
        }

        // Add composite unique for system_configs (has composite key)
        Schema::table('system_configs', function (Blueprint $table) {
            $table->dropUnique('system_configs_group_key_unique');
            $table->unique(['tenant_id', 'group', 'key']);
        });
    }

    public function down(): void
    {
        // Restore system_configs unique constraint
        Schema::table('system_configs', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'group', 'key']);
            $table->unique(['group', 'key']);
        });

        // Restore old unique constraints
        foreach ($this->uniqueConstraints as $table => $constraint) {
            Schema::table($table, function (Blueprint $table) use ($constraint) {
                $table->dropUnique($constraint['columns']);
                // Extract original column name (last item in array)
                $originalColumn = end($constraint['columns']);
                $table->unique($originalColumn);
            });
        }

        // Remove tenant_id column from all tables
        foreach (array_reverse($this->tables) as $table) {
            if (Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('tenant_id');
                });
            }
        }

        // Remove tenant_id from special tables
        foreach (array_keys($this->specialTables) as $table) {
            if (Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('tenant_id');
                });
            }
        }
    }
};
