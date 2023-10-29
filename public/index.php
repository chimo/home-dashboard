<?php

// Not much to see here, yet...

require_once '../vendor/autoload.php';
require_once '../private/config.php';

// TODO: make this a composer lib
require_once '../private/lib/edgeos.php';

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


function view($template, $params) {
    // declare(strict_types=1);

    $root_dir = __DIR__ . '/../private';
    $cache_dir = $root_dir . '/cache/templates';
    $template_dir = $root_dir . '/templates/templates';

    // Initialize the Latte engine
    $latte = new Latte\Engine;

    // Set the temporary directory for compiled templates
    $latte->setTempDirectory($cache_dir);

    // Enable auto-refresh for development mode. It recompiles templates on
    // every request.
    $latte->setautoRefresh();

    // Render the index.latte template
    $latte->render("$template_dir/$template");
}


function main($config) {
    $wifi_clients = get_wifi_clients($config['unifi']);
    $lan_info = get_lan_info($config['edgeos']);

    $output = combine($wifi_clients, $lan_info);

    view('index.latte', $output);

    // header('Content-Type: application/json');
    // echo json_encode($output);
}


main($config);

