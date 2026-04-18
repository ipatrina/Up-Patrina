<?php

  // Up Patrina
  // Version: 10.0.0
  // Date: 2026.04

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
    if ($bytes === 0 || floatval($bytes) < 0) return '0 B';
    $k = 1024;
    $sizes = ['B','KB','MB','GB','TB','PB','EB','ZB','YB'];
    $i = floor(log($bytes)/log($k));
    $value = $bytes/pow($k,$i);
    return number_format($value,2).' '.$sizes[$i];
}

$files = array_filter(scandir('.'), function($f) {
    global $blockedExtensions;
    return is_file($f) && !preg_match('/\.(' . $blockedExtensions . ')$/i', $f);
});
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
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
    table-layout:fixed;
}
td {
    padding:8px;
    border-bottom:1px solid #eee;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}
td:nth-child(1) { width:60%; }
td:nth-child(2) { width:12%; text-align:right; }
td:nth-child(3) { width:20%; text-align:right; }
td:nth-child(4) { width:8%; text-align:center; }
a {
    text-decoration:none;
    color:#333;
}
a.delete {
    color:red;
    cursor:pointer;
}
.footer {
    text-align:center;
    margin-top:20px;
    font-size:14px;
    color:#888;
}
</style>
</head>
<body>

<div class="card">
    <div class="title"><a href="">Up Patrina</a></div>
    <div id="selector" class="select" onclick="pick()">Select File</div>
    <div id="progress" class="progress">
        <div id="status" class="info"></div>
        <div id="info" class="info"></div>
        <progress id="bar" class="bar" value="0" max="100"></progress>
    </div>
</div>

<input type="file" id="f" style="display:none" onchange="start()">

<?php if (count($files) > 0): ?>
<div class="card">
<table>
<?php foreach ($files as $f): ?>
<tr>
<td title="<?php echo htmlspecialchars($f); ?>">
    <a href="<?php echo htmlspecialchars($f); ?>" target="_blank"><?php echo htmlspecialchars($f); ?></a>
</td>
<td><?php echo formatSize(filesize($f)); ?></td>
<td><?php echo date("Y-m-d H:i", filemtime($f)); ?></td>
<td><a class="delete" onclick="del('<?php echo str_replace("&#039;", "\\'", htmlspecialchars($f)); ?>')">Delete</a></td>
</tr>
<?php endforeach; ?>
</table>
</div>
<?php endif; ?>

<div class="footer">
This page is written by <a href="https://openai.com/blog/chatgpt" target="_blank">ChatGPT</a>
</div>

<script>
let file, size, chunk = 4*1024*1024;

function pick(){ document.getElementById('f').click(); }

function start(){
    file=document.getElementById('f').files[0];
    if(!file) return;
    size=file.size;
    document.getElementById('selector').style.display='none';
    document.getElementById('progress').style.display='block';
    document.getElementById('status').innerHTML = file.name + " 0% (0 B/"+format(size)+")";
    document.getElementById('info').innerHTML = "0 B/s";
    send(0);
}

function send(offset){
    let end=Math.min(offset+chunk-1,size-1);
    let xhr=new XMLHttpRequest();
    let t=Date.now();

    xhr.open("POST",location.href,true);
    xhr.timeout=100000;
    xhr.setRequestHeader("Content-Disposition","attachment; filename="+encodeURIComponent(file.name));
    xhr.setRequestHeader("Content-Range","bytes "+offset+"-"+end+"/"+size);
    xhr.setRequestHeader("Content-Type","application/octet-stream");

    xhr.onreadystatechange=function(){
        if(xhr.readyState==4){
            if(xhr.status==202){
                let dt=(Date.now()-t)/1000;
                let sp=(end-offset+1)/dt;
                document.getElementById('info').className="info";
                document.getElementById('info').innerHTML=format(sp)+"/s";
                update(end+1);
                send(end+1);
            }else if(xhr.status==200 || xhr.status==201){
                location.reload();
            }else{
                document.getElementById('info').className="info error";
                document.getElementById('info').innerHTML="("+xhr.status+") retrying...";
                setTimeout(()=>send(offset),1000);
            }
        }
    };

    xhr.ontimeout=function(){
        document.getElementById('info').className="info error";
        document.getElementById('info').innerHTML="Timeout retrying...";
        setTimeout(()=>send(offset),1000);
    };

    xhr.send(file.slice(offset,end+1));
}

function update(done){
    let p=Math.floor(done/size*100);
    document.getElementById('bar').value=p;
    document.getElementById('status').className="info";
    document.getElementById('status').innerHTML=file.name+" "+p+"% ("+format(done)+"/"+format(size)+")";
}

function format(bytes, decimals = 2) {
    if (bytes === 0 || parseFloat(bytes) < 0) return '0 B';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['B','KB','MB','GB','TB','PB','EB','ZB','YB'];
    const i = Math.floor(Math.log(bytes)/Math.log(k));
    return parseFloat((bytes/Math.pow(k,i)).toFixed(dm)) + ' ' + sizes[i];
}

function del(name){
    if(!confirm("Are you sure you want to permanently delete this file?\r\n\r\n"+name)) return;
    let x=new XMLHttpRequest();
    x.open("GET","?Delete="+encodeURIComponent(name),true);
    x.onreadystatechange=function(){
        if(x.readyState==4 && x.status==204) location.reload();
    };
    x.send();
}
</script>

</body>
</html>