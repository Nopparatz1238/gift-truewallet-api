<?php
require_once __DIR__ . './src/WalletVoucher.php';

use Syrup\VoucherAPI\Voucher;

try {
    $voucher = new Voucher(
        '', //Phone
        '' // Link
    );

    $result = $voucher->redeem();

    if ($result['status'] === 'success') {
        echo "ได้เงินรับเงิน {$result['data']['amount']} บาท จากคุณ {$result['data']['owner']}";
    } else {
        echo "ล้มเหลว ({$result['code']}): {$result['message']}";
    }
} catch (Exception $e) {
    echo "เกิดข้อผิดพลาด: " . $e->getMessage();
}

?>
