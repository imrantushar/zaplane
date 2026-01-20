<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if (!defined('ABSPATH')) exit;

class CreateNodeRunsTable extends Migration
{
    public function up(): void
    {
        Schema::create('node_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id');
            $table->string('node_key', 64);
            $table->unsignedBigInteger('parent_node_run_id')->nullable();
            $table->integer('iteration')->default(0);
            $table->string('status', 20)->default('pending');
            $table->longText('input_json')->nullable();
            $table->longText('output_json')->nullable();
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->datetime('resume_at')->nullable();
            $table->datetime('started_at')->nullable()->useCurrent();
            $table->datetime('finished_at')->nullable();

            $table->index('run_id');
            $table->index('node_key');
            $table->index('parent_node_run_id');
        });
    }

    public function down(): void
    {
        Schema::drop('node_runs');
    }
}
