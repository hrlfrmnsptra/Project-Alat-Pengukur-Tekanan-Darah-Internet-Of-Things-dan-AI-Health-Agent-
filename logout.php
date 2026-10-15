<?php
session_start();
session_destroy();

// Panggil library Firebase CDN atau gunakan cURL di PHP untuk mereset antrean di Realtime Database
// Contoh cURL sederhana untuk mengosongkan antrian/id_aktif langsung ke Firebase:
$url = "https://Realtime Firebase Database.firebaseio.com/antrian.json";
$data = json_encode(["id_aktif" => ""]);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_exec($ch);
curl_close($ch);

header("location:data_pasien.php");
exit();
?>