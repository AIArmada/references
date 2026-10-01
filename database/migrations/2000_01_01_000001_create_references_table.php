<?php

declare(strict_types=1);

use AIArmada\References\Support\ReferenceIdentityIndexes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('references', 'jsonb');
        $tableName = (string) config('references.database.tables.references', 'references');
        $contributorsTable = (string) config('references.database.tables.reference_contributors', 'reference_contributors');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('type')->index();
            $table->string('record_kind', 20)->default('work')->index();
            $table->unsignedInteger('edition_number')->nullable();
            $table->string('edition_label')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('title');
            $table->string('slug');
            $table->string('publisher')->nullable();
            $table->integer('year')->nullable();
            $table->string('isbn', 20)->nullable();
            $table->text('description')->nullable();
            $table->string('url')->nullable();
            $table->string('language', 10)->nullable();
            $table->foreignUuid('parent_id')->nullable()->index();
            $table->{$jsonType}('reference_parts')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();

            $table->index(['type', 'status']);
        });

        Schema::create($contributorsTable, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('reference_id')->index();
            $table->string('contributor_type');
            $table->foreignUuid('contributor_id');
            $table->string('role', 20)->default('author')->index();
            $table->timestampsTz();

            $table->index(['reference_id', 'role']);
            $table->index(['contributor_type', 'contributor_id']);
        });

        ReferenceIdentityIndexes::owner($tableName, 'slug');
    }
};
