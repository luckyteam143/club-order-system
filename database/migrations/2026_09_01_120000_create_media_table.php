<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A shared image library: upload an image once here, then pick it (by
     * name) from any image field elsewhere in the app instead of
     * re-uploading. `file` is a path on the `public` disk (always under
     * `media/…`), so the existing ImageColumn / Storage::disk('public')
     * helpers keep working unchanged wherever a media file is selected.
     */
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('file');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
