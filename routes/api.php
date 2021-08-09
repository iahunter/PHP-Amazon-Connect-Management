<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="API Documentation",
 *      description="L5 Swagger OpenApi description",
 *      @OA\Contact(
 *          email="travisriesenberg@gmail.com"
 *      ),
 *     @OA\License(
 *         name="Apache 2.0",
 *         url="http://www.apache.org/licenses/LICENSE-2.0.html"
 *     )
 * )
 */

/**
 * @OA\Get(
 *     path="/api/hello",
 *     summary="Hello world test for API troubleshooting",
 *     @OA\Response(response="200", description="Hello world example")
 * )
 **/

Route::middleware('api')->get('/hello', function (Request $request) {
    return 'hello world';
}); 

require __DIR__ . '/api.connect.ctrs.php'; 

require __DIR__ . '/api.connect.instance.php'; 

require __DIR__ . '/api.connect.agents.php'; 


