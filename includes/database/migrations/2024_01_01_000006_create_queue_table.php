<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Database\ORM\Migration;
use Zaplane\Database\ORM\Schema;
use Zaplane\Database\ORM\Blueprint;

if (!defined('ABSPATH')) exit;

class CreateQueueTable extends Migration
{
    public function up(): void
    {
        Schema::create('queue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id');
            $table->unsignedBigInteger('node_run_id');
            $table->datetime('available_at');
            $table->datetime('locked_at')->nullable();
            $table->char('lock_token', 36)->nullable();
            $table->string('locked_by', 50)->nullable();
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();

            $table->index('available_at');
            $table->index('locked_at');
            $table->index('run_id');
        });
    }

    public function down(): void
    {
        Schema::drop('queue');
    }
}
