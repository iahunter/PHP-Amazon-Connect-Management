<?php
use App\Http\Controllers; 
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect('ui/#/admin');
    //return view('welcome');
});

Route::get('/instances','App\Http\Controllers\ConnectInstanceController@list')->name('get_instances');

//Route::get('/instance/{instance_id}/agents','App\Http\Controllers\AgentStatusController@show')->name('get_instance_agent_status');

Route::get('/instance/{instance_id}/agents', function () {

    $agents = Agent::where('instance_id', $instance_id)->get();

    return view('instancewallboard', [
        'instance_id' => $instance_id,
        'agents' => $agents,
    ]);
});

