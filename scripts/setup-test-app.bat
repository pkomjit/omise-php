@echo off
setlocal enabledelayedexpansion

:: =============================================================================
:: Setup Test Laravel App for Omise PHP Package (Windows)
:: =============================================================================

echo ==============================================
echo Omise PHP Package - Test App Setup (Windows)
echo ==============================================
echo.

:: Get script directory and package directory
set "SCRIPT_DIR=%~dp0"
set "PACKAGE_DIR=%SCRIPT_DIR%.."
for %%I in ("%PACKAGE_DIR%") do set "PACKAGE_DIR=%%~fI"

:: Set test app name (default or from argument)
if "%~1"=="" (
    set "TEST_APP_NAME=omise-test-app"
) else (
    set "TEST_APP_NAME=%~1"
)

:: Get parent directory for test app
for %%I in ("%PACKAGE_DIR%\..") do set "PARENT_DIR=%%~fI"
set "TEST_APP_DIR=%PARENT_DIR%\%TEST_APP_NAME%"

echo Package directory: %PACKAGE_DIR%
echo Test app directory: %TEST_APP_DIR%
echo.

:: Check if test app already exists
if exist "%TEST_APP_DIR%" (
    echo WARNING: Directory %TEST_APP_DIR% already exists.
    set /p "confirm=Do you want to remove it and create fresh? (y/N): "
    if /i "!confirm!"=="y" (
        echo Removing existing directory...
        rmdir /s /q "%TEST_APP_DIR%"
    ) else (
        echo Aborted.
        exit /b 1
    )
)

:: Create Laravel app
echo.
echo [1/7] Creating new Laravel application...
cd "%PARENT_DIR%"
call composer create-project laravel/laravel "%TEST_APP_NAME%" --prefer-dist
if errorlevel 1 (
    echo ERROR: Failed to create Laravel application
    exit /b 1
)

cd "%TEST_APP_DIR%"

