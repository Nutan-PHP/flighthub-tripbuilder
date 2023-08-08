<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // $this->call(UserSeeder::class);

        //Airlines table seeding
        DB::table('airlines')->insert([
            [
                'code' => 'AC',
                'name' => 'Air Canada'
            ],[
                'code' => 'BA',
                'name' => 'British Airways'
            ]
        ]);

        //Airport table seeding
        DB::table('airports')->insert([
            [
                'code' => 'YUL',
                'city_code' => 'YMQ',
                'name' => 'Pierre Elliott Trudeau International',
                'city' => 'Montreal',
                'country_code' => 'CA',
                'latitude' => 45.457714,
                'longitude' => -73.749908,
                'timezone' => 'America/Montreal'
            ],[
                'code' => 'YVR',
                'city_code' => 'YVR',
                'name' => 'Vancouver International',
                'city' => 'Vancouver',
                'country_code' => 'CA',
                'latitude' => 49.194698,
                'longitude' => -123.179192,
                'timezone' => 'America/Vancouver'
            ],[
                'code' => 'YYZ',
                'city_code' => 'YYZ',
                'name' => 'Toronto Pearson International Airport',
                'city' => 'Toronto',
                'country_code' => 'CA',
                'latitude' => 43.6797,
                'longitude' => -79.6227,
                'timezone' => 'America/Toronto'
            ]
        ]);

        //Flight Schedules table seeding
        DB::table('flight_schedules')->insert([
            [
                'number' => '301',
                'departure_airport_code' => 'YUL',
                'arrival_airport_code' => 'YVR',
                'airline_code' => 'AC',
                'departure_time' => '10:00',
                'arrival_time' => '13:00',
                'duration' => '330',
                'price' =>	'600.31'
            ],[
                'number' => '304',
                'departure_airport_code' => 'YVR',
                'arrival_airport_code' => 'YUL',
                'airline_code' => 'AC',
                'departure_time' => '08:55',
                'arrival_time' => '17:00',
                'duration' => '330',
                'price' =>	'499.93'
            ],[
                'number' => '318',
                'departure_airport_code' => 'YVR',
                'arrival_airport_code' => 'YYZ',
                'airline_code' => 'BA',
                'departure_time' => '11:15:00',
                'arrival_time' => '18:45:00',
                'duration' => '270',
                'price' =>	'257.93'
            ],[
                'number' => '111',
                'departure_airport_code' => 'YYZ',
                'arrival_airport_code' => 'YUL',
                'airline_code' => 'BA',
                'departure_time' => '19:40:00',
                'arrival_time' => '20:56:00',
                'duration' => '76',
                'price' =>	'62.04'
            ]
        ]);
    }
}
