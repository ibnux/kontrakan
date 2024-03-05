<?php
include 'config.php';
include 'vendor/autoload.php';
include 'function.php';

use Medoo\Medoo;

$db = new Medoo([
    // required
    'database_type' => $db_tipe,
    'port' => $db_port,
    'database_name' => $db_name,
    'server' => $db_host,
    'username' => $db_user,
    'password' => $db_pass
]);

$iscek = !empty(_post('cek'));


$trx = $db->get(
    't_trx',
    ['id', 'tgl_dibuat', 'url_bayar', 'payment_id', 'bulan', 'total', 'channel', 'status', 'data'],
    ['status' => 1]
);
if (!$trx) {
    if ($iscek) header('location: ./?notfound');
    die();
}
$result = json_decode(getData($tripay_server . '/transaction/detail?' . http_build_query(['reference' => $trx['payment_id']]), [
    'Authorization: Bearer ' . $tripay_api
]), true);
if (!in_array($result['data']['status'], ['PAID', 'SETTLED'])) {
    if ($iscek) header('location: ./?msg=Tagihan%20belum%20dibayar&msgType=warning');
    die();
}

$max = $db->max('t_bulan', 'bulan');
$y = substr($max, 0, 4);
$m = substr($max, 4, 2);
$bulans = [];
if ($trx['bulan'] == 1) {
    //$bulan = date("Ym", strtotime());
    $d = new DateTime("$y-$m-1");
    $d->modify('next month');
    $bulan = $d->format('Ym');
    $bulans = [date("M Y", strtotime(substr($bulan, 0, 4).'-'.substr($bulan, 4, 2) . "-1"))];
    $db->insert('t_bulan', ['bulan' => $bulan, 'id_trx' => $trx['id'], 'tanggal_bayar' => date('Y-m-d H:i:s')]);
} else {
    for ($n = 0; $n < $trx['bulan']; $n++) {
        $d = new DateTime("$y-$m-1");
        $d->modify('next month');
        $bulan = $d->format('Ym');
        $y = $d->format('Y');
        $m = $d->format('m');
        $bulans[] = date("M Y", strtotime("$y-$m-1"));
        $db->insert('t_bulan', ['bulan' => $bulan, 'id_trx' => $trx['id'], 'tanggal_bayar' => date('Y-m-d H:i:s')]);
    }
}

$json = json_decode($trx['data'], true);
$json['result'] = $result;
$db->update(
    't_trx',
    [
        'data' => json_encode($json),
        'status' => 2,
        'tgl_bayar' => date('Y-m-d H:i:s')
    ],
    ['id' => $trx['id']]
);
if ($iscek) header('location: ./?msg=Pembayaran%20Sukses&msgType=success');
sendWa($pengontrak_nomor, "Pembayaran $judul_web Diterima,\n".
    "untuk $trx[bulan] Bulan\n".
    "Rp. ". number_format($trx['total'], 0, ',', '.')."\n\n".
    implode(", ", $bulans)
);
sendTelegram("Bayar kontrakan Lunas $trx[bulan] Bulan\n".
"Rp. " . number_format($trx['total'], 0, ',', '.') . "\n".
"Channel $trx[channel]\n\n".
implode(", ", $bulans));