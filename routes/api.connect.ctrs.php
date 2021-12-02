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

/**
 * @OA\Get(
 *   path="/api/ctrs/{instance}/report/today",
 *   tags={"CTRs"},
 *     summary="Get Todays Summary Ctr Report",
 *     description="",
 *     @OA\Parameter(
 *         name="instance",
 *         in="path",
 *         description="Get instance Call Report for Today",
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

Route::get('/ctrs/{instance}/report/today/', [ConnectCtrController::class, 'todaysCallSummary'])->name('instance.callreport');


/**
 * @OA\Get(
 *   path="/api/ctrs/{instance}/{queue}/report/today",
 *   tags={"CTRs"},
 *     summary="Get Todays Summary Ctr Report",
 *     description="",
 *     @OA\Parameter(
 *         name="instance",
 *         in="path",
 *         description="Get instance Call Report for Today",
 *         required=true,
 *         @OA\Schema(
 *             type="string",
 *         ),
 *     ),
 *     @OA\Parameter(
 *         name="queue",
 *         in="path",
 *         description="Queue",
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

Route::get('/ctrs/{instance}/{queue}/report/today/', [ConnectCtrController::class, 'todaysCallSummaryByQueue'])->name('instance.callreport');

/**
 * @OA\Get(
 *   path="/api/ctrs/{instance}/{queue}/report/yesterday",
 *   tags={"CTRs"},
 *     summary="Get Todays Summary Ctr Report",
 *     description="",
 *     @OA\Parameter(
 *         name="instance",
 *         in="path",
 *         description="Get instance Call Report for Today",
 *         required=true,
 *         @OA\Schema(
 *             type="string",
 *         ),
 *     ),
 *     @OA\Parameter(
 *         name="queue",
 *         in="path",
 *         description="Queue",
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

Route::get('/ctrs/{instance}/{queue}/report/yesterday/', [ConnectCtrController::class, 'yesterdaysCallSummaryByQueue'])->name('instance.callreport');

/**
 * @OA\Get(
 *   path="/api/ctrs/{instance}/report/year",
 *   tags={"CTRs"},
 *     summary="Get Summary Ctr Report",
 *     description="",
 *     @OA\Parameter(
 *         name="instance",
 *         in="path",
 *         description="Get instance Call Report for the Year",
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

Route::get('/ctrs/{instance}/report/year/', [ConnectCtrController::class, 'yearsCallSummary'])->name('instance.callreport');

/**
 * @OA\Get(
 *   path="/api/ctrs/{instance}/report/month",
 *   tags={"CTRs"},
 *     summary="Get Summary Ctr Report",
 *     description="",
 *     @OA\Parameter(
 *         name="instance",
 *         in="path",
 *         description="Get instance Call Report for the Year",
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

Route::get('/ctrs/{instance}/report/month/', [ConnectCtrController::class, 'monthsCallSummary'])->name('instance.callreport');

/**
 * @OA\Get(
 *   path="/api/ctrs/{instance}/report/months/daily",
 *   tags={"CTRs"},
 *     summary="Get Summary Ctr Report",
 *     description="",
 *     @OA\Parameter(
 *         name="instance",
 *         in="path",
 *         description="Get instance Daily Call Report for the Month",
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

Route::get('/ctrs/{instance}/report/months/daily', [ConnectCtrController::class, 'monthsDailyCallSummary'])->name('instance.callreport');

/**
 * @OA\Get(
 *   path="/api/ctrs/{instance}/report/week",
 *   tags={"CTRs"},
 *     summary="Get Summary Ctr Report",
 *     description="",
 *     @OA\Parameter(
 *         name="instance",
 *         in="path",
 *         description="Get instance Call Report for the Year",
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

Route::get('/ctrs/{instance}/report/week/', [ConnectCtrController::class, 'weeksCallSummary'])->name('instance.callreport');

/**
 * @OA\Get(
 *   path="/api/ctrs/{instance}/report/yesterday",
 *   tags={"CTRs"},
 *     summary="Get Summary Ctr Report",
 *     description="",
 *     @OA\Parameter(
 *         name="instance",
 *         in="path",
 *         description="Get instance Call Report for the Year",
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

Route::get('/ctrs/{instance}/report/yesterday/', [ConnectCtrController::class, 'yesterdayCallSummary'])->name('instance.callreport');



