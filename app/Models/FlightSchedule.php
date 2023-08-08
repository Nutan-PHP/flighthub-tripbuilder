<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlightSchedule extends Model
{
    protected $fillable = ['number', 'departure_airport_code', 'arrival_airport_code', 'airline_code', 'departure_time', 'arrival_time', 'duration', 'price'];
    public static function getFlightSchedule($searchInput){
        $query = FlightSchedule::where('departure_airport_code',$searchInput['from'])->where('arrival_airport_code',$searchInput['to']);
        $query = $query->orWhere(function($q) use ($searchInput){//Getting connecting flights
                    $q = $q->where('departure_airport_code',$searchInput['from'])->orWhere('arrival_airport_code',$searchInput['to']);
                });
         $query = $query->with(['departureAirports','arraivalAirports','airlines'])->orderBy('price','asc')->get();
        return $query;
    }

    public function departureAirports(){
        return $this->belongsTo(Airport::class,'departure_airport_code','code');
    }

    public function arraivalAirports(){
        return $this->belongsTo(Airport::class,'arrival_airport_code','code');
    }

    public function airlines(){
        return $this->belongsTo(Airline::class,'airline_code','code');
    }
}
