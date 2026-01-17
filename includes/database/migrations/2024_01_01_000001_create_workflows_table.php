<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Database\ORM\Migration;
use Zaplane\Database\ORM\Schema;
use Zaplane\Database\ORM\Blueprint;

if (!defined('ABSPATH')) exit;

class CreateWorkflowsTable extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->string('name');
            $table->enum('status', ['active', 'paused', 'draft'])->default('draft');
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::drop('workflows');
    }
}
