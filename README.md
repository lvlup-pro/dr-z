# Dr.Z - broker between Zabbix and status page for end user

## Why?

Sometimes you don't want give your enduser ability to download all your Zabbix monitoring data but you want to inform them about downtime in friendly way.

## How?

Just run Dr. Z, preferably on zabbix host. 

Then you can send generated result.json to some outside infra like cheap Digital Ocean VPS. You can get free $10 credit on DO by using [this link](https://www.digitalocean.com/?refcode=2cad4d210b4a).

By using javascript client for enduser status page you can even host it directly on AWS S3 only by syncing via cron result.json.
 
## TL;DR Quickstart on Ubuntu

```bash
sudo apt-get install php5-cli git
git clone https://github.com/lvlup-pro/dr-g.git lvlup-dr-g
cd lvlup-dr-g
curl -sS https://getcomposer.org/installer | php
php composer.phar install
php app.php
```