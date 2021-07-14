<?php

use App\Http\Controllers\ConnectCtrController;

/**
 * @OA\Get(
 *   path="/api/ctrs",
 *   tags={"CTRs"},
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

Route::apiResource('ctrs', ConnectCtrController::class);

