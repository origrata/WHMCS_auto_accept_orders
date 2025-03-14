<?php
/**
 * Auto accept whmcs order 
 * Developer : origrata
 * Email : admin@origrata.com
 * Website : origrata.com
 * Copyrights @ www.origrata.com
 * www.origrata.com
 * */
use WHMCS\Database\Capsule;

/**
 * Hook untuk auto accept order setelah checkout
 */
add_hook('AfterShoppingCartCheckout', 1, function ($vars) {
    $ServiceIDs = $vars['ServiceIDs'];

    // Ambil semua data dalam satu query
    $GData = Capsule::table('tblhosting')
        ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
        ->join('tblorders', 'tblhosting.orderid', '=', 'tblorders.id')
        ->whereIn('tblhosting.id', $ServiceIDs)
        ->select('tblproducts.autosetup as productAutosetup', 'tblorders.id as orderid', 'tblhosting.firstpaymentamount as productAmount', 'tblhosting.id as serviceId', 'tblorders.invoiceid')
        ->get();

    foreach ($GData as $data) {
        // Jika harga produk = 0, langsung accept order
        if ($data->productAmount == "0.00") {
            MakeAcceptOrder($data->orderid, $data->serviceId);
            continue;
        }

        if ($data->productAutosetup === "order") {
            MakeAcceptOrder($data->orderid, $data->serviceId);
        } elseif ($data->productAutosetup === "payment") {
            if (!empty($data->invoiceid)) {
                $InvoiceStatus = Capsule::table('tblinvoices')
                    ->where('id', $data->invoiceid)
                    ->value('status');

                if ($InvoiceStatus === 'Paid') {
                    MakeAcceptOrder($data->orderid, $data->serviceId);
                }
            }
        }
    }
});

/**
 * Hook untuk auto accept order setelah invoice dibayar
 */
add_hook('InvoicePaid', 1, function ($vars) {
    $InvoiceID = $vars['invoiceid'];

    // Ambil data order berdasarkan invoice
    $GData = Capsule::table('tblorders')
        ->join('tblhosting', 'tblorders.id', '=', 'tblhosting.orderid')
        ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
        ->where('tblorders.invoiceid', $InvoiceID)
        ->select('tblproducts.autosetup as productAutosetup', 'tblorders.id as orderid', 'tblhosting.id as serviceId', 'tblhosting.firstpaymentamount as productAmount')
        ->get();

    foreach ($GData as $data) {
        // Jika harga produk = 0, langsung accept order
        if ($data->productAmount == "0.00") {
            MakeAcceptOrder($data->orderid, $data->serviceId);
            continue;
        }

        if (in_array($data->productAutosetup, ["order", "payment"])) {
            MakeAcceptOrder($data->orderid, $data->serviceId);
        }
    }
});

/**
 * Fungsi untuk menerima order secara otomatis
 */
function MakeAcceptOrder($OrderID = "", $ServiceID = "")
{
    if (empty($OrderID) || empty($ServiceID)) {
        return;
    }

    $command = 'AcceptOrder';
    $postData = [
        'orderid'   => $OrderID,
        'autosetup' => '1',
        'sendemail' => '1',
    ];

    // **Cara 1:** Ambil admin pertama dengan roleid = 1 (default)
    $admin = Capsule::table('tbladmins')->where('roleid', 1)->first();

    // **Cara 2:** (Opsional) Gunakan admin tertentu jika diketahui
    // $admin = (object) ['username' => 'GadangBana'];

    if (!$admin) {
        return;
    }

    $adminUsername = $admin->username;

    // Panggil API untuk menerima order
    $results = localAPI($command, $postData, $adminUsername);
}
?>
