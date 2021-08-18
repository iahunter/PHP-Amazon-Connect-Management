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
            $table->string('account_id')->index();    // Parent Block ID
                $table->foreign('account_id')->references('account_number')->on('account')->onDelete('cascade');        // Create foreign key and try cascade deletes $table->integer('company_id')->unsigned()->index();    // Parent Block ID
            
            
			$table->string('name')->index();
            $table->string('instance_id')->index()->nullable();
            $table->string('region')->index()->nullable();
            $table->json('flows')->nullable();                       // JSON Custom Field Data
            $table->json('storage')->nullable();                       // JSON Custom Field Data
            $table->boolean('monitoring')->nullable();                       // JSON Custom Field Data
            $table->json('json')->nullable();                       // JSON Custom Field Data
            $table->json('build_data')->nullable();                       // JSON Custom Field Data
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
