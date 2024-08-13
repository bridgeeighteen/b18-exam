<div id="top"></div>

[![Contributors][contributors-shield]][contributors-url]
[![Forks][forks-shield]][forks-url]
[![Stargazers][stars-shield]][stars-url]
[![Issues][issues-shield]][issues-url]
[![MIT License][license-shield]][license-url]



<!-- 项目 LOGO -->
<br />
<div align="center">
  <a href="https://github.com/favocas/b18-exam">
    <img src="views/assets/logo_text.svg" alt="十八桥社区" height="80">
  </a>

<h3 align="center">入站测试系统</h3>

  <p align="center">
    十八桥社区使用的入站测试系统，基于 Bootstrap 和 PHP。
    <br />
    <br />
    <a href="https://github.com/favocas/b18-exam/issues">反馈 Bug</a>
    ·
    <a href="https://github.com/favocas/b18-exam/issues">请求新功能</a>
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
        <li><a href="#安装">安装</a></li>
      </ul>
    </li>
    <li><a href="#使用方法">使用方法</a></li>
    <li><a href="#主要功能">主要功能</a></li>
    <li><a href="#贡献">贡献</a></li>
    <li><a href="#许可证">许可证</a></li>
    <li><a href="#联系我们">联系我们</a></li>
  </ol>
</details>



<!-- 关于本项目 -->
## 关于本项目

这是十八桥社区的入站测试系统，用于让用户完成入站测试并根据成绩获得邀请码以注册。

<p align="right">(<a href="#top">回到顶部</a>)</p>



### 构建工具

* [Composer](https://getcomposer.org)
* [Bootstrap 4](https://getbootstrap.com/docs/4.6/)
* [JQuery](https://jquery.com)

<p align="right">(<a href="#top">回到顶部</a>)</p>



<!-- 开始 -->
## 开始

要获取本地副本并且配置运行，你可以按照下面的示例步骤操作。

### 依赖

* Composer
* MySQL
* PHP
* Nginx / Apache

### 安装

1. 在 [Cloudflare 仪表板](https://dash.cloudflare.com/) 获取 Turnstile 的密钥，然后去 Flarum 的个人主页获取 API 密钥。同时，你需要在 Flarum 中安装 [OAuth Center](https://discuss.flarum.org.cn/d/15447)，利用管理面板创建一个新的应用。回调地址填 `https://你的部署网站/admin/oauth.php`。
2. 克隆本仓库。
   ```sh
   git clone https://github.com/favocas/b18-exam.git
   ```
3. 安装 Composer 依赖包。
   ```sh
   composer update
   ```
4. 在 `config-example.php` 中根据注释提示完成配置。

<p align="right">(<a href="#top">回到顶部</a>)</p>


<!-- 路线图 -->
## 路线图

- [ ] 功能 1
- [ ] 功能 2
- [ ] 功能 3
    - [ ] 嵌套功能

到 [open issues](https://github.com/favocas/b18-exam/issues) 页查看所有请求的功能 （以及已知的问题）。

<p align="right">(<a href="#top">回到顶部</a>)</p>



<!-- 贡献 -->
## 贡献

贡献让开源社区成为了一个非常适合学习、互相激励和创新的地方。你所做出的任何贡献都是**受人尊敬**的。

如果你有好的建议，请复刻（fork）本仓库并且创建一个拉取请求（pull request）。你也可以简单地创建一个议题（issue），并且添加标签「enhancement」。不要忘记给项目点一个 star！再次感谢！

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

IRC 频道：[#bridgeeighteen at Libera.Chat](irc://irc.libera.chat/#bridgeeighteen)

<p align="right">(<a href="#top">回到顶部</a>)</p>




<!-- MARKDOWN 链接 & 图片 -->
<!-- https://www.markdownguide.org/basic-syntax/#reference-style-links -->
[contributors-shield]: https://img.shields.io/github/contributors/favocas/b18-exam.svg?style=for-the-badge
[contributors-url]: https://github.com/favocas/b18-exam/graphs/contributors
[forks-shield]: https://img.shields.io/github/forks/favocas/b18-exam.svg?style=for-the-badge
[forks-url]: https://github.com/favocas/b18-exam/network/members
[stars-shield]: https://img.shields.io/github/stars/favocas/b18-exam.svg?style=for-the-badge
[stars-url]: https://github.com/favocas/b18-exam/stargazers
[issues-shield]: https://img.shields.io/github/issues/favocas/b18-exam.svg?style=for-the-badge
[issues-url]: https://github.com/favocas/b18-exam/issues
[license-shield]: https://img.shields.io/github/license/favocas/b18-exam.svg?style=for-the-badge
[license-url]: https://github.com/favocas/b18-exam/blob/main/LICENSE
[linkedin-shield]: https://img.shields.io/badge/-LinkedIn-black.svg?style=for-the-badge&logo=linkedin&colorB=555
[linkedin-url]: https://linkedin.com/in/linkedin_username
[product-screenshot]: images/screenshot.png
