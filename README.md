<div id="top"></div>

<div align="center">
  <a href="https://github.com/bridgeeighteen/b18-exam">
    <img src="views/assets/logo_text.svg" alt="十八桥社区" height="60">
  </a>

<h3 align="center">论坛入站测试系统</h3>

  <p align="center">
    十八桥社区论坛使用的入站测试系统，基于 Bootstrap 和 PHP。
    <br />
    <br />
    <a href="https://github.com/bridgeeighteen/b18-exam/issues">反馈 Bug</a>
    ·
    <a href="https://github.com/bridgeeighteen/b18-exam/issues">请求新功能</a>
    <br />
    <br />
    <img src="https://img.shields.io/github/contributors/bridgeeighteen/b18-exam.svg" alt="贡献者总数">
    <img src="https://img.shields.io/github/forks/bridgeeighteen/b18-exam.svg" alt="Forks 总数">
    <img src="https://img.shields.io/github/stars/bridgeeighteen/b18-exam.svg" alt="Stars 总数">
    <img src="https://img.shields.io/github/issues/bridgeeighteen/b18-exam.svg" alt="Issues 总数">
    <img src="https://img.shields.io/packagist/v/bridgeeighteen/exam" alt="Composer 版本">
    <img src="https://img.shields.io/packagist/l/bridgeeighteen/exam" alt="许可证">
  </p>
</div>

<!-- 目录 -->
<details>
  <summary>目录</summary>
  <ol>
    <li>
      <a href="#关于本项目">关于本项目</a>
      <ul>
        <li><a href="#构建工具">构建工具</a></li>
      </ul>
    </li>
    <li>
      <a href="#开始">开始</a>
      <ul>
        <li><a href="#依赖">依赖</a></li>
        <li><a href="#正常安装（生产环境推荐）">正常安装</a></li>
        <li><a href="#使用Git克隆安装">使用 Git 克隆安装</a></li>
      </ul>
    </li>
    <li><a href="#主要功能">主要功能</a></li>
    <li><a href="#贡献">贡献</a></li>
    <li><a href="#许可证">许可证</a></li>
    <li><a href="#联系我们">联系我们</a></li>
  </ol>
</details>

<!-- 关于本项目 -->
## 关于本项目

这是十八桥社区论坛的入站测试系统，用于让用户完成入站测试并根据成绩获得邀请码以在论坛注册。

<p align="right">(<a href="#top">回到顶部</a>)</p>

### 构建工具

* [Composer](https://getcomposer.org)
* [Bootstrap 4.6](https://getbootstrap.com/docs/4.6/)
* [jQuery](https://jquery.com)

<p align="right">(<a href="#top">回到顶部</a>)</p>

<!-- 开始 -->
## 开始

要获取本地副本并且配置运行，你可以按照下面的示例步骤操作。

### 依赖

* Composer
* MySQL
* PHP
* Nginx / Apache
* 已经部署好的 Flarum
  * [FoF Doorman 插件](https://github.com/FriendsOfFlarum/doorman)
  * [OAuth Center 插件](https://github.com/FoskyM/flarum-oauth-center)

### 正常安装（生产环境推荐）

1. 在 [Cloudflare 仪表板](https://dash.cloudflare.com/)中获取 Turnstile 的密钥（测试用途不需要），然后去 Flarum 的个人主页获取 API 密钥。同时，你需要在 Flarum 中利用 OAuth Center 插件的管理面板创建一个新的应用，先复制（或记下）插件自动生成的 ID 和私钥，然后依需要填写其余内容。回调地址填 `https://你的部署网站/admin/oauth.php`。

2. 通过 Composer 创建新项目。这里的 `my-new-project` 可以根据实际需要更换。

   ```shell
   composer create-project bridgeeighteen/exam my-new-project
   ```

3. 在 `config-example.php` 中根据注释提示完成配置。如果只是用于测试，须保留模板中给定的 Turnstile 密钥。

4. 使用 phpMyAdmin 等导入 `table.sql` 中定义的数据表及结构。

5. 在 `questions` 表中手工录入试题。在后续版本中，可以通过管理面板导入 Markdown 试题，由系统自动识别并录入。

### 使用 Git 克隆安装

1. 在 [Cloudflare 仪表板](https://dash.cloudflare.com/)中获取 Turnstile 的密钥（测试用途不需要），然后去 Flarum 的个人主页获取 API 密钥。同时，你需要在 Flarum 中利用 OAuth Center 插件的管理面板创建一个新的应用，先复制（或记下）插件自动生成的 ID 和私钥，然后依需要填写其余内容。回调地址填 `https://你的部署网站/admin/oauth.php`。

2. 克隆本仓库。

   ```shell
   git clone https://github.com/bridgeeighteen/b18-exam.git
   ```

3. 安装 Composer 依赖包。

   ```shell
   composer install
   ```

4. 在 `config-example.php` 中根据注释提示完成配置。如果只是用于测试，须保留模板中给定的 Turnstile 密钥。

5. 使用 phpMyAdmin 等导入 `table.sql` 中定义的数据表及结构。

6. 在 `questions` 表中手工录入试题。在后续版本中，可以通过管理面板导入 Markdown 试题，由系统自动识别并录入。

<p align="right">(<a href="#top">回到顶部</a>)</p>


<!-- 主要功能 -->
## 主要功能

- [x] 支持单选/多选试题
- [x] 支持多基类（分区）试题设置
- [x] 时间作弊检测
- [x] 自定义过关分数阈值、每题全对分数和多选题漏选分数
- [x] 完美支持 Flarum 内置 API 接口和 FoF Doorman 插件自带 API 接口
- [ ] 识别 Markdown 并自动录入试题

你也可以到 [Open Issues](https://github.com/bridgeeighteen/b18-exam/issues) 页查看所有请求的功能（以及已知的问题）。

<p align="right">(<a href="#top">回到顶部</a>)</p>

<!-- 贡献 -->
## 贡献

贡献让开源社区成为了一个非常适合学习、互相激励和创新的地方。你所做出的任何贡献都是**受人尊敬**的。

如果你有好的建议，请复刻（Fork）本仓库并且创建一个拉取请求（Pull Request）。你也可以简单地创建一个议题（Issue），并且添加标签「enhancement」。不要忘记给项目点一个 Star！再次感谢！

1. 复刻（Fork）本项目
2. 创建你的 Feature 分支 (`git checkout -b feature/AmazingFeature`)
3. 提交你的变更 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到该分支 (`git push origin feature/AmazingFeature`)
5. 创建一个拉取请求（Pull Request）

<p align="right">(<a href="#top">回到顶部</a>)</p>

<!-- 许可证 -->
## 许可证

根据 LGPL-3.0+ 许可证分发。GPL-3.0 和 LGPL-3.0 的完整副本请见 [LICENSE](LICENSE)。

<p align="right">(<a href="#top">回到顶部</a>)</p>

<!-- 联系我们 -->
## 联系我们

管理团队邮箱：admin@bridge18.rr.nu

IRC 频道：irc://irc.libera.chat/#bridgeeighteen

<p align="right">(<a href="#top">回到顶部</a>)</p>
