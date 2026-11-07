<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * CRITICAL IMPORTS:
 */
use App\Http\Controllers\AuthController; 
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\VendorController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\BudgetOptimizerController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\BundleController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Api\PublicCatalogController;

// --- PUBLIC ROUTES ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login'); 

// --- OTP AUTH ROUTES ---
Route::post('/auth/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp']);

Route::post('/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

// --- PASSWORD RESET ROUTES ---
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetCode']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);

// --- PUBLIC BUNDLE CATALOG ROUTE ---
Route::get('/bundles', [BundleController::class, 'index']);

// --- PUBLIC SERVICE CATALOG ROUTE ---
Route::get('/services', [PublicCatalogController::class, 'index']);

// --- PUBLIC PAYMENT VERIFICATION ROUTE ---
Route::post('/verify-payment', [PaymentController::class, 'verifyPayment']);

// --- PROTECTED ROUTES ---
Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/user', [AuthController::class, 'userProfile']);
    Route::put('/user/update', [AuthController::class, 'updateProfile']);
    
    Route::post('/ai/optimize', [BudgetOptimizerController::class, 'optimize']);
    Route::post('/contact', [ContactController::class, 'storeFeedback']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']); 
    Route::delete('/notifications/delete-all', [NotificationController::class, 'deleteAll']); 

    Route::post('/create-checkout', [PaymentController::class, 'createCheckout']);

    Route::get('/messages/{userId}', [MessageController::class, 'getConversation']);
    Route::post('/messages', [MessageController::class, 'sendMessage']);
    Route::post('/messages/upload', [MessageController::class, 'uploadFile']); 
    Route::get('/contacts-list', [MessageController::class, 'getContacts']);
    Route::get('/users-list', function() {
        return response()->json(\App\Models\User::where('id', '!=', auth()->id())->get());
    });

    Route::get('/vendors', [VendorController::class, 'index']); 
    Route::get('/vendors/{id}', [VendorController::class, 'show']); 

    // Bundle management (Accessible sa authenticated vendors / users)
    Route::post('/bundles', [BundleController::class, 'store']);
    Route::delete('/bundles/{id}', [BundleController::class, 'destroy']);

    Route::prefix('bookings')->group(function () {
        Route::get('/', [BookingController::class, 'index']);
        Route::post('/', [BookingController::class, 'store']);
        Route::post('/payment', [BookingController::class, 'processPayment']);
        Route::get('/{id}', [BookingController::class, 'show']);
        Route::put('/{id}', [BookingController::class, 'update']);
        Route::delete('/{id}', [BookingController::class, 'destroy']);
        Route::post('/{id}/attach-services', [BookingController::class, 'attachServices']);
        Route::post('/{id}/attach-document', [BookingController::class, 'attachDocument']);
        Route::patch('/{id}/adjust-bill', [BookingController::class, 'adjustBill']);
        Route::patch('/{id}/assign-vendor', [BookingController::class, 'assignVendor']);
        Route::patch('/{id}/status', [BookingController::class, 'updateStatus']); 
    });

    Route::middleware('role:vendor')->group(function () {
        Route::get('/vendor/bookings', [BookingController::class, 'vendorBookings']); 
        Route::get('/vendor/services', [VendorController::class, 'myServices']);
        Route::post('/vendors', [VendorController::class, 'storeService']);
        Route::put('/vendors/{id}', [VendorController::class, 'updateService']);
        Route::delete('/vendors/{id}', [VendorController::class, 'destroyService']);
        Route::patch('/vendors/{id}/status', [VendorController::class, 'updateStatus']);
        Route::post('/vendors/toggle/{id}', [BookingController::class, 'toggleAvailability']);
        Route::post('/vendor/upload-permit', [VendorController::class, 'uploadPermit']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/stats', [AdminController::class, 'stats']);
        Route::get('/admin/bookings', [BookingController::class, 'allBookings']);
        Route::get('/admin/feedback', [ContactController::class, 'getFeedback']); 
        Route::get('/admin/users', [AdminController::class, 'getUsers']);
        Route::get('/admin/vendors', [AdminController::class, 'getVendors']);
        Route::get('/admin/vendor-permits', [AdminController::class, 'getVendorsForVerification']);
        Route::patch('/admin/vendors/{id}/verify', [AdminController::class, 'updateVerificationStatus']);
        Route::get('/admin/activity-logs', [AdminController::class, 'getActivityLogs']);
    });
});