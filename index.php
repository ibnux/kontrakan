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

$msg = _post('msg');
$msgType = _post('msgType');

if (!empty(_post('cek'))) {
    header("location: ./tripay.php?cek=" . _post('cek'));
    die("tripay.php?cek=" . _post('cek'));
}

if (!empty(_post('batalkan'))) {
    $db->update('t_trx', [
        'status' => 4,
        'data' => '',
        'tgl_bayar' => date('Y-m-d H:i:s'),
    ], ['id' => _post('batalkan')]);
    header("location: ./?" . time());
    die();
}


if (_post('bayar') == 'sekarang') {
    if ($db->has('t_trx', ['status' => 1])) {
        $msg = 'Masih ada transaksi yang belum dibayar';
        $msgType = 'warning';
    } else {
        $channel = _post('channel', 'BCAVA');
        $bulan = _post('bulan', 1);
        $total = $tarif_perbulan * $bulan;
        $db->insert('t_trx', [
            'tgl_dibuat' => date('Y-m-d H:i:s'),
            'url_bayar' => '',
            'bulan' => $bulan,
            'total' => $total,
            'channel' => $channel,
            'status' => 1
        ]);
        $trxID = $db->id();
        if ($trxID > 0) {
            $json = [
                'method' => $channel,
                'amount' => $total,
                'merchant_ref' => $trxID,
                'customer_name' =>  $pengontrak_nama,
                'customer_email' => $pengontrak_email,
                'customer_phone' => $pengontrak_nomor,
                'order_items' => [
                    [
                        'name' => "Kontrakan Q3-6",
                        'price' => $tarif_perbulan,
                        'quantity' => $bulan
                    ]
                ],
                'return_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/?cek=' . $trxID,
                'signature' => hash_hmac('sha256', $tripay_merchant . $trxID . $total, $tripay_private)
            ];
            $hasil = postJsonData($tripay_server . '/transaction/create', $json, ['Authorization: Bearer ' . $tripay_api]);
            $hasil = json_decode($hasil, true);
            if (!$hasil['success']) {
                $db->update('t_trx', [
                    'status' => 3,
                    'data' => json_encode($hasil)
                ], ['id' => $trxID]);
                $msg = 'Transaksi Gagal dibuat';
                $msgType = 'warning';
                sendTelegram("Gagal membuat transaksi\n\n" . json_encode($hasil));
            } else {
                $db->update('t_trx', [
                    'url_bayar' => $hasil['data']['checkout_url'],
                    'payment_id' => $hasil['data']['reference'],
                    'data' => json_encode(['request' => $hasil])
                ], ['id' => $trxID]);
                sendTelegram("Permintaan bayar kontrakan $bulan Bulan\nRp. " . number_format($total, 0, ',', '.') . "\nChannel $channel");
                header('location: ' . $hasil['data']['checkout_url']);
                die($hasil['data']['checkout_url']);
            }
        } else {
            $msg = 'Transaksi Gagal dibuat, mohon hubungi pemilik';
            $msgType = 'warning';
            sendTelegram("Transaksi Gagal dibuat, Dari databasenya");
        }
    }
}


?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title><?= $judul_web ?></title>

    <!-- Bootstrap -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css" integrity="sha384-HSMxcRTRxnN+Bdg0JdbxYKrThecOKuH5zCYotlSAcp1+c8xmyTe9GYg1l9a69psu" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/2.4.18/css/AdminLTE.min.css" integrity="sha512-SvzDTeVjcacK3/Re22K/cLJonaRQAOv5rJ58s8A5xIxhPTZLhQH5NOg2wziR8jWI14We8HHs5jEih2OkUW5Kfw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/2.4.18/css/skins/skin-blue.min.css" integrity="sha512-BkEsw04QmNkr3KQWCcQZX/HMoxo+opbv6ZRudUeh+/DmNoHonbPgDbC10jJyZF5ziwaMre3V23t5aiDN/VtUuQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />

</head>

