<?php
ob_start();
require __DIR__.'/clearance_compact.php';
$html=ob_get_clean();
echo str_replace('sm:w-64','sm:w-96',$html);