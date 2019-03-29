<?php

$datapoint = $_IPS['datapoint'];
$value = $_IPS['value'];

$ret = '';
if ($datapoint == '/heatSources/workingTime/totalSystem') {
	$m = $value;
    if ($m > 60) {
        $h = floor($m / 60);
        $m = $m % 60;
        $ret .= sprintf('%dh', $h);
    }
    if ($m > 0) {
        $ret .= sprintf('%dm', $m);
    }
}

echo $ret;