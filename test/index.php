<?php

require '../vyabase.php';
use \URNR\Vyabase as vya;

$vya = file_get_contents('a.vya');
$vyaAry = vya\indent2array($vya);

var_dump(vya\array2emmet($vyaAry));

var_dump(vya\VyaTag::parseTag('a[href="//google.com"]#id.class.class2'));