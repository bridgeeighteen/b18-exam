<?php
require_once __DIR__ . '/../includes/version.php';
?>
        <div class="row justify-content-center mt-5">
            <p>版本 <?php echo htmlspecialchars(VERSION); ?> | 使用 <a href="https://www.gnu.org/licenses/lgpl-3.0.html">LGPL-3.0-or-later</a> 许可证 | © 2025 The Bridge Eighteen Community.</p>
        </div>
    </div>
    <script src="./vendor/components/jquery/jquery.min.js"></script>
    <script src="./vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(function () {
            $('[data-toggle="tooltip"]').tooltip()
        })
    </script>
</body>

</html>
