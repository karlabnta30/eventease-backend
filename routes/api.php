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

// --- PUBLIC ROUTES ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login'); 

// --- OTP AUTH ROUTES ---
Route::post('/auth/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp']);

// --- PASSWORD RESET ROUTES ---
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetCode']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);

// --- PUBLIC BUNDLE CATALOG ROUTE ---
Route::get('/bundles', [BundleController::class, 'index']);

// --- PUBLIC PAYMENT VERIFICATION ROUTE ---
Route::post('/verify-payment', [PaymentController::class, 'verifyPayment']);

// --- PROTECTED ROUTES ---
Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/user', [AuthController::class, 'userProfile']);
    Route::put('/user/update', [AuthController::class, 'updateProfile']);
    
    // AI Budget Optimizer Integration
    Route::post('/ai/optimize', [BudgetOptimizerController::class, 'optimize']);
    
    // Feedback Submission
    Route::post('/contact', [ContactController::class, 'storeFeedback']);

    // Notifications API
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']); 
    Route::delete('/notifications/delete-all', [NotificationController::class, 'deleteAll']); 

    // --- PAYMONGO CHECKOUT ROUTE ---
    Route::post('/create-checkout', [PaymentController::class, 'createCheckout']);

    // --- MESSAGING & CONTACT LIST ROUTES ---
    Route::get('/messages/{userId}', [MessageController::class, 'getConversation']);
    Route::post('/messages', [MessageController::class, 'sendMessage']);
    Route::get('/contacts-list', [MessageController::class, 'getContacts']);
    Route::get('/users-list', function() {
        return response()->json(\App\Models\User::where('id', '!=', auth()->id())->get());
    });

    // --- CLIENT/SHARED ROUTES ---
    Route::get('/vendors', [VendorController::class, 'index']); 
    Route::get('/vendors/{id}', [VendorController::class, 'show']); 

    // --- BOOKING ROUTES ---
    Route::prefix('bookings')->group(function () {
        Route::get('/', [BookingController::class, 'index']);
        Route::post('/', [BookingController::class, 'store']);
        
        // Placed before dynamic {id} parameters to prevent route collision
        Route::post('/payment', [BookingController::class, 'processPayment']);

        Route::get('/{id}', [BookingController::class, 'show']);
        Route::put('/{id}', [BookingController::class, 'update']);
        Route::delete('/{id}', [BookingController::class, 'destroy']);
        
        // Multiple Services Attachment Route
        Route::post('/{id}/attach-services', [BookingController::class, 'attachServices']);
        
        // File Attachment Route for Chat/Bookings (5MB limit handled in controller validation)
        Route::post('/{id}/attach-document', [BookingController::class, 'attachDocument']);
        
        // Bill Adjustment Route for Final Pricing Negotiations
        Route::patch('/{id}/adjust-bill', [BookingController::class, 'adjustBill']);
        
        Route::patch('/{id}/assign-vendor', [BookingController::class, 'assignVendor']);
        Route::patch('/{id}/status', [BookingController::class, 'updateStatus']); 
    });

    // --- VENDOR ROUTES ---
    Route::middleware('role:vendor')->group(function () {
        Route::get('/vendor/bookings', [BookingController::class, 'vendorBookings']); 
        Route::get('/vendor/services', [VendorController::class, 'myServices']);
        Route::post('/vendors', [VendorController::class, 'storeService']);
        Route::put('/vendors/{id}', [VendorController::class, 'updateService']);
        Route::delete('/vendors/{id}', [VendorController::class, 'destroyService']);
        Route::patch('/vendors/{id}/status', [VendorController::class, 'updateStatus']);
        
        // Power Button Toggle (Availability)
        Route::post('/vendors/toggle/{id}', [BookingController::class, 'toggleAvailability']);

        // Vendor Permit Upload Route
        Route::post('/vendor/upload-permit', [VendorController::class, 'uploadPermit']);

        // Bundle Management Routes for Vendors
        Route::post('/bundles', [BundleController::class, 'store']);
        Route::delete('/bundles/{id}', [BundleController::class, 'destroy']);
    });

    // --- ADMIN ROUTES ---
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/stats', [AdminController::class, 'stats']);
        Route::get('/admin/bookings', [BookingController::class, 'allBookings']);
        Route::get('/admin/feedback', [ContactController::class, 'getFeedback']); 
        Route::get('/admin/users', [AdminController::class, 'getUsers']);
        Route::get('/admin/vendors', [AdminController::class, 'getVendors']);

        // ADMIN: Permit Moderation Routes
        Route::get('/admin/vendor-permits', [AdminController::class, 'getVendorsForVerification']);
        Route::patch('/admin/vendors/{id}/verify', [AdminController::class, 'updateVerificationStatus']);
    });
});