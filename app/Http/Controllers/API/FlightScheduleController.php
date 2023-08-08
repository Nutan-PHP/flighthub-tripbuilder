<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FlightSchedule;
use App\Models\Airport;
use DateTime, DateTimeZone, Validator, DB, Exception;

class FlightScheduleController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $statusCode = 200;
            $searchInput = $request->input();
            $flightDetails = [];
            $connectingFlightDetails=[];
            $connectingDepartureFlights = [];
            $connectingArrivalFlights = [];
            $finalFlightArr = [];
            if($searchInput['trip_type'] == 'oneway' || $searchInput['trip_type'] == 'roundtrip'){//Get one way flights for both the trip types
                $flightScheduleObj = FlightSchedule::getFlightSchedule($searchInput);
                if(!empty($flightScheduleObj) && $flightScheduleObj->count()){
                    $oneWayResponseArray = $this->getDirectAndtConnectingFlights($flightScheduleObj,$searchInput);//Source To Destination
                    if($searchInput['trip_type'] == 'oneway'){
                        return $this->sendResponse($oneWayResponseArray,'Total Number of trips found '.count($oneWayResponseArray));
                    }
                }
            }
            if($searchInput['trip_type'] == 'roundtrip'){//Get return flights for roundtrip
                $searchInput['from'] = $request->input('to');
                $searchInput['to'] = $request->input('from');
                $flightScheduleObj = FlightSchedule::getFlightSchedule($searchInput);
                if(!empty($flightScheduleObj) && $flightScheduleObj->count()){
                    $roundTripResponseArray = $this->getDirectAndtConnectingFlights($flightScheduleObj,$searchInput);//Destination To Source
                    
                    //Now getting all the combinations of flight from source to destination and return
                    $combinationOne = (isset($oneWayResponseArray['directFlights']) && isset($roundTripResponseArray['directFlights'])) ? $this->flightCombinations([[$oneWayResponseArray['directFlights']], [$roundTripResponseArray['directFlights']]]) : [];//Direct flights from both the sides
                    $combinationTwo = (isset($oneWayResponseArray['directFlights']) && isset($roundTripResponseArray['connectingFlights'])) ? $this->flightCombinations([[$oneWayResponseArray['directFlights']], [$roundTripResponseArray['connectingFlights']]]) : [];//Direct flights from source-destination & Connecting flights from destination-source
                    $combinationThree = (isset($oneWayResponseArray['connectingFlights']) && isset($roundTripResponseArray['directFlights'])) ? $this->flightCombinations([[$oneWayResponseArray['connectingFlights']], [$roundTripResponseArray['directFlights']]]) : [];//Connecting Flights from source-destination & Direct flights from destination-source
                    $combinationFourth = (isset($oneWayResponseArray['connectingFlights']) && isset($roundTripResponseArray['connectingFlights'])) ? $this->flightCombinations([[$oneWayResponseArray['connectingFlights']], [$roundTripResponseArray['connectingFlights']]]) : [];//Connecting flights from source-destination & Connecting flights from destination-source
                    $allCombinationFlights = [$combinationOne,$combinationTwo,$combinationThree,$combinationFourth];
                    
                    foreach($allCombinationFlights as $flightCombination){
                        if(!empty($flightCombination)){
                            $combinationFlightArr = [];
                            foreach($flightCombination as $flights){
                                $combinationFlightArr['airlines'][] = array_values(array_unique($this->array_flatten(array([$flights[0]['airlines']],[$flights[1]['airlines']])),SORT_REGULAR));
                                $combinationFlightArr['airports'][] = array_values(array_unique($this->array_flatten(array($flights[0]['airports'],$flights[1]['airports'])),SORT_REGULAR));
                                $combinationFlightArr['flights'][] = array($flights[0]['flights'],$flights[1]['flights']);
                                // $combinationFlightArr = array_merge_recursive($flights[0],$flights[1]);
                            }
                            $finalFlightArr[] = $combinationFlightArr;
                        }
                    }

                    return $this->sendResponse($finalFlightArr,'Total Number of trips found '.count($finalFlightArr));
                }
            }
            return $this->sendResponse($finalFlightArr,'No records found.');
        } catch (Exception $e) {
            DB::rollback();
            $statusCode = 500;
            $errorMessages = $e->getMessage();
            $error = 'There is some error while processing your request. Please try after some time.'; 
            return $this->sendError($error, $errorMessages, $statusCode);
        }
    }

    //Get Direct and Connecting flights in the root
    //How to Earn Extra Consideration -(only the brave) Support connecting flights
    public function getDirectAndtConnectingFlights($flightScheduleObj,$searchInput){
        if($flightScheduleObj && !empty($flightScheduleObj) && $flightScheduleObj->count()){
            foreach($flightScheduleObj as $key => $flight){
                if($flight->departure_airport_code == $searchInput['from'] && $flight->arrival_airport_code == $searchInput['to']){
                    //Direct Flights array
                    $flightDetails['directFlights'][] = $this->getFlightDetailResponseArray($flight);
                }elseif($flight->departure_airport_code == $searchInput['from'] || $flight->arrival_airport_code == $searchInput['to']){//One way connecting flights
                    //Connecting Flights array
                    if($flight->departure_airport_code == $searchInput['from']){
                        $connectingDepartureFlights[] = [$flight];
                    }elseif($flight->arrival_airport_code == $searchInput['to']){
                        $connectingArrivalFlights[] = [$flight];
                    }
                }
            }
            
            //Connecting Flights array
            //How to Earn Extra Consideration - Support connecting flights 
            if(!empty($connectingDepartureFlights) && !empty($connectingArrivalFlights)){
                $connectingFlightCombination = $this->flightCombinations([$connectingDepartureFlights, $connectingArrivalFlights], 0, 1);
                foreach($connectingFlightCombination as $key => $connectingFlights){
                    $airlines = [];
                    $airports = [];
                    $flights = [];
                    foreach($connectingFlights as $k => $connFlight){ 
                        $flightDetailResponseArray = $this->getFlightDetailResponseArray($connFlight);
                        $airlines[] = $flightDetailResponseArray['airlines'];
                        $airports[] = $flightDetailResponseArray['airports'];
                        $flights[] = $flightDetailResponseArray['flights'];
                    }
                    
                    $flightDetails['connectingFlights'][] = [
                            'airlines' => array_unique($this->array_flatten($airlines),SORT_REGULAR),
                            'airports' => array_values(array_unique($this->array_flatten($airports),SORT_REGULAR)),
                            'flights' => $flights
                    ];
                }
            }
        }
        return $flightDetails;
    }

    public function array_flatten(array $multiDimArray): array {
        $flatten = [];
        $singleArray = array_map(function($arr) use (&$flatten) {
            $flatten = array_merge($flatten, $arr);
        }, $multiDimArray);
        return $flatten;
    }
    
    public function getFlightDetailResponseArray($flight){
        $flightDetails = [];
        if(!empty($flight) && $flight->count()){
            $airlines = [];
            $airports = [];
            $flights = [];

            //Get all the airlines
            if(isset($flight->airlines) && $flight->airlines->count()){
                $flightDetails['airlines'] = $flight->airlines->makeHidden(['created_at','updated_at'])->ToArray();
            }
            //Get the departure airport
            if(isset($flight->departureAirports) && $flight->departureAirports->count()){
                $flightDetails['airports'][] = $flight->departureAirports->makeHidden(['created_at','updated_at'])->ToArray();
            }
            //Get the arrival airport
            if(isset($flight->arraivalAirports) && $flight->arraivalAirports->count()){
                $flightDetails['airports'][] = $flight->arraivalAirports->makeHidden(['created_at','updated_at'])->ToArray();
            }

            $flightDetails['flights'] = [
                'airline' => $flight->airline_code,
                'number' => $flight->number,
                'departure_airport' => $flight->departure_airport_code,
                'departure_time' => $flight->departure_time,
                'arrival_time' => $flight->arrival_time,
                'arrival_airport' => $flight->arrival_airport_code,
                'Duration' => $flight->duration,
                'price' => $flight->price
            ];
            
        }
        return $flightDetails;
    }

    public function flightCombinations($allFlights, $i = 0, $checkWaitDelay = 0) {
        
        if (!isset($allFlights[$i])) {
            return array();
        }
        if ($i == count($allFlights) - 1) {
            return $allFlights[$i];
        }
        
        // get combinations from subsequent flights array
        $tempFlight = $this->flightCombinations($allFlights, $i + 1);
        
        $result = array();
        
        // concat each array from tempFlight with each element from $allFlights[$i]
        foreach ($allFlights[$i] as $flight) {
            foreach ($tempFlight as $tFlight) {

                //How to Earn Extra Consideration 
                //- Support connecting flights - connection time should be at least 1 hour and at most 6 hours
                if($checkWaitDelay && count($tFlight) == 1){
                    $startTime = new DateTime($flight[0]->arrival_time); 
                    $diff = $startTime->diff(new DateTime($tFlight[0]->departure_time));
                    $hour = $diff->format('%h');
                    $minute = $diff->format('%i');
                    $waitdelayMinutes = ($hour * 60) + $minute;

                    if($waitdelayMinutes < 60 || $waitdelayMinutes > 360){
                        continue;
                    }
                }

                $result[] = is_array($tFlight) ? array_merge($flight, $tFlight) : array($flight, $tFlight);
            }
        }
        
        return $result;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'number' => 'required',
            'departure_airport_code' => 'required|exists:airports,code',
            'arrival_airport_code' => 'required|exists:airports,code',
            'airline_code' => 'required|exists:airlines,code',
            'departure_time' => 'required|date_format:H:i',
            'duration' => 'required|date_format:H:i',
            'price' => 'required|numeric'
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(),422);       
        }
        
        try {
            DB::beginTransaction();
            $statusCode = 200;
            
            // get timezones as per departure and arrival airport
            $departureAirport = Airport::where('code',$request->departure_airport_code)->get()->first();
            $arrivalAirport = Airport::where('code',$request->arrival_airport_code)->get()->first();

            $departureTimeZone = new DateTimeZone($departureAirport->timezone);
            $departureOffset = timezone_offset_get($departureTimeZone, new DateTime('now', $departureTimeZone)) / 3600;

            $arrivalTimeZone = new DateTimeZone($arrivalAirport->timezone);
            $arrivalOffset = timezone_offset_get($arrivalTimeZone, new DateTime('now', $arrivalTimeZone)) / 3600;

            //Get the difference between two timezones
            $hourdiff = $departureOffset - $arrivalOffset;

            $secs = strtotime($request->duration) - strtotime("00:00:00");
            $arrivalTime = date("H:i:s",strtotime($request->departure_time)+$secs);//Get actual arrival time
            
            //Get arrival time respect to arrival airport timezone
            if($hourdiff > 0){
                $secs = strtotime(abs($hourdiff).':00') - strtotime("00:00:00");
                $arrivalTimeWithTimeZone = date("H:i:s",strtotime($arrivalTime) - $secs);
            }else{
                $secs = strtotime(abs($hourdiff).':00') - strtotime("00:00:00");
                $arrivalTimeWithTimeZone = date("H:i:s",strtotime($arrivalTime) + $secs);
            }
            
            //Get the duration in total minutes
            $durationObj = new DateTime($request->duration);
            $hour = $durationObj->format('H');
            $minute = $durationObj->format('i');
            $duration = ($hour * 60) + $minute;

            //Create flight schedule object
            $flightScheduleObj = FlightSchedule::create(array_merge($request->all(), ['duration' => $duration , 'arrival_time' => $arrivalTimeWithTimeZone]));
            
            if($flightScheduleObj){
                $response['flightDetail'] =  $flightScheduleObj->fresh();
                DB::commit();
            }
        } catch (Exception $e) {
            DB::rollback();
            $statusCode = 500;
            $errorMessages = $e->getMessage();
            $error = 'There is some error while processing your request. Please try after some time.'; 
        }finally{
            if($statusCode != 200)
                return $this->sendError($error, $errorMessages, $statusCode);
            else
                return $this->sendResponse($response, 'Flight Details added successfully.');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $flightObj = FlightSchedule::where('id',$id)->with(['airlines', 'departureAirports', 'arraivalAirports'])->first();
        if($flightObj && !empty($flightObj) && $flightObj->count()){
            $response =  [
                'id' => $flightObj->id,
                'flight' => $flightObj->airline_code.''.$flightObj->number,
                'airline' => $flightObj->airlines->name,
                'departure_airport' => $flightObj->departureAirports->name .' ('.$flightObj->departure_airport_code.')',
                'arrival_airport' => $flightObj->arraivalAirports->name .' ('.$flightObj->arrival_airport_code.')',
                'departure_time' => date('h:i A', strtotime($flightObj->departure_time)),
                'arrival_time' => date('h:i A', strtotime($flightObj->arrival_time)),
                'duration' => intdiv($flightObj->duration, 60).':'. ($flightObj->duration % 60),
                'price' => $flightObj->price,
            ];
            return $this->sendResponse($response);
        }else{
            return $this->sendError('Invalid Record', [], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'number' => 'required',
            'departure_airport_code' => 'required|exists:airports,code',
            'arrival_airport_code' => 'required|exists:airports,code',
            'airline_code' => 'required|exists:airlines,code',
            'departure_time' => 'required|date_format:H:i',
            'duration' => 'required|date_format:H:i',
            'price' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(),422);       
        }

        try {
            DB::beginTransaction();
            $statusCode = 200;

            //get flight resource object to update
            $flightObj = FlightSchedule::find($id);
            $flightObj->number = $request->number;
            $flightObj->departure_airport_code = $request->departure_airport_code;
            $flightObj->arrival_airport_code = $request->arrival_airport_code;
            $flightObj->airline_code = $request->airline_code;
            $flightObj->departure_time = $request->departure_time;
            $flightObj->price = $request->price;

            // get timezones as per departure and arrival airport
            $departureAirport = Airport::where('code',$request->departure_airport_code)->get()->first();
            $arrivalAirport = Airport::where('code',$request->arrival_airport_code)->get()->first();

            $departureTimeZone = new DateTimeZone($departureAirport->timezone);
            $departureOffset = timezone_offset_get($departureTimeZone, new DateTime('now', $departureTimeZone)) / 3600;

            $arrivalTimeZone = new DateTimeZone($arrivalAirport->timezone);
            $arrivalOffset = timezone_offset_get($arrivalTimeZone, new DateTime('now', $arrivalTimeZone)) / 3600;

            //Get the difference between two timezones
            $hourdiff = $departureOffset - $arrivalOffset;

            $secs = strtotime($request->duration) - strtotime("00:00:00");
            $arrivalTime = date("H:i:s",strtotime($request->departure_time)+$secs);//Get actual arrival time
            
            //Get arrival time respect to arrival airport timezone
            if($hourdiff > 0){
                $secs = strtotime(abs($hourdiff).':00') - strtotime("00:00:00");
                $arrivalTimeWithTimeZone = date("H:i:s",strtotime($arrivalTime) - $secs);
            }else{
                $secs = strtotime(abs($hourdiff).':00') - strtotime("00:00:00");
                $arrivalTimeWithTimeZone = date("H:i:s",strtotime($arrivalTime) + $secs);
            }
            $flightObj->arrival_time = $arrivalTimeWithTimeZone;
            
            //Get the duration in total minutes
            $durationObj = new DateTime($request->duration);
            $hour = $durationObj->format('H');
            $minute = $durationObj->format('i');
            $duration = ($hour * 60) + $minute;
            $flightObj->duration = $duration;

            //Update flight resource object
            if($flightObj->save()){
                $response['flightDetail'] =  $flightObj->fresh()->makeHidden(['created_at','updated_at']);
                DB::commit();
            }
        } catch (Exception $e) {
            DB::rollback();
            $statusCode = 500;
            $errorMessages = $e->getMessage();
            $error = 'There is some error while processing your request. Please try after some time.'; 
        }finally{
            if($statusCode != 200)
                return $this->sendError($error, $errorMessages, $statusCode);
            else
                return $this->sendResponse($response, 'Flight Details updated successfully.');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $flightObj = FlightSchedule::query()->findOrFail($id);
        if($flightObj && !empty($flightObj) && $flightObj->count()){
            $flightObj->delete();
            return $this->sendResponse(['deleted' => true], 'Flight Details deleted successfully.');
        }else{
            return $this->sendError('Invalid Record', [], 404);
        }
    }
}
