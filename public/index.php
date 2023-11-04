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


function combine_network_clients($wifi_clients, $lan_info) {
    /*
        [
            'network': {
                'name': 'string',
                'clients': [
                    'mac': 'string',
                    'hostname': 'string',
                    'ipv4': 'string',
                    'wifi': {
                        'is_guest': true|false,
                        'satisfaction': int
                    },
                    'pending_updates': [
                        {
                            'package_name': 'string',
                            'local_version': 'string',
                            'latest_version': 'string'
                        }
                    ]
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

            $client = [
                'mac' => $mac,
                'hostname' => $lease->{'client-hostname'},
                'ipv4' => $ipv4,
                'wifi' => null,
                'pending_updates' => null
            ];

            foreach($wifi_clients as $wifi_client) {
                if ($wifi_client->mac == $mac) {

                    $client['wifi'] = [
                        'is_guest' => $wifi_client->is_guest,
                        'satisfaction' => $wifi_client->satisfaction_real
                    ];

                    break;
                }
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


function get_update_info($links) {
    $all_containers = [];

    foreach($links as $link) {
        $response = file_get_contents($link);
        $json = json_decode($response);
        $containers = $json->containers;
        $all_containers = array_merge($all_containers, $containers);
    }

    return $all_containers;
}


function get_network_clients($config) {
    $wifi_clients = get_wifi_clients($config['unifi']);
    $lan_info = get_lan_info($config['edgeos']);
    $output = combine_network_clients($wifi_clients, $lan_info);

    return $output;
}


function combine_updates($networks, $updates) {
    foreach($updates as $update) {
        $mac = $update->mac;

        foreach($networks as &$network) {
            $clients = $network['clients'];

            foreach($clients as &$client) {
                if ($client['mac'] === $mac) {
                    $client['pending_updates'] = $update->updates;

                    break;
                }
            }

            $network['clients'] = $clients;
        }
    }

    return $networks;
}


function main($config) {
    $networks = get_network_clients($config);
    $updates = get_update_info($config['update_links']);

    $output = combine_updates($networks, $updates);

    view(
        'index.latte',
        [
            'networks' => $output
        ]
    );

    /*
    header('Content-Type: application/json');
    echo json_encode($output, JSON_PRETTY_PRINT);
    */
}


main($config);

