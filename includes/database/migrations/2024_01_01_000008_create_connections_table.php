<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if (!defined('ABSPATH')) exit;

class CreateConnectionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('app', 100);
            $table->string('name');
            $table->string('auth_type', 20);
            $table->longText('encrypted_credentials');
            $table->string('status', 20)->default('active');
            $table->datetime('oauth_expires_at')->nullable();
            $table->datetime('last_used_at')->nullable();
            $table->datetime('last_tested_at')->nullable();
            $table->string('last_test_status', 20)->nullable();
            $table->datetime('created_at')->nullable()->useCurrent();
            $table->datetime('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

            $table->index('user_id');
            $table->index('app');
            $table->index('status');
            $table->index(['user_id', 'app'], 'user_app');
        });
    }

    public function down(): void
    {
        Schema::drop('connections');
    }
}
