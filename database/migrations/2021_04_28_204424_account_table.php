<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AccountTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		Schema::create('account', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('company_id')->unsigned()->index();    // Parent Block ID
                $table->foreign('company_id')->references('id')->on('company')->onDelete('cascade');        // Create foreign key and try cascade deletes
			$table->string('account_number')->index();
            $table->string('account_description')->nullable()->index();
            $table->string('account_app_key')->nullable()->index();
            $table->string('account_app_secret')->nullable()->index();
            $table->json('json')->nullable();                       // JSON Custom Field Data
			$table->timestamps();                       // Time Stamps
            $table->softDeletes();                      // Soft Deletes
		});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('account');
    }
}
