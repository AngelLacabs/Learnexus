@ -97,6 +97,52 @@ try {
    error_log("Certificate tracking error: " . $e->getMessage());
}

/* =====================
   SOLESOURCE INTEGRATION: Generate voucher for completed course
   Check if voucher already exists for this certificate
===================== */
try {
    require_once '../helpers/solesource_api.php';
    error_log("SOLESOURCE DEBUG: Starting voucher generation for certificate " . $certificate['certificateID']);
    
    // Check if voucher already exists for this certificate
    $stmt = $conn->prepare("SELECT voucherID FROM vouchers WHERE certificateID = ? LIMIT 1");
    $stmt->execute([$certificate['certificateID']]);
    $existingVoucher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("SOLESOURCE DEBUG: Existing voucher check - " . ($existingVoucher ? "Found ID: " . $existingVoucher['voucherID'] : "None found"));
    
    if (!$existingVoucher) {
        error_log("SOLESOURCE DEBUG: Calling solesource_generate_voucher...");
        
        // Generate new voucher
        $voucherResponse = solesource_generate_voucher(
            $userID, 
            $certificate['certificateID'],
            [
                'discount-type' => 'percent',
                'discount-value' => 12  // 12% discount for course completion
            ]
        );
        
        error_log("SOLESOURCE DEBUG: API Response - " . json_encode($voucherResponse));
        
        if ($voucherResponse['ok'] ?? false) {
            $_SESSION['new_voucher_code'] = $voucherResponse['code'];
            error_log("SoleSource: ✅ Voucher generated for user $userID - Code: " . $voucherResponse['code']);
        } else {
            error_log("SoleSource: ❌ Failed to generate voucher for user $userID - " . json_encode($voucherResponse));
        }
    } else {
        error_log("SoleSource: Voucher already exists for certificate " . $certificate['certificateID']);
    }
} catch (Exception $e) {
    error_log("SoleSource: ❌ EXCEPTION - " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
    error_log("SoleSource: Stack trace - " . $e->getTraceAsString());
} catch (Error $e) {
    error_log("SoleSource: ❌ FATAL ERROR - " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
}

/* =====================
   FETCH VOUCHER FOR THIS CERTIFICATE
===================== */
@ -546,11 +592,17 @@ function copyVoucherCode(code) {
    });
}

// Show voucher modal automatically when page loads
// Show voucher modal automatically after certificate is displayed
<?php if ($voucher): ?>
document.addEventListener('DOMContentLoaded', function() {
    const voucherModal = new bootstrap.Modal(document.getElementById('voucherModal'));
    voucherModal.show();
    // Wait 1.5 seconds so the certificate is visible first, then show voucher modal
    setTimeout(function() {
        const voucherModal = new bootstrap.Modal(document.getElementById('voucherModal'), {
            backdrop: 'static',
            keyboard: false
        });
        voucherModal.show();
    }, 1500);
});
<?php endif; ?>
</script>
@ -97,6 +97,52 @@ try {
    error_log("Certificate tracking error: " . $e->getMessage());
}

/* =====================
   SOLESOURCE INTEGRATION: Generate voucher for completed course
   Check if voucher already exists for this certificate
===================== */
try {
    require_once '../helpers/solesource_api.php';
    error_log("SOLESOURCE DEBUG: Starting voucher generation for certificate " . $certificate['certificateID']);
    
    // Check if voucher already exists for this certificate
    $stmt = $conn->prepare("SELECT voucherID FROM vouchers WHERE certificateID = ? LIMIT 1");
    $stmt->execute([$certificate['certificateID']]);
    $existingVoucher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("SOLESOURCE DEBUG: Existing voucher check - " . ($existingVoucher ? "Found ID: " . $existingVoucher['voucherID'] : "None found"));
    
    if (!$existingVoucher) {
        error_log("SOLESOURCE DEBUG: Calling solesource_generate_voucher...");
        
        // Generate new voucher
        $voucherResponse = solesource_generate_voucher(
            $userID, 
            $certificate['certificateID'],
            [
                'discount-type' => 'percent',
                'discount-value' => 12  // 12% discount for course completion
            ]
        );
        
        error_log("SOLESOURCE DEBUG: API Response - " . json_encode($voucherResponse));
        
        if ($voucherResponse['ok'] ?? false) {
            $_SESSION['new_voucher_code'] = $voucherResponse['code'];
            error_log("SoleSource: ✅ Voucher generated for user $userID - Code: " . $voucherResponse['code']);
        } else {
            error_log("SoleSource: ❌ Failed to generate voucher for user $userID - " . json_encode($voucherResponse));
        }
    } else {
        error_log("SoleSource: Voucher already exists for certificate " . $certificate['certificateID']);
    }
} catch (Exception $e) {
    error_log("SoleSource: ❌ EXCEPTION - " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
    error_log("SoleSource: Stack trace - " . $e->getTraceAsString());
} catch (Error $e) {
    error_log("SoleSource: ❌ FATAL ERROR - " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
}

/* =====================
   FETCH VOUCHER FOR THIS CERTIFICATE
===================== */
@ -546,11 +592,17 @@ function copyVoucherCode(code) {
    });
}

