<?php

// Not much to see here, yet...

require_once '../vendor/autoload.php';
require_once '../private/config.php';

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
};


function edgeos_login($api_root, $username, $password) {
    $ch = curl_init();
    $headers = [];

    curl_setopt($ch, CURLOPT_URL,$api_root);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,
        http_build_query(array(
            'username' => $username,
            'password' => $password
        )));

    curl_setopt($ch, CURLOPT_HEADERFUNCTION,
        function($curl, $header) use (&$headers)
        {
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) < 2)
                return $len;

            $headers[strtolower(trim($header[0]))][] = trim($header[1]);

            return $len;
        }
    );

    curl_exec($ch);
    curl_close($ch);

    $cookies = $headers['set-cookie'];
    $cookie_str = '';

    foreach ($cookies as $cookie) {
        $cookie_str .= $cookie . ' ';
    }

    $cookie_str = trim($cookie_str);

    return $cookie_str;
};


function edgeos_get_data($api_root, $data_type, $cookies) {
    $url = $api_root . '/api/edge/data.json?data=' . $data_type;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIE, $cookies);

    $data = curl_exec($ch);

    return $data;
};


function get_lan_info($config) {
    $api_root = $config['url'];
    $cookies = edgeos_login($api_root,
                            $config['username'],
                            $config['password']);

    $response = edgeos_get_data($api_root, 'dhcp_leases', $cookies);
    $obj = json_decode($response);
    $dhcp_leases = $obj->output->{'dhcp-server-leases'};

    return $dhcp_leases;
};


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
};


function main($config) {
    $wifi_clients = get_wifi_clients($config['unifi']);
    $lan_info = get_lan_info($config['edgeos']);

    $output = combine($wifi_clients, $lan_info);

    header('Content-Type: application/json');
    echo json_encode($output);
};


main($config);

