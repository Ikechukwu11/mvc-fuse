<?php

use Engine\Database\Migration;
use Engine\Database\Schema\Blueprint;
use Engine\Database\Schema\Schema;

/**
 * Migration: create_users_table
 * Created at: 2025_12_30_135146
 */
class Migration_2025_12_30_135146_create_users_table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        // Example: Create 'users' table
        // (new Schema($this->pdo))->createIfNotExists('users', function(Blueprint $table) {
        //     $table->id();
        //     $table->string('name');
        //     $table->timestamps();
        // });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        // Example: Drop 'users' table
        // (new Schema($this->pdo))->dropIfExists('users');
    }
}