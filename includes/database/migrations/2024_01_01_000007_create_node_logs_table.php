<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if (!defined('ABSPATH')) exit;

class CreateNodeLogsTable extends Migration
{
    public function up(): void
    {
        Schema::create('node_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('node_run_id');
            $table->string('level', 20)->default('info');
            $table->text('message')->nullable();
            $table->datetime('created_at')->nullable()->useCurrent();

            $table->index('node_run_id');
        });
    }

    public function down(): void
    {
        Schema::drop('node_logs');
    }
}
