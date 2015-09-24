<?php

return
        [
            "zabbix_api_url" => "http://zabbix.example.com/zabbix/api_jsonrpc.php",
            "zabbix_username" => "Admin",
            "zabbix_password" => "foobar",
            "zabbix_groups" =>
            [
                1 => ["name" => "Some good servers", "dc" => "OVH RBX-1", "country" => "fr", "city" => "Roubaix"],
            ]
];
