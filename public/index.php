<?php

// Not much to see here, yet...

require_once '../vendor/autoload.php';
require_once '../private/config.php';

// TODO: make this a composer lib
require_once '../private/edgeos_lib.php';

function get_wifi_clients($config) {
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

    return $clients;
}


function get_lan_info($config) {
    $client = new EdgeOS_API\Client(
        $config['username'],
        $config['password'],
        $config['url']
    );

    $client->login();

    $data = $client->get_dhcp_leases();
    $dhcp_leases = $data->{'dhcp-server-leases'};

    return $dhcp_leases;
}


function combine($wifi_clients, $lan_info) {
    foreach($lan_info as $server => $leases) {
        foreach($leases as $lease) {
            $mac = $lease->mac;

            foreach($wifi_clients as $wifi_client) {
                if ($wifi_client->mac == $mac) {
                    $lease->wifi = $wifi_client;
                }
            }
        }
    }

    return $lan_info;
}


function main($config) {
    $wifi_clients = get_wifi_clients($config['unifi']);
    $lan_info = get_lan_info($config['edgeos']);

    $output = combine($wifi_clients, $lan_info);

    header('Content-Type: application/json');
    echo json_encode($output);
}


main($config);

