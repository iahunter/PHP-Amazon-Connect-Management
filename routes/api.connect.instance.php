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

/**
 * @OA\Get(
 *   path="/api/instance/{id}/realtime",
 *   tags={"Instances"},
 *     summary="Get Instance Queue Realtime Metrics",
 *     description="",
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="Get instance queue stats by id",
 *         required=true,
 *         @OA\Schema(
 *             type="string",
 *         ),
 *     ),
 *   @OA\Response(
 *     response=200,
 *     description="successful operation"
 *   ),
 *	 security={
 *     {"passport": {}},
 *   },
 * )
 **/

Route::get('instance/{id}/realtime', [ConnectInstanceController::class, 'showInstanceQueueStats'])->name('instance.stats');

