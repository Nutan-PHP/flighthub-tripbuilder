<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFlightSchedulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('flight_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('number');
            $table->string('departure_airport_code');
            $table->string('arrival_airport_code');
            $table->string('airline_code');
            $table->time('departure_time'); 
            $table->time('arrival_time');  
            $table->integer('duration');
            $table->float('price', 8, 2);
            $table->timestamps();

            $table->foreign('departure_airport_code')->references('code')->on('airports')->onDelete('cascade');
            $table->foreign('arrival_airport_code')->references('code')->on('airports')->onDelete('cascade');
            $table->foreign('airline_code')->references('code')->on('airlines')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('flight_schedules');
    }
}
