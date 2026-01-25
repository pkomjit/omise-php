# =============================================================================
# Setup Test Laravel App for Omise PHP Package (PowerShell)
# =============================================================================

param(
    [string]$TestAppName = "omise-test-app"
)

$ErrorActionPreference = "Stop"

Write-Host "==============================================" -ForegroundColor Cyan
Write-Host "Omise PHP Package - Test App Setup (PowerShell)" -ForegroundColor Cyan
Write-Host "==============================================" -ForegroundColor Cyan
Write-Host ""

# Get directories
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$PackageDir = Split-Path -Parent $ScriptDir
$ParentDir = Split-Path -Parent $PackageDir
$TestAppDir = Join-Path $ParentDir $TestAppName

Write-Host "Package directory: $PackageDir"
Write-Host "Test app directory: $TestAppDir"
Write-Host ""

# Check if test app already exists
if (Test-Path $TestAppDir) {
    Write-Host "WARNING: Directory $TestAppDir already exists." -ForegroundColor Yellow
    $confirm = Read-Host "Do you want to remove it and create fresh? (y/N)"
    if ($confirm -eq 'y' -or $confirm -eq 'Y') {
        Write-Host "Removing existing directory..."
        Remove-Item -Recurse -Force $TestAppDir
    } else {
        Write-Host "Aborted." -ForegroundColor Red
        exit 1
    }
}

# Create Laravel app
Write-Host ""
Write-Host "[1/7] Creating new Laravel application..." -ForegroundColor Green
Set-Location $ParentDir
composer create-project laravel/laravel $TestAppName --prefer-dist
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: Failed to create Laravel application" -ForegroundColor Red
    exit 1
}

Set-Location $TestAppDir

# Add path repository to composer.json
Write-Host ""
Write-Host "[2/7] Configuring composer.json for local package..." -ForegroundColor Green
$composerJson = Get-Content "composer.json" -Raw | ConvertFrom-Json
$composerJson | Add-Member -NotePropertyName "repositories" -NotePropertyValue @(
    @{
        type = "path"
        url = "../omise-php"
        options = @{ symlink = $true }
    }
) -Force
$composerJson.require | Add-Member -NotePropertyName "pkomjit/omise-php" -NotePropertyValue "@dev" -Force
$composerJson | ConvertTo-Json -Depth 10 | Set-Content "composer.json"

# Install the package
Write-Host ""
Write-Host "[3/7] Installing omise-php package..." -ForegroundColor Green
composer update pkomjit/omise-php
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: Failed to install package" -ForegroundColor Red
    exit 1
}

# Publish config
Write-Host ""
Write-Host "[4/7] Publishing Omise configuration..." -ForegroundColor Green
php artisan vendor:publish --tag=omise-config

# Add environment variables
Write-Host ""
Write-Host "[5/7] Adding Omise environment variables..." -ForegroundColor Green
@"

# Omise Payment Gateway
OMISE_PUBLIC_KEY=pkey_test_your_public_key_here
OMISE_SECRET_KEY=skey_test_your_secret_key_here
OMISE_WEBHOOK_SECRET=
OMISE_MODE=test
"@ | Add-Content ".env"

# Create test routes
Write-Host ""
Write-Host "[6/7] Creating test routes..." -ForegroundColor Green
@'
<?php

use Illuminate\Support\Facades\Route;
use Omise\Laravel\Facades\Omise;

Route::get('/', function () {
    return view('welcome');
});

// =============================================================================
// Omise Package Test Routes
// =============================================================================

