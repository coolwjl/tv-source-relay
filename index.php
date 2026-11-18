<?php
if (!file_exists(__DIR__ . '/config.php')) {
    exit('❌ 缺少配置文件 config.php，请复制 config.example.php 并重命名修改密码！');
}
require __DIR__ . '/config.php';

// 清空全部缓存函数
function clearAllCache($cacheDir){
    if(is_dir($cacheDir)){
        $files = glob($cacheDir . '/*');
        foreach ($files as $f) {
            is_file($f) && unlink($f);
        }
    }
}

// 生成3位随机代号，去除易混淆字符 0 O 1 I
function makeRandomCode(){
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for($i=0;$i<3;$i++){
        $code .= $chars[rand(0, strlen($chars)-1)];
    }
    return $code;
}

// 检查代号是否重复
function codeExists($cfg, $checkCode, $skipId = 0){
    foreach($cfg['list'] as $item){
        if($item['code'] === $checkCode && $item['id'] != $skipId){
            return true;
        }
    }
    return false;
}

// 判断是否进入管理面板
$isAdminPage = isset($_GET['admin']);

// ---------------------- 管理面板逻辑 start ----------------------
if ($isAdminPage) {
    // Basic HTTP认证
    if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] !== $auth_user || $_SERVER['PHP_AUTH_PW'] !== $auth_pass) {
        header('WWW-Authenticate: Basic realm=""');
        header('HTTP/1.0 401 Unauthorized');
        exit('访问被拒绝，请输入账号密码');
    }

    // 清理缓存动作
    if (isset($_GET['clearcache'])) {
        clearAllCache($cacheDir);
        header('Location: ?admin');
        exit;
    }

    // 初始化配置文件
    if (!file_exists($jsonFile)) {
        $initData = [
            "default_id" => 0,
            "list" => []
        ];
        file_put_contents($jsonFile, json_encode($initData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    $cfg = json_decode(file_get_contents($jsonFile), true);

    $editId = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
    $editItem = null;
    if ($editId > 0) {
        foreach ($cfg['list'] as $s) {
            if ($s['id'] == $editId) {
                $editItem = $s;
                break;
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $act = $_POST['act'] ?? '';
        if ($act === 'add') {
            $name = trim($_POST['name'] ?? '');
            $url = trim($_POST['url'] ?? '');
            $note = trim($_POST['note'] ?? '');
            if ($name !== '' && $url !== '') {
                // 自动生成不重复代号
                do {
                    $code = makeRandomCode();
                } while(codeExists($cfg, $code));
                $newId = 0;
                foreach ($cfg['list'] as $item) {
                    if ($item['id'] > $newId) $newId = $item['id'];
                }
                $cfg['list'][] = [
                    'id' => $newId + 1,
                    'code' => $code,
                    'name' => $name,
                    'url' => $url,
                    'note' => $note
                ];
                clearAllCache($cacheDir);
            }
        } elseif ($act === 'edit') {
            $edit_id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $url = trim($_POST['url'] ?? '');
            $note = trim($_POST['note'] ?? '');
            if ($edit_id <= 0 || $name === '' || $url === '') {
                echo "<script>alert('名称和地址不能为空');history.back();</script>";
                exit;
            }
            foreach ($cfg['list'] as &$item) {
                if ($item['id'] == $edit_id) {
                    // 编辑不修改代号，保留原来code
                    $item['name'] = $name;
                    $item['url'] = $url;
                    $item['note'] = $note;
                    break;
                }
            }
            unset($item);
            clearAllCache($cacheDir);
        } elseif ($act === 'setdefault') {
            $did = intval($_POST['id'] ?? 0);
            $cfg['default_id'] = $did;
            clearAllCache($cacheDir);
        } elseif ($act === 'del') {
            $delId = intval($_POST['id'] ?? 0);
            $newList = [];
            foreach ($cfg['list'] as $item) {
                if ($item['id'] !== $delId) {
                    $newList[] = $item;
                }
            }
            $cfg['list'] = $newList;
            if ($delId == $cfg['default_id']) {
                $cfg['default_id'] = count($cfg['list']) > 0 ? $cfg['list'][0]['id'] : 0;
            } else {
                $hasDefault = false;
                foreach ($cfg['list'] as $item) {
                    if ($item['id'] == $cfg['default_id']) {
                        $hasDefault = true;
                        break;
                    }
                }
                if (!$hasDefault) {
                    $cfg['default_id'] = count($cfg['list']) > 0 ? $cfg['list'][0]['id'] : 0;
                }
            }
            clearAllCache($cacheDir);
        }
        file_put_contents($jsonFile, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        header('Location: ?admin');
        exit;
    }

    if (isset($_GET['test'])) {
        $u = $_GET['test'];
        $ch = curl_init($u);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, "okhttp/3.15");
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
            return 0;
        });
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        echo $code;
        exit;
    }

    $showList = [];
    $defaultItem = null;
    $otherItems = [];
    foreach ($cfg['list'] as $s) {
        if ($s['id'] == $cfg['default_id']) {
            $defaultItem = $s;
        } else {
            $otherItems[] = $s;
        }
    }
    if ($defaultItem) {
        $showList[] = $defaultItem;
    }
    $showList = array_merge($showList, $otherItems);

    // 输出管理面板HTML
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TV影视中转源管理面板</title>
<style>
*{box-sizing:border-box;font-family:system-ui}
body {
    font-size: 16px;
    line-height: 1.6;
    padding: 0 14px;
    max-width: 920px;
    margin: 30px auto;
}
.card{border:1px solid #ddd;border-radius:8px;padding:16px;margin-bottom:16px}
.item{border:1px solid #eee;padding:12px;border-radius:6px;margin:8px 0}
.def{background:#e6f4ff;border-color:#91caff}
.btn{padding:6px 10px;border:0;border-radius:4px;cursor:pointer;margin:2px}
.btn-blue{background:#1677ff;color:#fff}
.btn-red{background:#ff4d4f;color:#fff}
.btn-green{background:#00b42a;color:#fff}
input, select, button {font-size: 18px;padding: 8px 6px;}
input{width:100%;margin:4px 0;border:1px solid #ccc;border-radius:4px}
h4 { margin-top: 0;}
.form-title-tag{
    display:inline-block;
    background:#f2f2f2;
    border:1px solid #ddd;
    border-radius:4px;
    padding:4px 10px;
    font-size:14px;
    font-weight:normal;
    color:#333;
}
.form-title-tag .code-value{
    color:#666;
}
.form-title-tag .code-tip{
    color:#999;
    margin-left:6px;
}
</style>
</head>
<body>
<h2 style="text-align:center;">TV影视中转源管理</h2>
<div class="card">
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
    <div>
        <h4 style="margin:0;">接入地址 （<a href="javascript:copyUrl()" style="color:#1677ff;margin-left:4px;margin-right:4px;text-decoration:none" id="copyLink">点击复制</a><span id="copyTip" style="color:#00b42a;margin-left:6px;display:none">✅复制成功</span>） </h4>
    </div>
    <button class="btn" onclick="if(confirm('确定清空缓存？'))location.href='?admin&clearcache=1'">清理缓存</button>
</div>
<code id="tvUrl" style="display:block;margin:4px 0;">
<strong>
<?php
$proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); 
$baseUrl = $proto . '://' . $_SERVER['HTTP_HOST'] . $path . '/'. $scriptName;
echo $baseUrl ;
?>
</strong>
</code>
<p style="margin:8px 0 0 0;font-size:14px;color:#666;">指定源：<?=$baseUrl?>?y=代号</p>
</div>
<script>
    function copyUrl(){
        const text = document.getElementById('tvUrl').innerText.trim();
        const tipEl = document.getElementById('copyTip');
        const linkEl = document.getElementById('copyLink');
        linkEl.style.display = 'none';
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(()=>{
                showTip(tipEl, linkEl);
            }).catch(()=>{
                fallbackCopy(text, tipEl, linkEl);
            });
        } else {
            fallbackCopy(text, tipEl, linkEl);
        }
    }
    function fallbackCopy(text, tipEl, linkEl){
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'absolute';
        ta.style.left = '-9999px';
        ta.style.top = '-9999px';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        ta.setSelectionRange(0, ta.value.length);
        const ok = document.execCommand('copy');
        document.body.removeChild(ta);
        if(ok){
            tipEl.innerText = "✅复制成功 ";
            tipEl.style.color = "#00b42a";
            showTip(tipEl, linkEl);
        }else{
            tipEl.innerText = "❌复制失败 ";
            tipEl.style.color = "#f53f3f";
            tipEl.style.display = "inline";
            setTimeout(()=>{
                tipEl.style.display = "none";
                linkEl.style.display = "inline";
            }, 2000);
        }
    }
    function showTip(el, linkEl){
        el.style.display = "inline";
        setTimeout(()=>{
            el.style.display = "none";
            linkEl.style.display = 'inline';
        }, 2000);
    }
</script>
<div class="card">
<h4>源列表，共 <?=count($cfg['list'])?> 条</h4>
<?php if(empty($cfg['list'])){echo '<p>暂无数据源，请添加</p>';} ?>
<?php foreach($showList as $s): ?>
<div class="item <?= $s['id'] == $cfg['default_id'] ? 'def' : '' ?>">
<div style="display:flex;align-items:center;gap:8px;justify-content:space-between;">
    <b style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0;"><?=htmlspecialchars($s['name'])?></b>
    <span style="background:#eee;padding:2px 6px;border-radius:4px;font-size:13px;white-space:nowrap;">代号：<?=htmlspecialchars($s['code'])?></span>
</div>
<div style="word-break:break-all;font-size:14px;color:#444"><?=htmlspecialchars($s['url'])?></div>
<?php if(!empty($s['note'])):?><div style="font-size:13px;color:#777"><?=htmlspecialchars($s['note'])?></div><?php endif;?>
<div style="margin-top:8px">
<button class="btn btn-blue" onclick="testUrl('<?=htmlspecialchars($s['url'])?>',this)">测试</button>
<button type="button" class="btn btn-green" onclick="location.href='?admin&edit=<?=$s['id']?>'">修改</button>
<form style="display:inline" onsubmit="return confirm('确认删除？')" method="post">
<input type="hidden" name="act" value="del">
<input type="hidden" name="id" value="<?=$s['id']?>">
<button class="btn btn-red">删除</button>
</form>
<?php if($s['id'] == $cfg['default_id']): ?>
<span style="color:#1677ff;padding-left: 10px;">✅ 默认</span>
<?php endif; ?>
<?php if($s['id'] != $cfg['default_id']): ?>
<form style="display:inline" method="post">
<input type="hidden" name="act" value="setdefault">
<input type="hidden" name="id" value="<?=$s['id']?>">
<button class="btn">设为默认</button>
</form>
<?php endif; ?>
</div>
</div>
<?php endforeach;?>
</div>
<script>
function testUrl(u,el){
    el.innerText="测试中...";
    fetch('?admin&test='+encodeURIComponent(u))
    .then(r=>r.text())
    .then(code=>{
        if(code==200) el.innerText="正常";
        else el.innerText="异常("+code+")";
    })
    .catch(()=>el.innerText="失败");
}
(function(){
    const p = new URLSearchParams(location.search);
    if(p.has('edit')){
        setTimeout(()=>{
            document.getElementById('form-box').scrollIntoView({behavior:'smooth'});
        }, 50);
    }
})();
</script>
<div class="card" id="form-box">
<?php if ($editItem): ?>
    <h4><span style="display:inline-flex;align-items:center;gap:10px;">修改数据源
        <span class="form-title-tag">
            代号：<span class="code-value"><?=htmlspecialchars($editItem['code'])?></span>
            <span class="code-tip">（不可修改）</span>
        </span>
    </span></h4>
    <form method="post">
    <input type="hidden" name="act" value="edit">
    <input type="hidden" name="id" value="<?=$editItem['id']?>">
    名称：<input name="name" value="<?=htmlspecialchars($editItem['name'])?>" required>
    地址：<input name="url" value="<?=htmlspecialchars($editItem['url'])?>" required>
    备注：<input name="note" value="<?=htmlspecialchars($editItem['note'])?>">
    <button class="btn btn-green" type="submit">修改</button>
    <button type="button" class="btn" onclick="location.href='?admin'">取消</button>
    </form>
<?php else: ?>
    <h4><span style="display:inline-flex;align-items:center;gap:10px;">新增数据源
        <span class="form-title-tag">
            代号：<span class="code-value">自动生成</span>
        </span>
    </span></h4>
    <form method="post">
    <input type="hidden" name="act" value="add">
    名称：<input name="name" placeholder="例如：主源" required>
    地址：<input name="url" placeholder="https://xxx/xxx" required>
    备注：<input name="note" placeholder="备注（可选）">
    <button class="btn btn-blue" type="submit">添加</button>
    </form>
<?php endif; ?>
</div>
</body>
</html>
<?php
exit;
}
// ---------------------- 管理面板逻辑 end ----------------------

// ---------------------- TV中转接口逻辑 start（不带?admin参数时执行） ----------------------
// 创建缓存目录
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}
if (!file_exists($jsonFile)) {
    http_response_code(500);
    exit('配置文件不存在');
}
$cfg = json_decode(file_get_contents($jsonFile), true);
$wantCode = trim($_GET['y'] ?? '');
$target = null;
$useCode = '';

// 先尝试按代号匹配
if ($wantCode !== '') {
    foreach ($cfg['list'] as $item) {
        if ($item['code'] === $wantCode) {
            $target = $item['url'];
            $useCode = $item['code'];
            break;
        }
    }
}

// 上面没匹配到（y为空或者代号不存在），切默认源
if ($target === null) {
    foreach ($cfg['list'] as $item) {
        if ($item['id'] == $cfg['default_id']) {
            $target = $item['url'];
            $useCode = $item['code'];
            break;
        }
    }
}

// 连默认源都没有
if ($target === null) {
    http_response_code(503);
    exit('未设置默认源');
}

$cacheKey = md5($useCode);
$cacheFile = $cacheDir . '/' . $cacheKey . '.cache';

// 判断缓存是否有效
$cacheValid = false;
if (file_exists($cacheFile)) {
    $metaPath = $cacheFile . '.meta';
    $cacheInfo = json_decode(file_get_contents($metaPath), true);
    if ($cacheInfo && time() - $cacheInfo['time'] < $cacheTtl && $cacheInfo['code'] === $useCode && $cacheInfo['url'] === $target) {
        $cacheValid = true;
    }
}

if ($cacheValid) {
    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-cache, no-store, must-revalidate");
    echo file_get_contents($cacheFile);
    exit;
}

// 缓存失效，重新拉取上游源
$ch = curl_init($target);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_USERAGENT, "okhttp/3.15");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if($code != 200 || empty($resp)){
    http_response_code(502);
    exit("源拉取失败，HTTP码：".$code);
}

// 写入缓存和元信息
file_put_contents($cacheFile, $resp);
file_put_contents($cacheFile . '.meta', json_encode([
    'code' => $useCode,
    'url' => $target,
    'time' => time()
], JSON_UNESCAPED_SLASHES));

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-cache, no-store, must-revalidate");
echo $resp;
exit;
?>