:: Add path repository to composer.json
echo.
echo [2/7] Configuring composer.json for local package...
php -r "^
$composer = json_decode(file_get_contents('composer.json'), true);^
$composer['repositories'] = [['type' => 'path', 'url' => '../omise-php', 'options' => ['symlink' => true]]];^
$composer['require']['pkomjit/omise-php'] = '@dev';^
file_put_contents('composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));^
"

:: Install the package
echo.
echo [3/7] Installing omise-php package...
call composer update pkomjit/omise-php
if errorlevel 1 (
    echo ERROR: Failed to install package
    exit /b 1
)

:: Publish config
echo.
echo [4/7] Publishing Omise configuration...
call php artisan vendor:publish --tag=omise-config

:: Add environment variables
echo.
echo [5/7] Adding Omise environment variables...
echo.>> .env
echo # Omise Payment Gateway>> .env
echo OMISE_PUBLIC_KEY=pkey_test_your_public_key_here>> .env
echo OMISE_SECRET_KEY=skey_test_your_secret_key_here>> .env
echo OMISE_WEBHOOK_SECRET=>> .env
echo OMISE_MODE=test>> .env

:: Create test routes
echo.
echo [6/7] Creating test routes and views...
call :CreateTestRoutes
call :CreateQrView
call :CreateTestController

echo.
echo ==============================================
echo SUCCESS! Setup Complete!
echo ==============================================
echo.
echo Next steps:
echo.
echo 1. Update your Omise API keys in .env:
echo    cd %TEST_APP_DIR%
echo    Edit .env and replace OMISE_PUBLIC_KEY and OMISE_SECRET_KEY
echo.
echo 2. Start the development server:
echo    php artisan serve
echo.
echo 3. Test the package:
echo    - http://localhost:8000/omise-test              (Basic test)
echo    - http://localhost:8000/omise-test/promptpay    (Create charge)
echo    - http://localhost:8000/omise-test/promptpay/qr (QR code page)
echo.
echo 4. For webhook testing, use ngrok:
echo    ngrok http 8000
echo    Then update webhook URL in Omise dashboard
echo.

exit /b 0

:: =============================================================================
:: Functions
:: =============================================================================

:CreateTestRoutes
(
echo ^<?php
echo.
echo use Illuminate\Support\Facades\Route;
echo use Omise\Laravel\Facades\Omise;
echo.
echo Route::get^('/'^, function ^(^) {
echo     return view^('welcome'^);
echo }^);
echo.
echo // Omise Package Test Routes
echo Route::prefix^('omise-test'^)-^>group^(function ^(^) {
echo.
echo     // Basic package test
echo     Route::get^('/'^, function ^(^) {
echo         return response^(^)-^>json^([
echo             'status' =^> 'ok'^,
echo             'message' =^> 'Omise package loaded successfully!'^,
echo             'test_mode' =^> Omise::isTestMode^(^)^,
echo             'config' =^> Omise::getConfig^(^)-^>toArray^(^)^,
echo         ]^);
echo     }^);
echo.
echo     // Test PromptPay charge creation
echo     Route::get^('/promptpay'^, function ^(^) {
echo         try {
echo             $charge = Omise::payWithPromptPay^(100.00^);
echo             return response^(^)-^>json^([
echo                 'status' =^> 'ok'^,
echo                 'charge_id' =^> $charge-^>getId^(^)^,
echo                 'charge_status' =^> $charge-^>get^('status'^)^,
echo                 'qr_code_url' =^> Omise::promptPay^(^)-^>getQrCodeUrl^($charge^)^,
echo                 'amount' =^> $charge-^>get^('amount'^) / 100 . ' THB'^,
echo             ]^);
echo         } catch ^(\Exception $e^) {
echo             return response^(^)-^>json^(['status' =^> 'error'^, 'message' =^> $e-^>getMessage^(^)]^, 500^);
echo         }
echo     }^);
echo.
echo     // Test PromptPay with QR code display
echo     Route::get^('/promptpay/qr'^, function ^(^) {
echo         try {
echo             $charge = Omise::payWithPromptPay^(20.00^);
echo             $qrDataUri = Omise::promptPay^(^)-^>getQrCodeDataUri^($charge^);
echo             return view^('omise-test.qr'^, ['charge' =^> $charge^, 'qrDataUri' =^> $qrDataUri]^);
echo         } catch ^(\Exception $e^) {
echo             return response^(^)-^>json^(['status' =^> 'error'^, 'message' =^> $e-^>getMessage^(^)]^, 500^);
echo         }
echo     }^);
echo.
echo     // Check charge status
echo     Route::get^('/charge/{id}'^, function ^(string $id^) {
echo         try {
echo             $charge = Omise::getCharge^($id^);
echo             return response^(^)-^>json^(['status' =^> 'ok'^, 'charge' =^> $charge-^>toArray^(^)]^);
echo         } catch ^(\Exception $e^) {
echo             return response^(^)-^>json^(['status' =^> 'error'^, 'message' =^> $e-^>getMessage^(^)]^, 500^);
echo         }
echo     }^);
echo }^);
echo.
echo // Webhook endpoint
echo Route::post^('/webhooks/omise'^, function ^(^) {
echo     $handler = Omise::webhooks^(^);
echo     $handler-^>onChargeComplete^(function ^($event^, $data^) {
echo         \Log::info^('Omise charge complete'^, ['charge_id' =^> $data['id'] ?? null^, 'status' =^> $data['status'] ?? null]^);
echo     }^);
echo     try {
echo         $handler-^>handle^(request^(^)-^>getContent^(^)^, request^(^)-^>header^('Omise-Signature'^)^, request^(^)-^>header^('Omise-Signature-Timestamp'^)^);
echo         return response^(^)-^>json^(['status' =^> 'ok']^);
echo     } catch ^(\Exception $e^) {
echo         return response^(^)-^>json^(['error' =^> $e-^>getMessage^(^)]^, 400^);
echo     }
echo }^)-^>withoutMiddleware^([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]^);
) > routes\web.php
exit /b 0

:CreateQrView
if not exist "resources\views\omise-test" mkdir "resources\views\omise-test"
(
echo ^<!DOCTYPE html^>
echo ^<html lang="en"^>
echo ^<head^>
echo     ^<meta charset="UTF-8"^>
echo     ^<meta name="viewport" content="width=device-width, initial-scale=1.0"^>
echo     ^<title^>PromptPay QR Code Test^</title^>
echo     ^<style^>
echo         body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; text-align: center; }
echo         .qr-container { background: #f5f5f5; border-radius: 12px; padding: 30px; margin: 20px 0; }
echo         .qr-code { max-width: 300px; border-radius: 8px; }
echo         .info { background: #e8f4fd; border-radius: 8px; padding: 15px; margin: 20px 0; text-align: left; }
echo         .info dt { font-weight: bold; color: #666; }
echo         .info dd { margin: 0 0 10px 0; color: #333; }
echo         .status { display: inline-block; padding: 5px 15px; border-radius: 20px; background: #ffd700; color: #333; font-weight: bold; }
echo     ^</style^>
echo ^</head^>
echo ^<body^>
echo     ^<h1^>PromptPay Payment Test^</h1^>
echo     ^<div class="qr-container"^>
echo         @if^($qrDataUri^)
echo             ^<img src="{{ $qrDataUri }}" alt="PromptPay QR Code" class="qr-code"^>
echo             ^<p^>Scan with your banking app to pay^</p^>
echo         @else
echo             ^<p^>QR Code not available^</p^>
echo         @endif
echo     ^</div^>
echo     ^<div class="info"^>
echo         ^<dl^>
echo             ^<dt^>Charge ID^</dt^>^<dd^>{{ $charge-^>getId^(^) }}^</dd^>
echo             ^<dt^>Amount^</dt^>^<dd^>{{ number_format^($charge-^>get^('amount'^) / 100, 2^) }} THB^</dd^>
echo             ^<dt^>Status^</dt^>^<dd^>^<span class="status"^>{{ strtoupper^($charge-^>get^('status'^)^) }}^</span^>^</dd^>
echo             ^<dt^>Expires At^</dt^>^<dd^>{{ $charge-^>get^('expires_at'^) }}^</dd^>
echo         ^</dl^>
echo     ^</div^>
echo     ^<p^>^<a href="/omise-test/charge/{{ $charge-^>getId^(^) }}"^>Check Payment Status^</a^>^</p^>
echo ^</body^>
echo ^</html^>
) > resources\views\omise-test\qr.blade.php
exit /b 0

:CreateTestController
(
echo ^<?php
echo.
echo namespace App\Http\Controllers;
echo.
echo use Illuminate\Http\Request;
echo use Omise\Laravel\Facades\Omise;
echo use Omise\PaymentMethods\PromptPay;
echo.
echo class OmiseTestController extends Controller
echo {
echo     public function index^(^)
echo     {
echo         return response^(^)-^>json^([
echo             'package' =^> 'pkomjit/omise-php'^,
echo             'test_mode' =^> Omise::isTestMode^(^)^,
echo             'promptpay_limits' =^> [
echo                 'min' =^> PromptPay::toThb^(2000^) . ' THB'^,
echo                 'max' =^> PromptPay::toThb^(15000000^) . ' THB'^,
echo             ]^,
echo         ]^);
echo     }
echo.
echo     public function createCharge^(Request $request^)
echo     {
echo         $request-^>validate^(['amount' =^> 'required^|numeric^|min:20^|max:150000']^);
echo         $charge = Omise::payWithPromptPay^($request-^>amount^);
echo         return response^(^)-^>json^([
echo             'charge_id' =^> $charge-^>getId^(^)^,
echo             'status' =^> $charge-^>get^('status'^)^,
echo             'qr_code' =^> Omise::promptPay^(^)-^>getQrCodeBase64^($charge^)^,
echo             'amount' =^> $charge-^>get^('amount'^) / 100^,
echo         ]^);
echo     }
echo }
) > app\Http\Controllers\OmiseTestController.php
exit /b 0
