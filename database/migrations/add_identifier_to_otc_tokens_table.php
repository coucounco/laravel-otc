<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('otc_tokens', function (Blueprint $table) {
            $table->string('identifier')->nullable()->after('related_type');
            $table->unsignedBigInteger('related_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('otc_tokens', function (Blueprint $table) {
            $table->dropColumn('identifier');
            $table->unsignedBigInteger('related_id')->nullable(false)->change();
        });
    }
};
