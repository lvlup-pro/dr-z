<?php

//TODO: normal autoload doesn't work
//require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '../../vendor/confirm-it-solutions/php-zabbix-api/build/ZabbixApi.class.php';
//load config
require __DIR__ . '/../config.php';

use ZabbixApi\ZabbixApi;

//zabbix priority
//    0 - (default) not classified;
//    1 - information;
//    2 - warning;
//    3 - average;
//    4 - high;
//    5 - disaster.
Class Doctor
{

    private $triggers;

    function __construct($conf)
    {
        $this->conf = $conf;
        $this->zabbix = new ZabbixApi($this->conf["zabbix_api_url"], $this->conf["zabbix_username"], $this->conf["zabbix_password"]);
    }

    function offline_state($trigger)
    {
        if ($trigger["description"] == "Zabbix agent on {HOST.NAME} is unreachable for 5 minutes")
        {
            return ["lastchange" => $trigger["lastchange"] * 1000];
        }
    }

    function trigger_hide($trigger)
    {
        if (strpos($trigger->description, 'cagefs') !== false)
        {
            return true;
        }
    }

    function get_triggers()
    {
        $triggers = $this->zabbix->triggerGet(array(
            'groupids' => null,
            'hostids' => null,
            'monitored' => true,
            'maintenance' => false,
            'filter' => array('value' => 1),
            'skipDependent' => true,
            'expandComment' => true,
            'selectItems' => true,
            //'output' => array('triggerid', 'state', 'error', 'url', 'expression', 'description', 'priority', 'lastchange','comments'),
            'selectHosts' => array('hostid', 'name'),
            'selectLastEvent' => true,
            'limit' => '999'
        ));

        $this->triggers = [];
        foreach ($triggers as $trigger)
        {
            if (!$this->trigger_hide($trigger))
            {
                //Get host from trigger
                $current_trigger_hosts = [];
                foreach ($trigger->hosts as $host)
                {
                    array_push($current_trigger_hosts, $host->hostid);
                }
                //echo json_encode($current_trigger_hosts, JSON_PRETTY_PRINT);

                foreach ($current_trigger_hosts as $trigger_host)
                {
                    //Get ACKs from trigger
                    if ($trigger->lastEvent->acknowledged == 1)
                    {
                        $events = $this->zabbix->eventGet([
                            'eventids' => $trigger->lastEvent->eventid,
                            'select_acknowledges' => ['message', 'clock']//'acknowledgeid','userid','name','alias','surname'],
                        ]);
                        //echo json_encode($events, JSON_PRETTY_PRINT);
                        $acknowledges = [];
                        foreach ($events as $event)
                        {
                            foreach ($event->acknowledges as $ack)
                            {
                                array_push($acknowledges, ["message" => $ack->message, "clock" => $ack->clock]);
                                //echo json_encode($event, JSON_PRETTY_PRINT);
                            }
                        }
                        array_push($this->triggers, [
                            "hostid" => $trigger_host,
                            "lastchange" => $trigger->lastchange,
                            "priority" => $trigger->priority,
                            "triggerid" => $trigger->triggerid,
                            "description" => $trigger->description,
                            "eventid" => $trigger->lastEvent->eventid,
                            "acknowledged" => $trigger->lastEvent->acknowledged,
                            "acknowledges" => $acknowledges
                        ]);
                    } else
                    {
                        array_push($this->triggers, [
                            "hostid" => $trigger_host,
                            "lastchange" => $trigger->lastchange,
                            "priority" => $trigger->priority,
                            "triggerid" => $trigger->triggerid,
                            "description" => $trigger->description,
                            "eventid" => $trigger->lastEvent->eventid
                        ]);
                    }
                }
            }
        }
        return $this;
    }

    function get_hosts()
    {
        $result = [];
        $group_names = [];
        foreach ($this->conf["zabbix_groups"] as $group)
        {
            array_push($group_names, $group["name"]);
        }
        $unique_group_names = array_unique($group_names);

        foreach ($this->conf["zabbix_groups"] as $k => $v)
        {
            $hostGroup = $this->zabbix->hostGet(array(
                'groupids' => $k
            ));

            if (!isset($result[$v["name"]]["nodes"]))
            {
                $result[$v["name"]]["nodes"] = [];
            }

            foreach ($hostGroup as $host)
            {
                /* Check if offline by triggers */
                $trig = [];
                $offline_state = 0;
                $offline_from = time();
                foreach ($this->triggers as $trigger)
                {
                    //check if there is trigger for this host, then check if it is this host
                    if (isset($trigger["hostid"]) && $host->hostid == $trigger["hostid"])
                    {
                        $offline_check = $this->offline_state($trigger);
                        if ($offline_check)
                        {
                            $offline_state++;
                            if ($offline_check["clock"] < $offline_from)
                            {
                                $offline_from = $offline_check["lastchange"];
                            }
                        }
                    }
                }
                if ($offline_state > 0)
                {
                    $state = "offline";
                    $state_value = $offline_from;
                } else
                {
                    $state = "online";
                    $state_value = true;
                }
                /* */

                if (empty($trig))
                {
                    array_push($result[$v["name"]]["nodes"], [
                        "hostid" => $host->hostid,
                        "name" => $host->name,
                        "country" => $v["country"],
                        "city" => $v["city"],
                        "dc" => $v["dc"],
                        $state => $state_value
                    ]);
                } else
                {
                    array_push($result[$v["name"]]["nodes"], [
                        "hostid" => $host->hostid,
                        "name" => $host->name,
                        "country" => $v["country"],
                        "city" => $v["city"],
                        "dc" => $v["dc"],
                        "triggers" => $trig,
                        $state => $state_value
                    ]);
                }
            }
        }

        /* Sort result before saving */

        function sortz($a, $b)
        {
            return filter_var($a["name"], FILTER_SANITIZE_NUMBER_INT) - filter_var($b["name"], FILTER_SANITIZE_NUMBER_INT);
        }

        foreach ($unique_group_names as $group)
        {
            usort($result[$group]["nodes"], "sortz");
        }

        $this->result = $result;
        $this->json_result = json_encode($result, JSON_PRETTY_PRINT);
        return $this;
    }

    function show_triggers()
    {
        var_dump($this->triggers);
    }

    function show()
    {
        echo $this->json_result;
    }

    function save_file($filename)
    {
        file_put_contents(__DIR__ . "/../" . $filename, $this->json_result);
    }

}
