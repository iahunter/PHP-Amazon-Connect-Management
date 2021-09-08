<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeCtrBuckets extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('connnect_ctr_buckets', function (Blueprint $table) {
            $table->increments('id');
            $table->string('account_id')->index();    // Parent Block ID
                $table->foreign('account_id')->references('account_number')->on('account')->onDelete('cascade');
            $table->string('instance_id')->index();    // Parent Block ID
                $table->foreign('instance_id')->references('instance_id')->on('connect_instances')->onDelete('cascade'); 
            $table->string('name')->unique()->index();
            $table->string('region')->index(); 
            $table->string('monitor')->index()->nullable();
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
        Schema::dropIfExists('connnect_ctr_buckets');
    }
}
