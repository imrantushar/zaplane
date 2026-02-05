<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if (!defined('ABSPATH')) exit;

class CreateExecutionEdgesTable extends Migration
{
    public function up(): void
    {
        Schema::create('execution_edges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id');
            $table->unsignedBigInteger('from_node_run_id');
            $table->string('to_node_key', 64);
            $table->longText('payload_json')->nullable();
            $table->datetime('created_at')->nullable()->useCurrent();

            $table->index('run_id');
            $table->index('from_node_run_id');
            $table->index('to_node_key');
        });
    }

    public function down(): void
    {
        Schema::drop('execution_edges');
    }
}
