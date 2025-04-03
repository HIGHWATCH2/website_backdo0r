<?php
function isSiteDisabled() {
    $file_path = 'site_disabled.txt';
    
    if (file_exists($file_path)) {
        $status = trim(file_get_contents($file_path));
        return $status === 'DISABLED';
    }
    
    return false;
}

if (isSiteDisabled()) {
    header("HTTP/1.1 503 Service Unavailable");
    echo "<h1>Sito momentaneamente disabilitato</h1>";
    echo "<p>Il sito è attualmente offline per manutenzione. Riprova più tardi.</p>";
    exit();
}
?>
