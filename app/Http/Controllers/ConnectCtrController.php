<?php

namespace App\Http\Controllers;

use App\Models\AmazonConnect\Ctr;
use Illuminate\Support\Facades\Cache;

use Illuminate\Http\Request;

class ConnectCtrController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Ctr::all(); 

		//return DeviceCollection::collection($objects);
    }

    public function todaysCallSummaryByAgent(Request $request, $instance, $agent)
    {
        $report = Ctr::todays_call_summary($instance); 

        $response = [
            'status_code'       => 200,
            'success'           => true,
            'message'           => '',
            'request'           => $request->all(),
            'result'            => $report,
        ];

        return response()->json($response);

		//return DeviceCollection::collection($objects);
    }

    public function todaysCallSummary(Request $request, $instance)
    {
        $report = Ctr::todays_call_summary($instance); 

        $response = [
            'status_code'       => 200,
            'success'           => true,
            'message'           => '',
            'request'           => $request->all(),
            'result'            => $report,
        ];

        return response()->json($response);

		//return DeviceCollection::collection($objects);
    }

    public function yearsCallSummary(Request $request, $instance)
    {
        $report = Ctr::years_call_summary($instance); 

        $response = [
            'status_code'       => 200,
            'success'           => true,
            'message'           => '',
            'request'           => $request->all(),
            'result'            => $report,
        ];

        return response()->json($response);

		//return DeviceCollection::collection($objects);
    }

    public function monthsCallSummary(Request $request, $instance)
    {
        $report = Ctr::months_call_summary($instance); 

        $response = [
            'status_code'       => 200,
            'success'           => true,
            'message'           => '',
            'request'           => $request->all(),
            'result'            => $report,
        ];

        return response()->json($response);

		//return DeviceCollection::collection($objects);
    }

    public function weeksCallSummary(Request $request, $instance)
    {
        $report = Ctr::weeks_call_summary($instance); 

        $response = [
            'status_code'       => 200,
            'success'           => true,
            'message'           => '',
            'request'           => $request->all(),
            'result'            => $report,
        ];

        return response()->json($response);

		//return DeviceCollection::collection($objects);
    }

    public function yesterdayCallSummary(Request $request, $instance)
    {
        $report = Ctr::yesterday_call_summary($instance); 

        $response = [
            'status_code'       => 200,
            'success'           => true,
            'message'           => '',
            'request'           => $request->all(),
            'result'            => $report,
        ];

        return response()->json($response);

		//return DeviceCollection::collection($objects);
    }

    public function todaysCallSummaryByQueue(Request $request, $instance, $queue)
    {
        $key = "$instance.$queue"; 
        //$key = null; 
        if(Cache::has($key)){
            $response = Cache::get($key);
            $response['cache'] = true; 
            return response()->json($response);
        }
        
        $report = Ctr::todays_call_summary_by_queue($instance, $queue);


        $response = [
            'status_code'       => 200,
            'success'           => true,
            'message'           => '',
            'request'           => $request->all(),
            'result'            => $report,
        ];

        Cache::put($key, $response, 60); 

        return response()->json($response);

		//return DeviceCollection::collection($objects);
    }

    public function yesterdaysCallSummaryByQueue(Request $request, $instance, $queue)
    {
        
        $report = Ctr::yesterday_call_summary_by_queue($instance, $queue);

        $response = [
            'status_code'       => 200,
            'success'           => true,
            'message'           => '',
            'request'           => $request->all(),
            'result'            => $report,
        ];


        return response()->json($response);

		//return DeviceCollection::collection($objects);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
