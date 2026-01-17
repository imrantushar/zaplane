<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Database\ORM\Migration;
use Zaplane\Database\ORM\Schema;
use Zaplane\Database\ORM\Blueprint;

if (!defined('ABSPATH')) exit;

class CreateWorkflowVersionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_id');
            $table->longText('graph_json');
            $table->char('graph_hash', 64);
            $table->boolean('is_active')->default(true);
            $table->datetime('created_at')->nullable()->useCurrent();

            $table->index('workflow_id');
            $table->index('graph_hash');
        });
    }

    public function down(): void
    {
        Schema::drop('workflow_versions');
    }
}
