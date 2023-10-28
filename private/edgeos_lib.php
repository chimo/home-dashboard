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


    public function get_dhcp_leases() {
        $data = $this->get_data('dhcp_leases');

        return $data;
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

