<?php
function valid_ip($ip) {
    $caltech_ips = '/131.215.[0-9]{1,3}.[0-9]{1,3}/';

    if ($ip == '127.0.0.1' || $ip == '::1')
        return true;
    else if (preg_match($caltech_ips, $ip))
        return true;
    else
        return false;
}
?>