// Show voucher modal automatically when page loads
// Show voucher modal automatically after certificate is displayed
<?php if ($voucher): ?>
document.addEventListener('DOMContentLoaded', function() {
    const voucherModal = new bootstrap.Modal(document.getElementById('voucherModal'));
    voucherModal.show();
    // Wait 1.5 seconds so the certificate is visible first, then show voucher modal
    setTimeout(function() {
        const voucherModal = new bootstrap.Modal(document.getElementById('voucherModal'), {
            backdrop: 'static',
            keyboard: false
        });
        voucherModal.show();
    }, 1500);
});
<?php endif; ?>
</script>
@ -97,6 +97,52 @@ try {
    error_log("Certificate tracking error: " . $e->getMessage());
}

/* =====================
   SOLESOURCE INTEGRATION: Generate voucher for completed course
   Check if voucher already exists for this certificate
===================== */
try {
    require_once '../helpers/solesource_api.php';
    error_log("SOLESOURCE DEBUG: Starting voucher generation for certificate " . $certificate['certificateID']);
    
    // Check if voucher already exists for this certificate
    $stmt = $conn->prepare("SELECT voucherID FROM vouchers WHERE certificateID = ? LIMIT 1");
    $stmt->execute([$certificate['certificateID']]);
    $existingVoucher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("SOLESOURCE DEBUG: Existing voucher check - " . ($existingVoucher ? "Found ID: " . $existingVoucher['voucherID'] : "None found"));
    
    if (!$existingVoucher) {
        error_log("SOLESOURCE DEBUG: Calling solesource_generate_voucher...");
        
        // Generate new voucher
        $voucherResponse = solesource_generate_voucher(
            $userID, 
            $certificate['certificateID'],
            [
                'discount-type' => 'percent',
                'discount-value' => 12  // 12% discount for course completion
            ]
        );
        
        error_log("SOLESOURCE DEBUG: API Response - " . json_encode($voucherResponse));
        
        if ($voucherResponse['ok'] ?? false) {
            $_SESSION['new_voucher_code'] = $voucherResponse['code'];
            error_log("SoleSource: ✅ Voucher generated for user $userID - Code: " . $voucherResponse['code']);
        } else {
            error_log("SoleSource: ❌ Failed to generate voucher for user $userID - " . json_encode($voucherResponse));
        }
    } else {
        error_log("SoleSource: Voucher already exists for certificate " . $certificate['certificateID']);
    }
} catch (Exception $e) {
    error_log("SoleSource: ❌ EXCEPTION - " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
    error_log("SoleSource: Stack trace - " . $e->getTraceAsString());
} catch (Error $e) {
    error_log("SoleSource: ❌ FATAL ERROR - " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
}

/* =====================
   FETCH VOUCHER FOR THIS CERTIFICATE
===================== */
@ -546,11 +592,17 @@ function copyVoucherCode(code) {
    });
}

// Show voucher modal automatically when page loads
// Show voucher modal automatically after certificate is displayed
<?php if ($voucher): ?>
document.addEventListener('DOMContentLoaded', function() {
    const voucherModal = new bootstrap.Modal(document.getElementById('voucherModal'));
    voucherModal.show();
    // Wait 1.5 seconds so the certificate is visible first, then show voucher modal
    setTimeout(function() {
        const voucherModal = new bootstrap.Modal(document.getElementById('voucherModal'), {
            backdrop: 'static',
            keyboard: false
        });
        voucherModal.show();
    }, 1500);
});
<?php endif; ?>
</script>
