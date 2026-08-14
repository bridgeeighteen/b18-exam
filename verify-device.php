<?php
require_once 'config.php';
require_once 'includes/security.php';
require_once 'includes/db.php';
require_once 'includes/blacklist.php';

initSecurity();
sendSecurityHeaders();

// 安全验证页：黑名单命中后，先采集设备 UA / 位置（检测次数超过 3 次时
// 强制尝试采集 IMEI）并安全存入数据库，随后才拦截本次请求。
// 命中信息由 exam.php / matrix-exam.php / result.php / matrix-result.php
// 写入会话（$_SESSION['blacklist_hit']），一次性 nonce 防止伪造设备记录。

$hit = isset($_SESSION['blacklist_hit']) && is_array($_SESSION['blacklist_hit']) ? $_SESSION['blacklist_hit'] : null;
$imeiRequired = $hit !== null && blacklistImeiRequired((int)($hit['detection_count'] ?? 0));

$verified = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nonceOk = $hit !== null
        && isset($hit['nonce'])
        && hash_equals((string)$hit['nonce'], (string)($_POST['nonce'] ?? ''));

    if ($nonceOk) {
        // 一次性消费：立即清除会话中的命中信息，防止重复提交伪造设备记录
        unset($_SESSION['blacklist_hit']);

        $entryId = (int)$hit['entry_id'];
        if (getBlacklistEntry($entryId) !== null) {
            $ua = trim((string)($_POST['ua'] ?? ''));
            if ($ua === '') {
                $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
            }

            $lat = isset($_POST['latitude']) && is_numeric($_POST['latitude']) ? (float)$_POST['latitude'] : null;
            $lng = isset($_POST['longitude']) && is_numeric($_POST['longitude']) ? (float)$_POST['longitude'] : null;
            $accuracy = isset($_POST['accuracy']) && is_numeric($_POST['accuracy']) ? (float)$_POST['accuracy'] : null;
            $location = normalizeBlacklistLocation($lat, $lng, $accuracy, 'geolocation');

            $imei = trim((string)($_POST['imei'] ?? ''));

            recordBlacklistDevice($entryId, $ua, $location, $imei !== '' ? $imei : null);
        }

        $verified = true;
    } else {
        // nonce 无效：清空会话中的命中信息，按已拦截处理
        unset($_SESSION['blacklist_hit']);
        $verified = true;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安全验证 - 十八桥社区入站测试系统</title>
    <link rel="stylesheet" href="./vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="./views/assets/css/noto-face.css">
    <link rel="stylesheet" href="./views/assets/css/tokens.css">
</head>

<?php require './views/nav.php'; ?>
<div class="page page-narrow mx-auto">
<?php if ($verified) : ?>
    <div class="card form-card mt-4">
        <div class="card-body">
            <h1 class="page-title">无法继续测试</h1>
            <div class="alert alert-danger" role="alert">无法继续测试。如有疑问，请通过管理邮箱联系我们。</div>
            <a class="btn btn-primary" href="info.php" role="button">返回信息登记</a>
        </div>
    </div>
<?php elseif ($hit === null) : ?>
    <div class="card form-card mt-4">
        <div class="card-body">
            <h1 class="page-title">安全验证</h1>
            <div class="alert alert-info" role="alert">当前没有待处理的安全验证。如果你认为这是一个错误，请通过管理邮箱联系我们。</div>
            <a class="btn btn-primary" href="info.php" role="button">返回信息登记</a>
        </div>
    </div>
<?php else : ?>
    <div class="card form-card mt-4">
        <div class="card-body">
            <h1 class="page-title">安全验证</h1>
            <p class="page-subtitle">为保障系统安全，继续之前需要先完成设备信息采集。系统将自动读取当前设备的标识与位置信息（位置采集需要你的授权），采集完成后自动继续。</p>
            <ul class="mb-4">
                <li><span id="status-ua">正在采集设备信息…</span></li>
                <li><span id="status-location">正在采集位置信息…</span></li>
                <?php if ($imeiRequired) : ?>
                <li><span id="status-imei">正在采集设备 IMEI…</span></li>
                <?php endif; ?>
            </ul>
            <form id="verify-form" method="post" action="verify-device.php">
                <input type="hidden" name="nonce" value="<?php echo htmlspecialchars((string)$hit['nonce']); ?>">
                <input type="hidden" name="ua" id="field-ua" value="">
                <input type="hidden" name="latitude" id="field-latitude" value="">
                <input type="hidden" name="longitude" id="field-longitude" value="">
                <input type="hidden" name="accuracy" id="field-accuracy" value="">
                <?php if ($imeiRequired) : ?>
                <div class="form-section">
                    <label for="field-imei" class="form-label">设备 IMEI</label>
                    <input type="text" class="form-control" id="field-imei" name="imei" maxlength="20" inputmode="numeric" placeholder="拨号 *#06# 即可查看设备 IMEI">
                    <small class="form-text">出于安全验证需要，请填写当前设备的 IMEI（拨号 *#06# 即可查看）。留空将在倒计时结束后自动继续。</small>
                </div>
                <?php endif; ?>
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" id="verify-submit" class="btn btn-primary btn-lg w-100">继续</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        (function () {
            var form = document.getElementById('verify-form');
            var submitBtn = document.getElementById('verify-submit');
            var imeiRequired = <?php echo $imeiRequired ? 'true' : 'false'; ?>;

            document.getElementById('field-ua').value = navigator.userAgent || '';
            document.getElementById('status-ua').textContent = '设备信息采集完成。';

            var autoSubmitTimer = null;
            var submitted = false;
            var statusImei = imeiRequired ? document.getElementById('status-imei') : null;

            function proceed() {
                if (submitted) {
                    return;
                }
                submitted = true;
                form.submit();
            }

            function scheduleAutoSubmit(delayMs) {
                if (autoSubmitTimer !== null) {
                    clearInterval(autoSubmitTimer);
                }
                var remain = Math.max(1, Math.ceil(delayMs / 1000));
                if (statusImei) {
                    statusImei.textContent = '请在下方输入设备 IMEI，或等待倒计时结束自动继续（' + remain + ' 秒）。';
                }
                autoSubmitTimer = setInterval(function () {
                    remain--;
                    if (statusImei) {
                        statusImei.textContent = '请在下方输入设备 IMEI，或等待倒计时结束自动继续（' + remain + ' 秒）。';
                    }
                    if (remain <= 0) {
                        proceed();
                    }
                }, 1000);
            }

            function locationDone() {
                if (imeiRequired) {
                    submitBtn.textContent = '继续';
                    scheduleAutoSubmit(30000);
                } else {
                    document.getElementById('status-location').textContent = '位置信息采集完成，即将自动继续…';
                    submitBtn.textContent = '继续';
                    scheduleAutoSubmit(3000);
                }
            }

            function tryGeolocation(callback) {
                if (!('geolocation' in navigator)) {
                    callback(false);
                    return;
                }
                var done = false;
                navigator.geolocation.getCurrentPosition(
                    function (position) {
                        if (done) {
                            return;
                        }
                        done = true;
                        document.getElementById('field-latitude').value = position.coords.latitude;
                        document.getElementById('field-longitude').value = position.coords.longitude;
                        document.getElementById('field-accuracy').value = position.coords.accuracy;
                        document.getElementById('status-location').textContent = '位置信息采集完成。';
                        callback(true);
                    },
                    function () {
                        if (done) {
                            return;
                        }
                        done = true;
                        document.getElementById('status-location').textContent = '未能获取位置信息（已跳过）。';
                        callback(false);
                    },
                    { timeout: 10000, maximumAge: 300000 }
                );
                setTimeout(function () {
                    if (!done) {
                        done = true;
                        document.getElementById('status-location').textContent = '未能获取位置信息（已跳过）。';
                        callback(false);
                    }
                }, 12000);
            }

            submitBtn.addEventListener('click', function (event) {
                event.preventDefault();
                proceed();
            });

            form.addEventListener('submit', function () {
                submitted = true;
            });

            tryGeolocation(function () {
                locationDone();
            });
        })();
    </script>
<?php endif; ?>
</div>
<?php require './views/footer.php'; ?>
