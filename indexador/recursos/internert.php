<!DOCTYPE html>
<html>
<body>

<?php
// function hasInternet()
// {
//     $hosts = ['1.1.1.1', '1.0.0.1', '8.8.8.8', '8.8.4.4'];

//     foreach ($hosts as $host) {
//         if ($connected = @fsockopen($host, 443)) {
//             fclose($connected);
//             return true;
//         }
//     }

//     return false;
// }

function isConnected()
{
    // use 80 for http or 443 for https protocol
    $connected = @fsockopen("www.google.com", 80);
    if ($connected){
        fclose($connected);
        return true; 
    }
    return false;
}

var_dump(isConnected());
?>

</body>
</html>