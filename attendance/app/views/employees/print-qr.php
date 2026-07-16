<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code - <?= e($employee['full_name']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background: #f0f0f0;
            padding: 20px;
        }
        .qr-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            max-width: 300px;
            margin: 0 auto;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .qr-image {
            width: 200px;
            height: 200px;
            margin: 20px auto;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
        }
        .qr-image img {
            max-width: 100%;
            max-height: 100%;
        }
        .employee-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .employee-number {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }
        .qr-value {
            font-family: monospace;
            font-size: 12px;
            background: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            word-break: break-all;
        }
        .instructions {
            font-size: 11px;
            color: #999;
            margin-top: 15px;
            line-height: 1.4;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .qr-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">Print QR Code</button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 16px; cursor: pointer; margin-left: 10px;">Close</button>
    </div>
    
    <div class="qr-card">
        <div class="employee-name"><?= e($employee['full_name']) ?></div>
        <div class="employee-number"><?= e($employee['employee_number']) ?></div>
        
        <div class="qr-image">
            <!-- QR Code placeholder - in production, use a QR code library -->
            <div style="text-align: center;">
                <svg width="180" height="180" viewBox="0 0 180 180">
                    <!-- Simple QR-like pattern for demonstration -->
                    <rect x="10" y="10" width="40" height="40" fill="#000"/>
                    <rect x="20" y="20" width="20" height="20" fill="#fff"/>
                    <rect x="130" y="10" width="40" height="40" fill="#000"/>
                    <rect x="140" y="20" width="20" height="20" fill="#fff"/>
                    <rect x="10" y="130" width="40" height="40" fill="#000"/>
                    <rect x="20" y="140" width="20" height="20" fill="#fff"/>
                    <!-- Random pattern -->
                    <rect x="60" y="10" width="10" height="10" fill="#000"/>
                    <rect x="80" y="10" width="10" height="10" fill="#000"/>
                    <rect x="100" y="10" width="10" height="10" fill="#000"/>
                    <rect x="60" y="30" width="10" height="10" fill="#000"/>
                    <rect x="90" y="30" width="10" height="10" fill="#000"/>
                    <rect x="60" y="50" width="10" height="10" fill="#000"/>
                    <rect x="80" y="50" width="10" height="10" fill="#000"/>
                    <rect x="100" y="50" width="10" height="10" fill="#000"/>
                    <rect x="10" y="60" width="10" height="10" fill="#000"/>
                    <rect x="30" y="60" width="10" height="10" fill="#000"/>
                    <rect x="50" y="60" width="10" height="10" fill="#000"/>
                    <rect x="70" y="60" width="10" height="10" fill="#000"/>
                    <rect x="90" y="60" width="10" height="10" fill="#000"/>
                    <rect x="110" y="60" width="10" height="10" fill="#000"/>
                    <rect x="130" y="60" width="10" height="10" fill="#000"/>
                    <rect x="150" y="60" width="10" height="10" fill="#000"/>
                    <rect x="60" y="80" width="10" height="10" fill="#000"/>
                    <rect x="80" y="80" width="10" height="10" fill="#000"/>
                    <rect x="100" y="80" width="10" height="10" fill="#000"/>
                    <rect x="120" y="80" width="10" height="10" fill="#000"/>
                    <rect x="60" y="100" width="10" height="10" fill="#000"/>
                    <rect x="90" y="100" width="10" height="10" fill="#000"/>
                    <rect x="110" y="100" width="10" height="10" fill="#000"/>
                    <rect x="60" y="120" width="10" height="10" fill="#000"/>
                    <rect x="80" y="120" width="10" height="10" fill="#000"/>
                    <rect x="100" y="120" width="10" height="10" fill="#000"/>
                    <rect x="60" y="140" width="10" height="10" fill="#000"/>
                    <rect x="90" y="140" width="10" height="10" fill="#000"/>
                    <rect x="110" y="140" width="10" height="10" fill="#000"/>
                    <rect x="130" y="140" width="10" height="10" fill="#000"/>
                    <rect x="150" y="140" width="10" height="10" fill="#000"/>
                    <rect x="60" y="160" width="10" height="10" fill="#000"/>
                    <rect x="80" y="160" width="10" height="10" fill="#000"/>
                    <rect x="100" y="160" width="10" height="10" fill="#000"/>
                </svg>
            </div>
        </div>
        
        <div class="qr-value"><?= e($employee['qr_code_value'] ?? 'Not Generated') ?></div>
        
        <div class="instructions">
            <strong>Instructions:</strong><br>
            Scan this QR code to record attendance.<br>
            Keep this card safe and do not share.
        </div>
    </div>
    
    <script>
        // Auto-print on load (optional - uncomment if desired)
        // window.onload = function() { window.print(); };
    </script>
</body>
</html>
