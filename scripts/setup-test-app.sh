#!/bin/bash

# =============================================================================
# Setup Test Laravel App for Omise PHP Package
# =============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PACKAGE_DIR="$(dirname "$SCRIPT_DIR")"
TEST_APP_NAME="${1:-omise-test-app}"
TEST_APP_DIR="$(dirname "$PACKAGE_DIR")/$TEST_APP_NAME"

echo "=============================================="
echo "Omise PHP Package - Test App Setup"
echo "=============================================="
echo ""
echo "Package directory: $PACKAGE_DIR"
echo "Test app directory: $TEST_APP_DIR"
echo ""

# Check if test app already exists
if [ -d "$TEST_APP_DIR" ]; then
    echo "⚠️  Directory $TEST_APP_DIR already exists."
    read -p "Do you want to remove it and create fresh? (y/N): " confirm
    if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
        echo "Removing existing directory..."
        rm -rf "$TEST_APP_DIR"
    else
        echo "Aborted."
        exit 1
    fi
fi

# Create Laravel app
echo ""
echo "📦 Creating new Laravel application..."
cd "$(dirname "$PACKAGE_DIR")"
composer create-project laravel/laravel "$TEST_APP_NAME" --prefer-dist

cd "$TEST_APP_DIR"

# Add path repository to composer.json
echo ""
echo "🔧 Configuring composer.json for local package..."
php -r "
\$composer = json_decode(file_get_contents('composer.json'), true);
\$composer['repositories'] = [
    [
        'type' => 'path',
        'url' => '../omise-php',
        'options' => ['symlink' => true]
    ]
];
\$composer['require']['pkomjit/omise-php'] = '@dev';
file_put_contents('composer.json', json_encode(\$composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
"

# Install the package
echo ""
echo "📥 Installing omise-php package..."
composer update pkomjit/omise-php

# Publish config
echo ""
echo "📄 Publishing Omise configuration..."
php artisan vendor:publish --tag=omise-config

# Add environment variables
echo ""
echo "🔑 Adding Omise environment variables..."
cat >> .env << 'EOF'

# Omise Payment Gateway
OMISE_PUBLIC_KEY=pkey_test_your_public_key_here
OMISE_SECRET_KEY=skey_test_your_secret_key_here
OMISE_WEBHOOK_SECRET=
OMISE_MODE=test
EOF

# Create test routes
echo ""
echo "🛣️  Creating test routes..."
cat > routes/web.php << 'EOF'
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
            // Create a 100 THB charge
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
            $charge = Omise::payWithPromptPay(20.00); // Minimum amount
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
            // Handle successful payment
            \Log::info('Payment successful!');
        } else {
            // Handle failed payment
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
EOF

# Create QR code view
echo ""
echo "🖼️  Creating QR code test view..."
mkdir -p resources/views/omise-test
cat > resources/views/omise-test/qr.blade.php << 'EOF'
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
        }
        .qr-container {
            background: #f5f5f5;
            border-radius: 12px;
            padding: 30px;
            margin: 20px 0;
        }
        .qr-code {
            max-width: 300px;
            border-radius: 8px;
        }
        .info {
            background: #e8f4fd;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            text-align: left;
        }
        .info dt {
            font-weight: bold;
            color: #666;
        }
        .info dd {
            margin: 0 0 10px 0;
            color: #333;
        }
        .status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            background: #ffd700;
            color: #333;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>PromptPay Payment Test</h1>

    <div class="qr-container">
        @if($qrDataUri)
            <img src="{{ $qrDataUri }}" alt="PromptPay QR Code" class="qr-code">
            <p>Scan with your banking app to pay</p>
        @else
            <p>QR Code not available</p>
        @endif
    </div>

    <div class="info">
        <dl>
            <dt>Charge ID</dt>
            <dd>{{ $charge->getId() }}</dd>

            <dt>Amount</dt>
            <dd>{{ number_format($charge->get('amount') / 100, 2) }} THB</dd>

            <dt>Status</dt>
            <dd><span class="status">{{ strtoupper($charge->get('status')) }}</span></dd>

            <dt>Expires At</dt>
            <dd>{{ $charge->get('expires_at') }}</dd>
        </dl>
    </div>

    <p>
        <a href="/omise-test/charge/{{ $charge->getId() }}">Check Payment Status</a>
    </p>
</body>
</html>
EOF

# Create test controller (optional, for more complex testing)
echo ""
echo "🎮 Creating test controller..."
cat > app/Http/Controllers/OmiseTestController.php << 'EOF'
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Omise\Laravel\Facades\Omise;
use Omise\PaymentMethods\PromptPay;

class OmiseTestController extends Controller
{
    /**
     * Test basic package functionality.
     */
    public function index()
    {
        return response()->json([
            'package' => 'pkomjit/omise-php',
            'test_mode' => Omise::isTestMode(),
            'promptpay_limits' => [
                'min' => PromptPay::toThb(2000) . ' THB',
                'max' => PromptPay::toThb(15000000) . ' THB',
            ],
        ]);
    }

    /**
     * Create a PromptPay charge with custom amount.
     */
    public function createCharge(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:20|max:150000',
        ]);

        $charge = Omise::payWithPromptPay($request->amount);

        return response()->json([
            'charge_id' => $charge->getId(),
            'status' => $charge->get('status'),
            'qr_code' => Omise::promptPay()->getQrCodeBase64($charge),
            'amount' => $charge->get('amount') / 100,
            'currency' => $charge->get('currency'),
        ]);
    }
}
EOF

echo ""
echo "=============================================="
echo "✅ Setup Complete!"
echo "=============================================="
echo ""
echo "Next steps:"
echo ""
echo "1. Update your Omise API keys in .env:"
echo "   cd $TEST_APP_DIR"
echo "   Edit .env and replace OMISE_PUBLIC_KEY and OMISE_SECRET_KEY"
echo ""
echo "2. Start the development server:"
echo "   php artisan serve"
echo ""
echo "3. Test the package:"
echo "   - http://localhost:8000/omise-test         (Basic test)"
echo "   - http://localhost:8000/omise-test/promptpay    (Create charge)"
echo "   - http://localhost:8000/omise-test/promptpay/qr (QR code page)"
echo ""
echo "4. For webhook testing, use ngrok:"
echo "   ngrok http 8000"
echo "   Then update webhook URL in Omise dashboard"
echo ""
