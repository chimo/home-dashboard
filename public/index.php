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
    /*
        [
            'network': {
                'name': 'fsoc',
                'clients': [
                    'mac': 'xxx',
                    'hostname': '',
                    'ipv4': 'yyy',
                    'is_guest': true|false,
                    'is_wifi': true|false,
                ]
            }
        ]
     */

    $networks = [];

    foreach($lan_info as $server => $leases) {
        $network = [
            'name' => $server,
            'clients' => []
        ];

        if (!is_object($leases)) {
            continue;
        }

        foreach($leases as $ipv4 => $lease) {
            $mac = $lease->mac;
            $is_wifi = false;
            $is_guest = false;

            foreach($wifi_clients as $wifi_client) {
                if ($wifi_client->mac == $mac) {
                    $is_wifi = true;

                    $is_guest = $wifi_client->is_guest;
                    break;
                }
            }

            $client = [
                'mac' => $mac,
                'hostname' => $lease->{'client-hostname'},
                'ipv4' => $ipv4
            ];

            if ($is_wifi === true) {
                $client['wifi'] = [
                    'is_guest' => $is_guest
                ];
            } else {
                $client['wifi'] = null;
            }

            $network['clients'][] = $client;
        }

        $networks[] = $network;
    }

    return $networks;
}


function view($template, $params) {
    // declare(strict_types=1);

    $root_dir = __DIR__ . '/../private';
    $cache_dir = $root_dir . '/cache/templates';
    $template_dir = $root_dir . '/templates';

    // Initialize the Latte engine
    $latte = new Latte\Engine;

    // Set the temporary directory for compiled templates
    $latte->setTempDirectory($cache_dir);

    // Enable auto-refresh for development mode. It recompiles templates on
    // every request.
    $latte->setautoRefresh();

    // Render the index.latte template
    $latte->render("$template_dir/$template", $params);
}


function main($config) {
    $wifi_clients = get_wifi_clients($config['unifi']);
    $lan_info = get_lan_info($config['edgeos']);

    $output = combine($wifi_clients, $lan_info);

    view(
        'index.latte',
        [
            'networks' => $output
        ]
    );

    //header('Content-Type: application/json');
    //echo json_encode($output, JSON_PRETTY_PRINT);
}


main($config);

