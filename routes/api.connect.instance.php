<?php

use App\Http\Controllers\ConnectInstanceController;

/**
 * @OA\Get(
 *   path="/api/instances",
 *   tags={"Instances"},
 *     summary="Get List of Connect Instances",
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

Route::apiResource('instances', ConnectInstanceController::class);

