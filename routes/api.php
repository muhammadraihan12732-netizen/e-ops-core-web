<?php
use App\Http\Controllers\{AnalyticsController, AuthController, CategoryController, MonitoringController, ProductController, TicketController, TransactionController, UserController};
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'me']);
    Route::get('analytics/overview', [AnalyticsController::class, 'overview']);
    Route::get('categories', [CategoryController::class, 'index']);

    Route::middleware('role:admin,kasir,analyst')->get('products', [ProductController::class, 'index']);
    Route::middleware('role:admin')->group(function () {
        Route::post('products', [ProductController::class, 'store']);
        Route::put('products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);

        Route::post('categories', [CategoryController::class, 'store']);
        Route::put('categories/{category}', [CategoryController::class, 'update']);
        Route::delete('categories/{category}', [CategoryController::class, 'destroy']);

        Route::get('roles', [UserController::class, 'roles']);
        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::put('users/{user}', [UserController::class, 'update']);
    });
    Route::middleware('role:admin,kasir')->group(function () {
        Route::get('transactions', [TransactionController::class, 'index']);
        Route::get('transactions/{transaction}', [TransactionController::class, 'show']);
        Route::post('transactions', [TransactionController::class, 'store']);
    });
    Route::middleware('role:admin,analyst')->group(function () {
        Route::get('analytics/sales', [AnalyticsController::class, 'sales']);
        Route::get('analytics/top', [AnalyticsController::class, 'top']);
        Route::get('analytics/forecast', [AnalyticsController::class, 'forecast']);
        Route::get('reports/sales.csv', [AnalyticsController::class, 'export']);
    });
    Route::middleware('role:admin,support')->group(function () {
        Route::get('monitoring/health', [MonitoringController::class, 'health']);
        Route::get('monitoring/metrics', [MonitoringController::class, 'metrics']);
        Route::get('logs', [MonitoringController::class, 'logs']);
        Route::patch('tickets/{ticket}/status', [TicketController::class, 'status']);
    });
    Route::get('tickets', [TicketController::class, 'index']);
    Route::post('tickets', [TicketController::class, 'store']);
    Route::post('tickets/{ticket}/replies', [TicketController::class, 'reply']);
});
