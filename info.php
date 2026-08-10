<?php 
require 'config.php';
require_once 'includes/security.php';
require_once 'includes/oauth.php';

initSecurity();
sendSecurityHeaders();
?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>信息登记 - 十八桥社区入站测试系统</title>
    <link rel="stylesheet" href="./vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="./views/assets/css/noto-face.css">
    <link rel="stylesheet" href="./views/assets/css/tokens.css">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit"></script>
</head>

<?php 
require './views/nav.php'; 
$forumAvailable = !FORUM_CLOSED;
$matrixAvailable = MATRIX_ENABLED && !MATRIX_CLOSED;
?>
                <div class="alert alert-danger d-none" role="alert" id="turnstile-error">Cloudflare Turnstile 的 JavaScript 脚本似乎未正确加载。建议刷新页面重新填写。</div>
                <div class="page page-narrow mx-auto">
                    <h1 class="page-title">信息登记</h1>
                    <p class="page-subtitle">在正式开始测试前，请选择你要注册的平台并填写相应的基本信息，以便我们核查邀请码或注册 Token 的使用情况。请记住，将邀请码或注册 Token 转让给他人是绝对禁止的，这会导致账号被封禁。</p>
                    <div class="row g-3 mt-2 mb-4" id="register-choice" role="radiogroup" aria-label="注册目标">
                        <div class="col-md-6">
                            <div class="card choice-card <?php if (!$forumAvailable) : ?>disabled<?php endif; ?> <?php if ($forumAvailable) : ?>active<?php endif; ?>" id="choice-forum" role="radio" aria-checked="<?php echo $forumAvailable ? 'true' : 'false'; ?>" tabindex="<?php echo $forumAvailable ? '0' : '-1'; ?>">
                                <div class="card-body">
                                    <h5 class="card-title mb-2">注册社区论坛</h5>
                                    <p class="card-text mb-0">完成入站测试并获得邀请码，用于注册十八桥社区论坛账号。</p>
                                    <?php if (!$forumAvailable) : ?>
                                    <div class="alert alert-warning mb-0 mt-3" role="alert">测试通道已关闭，原因：<?php echo htmlspecialchars(FORUM_CLOSED_REASON); ?> 更多详情请查看社区论坛和联邦宇宙官宣账号。</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php if (MATRIX_ENABLED) : ?>
                        <div class="col-md-6">
                            <div class="card choice-card <?php if (!$matrixAvailable) : ?>disabled<?php endif; ?>" id="choice-matrix" role="radio" aria-checked="false" tabindex="<?php echo $matrixAvailable ? '0' : '-1'; ?>">
                                <div class="card-body">
                                    <h5 class="card-title mb-2">注册<?php echo htmlspecialchars(MATRIX_INSTANCE_NAME); ?></h5>
                                    <p class="card-text mb-0">完成礼仪测试并获得注册 Token，用于在<?php echo htmlspecialchars(MATRIX_INSTANCE_NAME); ?>上注册账号。</p>
                                    <?php if (!$matrixAvailable) : ?>
                                    <div class="alert alert-warning mb-0 mt-3" role="alert">测试通道已关闭，原因：<?php echo htmlspecialchars(MATRIX_CLOSED_REASON); ?> 更多详情请查看社区论坛和联邦宇宙官宣账号。</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card form-card mt-1 <?php if (!$forumAvailable) : ?>d-none<?php endif; ?>" id="form-forum">
                        <div class="card-body">
                            <form action="exam.php" method="post">
                                <?php if (masOAuthEnabled()) : ?>
                                <?php $matrixOauth = matrixOAuthVerified(); ?>
                                <?php if ($matrixOauth !== null) : ?>
                                <div class="form-section">
                                    <div class="alert alert-success" role="alert">已通过 Matrix 账号（<?php echo htmlspecialchars($matrixOauth['mxid']); ?>）验证。请填写与该 Matrix 账号绑定的电子邮件地址，提交后基本礼仪题部分将免考并直接获得满分。</div>
                                </div>
                                <?php else : ?>
                                <div class="form-section">
                                    <a class="btn btn-outline-secondary w-100" href="oauth-matrix.php" role="button">使用 Matrix 账号登录（免考礼仪测试）</a>
                                    <small class="form-text">已在<?php echo htmlspecialchars(MATRIX_INSTANCE_NAME); ?>等 Matrix 实例拥有账号的用户，可以通过登录其 Matrix 账号免除入站测试中的基本礼仪题。</small>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>
                                <div class="form-section">
                                    <label for="InputUsername" class="form-label">用户名</label>
                                    <input type="text" class="form-control" id="InputUsername" aria-describedby="usernameHelp" name="username" required>
                                    <small id="usernameHelp" class="form-text">如果测试通过后想更换，可以在注册时填写。</small>
                                </div>
                                <div class="form-section">
                                    <label for="InputEmail" class="form-label">电子邮件地址</label>
                                    <input type="email" class="form-control" id="InputEmail" aria-describedby="emailHelp" name="email" required>
                                    <small id="emailHelp" class="form-text">请一定确保这里的地址与注册的地址完全一致，否则账号将因被视为“邀请码滥用”遭到封禁。</small>
                                </div>
                                <div class="form-section">
                                    <label for="categories" class="form-label">选择基类</label>
                                    <div class="chip-list">
                                        <div class="chip">
                                            <input class="form-check-input" type="checkbox" name="categories[]" value="IT" id="category_IT">
                                            <label class="form-check-label" for="category_IT">IT</label>
                                        </div>
                                        <div class="chip">
                                            <input class="form-check-input" type="checkbox" name="categories[]" value="ACGN" id="category_ACGN">
                                            <label class="form-check-label" for="category_ACGN">ACGN</label>
                                        </div>
                                        <div class="chip">
                                            <input class="form-check-input" type="checkbox" name="categories[]" value="Virtual_Singer" id="category_Virtual_Singer">
                                            <label class="form-check-label" for="category_Virtual_Singer">虚拟歌手</label>
                                        </div>
                                        <div class="chip">
                                            <input class="form-check-input" type="checkbox" name="categories[]" value="Broadcasting" id="category_Broadcasting">
                                            <label class="form-check-label" for="category_Broadcasting">广播电视</label>
                                        </div>
                                    </div>
                                    <small id="categoriesHelp" class="form-text">社区将论坛目前规划的板块划分为以上四个基本类型，请从中选择两类作为自选试题的考查方向。</small>
                                </div>
                                <div class="form-section form-check">
                                    <input type="checkbox" class="form-check-input" id="ruleCheck" required>
                                    <label class="form-check-label" for="ruleCheck">我已阅读<a href="<?php echo htmlspecialchars(TOS_URL); ?>">使用条款</a>，并确认完全理解其内容。</label>
                                </div>
                                <div class="form-section" id="turnstile-forum"></div>
                                <button type="submit" class="btn btn-primary btn-lg w-100">开始测试</button>
                            </form>
                        </div>
                    </div>
                    <?php if (MATRIX_ENABLED) : ?>
                    <div class="card form-card mt-1 <?php if ($forumAvailable || !$matrixAvailable) : ?>d-none<?php endif; ?>" id="form-matrix">
                        <div class="card-body">
                            <form action="matrix-exam.php" method="post">
                                <?php if (forumOAuthEnabled()) : ?>
                                <?php $forumOauth = forumOAuthVerified(); ?>
                                <?php if ($forumOauth !== null) : ?>
                                <div class="form-section">
                                    <div class="alert alert-success" role="alert">已通过论坛账号（<?php echo htmlspecialchars($forumOauth['username'] !== '' ? $forumOauth['username'] : 'ID ' . $forumOauth['user_id']); ?>）验证。请使用与该论坛账号绑定的电子邮件地址填写下方表单，提交后礼仪测试将免考并直接获得满分。</div>
                                </div>
                                <?php else : ?>
                                <div class="form-section">
                                    <a class="btn btn-outline-secondary w-100" href="oauth-forum.php" role="button">使用论坛账号登录（免考礼仪测试）</a>
                                    <small class="form-text">已注册十八桥社区论坛账号的用户，可以通过登录论坛账号免除礼仪测试，直接获取注册 Token。请使用与论坛账号绑定的电子邮件地址填写下方表单。</small>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>
                                <div class="form-section">
                                    <label for="MatrixInputUsername" class="form-label">期望用户名</label>
                                    <input type="text" class="form-control" id="MatrixInputUsername" aria-describedby="matrixUsernameHelp" name="username" required minlength="1" maxlength="254">
                                    <small id="matrixUsernameHelp" class="form-text">仅限小写字母、数字以及 . _ = - 符号。实际注册时可以使用略有不同的用户名。</small>
                                </div>
                                <div class="form-section">
                                    <label for="MatrixInputEmail" class="form-label">电子邮件地址</label>
                                    <input type="email" class="form-control" id="MatrixInputEmail" aria-describedby="matrixEmailHelp" name="email" required>
                                    <small id="matrixEmailHelp" class="form-text">请一定确保这里的地址与注册后绑定的地址完全一致，否则账号将因被视为疑似滥用注册 Token 遭到封停。</small>
                                </div>
                                <div class="alert alert-warning" role="alert">
                                    <strong>重要提醒：</strong>实际注册时可以使用与在这里登记的用户名有些许不同的用户名，也可以使用你的其它电子邮件地址注册，但必须在注册之后绑定在这里登记的电子邮件地址，否则会被认为疑似滥用注册 Token。用户名实际注册后不能更改。社区理事会和<?php echo htmlspecialchars(MATRIX_INSTANCE_NAME); ?>管理委员会在各自职能之内保留警告和择时封停或清理疑似滥用注册 Token 的账号的权利。
                                </div>
                                <div class="form-section form-check">
                                    <input type="checkbox" class="form-check-input" id="matrixRuleCheck" required>
                                    <label class="form-check-label" for="matrixRuleCheck">我已阅读<a href="<?php echo htmlspecialchars(MATRIX_TOS_URL); ?>">使用条款</a>，并确认完全理解其内容。</label>
                                </div>
                                <div class="form-section" id="turnstile-matrix"></div>
                                <button type="submit" class="btn btn-primary btn-lg w-100">开始礼仪测试</button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <script>
                      document.addEventListener('DOMContentLoaded', function() {
                          const forumCard = document.getElementById('choice-forum');
                          const matrixCard = document.getElementById('choice-matrix');
                          const urlParams = new URLSearchParams(window.location.search);
                          if (urlParams.get('target') === 'matrix' && matrixCard && !matrixCard.classList.contains('disabled')) {
                              selectChoice('choice-matrix');
                          } else if (forumCard && forumCard.classList.contains('disabled') && matrixCard && !matrixCard.classList.contains('disabled')) {
                              selectChoice('choice-matrix');
                          }
                          const checkboxes = document.querySelectorAll('input[name="categories[]"]');
                          checkboxes.forEach(checkbox => {
                              checkbox.addEventListener('change', function() {
                                  const checkedCount = document.querySelectorAll('input[name="categories[]"]:checked').length;
                                  if (checkedCount > 2) {
                                      this.checked = false;
                                      alert('只能选择两个基类。');
                                  }
                              });
                          });
                      });
                      function selectChoice(id) {
                          const forumCard = document.getElementById('choice-forum');
                          const matrixCard = document.getElementById('choice-matrix');
                          const matrixForm = document.getElementById('form-matrix');
                          const isForum = id === 'choice-forum';
                          if (isForum) {
                              if (!forumCard || forumCard.classList.contains('disabled')) {
                                  return;
                              }
                              forumCard.classList.add('active');
                              forumCard.setAttribute('aria-checked', 'true');
                              if (matrixCard) {
                                  matrixCard.classList.remove('active');
                                  matrixCard.setAttribute('aria-checked', 'false');
                              }
                          } else {
                              if (!matrixCard || matrixCard.classList.contains('disabled')) {
                                  return;
                              }
                              matrixCard.classList.add('active');
                              matrixCard.setAttribute('aria-checked', 'true');
                              forumCard.classList.remove('active');
                              forumCard.setAttribute('aria-checked', 'false');
                          }
                          document.getElementById('form-forum').classList.toggle('d-none', !isForum);
                          if (matrixForm) {
                              matrixForm.classList.toggle('d-none', isForum);
                          }
                      }
                      document.querySelectorAll('.choice-card').forEach(card => {
                          card.addEventListener('click', function() {
                              selectChoice(this.id);
                          });
                          card.addEventListener('keydown', function(e) {
                              if (e.key === 'Enter' || e.key === ' ') {
                                  e.preventDefault();
                                  selectChoice(this.id);
                              }
                          });
                      });
                      if (typeof turnstile !== 'undefined') {
                        turnstile.ready(function () {
                            <?php if ($forumAvailable) : ?>
                            turnstile.render('#turnstile-forum', {
                                sitekey: <?php echo "'" . htmlspecialchars(CF_TURNSTILE_SITEKEY) . "'" ?>,
                                callback: function (token) {
                                    console.log(`Turnstile 成功通过，已获取 Token。`);
                                    },
                            });
                            <?php endif; ?>
                            <?php if ($matrixAvailable) : ?>
                            turnstile.render('#turnstile-matrix', {
                                sitekey: <?php echo "'" . htmlspecialchars(CF_TURNSTILE_SITEKEY) . "'" ?>,
                                callback: function (token) {
                                    console.log(`Turnstile 成功通过，已获取 Token。`);
                                    },
                            });
                            <?php endif; ?>
                        });
                      } else {
                        document.getElementById('turnstile-error').style.display = 'block';
                        console.error('Turnstile 未定义。');
                      }
                    </script>
<?php require './views/footer.php'; ?>
