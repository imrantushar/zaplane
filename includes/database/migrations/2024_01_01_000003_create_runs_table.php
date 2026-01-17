<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Database\ORM\Migration;
use Zaplane\Database\ORM\Schema;
use Zaplane\Database\ORM\Blueprint;

if (!defined('ABSPATH')) exit;

class CreateRunsTable extends Migration
{
    public function up(): void
    {
        Schema::create('runs', function (Blueprint $table) {
            $table->id();
            $table->char('workflow_version_hash', 64);
            $table->string('target_node_key', 64)->nullable();
            $table->string('start_node_key', 64)->nullable();
            $table->string('status', 20)->default('running');
            $table->longText('trigger_data')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->datetime('started_at')->nullable()->useCurrent();
            $table->datetime('finished_at')->nullable();
            $table->text('last_error')->nullable();

            $table->index('workflow_version_hash');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::drop('runs');
    }
}
