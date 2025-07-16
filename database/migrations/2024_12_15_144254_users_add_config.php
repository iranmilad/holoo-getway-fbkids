<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('config')->nullable();
            $table->integer('customer_sarfasl')->default(1030001);
            $table->string('holooCustomerID')->unique()->nullable();

            $table->string('activeLicense', 12)->nullable();

            $table->string('mobile', 50)->nullable();
            $table->date('expireActiveLicense')->nullable();

            $table->enum('holo_unit', ['rial', 'toman'])->default('rial');
            $table->enum('plugin_unit', ['rial', 'toman'])->default('toman');

            $table->string('serial')->default('10304923');
            $table->string('holooDatabaseName')->default('Holoo1');
            $table->string('apiKey')->default('B06978A4BDC049EB9CFC17E7FDF329350BADB97DACA44E338C664E31F5EEB078');
            $table->enum('user_traffic', ['heavy', 'normal', 'light'])->default('heavy');
            $table->boolean('allow_insert_product')->default(false);

            $table->text('cloudToken')->nullable();
            $table->date('cloudTokenExDate')->nullable();

            $table->text('dashboardToken')->nullable();

            $table->boolean('night_update')->default(false);
            $table->boolean('active')->default(true);
            $table->boolean('allowUpdate')->default(true);

            $table->boolean('poshak')->default(false);
            $table->boolean('mirror')->default(false);

            // اصلاح ستون parent
            $table->string('parent')->nullable();

            $table->string('queue_server', 500)->default('redis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // بررسی و حذف ستون‌ها
            if (Schema::hasColumn('users', 'config')) {
                $table->dropColumn('config');
            }
            if (Schema::hasColumn('users', 'customer_sarfasl')) {
                $table->dropColumn('customer_sarfasl');
            }
            if (Schema::hasColumn('users', 'holooCustomerID')) {
                $table->dropColumn('holooCustomerID');
            }
            if (Schema::hasColumn('users', 'activeLicense')) {
                $table->dropColumn('activeLicense');
            }
            if (Schema::hasColumn('users', 'name')) {
                $table->dropColumn('name');
            }
            if (Schema::hasColumn('users', 'mobile')) {
                $table->dropColumn('mobile');
            }
            if (Schema::hasColumn('users', 'expireActiveLicense')) {
                $table->dropColumn('expireActiveLicense');
            }
            if (Schema::hasColumn('users', 'holo_unit')) {
                $table->dropColumn('holo_unit');
            }
            if (Schema::hasColumn('users', 'plugin_unit')) {
                $table->dropColumn('plugin_unit');
            }
            if (Schema::hasColumn('users', 'serial')) {
                $table->dropColumn('serial');
            }
            if (Schema::hasColumn('users', 'holooDatabaseName')) {
                $table->dropColumn('holooDatabaseName');
            }
            if (Schema::hasColumn('users', 'apiKey')) {
                $table->dropColumn('apiKey');
            }
            if (Schema::hasColumn('users', 'user_traffic')) {
                $table->dropColumn('user_traffic');
            }
            if (Schema::hasColumn('users', 'allow_insert_product')) {
                $table->dropColumn('allow_insert_product');
            }
            if (Schema::hasColumn('users', 'cloudToken')) {
                $table->dropColumn('cloudToken');
            }
            if (Schema::hasColumn('users', 'cloudTokenExDate')) {
                $table->dropColumn('cloudTokenExDate');
            }
            if (Schema::hasColumn('users', 'dashboardToken')) {
                $table->dropColumn('dashboardToken');
            }
            if (Schema::hasColumn('users', 'night_update')) {
                $table->dropColumn('night_update');
            }
            if (Schema::hasColumn('users', 'active')) {
                $table->dropColumn('active');
            }
            if (Schema::hasColumn('users', 'allowUpdate')) {
                $table->dropColumn('allowUpdate');
            }
            if (Schema::hasColumn('users', 'poshak')) {
                $table->dropColumn('poshak');
            }
            if (Schema::hasColumn('users', 'mirror')) {
                $table->dropColumn('mirror');
            }
            if (Schema::hasColumn('users', 'parent')) {
                $table->dropColumn('parent');
            }
            if (Schema::hasColumn('users', 'queue_server')) {
                $table->dropColumn('queue_server');
            }
        });
    }
};