<body class="hold-transition skin-blue layout-top-nav">
    <div class="wrapper">
        <div class="content-wrapper">
            <div class="container">
                <h1><?= $judul_web ?></h1>
                <?php if (isset($msgType) && !empty($msgType)) { ?>
                    <div class="alert alert-<?= $msgType ?>" role="alert"><?= $msg ?></div>
                <?php } ?>
                <div class="row">
                    <div class="col-md-1"></div>
                    <div class="col-md-4">
                        <?php $trx = $db->get('t_trx', ['id', 'bulan', 'total', 'channel', 'url_bayar'], ['status' => 1]);
                        if ($trx) { ?>
                            <div class="box box-danger">
                                <div class="box-header with-border">
                                    <h3 class="box-title">Belum Dibayar</h3>
                                </div>
                                <table class="table table-striped table-condensed table-hover table-bordered">
                                    <tr>
                                        <td><?= $trx['bulan'] ?> Bulan</td>
                                        <td>Rp <?= number_format($trx['total'], 0, ',', '.') ?></td>
                                    </tr>
                                    <tr>
                                        <td>Via</td>
                                        <td><?= $trx['channel'] ?></td>
                                    </tr>
                                </table>
                                <div class="box-footer">
                                    <div class="btn-group btn-group-justified" role="group" aria-label="...">
                                        <a href="./?batalkan=<?= $trx['id'] ?>" class="btn btn-danger btn-flat btn-block btn-xs">Batalkan</a>
                                        <a href="./tripay.php?cek=<?= $trx['id'] ?>" class="btn btn-warning btn-flat btn-block btn-xs">Cek Pembayaran</a>
                                    </div>
                                    <a href="<?= $trx['url_bayar'] ?>" class="btn btn-success btn-flat btn-block">Bayar Sekarang</a>
                                </div>
                            </div>
                        <?php } else { ?>
                            <form method="post">
                                <div class="box box-success">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Bayar Kontrakan</h3>
                                    </div>
                                    <div class="box-body">
                                        <div class="form-group">
                                            <label>Berapa bulan?</label>
                                            <select name="bulan" class="form-control" required>
                                                <option value="1">1 Bulan</option>
                                                <option value="2">2 Bulan</option>
                                                <option value="3">3 Bulan</option>
                                                <option value="4">4 Bulan</option>
                                                <option value="5">5 Bulan</option>
                                                <option value="6">6 Bulan</option>
                                            </select>
                                            <p class="help-block"><?= number_format($tarif_perbulan) ?>/bulan</p>
                                        </div>
                                        <div class="form-group">
                                            <label>Pembayaran melalui</label>
                                            <select name="channel" class="form-control">
                                                <option value="BCAVA">Virtual Account BCA</option>
                                                <option value="MANDIRIVA">Virtual Account Mandiri</option>
                                                <option value="OVO">OVO</option>
                                                <option value="QRIS">QRIS</option>
                                                <option value="BRIVA">Virtual Account BRI</option>
                                                <option value="BNIVA">Virtual Account BNI</option>
                                                <option value="BSIVA">Virtual Account BSI</option>
                                                <option value="MYBVA">Virtual Account Maybank</option>
                                                <option value="PERMATAVA">Virtual Account Permata Bank</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="box-footer">
                                        <button type="submit" class="btn btn-primary btn-block btn-xs" name="bayar" value="sekarang" onclick="return confirm('Lakukan Pembayaran?')">Bayar Sekarang</button>
                                    </div>
                                </div>
                            </form>
                        <?php } ?>
                    </div>
                    <div class="col-md-6">
                        <div class="box box-success">
                            <div class="box-header with-border">
                                <h3 class="box-title">Bulan yang sudah dibayar</h3>
                            </div>
                            <table class="table table-striped table-condensed table-hover table-bordered">
                                <thead>
                                    <tr>
                                        <th>Bulan</th>
                                        <th>Tanggal Bayar</th>
                                    </tr>
                                </thead>
                                <?php
                                $bulans = $db->select(
                                    't_bulan',
                                    ['[>]t_trx' => ['id_trx' => 'id']],
                                    ['t_bulan.id', 't_bulan.bulan', 'id_trx', 'channel', 'tanggal_bayar'],
                                    ['ORDER' => ['id' => 'DESC'], 'LIMIT' => 12]
                                );
                                if (is_array($bulans)) {
                                    foreach ($bulans as $b) {
                                ?><tr>
                                            <td><?= date("M Y", strtotime(substr($b['bulan'], 0, 4) . '-' . substr($b['bulan'], 4, 2) . "-1")) ?></td>
                                            <td><?= date("d M Y H:i", strtotime($b['tanggal_bayar'])) ?></td>
                                            <td><?= ($b['id_trx'] == 0) ? 'Tunai' : $b['channel'] ?></td>
                                        </tr><?php
                                            }
                                        }
                                                ?>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>