<?php

$data = json_decode(base64_decode($argv[1]), true)["results"];

$list = [];
foreach ($data as $bb) {
    $list[] = strstr($bb[0], 'https') ? $bb[0] : "http://{$bb[0]}";
}

echo json_encode($list);