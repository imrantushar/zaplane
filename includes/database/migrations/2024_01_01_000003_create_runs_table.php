<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if (!defined('ABSPATH')) exit;

class CreateRunsTable extends Migration
{
    public function up(): void
    {
        Schema::create('runs', function (Blueprint $table) {
            $table->id();
            $table->char('workflow_version_hash', 64);
            $table->unsignedInteger('target_node_key')->nullable();
            $table->unsignedInteger('start_node_key')->nullable();
            $table->string('status', 20)->default('running');
            $table->longText('trigger_data')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->datetime('started_at')->nullable()->useCurrent();
            $table->datetime('finished_at')->nullable();
            $table->text('last_error')->nullable();
            $table->boolean('is_test')->default(false);
            $table->unsignedBigInteger('workflow_id')->nullable();

            $table->index('workflow_version_hash');
            $table->index('status');
            $table->index('workflow_id');
        });
    }

    public function down(): void
    {
        Schema::drop('runs');
    }
}
