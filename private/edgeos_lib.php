<?php

namespace EdgeOS_API;

class Client
{
    protected $username     = '';
    protected $password     = '';
    protected $baseurl      = 'https://localhost';
    protected $cookies      = '';
    protected $is_logged_in = false;

    public function __construct($username, $password, $baseurl = '') {
        $this->username = trim($username);
        $this->password = trim($password);

        if (!empty($baseurl)) {
            $this->baseurl = trim($baseurl);
        }
    }


    public function __destruct() {
        if ($this->is_logged_in) {
            $this->logout();
        }
    }


    protected function get_data($type) {
        $url = $this->baseurl . '/api/edge/data.json?data=' . $type;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, $this->cookies);

        $data = curl_exec($ch);

        return json_decode($data)->output;
    }


    public function get_dhcp_stats() {
        $data = $this->get_data('dhcp_stats');

        return $data;
    }


    public function get_firewall_stats() {
        $data = $this->get_data('fw_stats');

        return $data;
    }


    public function get_gpon_onu_list() {
        $data = $this->get_data('gpon_onu_list');

        return $data;
    }


    public function get_nat_stats() {
        $data = $this->get_data('nat_stats');

        return $data;
    }


    public function get_routes() {
        $data = $this->get_data('routes');

        return $data;
    }


    public function get_sys_info() {
        $data = $this->get_data('sys_info');

        return $data;
    }


    public function is_default_config() {
        $data = $this->get_data('default_config');

        return ($data === '1');
    }


    public function get_dhcp_leases() {
        $data = $this->get_data('dhcp_leases');

        return $data;
    }


    // NOTE: Need to be admin, otherwise HTTP 403 is returned
    public function get_configurations($node = null) {
        $query = '';

        if ($node !== null) {
            $query = urlencode('?' . $node);
        }

        $url = $this->baseurl . '/api/edge/getcfg.json' . $query;

        echo $url;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, $this->cookies);

        $data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        error_log($http_code);

        return json_decode($data);
    }


    // FIXME: /api/edge/partial.json returns a 404
    //        Investigate.
    public function get_settings_section($struct) {
        $query = urlencode('?struct=' . $struct);
        $url = $this->baseurl . '/api/edge/partial.json' . $query;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, $this->cookies);

        $data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        error_log($http_code);

        return json_decode($data);
    }


    // Predefined, hardcoded list of settings
    public function get_settings() {
        $url = $this->baseurl . '/api/edge/get.json';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, $this->cookies);

        $data = curl_exec($ch);
        curl_close($ch);

        return json_decode($data);
    }


    protected function update_cookie() {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['set-cookie']) && !empty($_SESSION['set-cookie'])) {
            $this->cookies = $_SESSION['set-cookie'];

            return true;
        }

        return false;
    }


    public function get_cookies() {
        return $this->cookies;
    }


    public function login() {
        // Skip the login process if already logged in
        if ($this->update_cookie()) {
            $this->is_logged_in = true;
        }

        if ($this->is_logged_in === true) {
            return true;
        }

        $ch = curl_init();
        $headers = [];

        curl_setopt($ch, CURLOPT_URL, $this->baseurl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS,
            http_build_query(array(
                'username' => $this->username,
                'password' => $this->password
            )));

        // Cookie-handling
        curl_setopt(
            $ch,
            CURLOPT_HEADERFUNCTION,
            [ $this, 'response_header_callback' ]
        );

        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 200 && $http_code < 400) {
            $this->is_logged_in = true;
            return $this->is_logged_in;
        }

        return false;
    }


    protected function response_header_callback($ch, $header) {
        if (stripos($header, 'set-cookie') !== false) {
            $cookie = trim(str_replace(
                ['set-cookie: ', 'Set-Cookie: '], '' , $header
            ));

            if (!empty($cookie)) {
                // We need both PHPSESSID and X-CSRF-TOKEN
                $this->cookies = $cookie;
                $this->is_logged_in = true;
            }
        }

        return strlen($header);
    }


    public function logout() {
        $this->is_logged_in = false;
        $this->cookies = '';

        return true;
    }
}

