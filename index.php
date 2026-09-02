<?php

  // Up Patrina
  // Version: 11.0.0
  // Improved by Doraenon
  // Date: 2026.09

$blockedExtensions = "php|phar|sh|py";

$cl = isset($_SERVER['CONTENT_LENGTH']) ? intval($_SERVER['CONTENT_LENGTH']) : 0;

if (isset($_GET['Delete'])) {
    $f = basename($_GET['Delete']);
    if (isBadFilename($f)) {
        http_response_code(400);
        exit;
    }
    if ($f && file_exists($f)) {
        unlink($f);
        http_response_code(204);
    } else {
        http_response_code(404);
    }
    exit;
}

if ($cl > 0) {
    $h = getallheaders();
    if (!isset($h['Content-Disposition']) || !isset($h['Content-Range'])) {
        http_response_code(400);
        exit;
    }
    if (!preg_match('/filename=([^;]+)/', $h['Content-Disposition'], $m)) {
        http_response_code(400);
        exit;
    }
    $filename = urldecode(trim($m[1], '"'));
    if (isBadFilename($filename)) {
        http_response_code(400);
        exit;
    }
    $data = file_get_contents("php://input");
    if (strlen($data) != $cl) {
        http_response_code(400);
        exit;
    }
    if (!preg_match('/bytes\s+(\d+)-(\d+)\/(\d+|\*)/', $h['Content-Range'], $r)) {
        http_response_code(400);
        exit;
    }
    $start = intval($r[1]);
    $end = intval($r[2]);
    $size = file_exists($filename) ? filesize($filename) : 0;
    if ($start != $size) {
        http_response_code(409);
        header('Range: bytes=' . $size . '-');
        exit;
    }
    $fp = fopen($filename, 'ab');
    if (!$fp) {
        http_response_code(400);
        exit;
    }
    if (fwrite($fp, $data) === false) {
        fclose($fp);
        http_response_code(400);
        exit;
    }
    fclose($fp);
    if ($end + 1 >= intval($r[3])) {
        http_response_code(201);
    } else {
        http_response_code(202);
    }
    exit;
}

function isBadFilename($filename) {
    global $blockedExtensions;
    return preg_match('/[\\\\\/:*?"<>|]/', $filename) || preg_match('/^\./', $filename) || preg_match('/\.(' . $blockedExtensions . ')$/i', $filename);
}

function formatSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    $units = [
        [1024000, 1024, 'K'],
        [1048576000, 1048576, 'M'],
        [PHP_INT_MAX, 1073741824, 'G']
    ];
    foreach ($units as $unit) {
        if ($bytes < $unit[0]) {
            return number_format($bytes / $unit[1], 2) . ' ' . $unit[2];
        }
    }
}

