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
            $table->string('contact_id')->unique()->index();
            $table->string('s3_key')->nullable()->index();
            $table->string('account')->nullable()->index();
            $table->string('instance_id')->index();
            $table->string('channel')->nullable();
            $table->string('initiation_method')->nullable();
            $table->string('queue')->nullable();
			$table->integer('queue_duration')->nullable();    			
            $table->string('customer_endpoint')->nullable()->index();
            $table->string('system_endpoint')->nullable()->index();
            $table->string('agent')->nullable()->index();
            $table->timestamp('connect_to_agent_time')->nullable()->index();
            $table->integer('connect_to_agent_duration')->nullable();
            $table->timestamp('start_time')->nullable()->index();
            $table->timestamp('disconnect_time')->nullable()->index();
            $table->integer('contact_duration')->nullable();
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
