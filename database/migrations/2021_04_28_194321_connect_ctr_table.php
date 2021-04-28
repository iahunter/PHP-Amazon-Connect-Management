<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ConnectCtrTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		Schema::create('connect_ctrs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('instance_id')->index();
            $table->string('key')->index(); // Global Call ID
			$table->string('channel')->nullable();
            $table->string('contact_id')->nullable();
            $table->string('queue')->nullable();
			$table->integer('queue_duration')->nullable();    			
            $table->string('calling_name')->nullable();
            $table->string('calling_number')->nullable()->index();
            $table->string('called_number')->nullable()->index();
            $table->string('agent')->nullable()->index();
            $table->timestamp('start_time')->nullable()->index();
            $table->timestamp('disconnect_time')->nullable()->index();
            $table->integer('call_duration')->nullable();
            $table->integer('disconnect_initiator')->nullable();
            $table->integer('disconnect_reason')->nullable();

            $table->json('cdr_json')->nullable();                       // JSON Custom Field Data
		});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('connect_ctrs');
    }
}