$files = array_filter(scandir('.'), function($f) {
    global $blockedExtensions;
    return is_file($f) && !preg_match('/\.(' . $blockedExtensions . ')$/i', $f);
});
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="initial-scale=1.0,maximum-scale=1,user-scalable=no,width=device-width,height=device-height"/>
<title>Up Patrina</title>
<style>
body {
    margin:0 auto;
    width:800px;
    padding:30px;
    font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
    background:#f5f7fa;
}
.card {
    background:#fff;
    border-radius:10px;
    box-shadow:0 2px 10px rgba(0,0,0,0.05);
    padding:20px;
    margin-bottom:20px;
}
.title {
    text-align:center;
    font-size:22px;
    margin-bottom:10px;
    cursor:pointer;
    color:#000;
}
.select {
    text-align:center;
    padding:40px;
    border:2px dashed #4a90e2;
    border-radius:10px;
    cursor:pointer;
    color:#4a90e2;
    transition: background 0.2s;
}
.select.hover {
    background: #eef5fd;
    border-color: #357abd;
}
.progress {
    display:none;
    margin-top:15px;
}
.bar {
    width:100%;
}
.info {
    font-size:14px;
    margin-bottom:5px;
}
.error {
    color:#d9534f;
}
table {
    width:100%;
    border-collapse:collapse;
    table-layout: fixed; 
}
td, th {
    padding:8px;
    border-bottom:1px solid #eee;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

td:nth-child(1), th:nth-child(1) { width: 58%; }
td:nth-child(2), th:nth-child(2) { width: 12%; text-align:right; }
td:nth-child(3), th:nth-child(3) { width: 22%; text-align:right; }
td:nth-child(4), th:nth-child(4) { width: 8%; text-align:center; }

thead th {
    background-color: #f8f9fa;
    font-weight: 600;
    color: #555;  
}
a {
    text-decoration:none;
    color:#333;
}
a.delete svg {
    cursor:pointer;
    display: block;
    margin: 0 auto;
}
.footer {
    text-align:center;
    margin-top:20px;
    font-size:14px;
    color:#888;
}

@media (max-width: 768px) {
    body {
        width: 100%;
        padding: 15px;
        box-sizing: border-box;
    }
    td:nth-child(3), th:nth-child(3) {
        display: none;
    }
    td:nth-child(1), th:nth-child(1) { width: 63%; }
    td:nth-child(2), th:nth-child(2) { width: 28%; }
    td:nth-child(4), th:nth-child(4) { width: 9%; }
}
</style>
</head>
<body>

<div class="card">
    <div class="title"><a href="">Up Patrina</a></div>
    <div id="selector" class="select" onclick="pick()">选择文件 或 拖动文件到此处</div>
    <div id="progress" class="progress">
        <div id="status" class="info"></div>
        <div id="info" class="info"></div>
        <progress id="bar" class="bar" value="0" max="100"></progress>
    </div>
</div>

<input type="file" id="f" style="display:none"/>

<?php if (count($files) > 0): ?>
<div class="card">
<table>
    <thead>
        <tr>
            <th>对象名称</th>
            <th>文件大小</th>
            <th style="text-align:center;">更新时间</th>
            <th>删除</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($files as $f): ?>
    <tr>
    <td title="<?php echo htmlspecialchars($f); ?>">
        <a href="<?php echo htmlspecialchars($f); ?>" target="_blank" download><?php echo htmlspecialchars($f); ?></a>
    </td>
    <td><?php echo formatSize(filesize($f)); ?></td>
    <td><?php echo date("Y-m-d H:i", filemtime($f)); ?></td>
    <td><a class="delete" onclick="del('<?php echo str_replace("&#039;", "\\'", htmlspecialchars($f)); ?>')"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><line x1="3" y1="3" x2="13" y2="13" stroke="#f00" stroke-width="2.5" stroke-linecap="round"/><line x1="13" y1="3" x2="3" y2="13" stroke="#f00" stroke-width="2.5" stroke-linecap="round"/></svg></a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<div class="footer">
This page was written by <a href="https://openai.com/blog/chatgpt" target="_blank">ChatGPT</a><br/>
And improved by <a href="https://github.com/doraenon" target="_blank">Doraenon</a>
</div>

<script>
var lang = navigator.language || (navigator.languages && navigator.languages[0]) || '';
var isZh = lang.indexOf('zh') === 0;
var L = {
    select:      isZh ? '选择文件 或 拖动文件到此处' : 'Select or drop file here',
    col_name:    isZh ? '对象名称' : 'Name',
    col_size:    isZh ? '文件大小' : 'Size',
    col_time:    isZh ? '更新时间' : 'Modified',
    col_del:     isZh ? '删除' : 'Del',
    del_confirm: isZh ? '确定要永久删除此文件吗？' : 'Are you sure you want to permanently delete this file?'
};
var ths = document.querySelectorAll('thead th');
for (var i = 0; i < ths.length; i++) {
    var keys = ['col_name', 'col_size', 'col_time', 'col_del'];
    if (keys[i]) ths[i].textContent = L[keys[i]];
}
document.getElementById('selector').textContent = L.select;

var file, size, chunk = 4*1024*1024;

function pick(){ document.getElementById('f').click(); }

function start(){
    if(!file) {
        file = document.getElementById('f').files[0];
        size = file.size;
    }
    if(!file) return;
    document.getElementById('selector').style.display='none';
    document.getElementById('progress').style.display='block';
    document.getElementById('status').innerHTML = file.name + " 0% (0 B/"+format(size)+")";
    document.getElementById('info').innerHTML = "0 B/s";
    send(0);
}

function send(offset){
    var end=Math.min(offset+chunk-1,size-1);
    var xhr=new XMLHttpRequest();
    var t=new Date().getTime();

    xhr.open("POST",location.href,true);
    xhr.timeout=100000;
    xhr.setRequestHeader("Content-Disposition","attachment; filename="+encodeURIComponent(file.name));
    xhr.setRequestHeader("Content-Range","bytes "+offset+"-"+end+"/"+size);
    xhr.setRequestHeader("Content-Type","application/octet-stream");

    xhr.onreadystatechange=function(){
        if(xhr.readyState==4){
            if(xhr.status==202){
                var dt=(new Date().getTime()-t)/1000;
                var sp=(end-offset+1)/dt;
                document.getElementById('info').className="info";
                document.getElementById('info').innerHTML=format(sp)+"/s";
                update(end+1);
                send(end+1);
            }else if(xhr.status==200 || xhr.status==201){
                location.reload();
            }else if(xhr.status==409){
                var range=xhr.getResponseHeader('Range')||'';
                var m=range.match(/bytes=(\d+)-/);
                var serverSize=m?parseInt(m[1]):0;
                send(serverSize);
            }else{
                document.getElementById('info').className="info error";
                document.getElementById('info').innerHTML="("+xhr.status+") retrying...";
                setTimeout(function(){send(offset);},1000);
            }
        }
    };

    xhr.ontimeout=function(){
        document.getElementById('info').className="info error";
        document.getElementById('info').innerHTML="Timeout retrying...";
        setTimeout(function(){send(offset);},1000);
    };

    var blob = file.slice ? file.slice(offset, end + 1) : file.msSlice ? file.msSlice(offset, end + 1) : file;
    xhr.send(blob);
}

function update(done){
    var p=Math.floor(done/size*100);
    document.getElementById('bar').value=p;
    document.getElementById('status').className="info";
    document.getElementById('status').innerHTML=file.name+" "+p+"% ("+format(done)+"/"+format(size)+")";
}

function format(bytes, decimals) {
    if (bytes === 0 || parseFloat(bytes) < 0) return '0 B';
    var k = 1024;
    var dm = decimals < 0 ? 0 : decimals;
    var sizes = ['B','KB','MB','GB','TB','PB','EB','ZB','YB'];
    var i = Math.floor(Math.log(bytes)/Math.log(k));
    return parseFloat((bytes/Math.pow(k,i)).toFixed(dm)) + ' ' + sizes[i];
}

function del(name){
    if(!confirm(L.del_confirm + "\r\n\r\n" + name)) return;
    var x=new XMLHttpRequest();
    x.open("GET","?Delete="+encodeURIComponent(name),true);
    x.onreadystatechange=function(){
        if(x.readyState==4 && x.status==204) location.reload();
    };
    x.send();
}

var selector = document.getElementById('selector');
selector.addEventListener('dragover', function(e) {
    e.preventDefault();
    e.stopPropagation();
    selector.className += ' hover';
});
selector.addEventListener('dragleave', function(e) {
    e.preventDefault();
    e.stopPropagation();
    selector.className = selector.className.replace(' hover', '');
});
selector.addEventListener('drop', function(e) {
    e.preventDefault();
    e.stopPropagation();
    selector.className = selector.className.replace(' hover', '');

    if (e.dataTransfer.files.length > 0) {
        file = e.dataTransfer.files[0]; //更改写法，避免firefox下拖拽上传复用前一个上传路径的bug。
        size = file.size;
        start();
    }
});
document.addEventListener('DOMContentLoaded', function(){document.getElementById('f').addEventListener('change', start);});
</script>
</body>
</html>