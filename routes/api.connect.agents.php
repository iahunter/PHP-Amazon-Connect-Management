<?php

use App\Http\Controllers\AgentStatusController;

/**
 * @OA\Get(
 *   path="/api/agents",
 *   tags={"Agents"},
 *     summary="Get List of Agent Status",
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
Route::apiResource('agents', AgentStatusController::class);

/**
 * @OA\Get(
 *   path="/api/agents/{id}",
 *   tags={"Agents"},
 *     summary="Get List of Agent Status by Instance",
 *     description="",
 *     operationId="",
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="Get Agent Status by Instance",
 *         required=true,
 *         @OA\Schema(
 *             type="string"
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
Route::get('/agents/{id}', [AgentStatusController::class, 'show'])->name('agentstatusreport.show');


