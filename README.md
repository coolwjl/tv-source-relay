# TV中转源管理PHP
单文件TV源中转+web管理面板。

## 部署步骤
1. 上传 index.php、config.example.php 到php服务器
2. 复制 config.example.php 为 config.php，修改里面账号密码
3. 访问 `index.php?admin` 进入管理后台
4. 接口直接访问 index.php，指定源：`index.php?y=XXX`

### 目录权限
cache目录、source.json 程序运行自动生成，需要目录读写权限。

### 目录权限
 #### ngnix伪静态设置，根据目录进行修改，不设置伪静态也可以
   rewrite ^/tv$ /index.php last;
   rewrite ^/tv/([A-Za-z0-9]+)$ /index.php?y=$1 last;
   rewrite ^/admin$ /index.php?admin last;