Route::prefix('omise-test')->group(function () {

    // Basic package test
    Route::get('/', function () {
        return response()->json([
            'status' => 'ok',
            'message' => 'Omise package loaded successfully!',
            'test_mode' => Omise::isTestMode(),
            'config' => Omise::getConfig()->toArray(),
        ]);
    });

    // Test PromptPay charge creation
    Route::get('/promptpay', function () {
        try {
            $charge = Omise::payWithPromptPay(100.00);

            return response()->json([
                'status' => 'ok',
                'charge_id' => $charge->getId(),
                'charge_status' => $charge->get('status'),
                'qr_code_url' => Omise::promptPay()->getQrCodeUrl($charge),
                'amount' => $charge->get('amount') / 100 . ' THB',
                'expires_at' => $charge->get('expires_at'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    });

    // Test PromptPay with QR code display
    Route::get('/promptpay/qr', function () {
        try {
            $charge = Omise::payWithPromptPay(20.00);
            $qrDataUri = Omise::promptPay()->getQrCodeDataUri($charge);

            return view('omise-test.qr', [
                'charge' => $charge,
                'qrDataUri' => $qrDataUri,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    });

    // Check charge status
    Route::get('/charge/{id}', function (string $id) {
        try {
            $charge = Omise::getCharge($id);

            return response()->json([
                'status' => 'ok',
                'charge' => $charge->toArray(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    });
});

// Webhook endpoint
Route::post('/webhooks/omise', function () {
    $handler = Omise::webhooks();

    $handler->onChargeComplete(function ($event, $data) {
        \Log::info('Omise charge complete', [
            'charge_id' => $data['id'] ?? null,
            'status' => $data['status'] ?? null,
        ]);

        if ($data['status'] === 'successful') {
            \Log::info('Payment successful!');
        } else {
            \Log::warning('Payment failed', [
                'failure_code' => $data['failure_code'] ?? null,
            ]);
        }
    });

    try {
        $handler->handle(
            request()->getContent(),
            request()->header('Omise-Signature'),
            request()->header('Omise-Signature-Timestamp')
        );

        return response()->json(['status' => 'ok']);
    } catch (\Exception $e) {
        \Log::error('Webhook error: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 400);
    }
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
'@ | Set-Content "routes/web.php"

# Create QR code view
Write-Host ""
Write-Host "[7/7] Creating QR code test view..." -ForegroundColor Green
New-Item -ItemType Directory -Force -Path "resources/views/omise-test" | Out-Null
@'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PromptPay QR Code Test</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            text-align: center;
            background: #f0f2f5;
        }
        .card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1a1a2e;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .qr-container {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 30px;
            margin: 20px 0;
        }
        .qr-code {
            max-width: 280px;
            border-radius: 8px;
            border: 4px solid white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .scan-text {
            color: #666;
            margin-top: 15px;
            font-size: 14px;
        }
        .info {
            background: #e3f2fd;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #555;
        }
        .info-value {
            color: #1a1a2e;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            background: #fff3cd;
            color: #856404;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
        }
        .status.successful {
            background: #d4edda;
            color: #155724;
        }
        .status.failed {
            background: #f8d7da;
            color: #721c24;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #4361ee;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 20px;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #3651d4;
        }
        .amount {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 10px 0;
        }
        .currency {
            font-size: 18px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>PromptPay Payment</h1>
        <p class="subtitle">Scan QR code with your banking app</p>

        <div class="amount">
            {{ number_format($charge->get('amount') / 100, 2) }}
            <span class="currency">THB</span>
        </div>

        <div class="qr-container">
            @if($qrDataUri)
                <img src="{{ $qrDataUri }}" alt="PromptPay QR Code" class="qr-code">
                <p class="scan-text">Open your mobile banking app and scan this QR code</p>
            @else
                <p>QR Code not available</p>
            @endif
        </div>

        <div class="info">
            <div class="info-row">
                <span class="info-label">Charge ID</span>
                <span class="info-value">{{ $charge->getId() }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Status</span>
                <span class="info-value">
                    <span class="status {{ $charge->get('status') }}">
                        {{ $charge->get('status') }}
                    </span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Expires At</span>
                <span class="info-value">{{ $charge->get('expires_at') }}</span>
            </div>
        </div>

        <a href="/omise-test/charge/{{ $charge->getId() }}" class="btn">
            Check Payment Status
        </a>
    </div>
</body>
</html>
'@ | Set-Content "resources/views/omise-test/qr.blade.php"

Write-Host ""
Write-Host "==============================================" -ForegroundColor Green
Write-Host "SUCCESS! Setup Complete!" -ForegroundColor Green
Write-Host "==============================================" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host ""
Write-Host "1. Update your Omise API keys in .env:"
Write-Host "   cd $TestAppDir"
Write-Host "   Edit .env and replace OMISE_PUBLIC_KEY and OMISE_SECRET_KEY"
Write-Host ""
Write-Host "2. Start the development server:"
Write-Host "   php artisan serve" -ForegroundColor Cyan
Write-Host ""
Write-Host "3. Test the package:"
Write-Host "   - http://localhost:8000/omise-test              (Basic test)" -ForegroundColor Cyan
Write-Host "   - http://localhost:8000/omise-test/promptpay    (Create charge)" -ForegroundColor Cyan
Write-Host "   - http://localhost:8000/omise-test/promptpay/qr (QR code page)" -ForegroundColor Cyan
Write-Host ""
Write-Host "4. For webhook testing, use ngrok:"
Write-Host "   ngrok http 8000"
Write-Host "   Then update webhook URL in Omise dashboard"
Write-Host ""
