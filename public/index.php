<?php

// Not much to see here, yet...

require_once '../vendor/autoload.php';
require_once '../private/config.php';

function get_wifi_info($config) {
    $unifi_connection = new UniFi_API\Client(
        $config['username'],
        $config['password'],
        $config['url'], 
        $config['site_id'],
        $config['version'],
        true
    );

    $unifi_connection->login();

    $clients = $unifi_connection->list_clients();
    $essids = array();

    foreach ($clients as $client) {
        $essid = $client->essid;
        $hostname = $client->hostname;

        if (!array_key_exists($essid, $essids)) {
            $essids[$essid] = array();
        }

        array_push($essids[$essid], $hostname);
    }

    return $essids;
};


function get_lan_info($config) {
    return 'placeholder';
};


function main($config) {
    $wifi_info = get_wifi_info($config['unifi']);
    $lan_info = get_lan_info($config['edgeos']);

    print_r($wifi_info);
    print_r($lan_info);
};


main($config);

