# TV影视中转源管理PHP
AI写的单文件TV中转源web管理面板  
固定一个URL进行数据转发，在手机上一键切换需要中转的源，还可以指定特定的源   
源失效后不用在电视上按遥控器操作，远程切换后电视上APP退出重进即可  
老人或者老婆看不了电视时，做儿子和好老公的就可以不用浪费口水了……  

支持各种空壳类影视APP，TVBOX、fongmi影视、OK影视、影视仓等等  
source.json里面已经内制了几条相对稳定的源，按需食用  
本文件只对上游源进行缓存并转发，不做任何修改，默认缓存时间为12小时，减小上游源的服务器压力

## 部署步骤
1. 上传 index.php、config.php、source.json 到php服务器
2. 修改config.php里面账号密码
3. 访问 `index.php?admin` 进入管理后台
4. 接口直接访问 index.php，指定源：`index.php?y=XXX`
5. 配置好伪静态后可以简化URL

### 目录权限
cache目录、source.json 程序运行自动生成，需要目录读写权限。

### 演示页面
<img width="442" height="952" alt="image" src="https://github.com/user-attachments/assets/7aa33b6c-b13c-42a2-8882-025f7a869d1f" />

### ngnix伪静态设置，按需设置
```nginx
rewrite ^/tv$ /index.php last;
rewrite ^/tv/([A-Za-z0-9]+)$ /index.php?y=$1 last;
rewrite ^/admin$ /index.php?admin last;
