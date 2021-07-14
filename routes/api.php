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

/*
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
*/

/**
 * @OA\Get(
 *   path="/api/ctrs",
 *   tags={"Group"},
 *     summary="Get List of Ctrs",
 *     description="",
 *   @OA\Response(
 *     response=200,
 *     description="successful operation"
 *   ),
 *	 security={
 *     {"passport": {}},
 *   },
 * )
 **/

use App\Http\Controllers\ConnectCtrController;

Route::apiResource('ctrs', ConnectCtrController::class);

//Route::middleware('api')->get('/ctrs', 'ConnectCtrController::class, @index')->name('ctrs');
//Route::get('/ctrs', [UserController::class, 'index']);


//Route::get('/ctrs', [ConnectCtrController::class, 'index']);



