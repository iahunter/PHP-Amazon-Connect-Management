<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		Schema::create('connect_agents', function (Blueprint $table) {
            $table->increments('id');
            $table->string('instance_id')->index();    // Parent Block ID
                $table->foreign('instance_id')->references('instance_id')->on('connect_instances')->onDelete('cascade'); 
            $table->string('arn')->unique()->index();
            $table->string('username')->index()->nullable();
            $table->string('status')->index()->nullable();
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
        Schema::dropIfExists('connect_agents');
    }
}
