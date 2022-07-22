<?php

$data = json_decode(base64_decode($argv[1]), true)["data"]["data"];

$list = [];
foreach ($data as $bb) {
    $list[] = $bb;
}

echo json_encode($list);