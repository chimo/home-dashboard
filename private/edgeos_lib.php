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
            $this->baseurl  = trim($baseurl);
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


    public function login() {
        // Skip the login process if already logged in
        #if ($this->update_unificookie()) {
        #    $this->is_logged_in = true;
        #}

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

        $this->cookies = $cookie_str;
        $this->is_logged_in = true;

        return true;
    }


    public function logout() {
        $this->is_logged_in = false;
        $this->cookies      = '';

        return true;
    }
}

