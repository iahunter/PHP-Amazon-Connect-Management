<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ContactFlow extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		Schema::create('connect_contact_flows', function (Blueprint $table) {
            $table->increments('id');
            $table->string('instance_id')->index();    // Parent Block ID
                $table->foreign('instance_id')->references('instance_id')->on('connect_instances')->onDelete('cascade');        // Create foreign key and try cascade deletes
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
        Schema::dropIfExists('connect_contact_flows');
    }
}
