<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class InstancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		Schema::create('connect_instances', function (Blueprint $table) {
            $table->increments('id');
			$table->string('account')->index();
			$table->string('name')->index();
            $table->string('instance_id')->index()->nullable();
            $table->json('cdr_json')->nullable();                       // JSON Custom Field Data
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
        Schema::dropIfExists('connect_instances');
    }
}
