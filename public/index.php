<?php

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

    /*
     * {
     *    'mac': {
     *      'hostname': 'string',
     *      'ipv4': 'string',
     *      'mac': 'string',
     *      'network': 'string',
     *      'wifi': {
     *        'is_guest': true|false,
     *        'satisfaction': int
     *      }
     *   }
     * }
     */

    $clients_by_mac = [];

    foreach($clients as $client) {
        $mac = $client->mac;

        $clients_by_mac[$mac] = [
            'network' => $client->essid,
            'hostname' => $client->hostname,
            'ipv4' => $client->ip,
            'wifi' => [
                'is_guest' => $client->is_guest,
                'satisfaction' => $client->satisfaction
            ]
        ];
    }

    return $clients_by_mac;
}


function get_dhcp_leases($client) {
    $data = $client->get_dhcp_leases();
    $networks = $data->{'dhcp-server-leases'};

    /*
     * {
     *   'mac': {
     *     'hostname': 'string',
     *     'ipv4': 'string',
     *     'mac': 'string',
     *     'network': 'string',
     *     'type': 'string'
     *   }
     * }
     */

    $leases = [];

    foreach($networks as $network_name => $lease) {
        foreach($lease as $ipv4 => $lease_info) {
            $mac = $lease_info->mac;

            $leases[$mac] = [
                'ipv4' => $ipv4,
                'mac' => $mac,
                'hostname' => $lease_info->{'client-hostname'},
                'network' => $network_name,
                'type' => 'lease'
            ];
        }
    }

    return $leases;
}


function get_dhcp_reservations($client) {
    $response = $client->get_settings_section('{"service": null}');
    $servers = $response->GET->service->{'dhcp-server'}
                                      ->{'shared-network-name'};

    $dhcp_reservations = [];

    /*
     * {
     *   'mac': {
     *     'hostname': 'string',
     *     'ipv4': 'string',
     *     'mac': 'string',
     *     'network': 'string',
     *     'type': 'string'
     *   }
     * }
     */

    foreach($servers as $server_name => $server) {
        $subnets = $server->subnet;

        foreach($subnets as $subnet_range => $subnet) {
            if (isset($subnet->{'static-mapping'})) {
                $mappings = $subnet->{'static-mapping'};

                foreach($mappings as $name => $mapping) {
                    $dhcp_reservations[$mapping->{'mac-address'}] = [
                        'hostname' => $name,
                        'ipv4' => $mapping->{'ip-address'},
                        'mac' => $mapping->{'mac-address'},
                        'network' => $server_name,
                        'type' => 'dhcp_reservation'
                    ];
                }
            }
        }
    }

    return $dhcp_reservations;
}


function get_edgeos_client($config) {
    $client = new EdgeOS_API\Client(
        $config['username'],
        $config['password'],
        $config['url']
    );

    $client->login();

    return $client;
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

        if (is_array($containers)) {
            $all_containers = array_merge($all_containers, $containers);
        }
    }

    return $all_containers;
}


function get_network_clients($config) {
    $wifi_clients = get_wifi_clients($config['unifi']);

    $edgeos_client = get_edgeos_client($config['edgeos']);
    $dhcp_leases = get_dhcp_leases($edgeos_client);
    $dhcp_reservations = get_dhcp_reservations($edgeos_client);

    // Combine data based on mac address
    // (wifi clients also show up on DHCP server)
    $output = array_replace_recursive($wifi_clients, $dhcp_leases,
        $dhcp_reservations);

    return $output;
}


function add_updates($clients, $containers) {
    foreach($containers as $container) {
        $mac = $container->mac;

        if (isset($clients[$mac])) {
            $clients[$mac]['pending_updates'] = $container->updates;
        }
    }

    return $clients;
}


function group_clients_by_network($clients) {
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

    foreach($clients as $client) {
        $network = $client['network'];

        if (!isset($networks[$network])) {
            $networks[$network] = [
                'name' => $network,
                'clients' => []
            ];
        }

        unset($client['network']);
        $networks[$network]['clients'][] = $client;
    }

    $output = [];

    foreach($networks as $network) {
        $output[] = $network;
    }

    return $output;
}

// This isn't very good or efficient, but just testing things...
// This requires iputils-ping package since busybox `ping` requires root
function add_ping_info($clients) {
    $results = [];

    foreach($clients as $mac => $client) {
        $ipv4 = $client['ipv4'];

        exec('/bin/ping -c 1 -W 2 ' . $ipv4, $output, $return_code);

        $client['is_online'] = ($return_code == 0);
        $results[$mac] = $client;
    }

    return $results;
}


function main($config) {
    $clients = get_network_clients($config);
    $clients = add_ping_info($clients);

    $containers_info = get_update_info($config['update_links']);

    $clients = add_updates($clients, $containers_info);

    $output = group_clients_by_network($clients);

    view(
        'index.latte',
        [
            'networks' => $output
        ]
    );
}


main($config);


