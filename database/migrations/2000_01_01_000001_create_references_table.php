<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('references', 'jsonb');

        Schema::create(config('references.database.tables.references', 'ref_references'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('type')->index();
            $table->string('status', 20)->default('draft');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('author')->nullable();
            $table->string('publisher')->nullable();
            $table->integer('year')->nullable();
            $table->string('isbn', 20)->nullable();
            $table->text('description')->nullable();
            $table->string('url')->nullable();
            $table->string('language', 10)->nullable();
            $table->foreignUuid('parent_id')->nullable()->index();
            $table->{$jsonType}('reference_parts')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['type', 'status']);
        });
    }
};
