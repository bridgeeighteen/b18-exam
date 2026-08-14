(function () {
    'use strict';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function apiRequest(method, path, params) {
        method = method || 'GET';
        var url = '../api/index.php/v1' + path;
        var options = {
            method: method,
            credentials: 'same-origin'
        };

        var query = Object.keys(params || {}).filter(function (k) {
            return params[k] !== '' && params[k] !== null && params[k] !== undefined;
        }).map(function (k) {
            return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
        }).join('&');

        if (method === 'GET' || method === 'DELETE') {
            if (query) {
                url += (url.indexOf('?') === -1 ? '?' : '&') + query;
            }
            if (method === 'DELETE') {
                options.headers = {
                    'X-CSRF-Token': csrfToken()
                };
            }
        } else {
            options.headers = {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken()
            };
            options.body = JSON.stringify(params || {});
        }

        return fetch(url, options).then(function (response) {
            if (response.status === 204) {
                return null;
            }
            return response.json().then(function (payload) {
                if (!payload.ok) {
                    var message = payload.data && payload.data.error ? payload.data.error.message : '请求失败。';
                    throw new Error(message);
                }
                return payload.data;
            });
        });
    }

    // ---------- tests.php ----------

    var statusLabels = {
        submitted: '已完成',
        in_progress: '进行中',
        abandoned: '中途退出',
        not_started: '未开始'
    };

    var detailButtons = document.querySelectorAll('[data-detail]');
    detailButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var channel = button.getAttribute('data-channel');
            var hasResult = button.getAttribute('data-has-result') === '1';
            var id = button.getAttribute('data-detail');
            var userId = button.getAttribute('data-user-id');
            var status = button.getAttribute('data-status') || '';
            var body = document.getElementById('detailModalBody');
            body.innerHTML = '<div class="text-muted">加载中…</div>';

            var request = hasResult
                ? apiRequest('GET', '/results/' + encodeURIComponent(id), { channel: channel })
                : apiRequest('GET', '/users/' + encodeURIComponent(userId), { channel: channel });

            request.then(function (data) {
                var user = data.user;
                var history = data.history || [];
                var html = '<dl class="row mb-3">';
                html += '<dt class="col-sm-3">用户 ID</dt><dd class="col-sm-9">' + escapeHtml(user.id !== undefined ? user.id : user.user_id) + '</dd>';
                html += '<dt class="col-sm-3">用户名</dt><dd class="col-sm-9">' + escapeHtml(user.username) + '</dd>';
                html += '<dt class="col-sm-3">邮箱</dt><dd class="col-sm-9">' + escapeHtml(user.email) + '</dd>';
                if (user.selected_categories) {
                    html += '<dt class="col-sm-3">基类组合</dt><dd class="col-sm-9">' + escapeHtml(user.selected_categories) + '</dd>';
                }
                if (user.matrix_oauth_mxid) {
                    html += '<dt class="col-sm-3">Matrix 免考</dt><dd class="col-sm-9">' + escapeHtml(user.matrix_oauth_mxid) + (user.matrix_oauth_verified_at ? '（' + escapeHtml(user.matrix_oauth_verified_at) + '）' : '') + '</dd>';
                }
                if (user.forum_oauth_user_id) {
                    html += '<dt class="col-sm-3">论坛免考</dt><dd class="col-sm-9">用户 #' + escapeHtml(user.forum_oauth_user_id) + (user.forum_oauth_verified_at ? '（' + escapeHtml(user.forum_oauth_verified_at) + '）' : '') + '</dd>';
                }
                html += '<dt class="col-sm-3">状态</dt><dd class="col-sm-9">' + escapeHtml(statusLabels[status] || '—') + '</dd>';
                html += '<dt class="col-sm-3">开始时间</dt><dd class="col-sm-9">' + escapeHtml(user.start_time || '—') + '</dd>';
                html += '</dl>';

                html += '<h6 class="section-head">历史测试记录</h6>';
                html += '<table class="table table-sm"><thead><tr><th>ID</th><th>分数</th><th>交卷时间</th><th>凭据</th></tr></thead><tbody>';
                history.forEach(function (row) {
                    html += '<tr><td>' + escapeHtml(row.id) + '</td><td>' + escapeHtml(row.score) + '</td><td>' + escapeHtml(row.end_time) + '</td><td><code>' + escapeHtml(row.code) + '</code></td></tr>';
                });
                if (history.length === 0) {
                    html += '<tr><td colspan="4" class="text-muted">暂无记录</td></tr>';
                }
                html += '</tbody></table>';
                body.innerHTML = html;
            }).catch(function (error) {
                body.innerHTML = '<div class="alert alert-danger mb-0" role="alert">' + escapeHtml(error.message) + '</div>';
            });
        });
    });

    document.querySelectorAll('.btn-delete-result').forEach(function (button) {
        button.addEventListener('click', function () {
            var channel = button.getAttribute('data-channel');
            var id = button.getAttribute('data-id');
            if (!confirm('确定要删除该测试记录吗？')) {
                return;
            }
            apiRequest('DELETE', '/results/' + encodeURIComponent(id), { channel: channel }).then(function () {
                location.reload();
            }).catch(function (error) {
                alert(error.message);
            });
        });
    });

    document.querySelectorAll('.btn-delete-user').forEach(function (button) {
        button.addEventListener('click', function () {
            var channel = button.getAttribute('data-channel');
            var userId = button.getAttribute('data-user-id');
            if (!confirm('确定要删除该用户及其全部测试记录吗？此操作不可恢复。')) {
                return;
            }
            apiRequest('DELETE', '/users/' + encodeURIComponent(userId), { channel: channel }).then(function () {
                location.reload();
            }).catch(function (error) {
                alert(error.message);
            });
        });
    });

    // ---------- questions.php ----------

    function fillEditForm(question) {
        document.getElementById('edit_id').value = question.id;
        document.getElementById('edit_category').value = question.category;
        document.getElementById('edit_type').value = question.type;
        document.getElementById('edit_author').value = question.author || '';
        document.getElementById('edit_question_text').value = question.question_text;
        document.getElementById('edit_option_a').value = question.option_a;
        document.getElementById('edit_option_b').value = question.option_b;
        document.getElementById('edit_option_c').value = question.option_c;
        document.getElementById('edit_option_d').value = question.option_d;
        document.getElementById('edit_answer').value = question.answer;
        var error = document.getElementById('editModalError');
        error.classList.add('d-none');
    }

    document.querySelectorAll('.btn-edit-question').forEach(function (button) {
        button.addEventListener('click', function () {
            apiRequest('GET', '/questions/' + encodeURIComponent(button.getAttribute('data-id'))).then(function (question) {
                fillEditForm(question);
                var modal = new bootstrap.Modal(document.getElementById('editModal'));
                modal.show();
            }).catch(function (error) {
                alert(error.message);
            });
        });
    });

    var saveButton = document.getElementById('btn-save-question');
    if (saveButton) {
        saveButton.addEventListener('click', function () {
            var payload = {
                id: document.getElementById('edit_id').value,
                category: document.getElementById('edit_category').value,
                type: document.getElementById('edit_type').value,
                author: document.getElementById('edit_author').value,
                question_text: document.getElementById('edit_question_text').value,
                option_a: document.getElementById('edit_option_a').value,
                option_b: document.getElementById('edit_option_b').value,
                option_c: document.getElementById('edit_option_c').value,
                option_d: document.getElementById('edit_option_d').value,
                answer: document.getElementById('edit_answer').value
            };
            saveButton.disabled = true;
            apiRequest('PUT', '/questions/' + encodeURIComponent(document.getElementById('edit_id').value), payload).then(function () {
                location.reload();
            }).catch(function (error) {
                var errorBox = document.getElementById('editModalError');
                errorBox.textContent = error.message;
                errorBox.classList.remove('d-none');
                saveButton.disabled = false;
            });
        });
    }

    document.querySelectorAll('.btn-delete-question').forEach(function (button) {
        button.addEventListener('click', function () {
            var id = button.getAttribute('data-id');
            if (!confirm('确定要删除这道题目吗？此操作不可恢复。')) {
                return;
            }
            apiRequest('DELETE', '/questions/' + encodeURIComponent(id)).then(function () {
                location.reload();
            }).catch(function (error) {
                alert(error.message);
            });
        });
    });

    // ---------- 导入预览 / 确认 ----------

    var previewButton = document.getElementById('btn-preview-import');
    var confirmButton = document.getElementById('btn-confirm-import');
    var clearButton = document.getElementById('btn-clear-import');

    if (previewButton) {
        var pendingMarkdown = null;
        var pendingCategory = '';

        previewButton.addEventListener('click', function () {
            var markdown = document.getElementById('import_markdown').value;
            if (!markdown.trim()) {
                alert('请先粘贴 Markdown 表格内容。');
                return;
            }
            previewButton.disabled = true;
            apiRequest('POST', '/questions/import', {
                markdown: markdown,
                category: document.getElementById('import_category').value,
                skip_duplicates: document.getElementById('skip_duplicates').checked,
                dry_run: true
            }).then(function (data) {
                pendingMarkdown = markdown;
                pendingCategory = document.getElementById('import_category').value;

                var html = '<div class="alert alert-info" role="alert">共识别 ' + data.total + ' 行，其中有效 ' + data.valid + ' 行、无效 ' + data.invalid + ' 行。</div>';

                if (data.errors.length > 0) {
                    html += '<h6 class="section-head">无效行（不参与导入）</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>行号</th><th>内容</th><th>问题</th></tr></thead><tbody>';
                    data.errors.forEach(function (item) {
                        html += '<tr><td>' + item.row + '</td><td><code>' + escapeHtml(item.raw) + '</code></td><td>' + escapeHtml(item.errors.join('；')) + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                }

                var resultBox = document.getElementById('import-result');
                resultBox.innerHTML = html;
                confirmButton.classList.remove('d-none');
                clearButton.classList.remove('d-none');
            }).catch(function (error) {
                alert(error.message);
            }).finally(function () {
                previewButton.disabled = false;
            });
        });

        confirmButton.addEventListener('click', function () {
            confirmButton.disabled = true;
            apiRequest('POST', '/questions/import', {
                markdown: pendingMarkdown,
                category: pendingCategory,
                skip_duplicates: document.getElementById('skip_duplicates').checked,
                dry_run: false
            }).then(function (data) {
                document.getElementById('import-result').innerHTML =
                    '<div class="alert alert-success" role="alert">导入完成：新增 ' + data.imported + ' 题、跳过重复 ' + data.skipped + ' 题、失败 ' + data.failed + ' 题。</div>' +
                    (data.errors.length > 0 ? '<div class="alert alert-warning" role="alert">' + data.errors.map(escapeHtml).join('<br>') + '</div>' : '');
                confirmButton.classList.add('d-none');
                clearButton.classList.remove('d-none');
                document.getElementById('import_markdown').value = '';
            }).catch(function (error) {
                alert(error.message);
                confirmButton.disabled = false;
            });
        });

        clearButton.addEventListener('click', function () {
            document.getElementById('import-result').innerHTML = '';
            confirmButton.classList.add('d-none');
            clearButton.classList.add('d-none');
        });
    }

    // ---------- 导出 ----------

    var exportButton = document.getElementById('btn-export');
    if (exportButton) {
        exportButton.addEventListener('click', function () {
            exportButton.disabled = true;
            apiRequest('GET', '/questions/export', {
                category: document.getElementById('export_category').value,
                type: document.getElementById('export_type').value
            }).then(function (data) {
                document.getElementById('export_markdown').value = data.markdown;
            }).catch(function (error) {
                alert(error.message);
            }).finally(function () {
                exportButton.disabled = false;
            });
        });
    }

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = String(value === null || value === undefined ? '' : value);
        return div.innerHTML;
    }
})();
